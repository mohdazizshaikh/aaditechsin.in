<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/operations.php';
header('X-Robots-Tag: noindex, nofollow');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(405, ['error' => 'Method not allowed']);
if (!opsSsl()) jsonResponse(403, ['error' => 'HTTPS required']);
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) jsonResponse(413, ['error' => 'Request too large']);
if (strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0])) !== 'application/json') jsonResponse(415,['error'=>'JSON required']);
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$currentHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
if ($origin !== '') {
    $parts=parse_url($origin);
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || strtolower((string)($parts['host'] ?? '')) !== explode(':',$currentHost)[0]) {
        jsonResponse(403, ['error'=>'Origin not allowed']);
    }
}
if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site') jsonResponse(403,['error'=>'Cross-site request not allowed']);
$raw=file_get_contents('php://input',false,null,0,8193);
$data=is_string($raw)?json_decode($raw,true):null;
if (!is_array($data)) jsonResponse(400,['error'=>'Invalid form']);
if (!empty($data['company_website'])) jsonResponse(200,['ok'=>true]); // Honeypot; no database write.
$scalar=function(string $key) use($data): string {
    $v=$data[$key]??'';
    return is_string($v) ? trim($v) : '';
};
$name=$scalar('fullName');$email=$scalar('email');$phone=$scalar('phone');
$service=$scalar('service');$device=$scalar('device');$message=$scalar('message');$source=$scalar('source')?:'website';
$consent=$scalar('contactConsent')==='yes';
if (!$consent || $name==='' || opsLen($name)>100 || !filter_var($email,FILTER_VALIDATE_EMAIL)
    || opsLen($email)>120 || opsLen($phone)>20 || opsLen($service)>100 || $service===''
    || opsLen($device)>100 || $message==='' || opsLen($message)>2000
    || !in_array($source,['website','av-planner','guided-chat'],true)) {
    jsonResponse(422,['error'=>'Please check the required fields']);
}
try {$cfg=opsConfig();}catch(Throwable $e){jsonResponse(503,['error'=>'Inquiry service not configured']);}
if (($cfg['leads']['enabled']??false)!==true || ($cfg['database']['enabled']??false)!==true) jsonResponse(503,['error'=>'Inquiry service not available']);
$salt=(string)($cfg['rate_limit_salt']??'');
if (strlen($salt)<32 || str_starts_with($salt,'REPLACE_')) jsonResponse(503,['error'=>'Spam protection not configured']);
$ipHash=hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR']??''),$salt);
$dir=dirname(__DIR__,2).'/secure/rate';
if (!is_dir($dir)) @mkdir($dir,0700,true);
if (!is_dir($dir) || !is_writable($dir)) jsonResponse(503,['error'=>'Spam protection unavailable']);
$handle=@fopen($dir.'/'.$ipHash,'c+');
if (!$handle || !flock($handle,LOCK_EX)) jsonResponse(503,['error'=>'Spam protection unavailable']);
$last=(int)trim((string)stream_get_contents($handle));
if ($last>0 && time()-$last<300) {flock($handle,LOCK_UN);fclose($handle);jsonResponse(429,['error'=>'Please wait before sending another inquiry']);}
try {
    $pdo=opsDb($cfg);
    $ref=opsRef('ATL');
    $sql='INSERT INTO leads(reference,full_name,email,phone,service,device,message,source,consent_at) VALUES(?,?,?,?,?,?,?,?,UTC_TIMESTAMP())';
    $stmt=$pdo->prepare($sql);$stmt->execute([$ref,$name,$email,$phone,$service,$device,$message,$source]);
    rewind($handle);ftruncate($handle,0);fwrite($handle,(string)time());fflush($handle);
    flock($handle,LOCK_UN);fclose($handle);
} catch(Throwable $e) {
    flock($handle,LOCK_UN);fclose($handle);
    error_log('Aaditechs lead persistence failed');
    jsonResponse(503,['error'=>'Could not save inquiry']);
}
// Email notification is best effort. The stored lead is the system of record.
$mailCfg=$cfg['mail']??[];
if (($mailCfg['enabled']??false)===true) {
    try {
        $autoload=dirname(__DIR__,2).'/secure/vendor/autoload.php';
        if (!is_file($autoload)) throw new RuntimeException('Mail dependency missing');
        require_once $autoload;
        $mail=new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();$mail->Host=(string)$mailCfg['host'];$mail->Port=(int)$mailCfg['port'];
        $mail->SMTPAuth=true;$mail->Username=(string)$mailCfg['username'];$mail->Password=(string)$mailCfg['password'];
        $mail->SMTPSecure=PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;$mail->CharSet='UTF-8';
        $mail->setFrom((string)$mailCfg['from_email'],'Aaditechs Lead Engine');
        $mail->addAddress((string)$mailCfg['inquiry_recipient']);
        $mail->addReplyTo($email,$name);$mail->isHTML(false);
        $mail->Subject='New Aaditechs inquiry '. $ref;
        $mail->Body="Reference: {$ref}\nName: {$name}\nEmail: {$email}\nPhone: {$phone}\nService: {$service}\nDevice: {$device}\nSource: {$source}\n\nMessage:\n{$message}\n\nContact consent: yes";
        $mail->send();
    } catch(Throwable $e) {error_log('Aaditechs notification failed for reference '.$ref);}
}
jsonResponse(201,['ok'=>true,'reference'=>$ref]);
