<?php
declare(strict_types=1);
/**
 * Aaditechs mail outbox: CLI only, always private (outside public_html).
 * php notification-worker.php --dry-run    # read-only statistics; safe default
 * php notification-worker.php --run        # send up to 20 due alerts
 * Never pass secrets in argv; set in private aaditechs-config.php.
 * Scheduling: Hostinger Cron Jobs every 5-15 minutes after staging acceptance.
 * Delivery is AT LEAST ONCE: a crash after SMTP send may cause a duplicate.
 */
if (PHP_SAPI !== 'cli') {http_response_code(404);exit;}
$mode=$argv[1]??'--dry-run';
if (!in_array($mode,['--run','--dry-run'],true) || count($argv)>2) {
    fwrite(STDERR,"Usage: php notification-worker.php [--dry-run|--run]\n");exit(2);
}
require dirname(__DIR__,2).'/public_html/api/operations.php';
function queueMail(array $cfg, array $row): void {
    $mailCfg=$cfg['mail']??[];
    $vendor=dirname(__DIR__).'/vendor/autoload.php';
    if (!is_file($vendor)) throw new RuntimeException('Mailer dependency missing');
    require_once $vendor;
    $mail=new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host=(string)($mailCfg['host']??'');
    $mail->Port=(int)($mailCfg['port']??587);
    $mail->SMTPAuth=true;
    $mail->Username=(string)($mailCfg['username']??'');
    $mail->Password=(string)($mailCfg['password']??'');
    $mail->SMTPSecure=PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet='UTF-8';
    $mail->Timeout=12;
    $mail->setFrom((string)$mailCfg['from_email'],'Aaditechs Lead Engine');
    $mail->addAddress((string)$mailCfg['inquiry_recipient']);
    $mail->addReplyTo((string)$row['email'],(string)$row['full_name']);
    $mail->isHTML(false);
    $mail->Subject='New Aaditechs inquiry '.$row['reference'];
    $mail->Body="Reference: {$row['reference']}\nName: {$row['full_name']}\nEmail: {$row['email']}\nPhone: {$row['phone']}\nService: {$row['service']}\nDevice: {$row['device']}\nSource: {$row['source']}\n\nMessage:\n{$row['message']}\n\nContact consent: yes";
    $mail->send();
}
try {
    $cfg=opsConfig();
    $pdo=opsDb($cfg);
    $counts=$pdo->query('SELECT status, COUNT(*) AS total FROM notification_outbox GROUP BY status')->fetchAll();
    $summary=['pending'=>0,'sending'=>0,'sent'=>0,'dead'=>0];
    foreach($counts as $entry) $summary[(string)$entry['status']]=(int)$entry['total'];
    if ($mode==='--dry-run') {
        foreach($summary as $name=>$value)echo $name.': '.$value."\n";
        echo "READ-ONLY: no emails sent or rows changed.\n";
        exit(0);
    }
    if (($cfg['mail']['enabled']??false)!==true || ($cfg['leads']['enabled']??false)!==true) {
        throw new RuntimeException('Mail and leads must be explicitly enabled');
    }
    $mailCfg=$cfg['mail'];
    foreach(['host','username','password','from_email','inquiry_recipient'] as $field) {
        $value=$mailCfg[$field]??'';
        if (!is_string($value) || trim($value)==='' || str_starts_with($value,'REPLACE_')) {
            throw new RuntimeException('Mail is not securely configured');
        }
    }
    if (!filter_var($mailCfg['from_email'], FILTER_VALIDATE_EMAIL) || !filter_var($mailCfg['inquiry_recipient'], FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('SMTP addresses are invalid');
    }
    // A worker can crash after its final claimed attempt. Move these expired
    // max-attempt leases to dead so the owner can investigate/requeue them.
    $pdo->exec("UPDATE notification_outbox SET status='dead',locked_at=NULL,last_error_code='lease_expired'
        WHERE status='sending' AND attempts>=6 AND locked_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)");
    // Underlying calls use a short claim lease and compare-and-swap update to
    // prevent parallel cron workers from sending the same row concurrently.
    $ids=$pdo->query("SELECT id FROM notification_outbox WHERE
        (status='pending' AND next_attempt_at <= UTC_TIMESTAMP())
        OR (status='sending' AND locked_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE))
        ORDER BY id LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
    $sent=0;$deferred=0;$dead=0;
    foreach($ids as $queueId) {
        $claim=$pdo->prepare("UPDATE notification_outbox SET status='sending',locked_at=UTC_TIMESTAMP(),attempts=attempts+1
            WHERE id=? AND attempts<6 AND (
              (status='pending' AND next_attempt_at <= UTC_TIMESTAMP())
              OR (status='sending' AND locked_at < DATE_SUB(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)))");
        $claim->execute([(int)$queueId]);
        if($claim->rowCount()!==1)continue;
        $stmt=$pdo->prepare('SELECT o.attempts,l.reference,l.full_name,l.email,l.phone,l.service,l.device,l.source,l.message FROM notification_outbox o JOIN leads l ON l.id=o.lead_id WHERE o.id=?');
        $stmt->execute([(int)$queueId]);
        $row=$stmt->fetch();
        if (!$row) { // Avoid a stuck queue after a deleted lead.
            $pdo->prepare("UPDATE notification_outbox SET status='dead',last_error_code='lead_missing',locked_at=NULL WHERE id=?")->execute([(int)$queueId]);
            $dead++; continue;
        }
        try {
            queueMail($cfg,$row);
            $pdo->prepare("UPDATE notification_outbox SET status='sent',sent_at=UTC_TIMESTAMP(),locked_at=NULL,last_error_code='' WHERE id=? AND status='sending'")->execute([(int)$queueId]);
            $sent++;
        } catch(Throwable $error) {
            // Do not print SMTP exceptions: provider errors may contain PII.
            $attempts=(int)$row['attempts'];
            $gaveUp=$attempts>=6;
            $minutes=[5,15,60,240,720,1440][min($attempts-1,5)];
            $stmt=$pdo->prepare("UPDATE notification_outbox SET status=?,locked_at=NULL,last_error_code='smtp_unavailable',
                next_attempt_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? MINUTE) WHERE id=? AND status='sending'");
            $stmt->execute([$gaveUp?'dead':'pending',$minutes,(int)$queueId]);
            $gaveUp?$dead++:$deferred++;
            error_log('Aaditechs notification delivery deferred for queue id '.(int)$queueId);
        }
    }
    echo 'processed sent='.$sent.' deferred='.$deferred.' dead='.$dead."\n";
    exit(0);
} catch(Throwable $e) {
    fwrite(STDERR,"BLOCKED: notification worker requires enabled, configured private services and updated schema.\n");
    exit(1);
}
