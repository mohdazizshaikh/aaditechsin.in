<?php
declare(strict_types=1);
require __DIR__ . '/../api/operations.php';
opsHeaders();
try {
    $cfg=opsConfig();
    if (($cfg['portal']['enabled']??false)!==true || !opsSsl()) throw new RuntimeException('Operations portal is not enabled or HTTPS is unavailable');
    opsSession();
    opsPublicBaseUrl($cfg);
    opsTrackingTtlDays($cfg);
    $pdo=opsDb($cfg);
    // 30-minute inactivity expiration and immediate disabled-partner revocation.
    if(isset($_SESSION['role']) && time()-(int)($_SESSION['last_seen']??0)>1800) {
        $_SESSION=[];session_regenerate_id(true);
    }
    $_SESSION['last_seen']=time();
    if(($_SESSION['role']??null)==='partner') {
        $check=$pdo->prepare('SELECT session_version FROM partners WHERE id=? AND is_active=1');
        $check->execute([(int)($_SESSION['partner_id']??0)]);
        $version=$check->fetchColumn();
        if($version===false || !isset($_SESSION['partner_session_version'])
            || (int)$version !== (int)$_SESSION['partner_session_version']) {
            $_SESSION=[];session_regenerate_id(true);
        }
    } elseif(($_SESSION['role']??null)==='admin') {
        // Owner changing the private admin password hash invalidates old sessions.
        $fingerprint=opsAdminSessionFingerprint($cfg);
        if(!hash_equals($fingerprint,(string)($_SESSION['admin_fingerprint']??''))) {
            $_SESSION=[];session_regenerate_id(true);
        }
    }
} catch(Throwable $e) {
    http_response_code(503);exit('Aaditechs operations portal is not configured.');
}
function h(mixed $s): string {return opsH((string)$s);}
function redirectPortal(string $message): never {
    $_SESSION['flash']=$message;
    header('Location: /portal/',true,303);exit;
}
function requireAdmin(): void {if(($_SESSION['role']??null)!=='admin'){http_response_code(403);exit('Access denied');}}
function requireSignedIn(): void {if(!isset($_SESSION['role'])){http_response_code(403);exit('Sign in required');}}
$states=['assessment','quote_pending','awaiting_customer_approval','approved','in_progress','work_completed','closed','cancelled'];
$csrf=opsCsrf();
if(($_SERVER['REQUEST_METHOD']??'')==='POST') {
    opsCheckCsrf();
    $action=(string)($_POST['action']??'');
    if($action==='logout') {
        $_SESSION=[];session_regenerate_id(true);session_destroy();
        header('Location: /portal/',true,303);exit;
    }
    if($action==='login') {
        // Basic anti-brute-force per salted IP, held outside webroot.
        $secret=(string)($cfg['rate_limit_salt']??'');
        if(strlen($secret)<32 || str_starts_with($secret,'REPLACE_')){http_response_code(503);exit('Authentication protection not configured');}
        $dir=dirname(__DIR__,2).'/secure/login-attempts';
        if(!is_dir($dir))@mkdir($dir,0700,true);
        if(!is_dir($dir)||!is_writable($dir)){http_response_code(503);exit('Authentication temporarily unavailable');}
        $key=hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR']??''),$secret);
        $fp=@fopen($dir.'/'.$key,'c+');
        if(!$fp||!flock($fp,LOCK_EX)){http_response_code(503);exit('Authentication temporarily unavailable');}
        $attempts=json_decode((string)stream_get_contents($fp),true);
        if(!is_array($attempts)||time()-(int)($attempts['started']??0)>1200)$attempts=['started'=>time(),'count'=>0];
        if((int)$attempts['count']>=8){flock($fp,LOCK_UN);fclose($fp);http_response_code(429);exit('Too many attempts; try again later');}
        $email=strtolower(trim((string)($_POST['email']??'')));
        $password=(string)($_POST['password']??'');
        $totp=(string)($_POST['totp']??'');
        $role=null;$partner=null;
        if($email!=='' && hash_equals(strtolower((string)($cfg['portal']['admin_email']??'')),$email)
            && !str_starts_with((string)($cfg['portal']['admin_password_hash']??''),'REPLACE_')
            && password_verify($password,(string)($cfg['portal']['admin_password_hash']??''))
            && (($cfg['portal']['admin_totp_enabled']??false)!==true
                || opsVerifyTotp((string)($cfg['portal']['admin_totp_secret']??''), $totp))) {
            $role='admin';
        } elseif($email!=='') {
            $stmt=$pdo->prepare('SELECT id,display_name,password_hash,session_version FROM partners WHERE login_email=? AND is_active=1 LIMIT 1');
            $stmt->execute([$email]);$partner=$stmt->fetch();
            if($partner && password_verify($password,(string)$partner['password_hash']))$role='partner';
        }
        if(!$role){$attempts['count']++;rewind($fp);ftruncate($fp,0);fwrite($fp,json_encode($attempts));fflush($fp);flock($fp,LOCK_UN);fclose($fp);$_SESSION['flash']='Invalid sign-in details';header('Location: /portal/',true,303);exit;}
        rewind($fp);ftruncate($fp,0);fwrite($fp,json_encode(['started'=>time(),'count'=>0]));fflush($fp);flock($fp,LOCK_UN);fclose($fp);
        session_regenerate_id(true);
        unset($_SESSION['csrf']); // Rotate pre-authentication CSRF secret on privilege elevation.
        $_SESSION['role']=$role;$_SESSION['account_email']=$email;$_SESSION['partner_id']=$role==='partner'?(int)$partner['id']:null;
        $_SESSION['partner_session_version']=$role==='partner'?(int)$partner['session_version']:null;
        $_SESSION['admin_fingerprint']=$role==='admin'?opsAdminSessionFingerprint($cfg):null;
        redirectPortal('Signed in successfully');
    }
    requireSignedIn();
    try {
        if($action==='add_partner') {
            requireAdmin();
            $name=trim((string)($_POST['name']??''));$email=strtolower(trim((string)($_POST['email']??'')));
            $specialties=trim((string)($_POST['specialties']??''));$password=(string)($_POST['password']??'');
            if($name===''||opsLen($name)>120||!filter_var($email,FILTER_VALIDATE_EMAIL)||opsLen($email)>160||opsLen($specialties)>250||strlen($password)<12||strlen($password)>200 || $email===strtolower((string)$cfg['portal']['admin_email']))throw new InvalidArgumentException('Check partner name, email (different from owner), and 12+ character password');
            $hash=password_hash($password,PASSWORD_DEFAULT);
            $stmt=$pdo->prepare('INSERT INTO partners(display_name,login_email,password_hash,specialties) VALUES(?,?,?,?)');
            $stmt->execute([$name,$email,$hash,$specialties]);
            redirectPortal('Partner created. Share the password privately; it will not be displayed again.');
        }
        if($action==='manage_partner') {
            requireAdmin();
            $id=filter_var($_POST['partner_id']??'',FILTER_VALIDATE_INT);
            $mode=(string)($_POST['mode']??'');
            if(!$id||!in_array($mode,['disable','enable','password'],true))throw new InvalidArgumentException('Invalid partner action');
            if($mode==='password') {
                $pass=(string)($_POST['new_password']??'');
                if(strlen($pass)<12||strlen($pass)>200)throw new InvalidArgumentException('Use a password of at least 12 characters');
                $stmt=$pdo->prepare('UPDATE partners SET password_hash=?,session_version=session_version+1 WHERE id=?');
                $stmt->execute([password_hash($pass,PASSWORD_DEFAULT),$id]);
            } else {
                $stmt=$pdo->prepare('UPDATE partners SET is_active=?,session_version=session_version+1 WHERE id=?');
                $stmt->execute([$mode==='enable'?1:0,$id]);
            }
            redirectPortal('Partner access updated. Disabling a partner blocks their next authenticated request.');
        }
        if($action==='retry_notification') {
            requireAdmin();
            $id=filter_var($_POST['notification_id']??'',FILTER_VALIDATE_INT);
            if(!$id || ($cfg['mail']['enabled']??false)!==true)throw new InvalidArgumentException('Notification retry unavailable');
            // Only explicitly failed alerts can be requeued; no SMTP in web requests.
            $stmt=$pdo->prepare("UPDATE notification_outbox SET status='pending',attempts=0,
                next_attempt_at=UTC_TIMESTAMP(),locked_at=NULL,last_error_code='' WHERE id=? AND status='dead'");
            $stmt->execute([$id]);
            if($stmt->rowCount()!==1)throw new InvalidArgumentException('Failed notification not found');
            redirectPortal('Notification requeued for the scheduled private worker');
        }
        if($action==='set_lead_status') {
            requireAdmin();$id=filter_var($_POST['lead_id']??'',FILTER_VALIDATE_INT);$status=(string)($_POST['status']??'');
            if(!$id||!in_array($status,['new','qualified','responded','converted','closed'],true))throw new InvalidArgumentException('Invalid lead status');
            $stmt=$pdo->prepare('UPDATE leads SET status=? WHERE id=?');$stmt->execute([$status,$id]);
            redirectPortal('Lead status updated');
        }
        if($action==='create_job') {
            requireAdmin();
            $leadId=filter_var($_POST['lead_id']??'',FILTER_VALIDATE_INT);
            $partnerId=($_POST['partner_id']??'')===''?null:filter_var($_POST['partner_id'],FILTER_VALIDATE_INT);
            $title=trim((string)($_POST['title']??''));$description=trim((string)($_POST['description']??''));
            if(!$leadId||$title===''||opsLen($title)>160||opsLen($description)>2000||($partnerId!==null&&!$partnerId))throw new InvalidArgumentException('Invalid case information');
            if($partnerId!==null && ($_POST['partner_scope_checked']??'')!=='yes')throw new InvalidArgumentException('Confirm customer authorization and sanitized partner scope before assignment');
            $pdo->beginTransaction();
            $check=$pdo->prepare('SELECT id FROM leads WHERE id=? FOR UPDATE');$check->execute([$leadId]);
            if(!$check->fetch())throw new InvalidArgumentException('Lead not found');
            if($partnerId!==null){$check=$pdo->prepare('SELECT id FROM partners WHERE id=? AND is_active=1');$check->execute([$partnerId]);if(!$check->fetch())throw new InvalidArgumentException('Partner not found');}
            $token=bin2hex(random_bytes(32));$case=opsRef('ATC');
            $stmt=$pdo->prepare('INSERT INTO jobs(case_code,lead_id,partner_id,title,description,tracking_token_hash,tracking_expires_at) VALUES(?,?,?,?,?,?,?)');
            $stmt->execute([$case,$leadId,$partnerId,$title,$description,hash('sha256',$token),opsTrackingExpiry($cfg)]);
            $job=(int)$pdo->lastInsertId();
            $stmt=$pdo->prepare("UPDATE leads SET status='converted' WHERE id=?");$stmt->execute([$leadId]);
            opsEvent($pdo,$job,'admin',(string)$_SESSION['account_email'],'created','Initial case created');
            $pdo->commit();
            // Never persist the un-hashed tracking secret in database or Git; display once to the signed-in admin.
            $_SESSION['new_tracking_link']='/track/?token='.$token;
            redirectPortal('Case '.$case.' created. Copy the one-time customer tracking link below.');
        }
        if($action==='update_job') {
            requireAdmin();
            $id=filter_var($_POST['job_id']??'',FILTER_VALIDATE_INT);
            $status=(string)($_POST['status']??'');
            $partnerId=($_POST['partner_id']??'')===''?null:filter_var($_POST['partner_id'],FILTER_VALIDATE_INT);
            $quote=($_POST['customer_quote']??'')===''?null:(string)$_POST['customer_quote'];
            $approvalMethod=(string)($_POST['approval_method']??'');
            $approvalReference=trim((string)($_POST['approval_reference']??''));
            $cost=($_POST['partner_cost']??'')===''?null:(string)$_POST['partner_cost'];
            $note=trim((string)($_POST['note']??''));
            foreach([$quote,$cost] as $v){if($v!==null&&(!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/',$v)||(float)$v>9999999999.99))throw new InvalidArgumentException('Invalid amount');}
            if(!$id||!in_array($status,$states,true)||($partnerId!==null&&!$partnerId)||opsLen($note)>1500)throw new InvalidArgumentException('Invalid case update');
            if($partnerId!==null){$stmt=$pdo->prepare('SELECT id FROM partners WHERE id=? AND (is_active=1 OR id=(SELECT partner_id FROM jobs WHERE id=?))');$stmt->execute([$partnerId,$id]);if(!$stmt->fetch())throw new InvalidArgumentException('Partner not found');}
            $pdo->beginTransaction();
            $oldStmt=$pdo->prepare('SELECT status,customer_quote,partner_id FROM jobs WHERE id=? FOR UPDATE');
            $oldStmt->execute([$id]);$old=$oldStmt->fetch();
            if(!$old)throw new InvalidArgumentException('Case not found');
            $previous=(string)$old['status'];
            if($partnerId!==null && $partnerId!==(int)($old['partner_id']??0) && ($_POST['partner_scope_checked']??'')!=='yes')throw new InvalidArgumentException('Confirm customer authorization before assigning a different partner');
            if(!opsCanAdminTransition($previous,$status))throw new InvalidArgumentException('Invalid case status transition');
            if(in_array($status,['awaiting_customer_approval','approved','in_progress','work_completed','closed'],true)
                && ($quote===null || (float)$quote<=0))throw new InvalidArgumentException('Set a reviewed positive customer quotation before proceeding');
            if($status==='approved' && $previous!=='approved'
                && !opsValidApprovalProof($approvalMethod,$approvalReference))throw new InvalidArgumentException('Record a customer approval method and evidence reference');
            if(in_array($previous,['approved','in_progress','work_completed','closed'],true)
                && $old['customer_quote']!==null && $quote!==null
                && (float)$quote!==(float)$old['customer_quote']
                && $status!=='quote_pending')throw new InvalidArgumentException('Return case to quote pending before changing an approved customer quotation');
            $stmt=$pdo->prepare('UPDATE jobs SET status=?,partner_id=?,customer_quote=?,partner_cost=? WHERE id=?');
            $stmt->execute([$status,$partnerId,$quote,$cost,$id]);
            if($stmt->rowCount()===0){$check=$pdo->prepare('SELECT id FROM jobs WHERE id=?');$check->execute([$id]);if(!$check->fetch())throw new InvalidArgumentException('Case not found');}
            opsEvent($pdo,$id,'admin',(string)$_SESSION['account_email'],'updated',$note?:('Case status: '.$previous.' → '.$status));
            if($status==='approved' && $previous!=='approved') {
                opsEvent($pdo,$id,'admin',(string)$_SESSION['account_email'],'customer_approval_recorded',
                    'Method: '.$approvalMethod.'; evidence: '.$approvalReference.'; entered by owner (not verified by system)');
            }
            $pdo->commit();
            redirectPortal('Case updated; customer approval is never inferred from a website status');
        }
        if($action==='rotate_link') {
            requireAdmin();$id=filter_var($_POST['job_id']??'',FILTER_VALIDATE_INT);
            if(!$id)throw new InvalidArgumentException('Invalid case');
            $token=bin2hex(random_bytes(32));
            $stmt=$pdo->prepare('UPDATE jobs SET tracking_token_hash=?,tracking_expires_at=? WHERE id=?');$stmt->execute([hash('sha256',$token),opsTrackingExpiry($cfg),$id]);
            if(!$stmt->rowCount())throw new InvalidArgumentException('Case not found');
            opsEvent($pdo,$id,'admin',(string)$_SESSION['account_email'],'tracking_token_rotated');
            $_SESSION['new_tracking_link']='/track/?token='.$token;
            redirectPortal('Previous customer tracking link revoked. Copy the new link below.');
        }
        if($action==='partner_update') {
            if(($_SESSION['role']??'')!=='partner')throw new InvalidArgumentException('Partner account required');
            $id=filter_var($_POST['job_id']??'',FILTER_VALIDATE_INT);
            $status=(string)($_POST['status']??'');$note=trim((string)($_POST['note']??''));
            if(!$id||!in_array($status,['assessment','in_progress','work_completed'],true)||opsLen($note)>1500)throw new InvalidArgumentException('Invalid status update');
            if($note==='')throw new InvalidArgumentException('Please provide a brief work note');
            $pdo->beginTransaction();
            $check=$pdo->prepare('SELECT status FROM jobs WHERE id=? AND partner_id=? FOR UPDATE');
            $check->execute([$id,(int)$_SESSION['partner_id']]);$previous=$check->fetchColumn();
            if($previous===false || !opsCanPartnerUpdate((string)$previous,$status)) {
                throw new InvalidArgumentException('Case not assigned or customer approval not recorded');
            }
            if($status!==$previous) {
                $stmt=$pdo->prepare('UPDATE jobs SET status=? WHERE id=? AND partner_id=?');
                $stmt->execute([$status,$id,(int)$_SESSION['partner_id']]);
            }
            opsEvent($pdo,$id,'partner',(string)$_SESSION['account_email'],'partner_update',
                $previous.' → '.$status.': '.$note);
            $pdo->commit();
            redirectPortal('Work update recorded. Customer quote approval remains owner-controlled.');
        }
        throw new InvalidArgumentException('Unknown action');
    } catch(Throwable $e) {
        if($pdo->inTransaction())$pdo->rollBack();
        error_log('Aaditechs operations rejected action '.$action);
        $_SESSION['flash']=$e instanceof InvalidArgumentException ? $e->getMessage() : 'Could not complete the request';
        header('Location: /portal/',true,303);exit;
    }
}
$role=(string)($_SESSION['role']??'');
$flash=(string)($_SESSION['flash']??'');unset($_SESSION['flash']);
$tracking=(string)($_SESSION['new_tracking_link']??'');unset($_SESSION['new_tracking_link']);
$trackingOrigin='';
try {$trackingOrigin=opsPublicBaseUrl($cfg);}catch(Throwable $e){$tracking='';}
$partners=[];$leads=[];$jobs=[];$activityByJob=[];$notificationCounts=[];$failedNotifications=[];
if($role==='admin') {
    $partners=$pdo->query('SELECT id,display_name,login_email,specialties,is_active FROM partners ORDER BY display_name LIMIT 200')->fetchAll();
    $notificationCounts=$pdo->query('SELECT status,COUNT(*) AS total FROM notification_outbox GROUP BY status')->fetchAll();
    $failedNotifications=$pdo->query("SELECT o.id,l.reference,o.attempts,o.last_error_code,o.updated_at FROM notification_outbox o JOIN leads l ON l.id=o.lead_id WHERE o.status='dead' ORDER BY o.updated_at DESC LIMIT 30")->fetchAll();
    $leads=$pdo->query('SELECT id,reference,full_name,email,phone,service,device,message,source,status,created_at FROM leads ORDER BY created_at DESC LIMIT 75')->fetchAll();
    $jobs=$pdo->query('SELECT j.*,p.display_name AS partner_name,l.reference AS lead_reference FROM jobs j JOIN leads l ON l.id=j.lead_id LEFT JOIN partners p ON p.id=j.partner_id ORDER BY j.updated_at DESC LIMIT 75')->fetchAll();
    if($jobs) {
        $ids=array_map(fn($job)=>(int)$job['id'],$jobs);
        $placeholders=implode(',',array_fill(0,count($ids),'?'));
        $stmt=$pdo->prepare("SELECT job_id,actor_role,actor_label,event_type,note,created_at FROM job_activity WHERE job_id IN ($placeholders) ORDER BY created_at DESC LIMIT 300");
        $stmt->execute($ids);foreach($stmt->fetchAll() as $activity)$activityByJob[(int)$activity['job_id']][]=$activity;
    }
} elseif($role==='partner') {
    $stmt=$pdo->prepare('SELECT id,case_code,title,description,status,created_at,updated_at FROM jobs WHERE partner_id=? ORDER BY updated_at DESC LIMIT 75');
    $stmt->execute([(int)$_SESSION['partner_id']]);$jobs=$stmt->fetchAll();
}
?>
<!doctype html><html lang="en-IN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Aaditechs Private Operations Portal</title><link rel="stylesheet" href="/assets/portal.css"></head><body>
<header><div class="shell top"><a class="brand" href="/">Aaditechs <small>Private Operations</small></a><?php if($role): ?><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><button name="action" value="logout">Sign out</button></form><?php endif; ?></div></header>
<main class="shell">
<h1><?= $role==='admin'?'Owner Dashboard':($role==='partner'?'Assigned Partner Jobs':'Secure Sign-in') ?></h1><p class="sub">This portal is separate from the public website. Partner accounts see assigned work only, not customer contact details or owner financials.</p>
<?php if($flash): ?><p class="alert" role="status"><?=h($flash)?></p><?php endif; ?>
<?php if($tracking): ?><section class="panel alert"><h2>Customer tracking link — copy now</h2><p>The token is shown once; a SHA-256 hash and an expiry time are saved. Send privately to the correct customer. Anyone with the link can view limited case status until expiry/revocation.</p><code><?=h($trackingOrigin.$tracking)?></code></section><?php endif; ?>
<?php if(!$role): ?>
<section class="panel narrow"><h2>Sign in</h2><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><label>Email<input name="email" type="email" maxlength="160" required autocomplete="username"></label><label>Password<input name="password" type="password" required autocomplete="current-password"></label><label>Authenticator code (owner, if enabled)<input name="totp" inputmode="numeric" type="text" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code" placeholder="6-digit code"></label><button name="action" value="login">Sign in</button></form><p class="small">Accounts are provisioned by Aaditechs. No public registration. Contact the owner for account access.</p></section>
<?php elseif($role==='admin'): ?>
<?php if(($cfg['growth']['enabled']??false)===true&&($cfg['intelligence_dashboard']['enabled']??false)===true): ?><section class="panel"><h2>Business intelligence</h2><p>Review aggregate inquiries, opportunities, communications, approvals and integration health.</p><p><a href="/portal/intelligence.php">Open private intelligence dashboard</a></p></section><?php endif; ?>
<?php if(($cfg['media_upload']['inventory_enabled']??false)===true): ?><section class="panel"><h2>Private media</h2><p><a href="/portal/media.php">Open owner media inventory and preview</a></p></section><?php endif; ?>
<section class="panel"><h2>Owner notification delivery</h2><p class="small">Inquiries are saved to MySQL; optional email alerts are queued and delivered by the private Hostinger cron worker. A database lead is not proof that an email was delivered.</p>
<p class="small">Email feature: <?=($cfg['mail']['enabled']??false)===true?'Enabled (requires configured cron)':'Disabled'?>.</p>
<?php foreach($notificationCounts as $item): ?><p class="small"><?=h($item['status'])?>: <?=h($item['total'])?></p><?php endforeach; ?>
<?php if($failedNotifications): ?><h3>Needs attention</h3><p class="small">Only failed messages can be manually requeued. Check SMTP configuration first.</p><?php foreach($failedNotifications as $notification): ?>
<form class="inline" method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="notification_id" value="<?=h($notification['id'])?>"><span><?=h($notification['reference'])?> · attempts <?=h($notification['attempts'])?> · <?=h($notification['last_error_code'])?></span><button name="action" value="retry_notification">Queue retry</button></form>
<?php endforeach; ?><?php endif; ?></section>
<section class="panel"><h2>Recent leads <span class="count"><?=count($leads)?></span></h2><p class="small">Newest 75 leads. Review requirements before quoting. Do not share customer personal details with a partner without an appropriate basis.</p>
<?php if(!$leads): ?><p>No leads yet. The website lead feature is disabled until Hostinger database and privacy setup are complete.</p><?php endif; ?>
<?php foreach($leads as $lead): ?><details class="entry"><summary><strong><?=h($lead['reference'])?></strong> · <?=h($lead['service'])?> · <?=h($lead['status'])?> <span><?=h($lead['created_at'])?></span></summary><p>Name: <?=h($lead['full_name'])?> · <?=h($lead['email'])?> · <?=h($lead['phone'])?></p><p>Device: <?=h($lead['device'])?> · Source: <?=h($lead['source'])?></p><pre><?=h($lead['message'])?></pre><form class="inline" method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="lead_id" value="<?=h($lead['id'])?>"><label>Lead status<select name="status"><?php foreach(['new','qualified','responded','converted','closed'] as $s): ?><option value="<?=h($s)?>" <?= $lead['status']===$s?'selected':'' ?>><?=h($s)?></option><?php endforeach; ?></select></label><button name="action" value="set_lead_status">Update</button></form>
<details class="nested"><summary>Create case / work order</summary><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="lead_id" value="<?=h($lead['id'])?>"><label>Title<input name="title" maxlength="160" required value="<?=h($lead['service'])?> assessment"></label><label>Scope summary (no unnecessary customer data)<textarea name="description" maxlength="2000"></textarea></label><label>Partner<select name="partner_id"><option value="">Not assigned</option><?php foreach($partners as $partner):if(!$partner['is_active'])continue; ?><option value="<?=h($partner['id'])?>"><?=h($partner['display_name'])?></option><?php endforeach; ?></select></label><label><input type="checkbox" name="partner_scope_checked" value="yes"> I verified the scope is safe to share with any assigned partner and have a valid basis to coordinate the service.</label><button name="action" value="create_job">Create case and tracking token</button></form></details></details><?php endforeach; ?></section>
<section class="panel"><h2>Service cases <span class="count"><?=count($jobs)?></span></h2>
<?php foreach($jobs as $job): ?><details class="entry"><summary><strong><?=h($job['case_code'])?></strong> · <?=h($job['title'])?> · <?=h($job['status'])?> <span><?=h($job['updated_at'])?></span></summary><p>Lead: <?=h($job['lead_reference'])?> · Assigned: <?=h($job['partner_name']??'Unassigned')?></p><pre><?=h($job['description'])?></pre><form method="post" class="edit-grid"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="job_id" value="<?=h($job['id'])?>"><label>Status<select name="status"><?php foreach($states as $s): ?><option value="<?=h($s)?>" <?= $job['status']===$s?'selected':'' ?>><?=h(str_replace('_',' ',$s))?></option><?php endforeach; ?></select></label><label>Assigned partner<select name="partner_id"><option value="">Unassigned</option><?php foreach($partners as $partner):if(!$partner['is_active'] && (int)$job['partner_id']!==(int)$partner['id'])continue; ?><option value="<?=h($partner['id'])?>" <?= (int)$job['partner_id']===(int)$partner['id']?'selected':'' ?>><?=h($partner['display_name'])?><?= $partner['is_active']?'':' (inactive — reassign)' ?></option><?php endforeach; ?></select></label><label>Customer quote (INR)<input name="customer_quote" inputmode="decimal" value="<?=h($job['customer_quote'])?>" placeholder="After manual review"></label><label>Partner cost (INR)<input name="partner_cost" inputmode="decimal" value="<?=h($job['partner_cost'])?>" placeholder="Internal only"></label><label class="full">Owner-only activity note<textarea name="note" maxlength="1500" placeholder="Internal note; partner and customer cannot see this"></textarea></label><label>Customer approval method<select name="approval_method"><option value="">Not applicable unless approving</option><option value="signed_document">Signed document</option><option value="customer_email">Customer email</option><option value="recorded_call">Recorded call/consent record</option><option value="other_record">Other documented approval</option></select></label><label>Approval evidence reference<input name="approval_reference" maxlength="300" placeholder="Required when moving to Approved; email ID, dated record, etc."></label><label class="full"><input type="checkbox" name="partner_scope_checked" value="yes"> If assigning/reassigning a partner, I checked their scope and customer authorization.</label><button name="action" value="update_job">Save reviewed update</button></form>
<form method="post" class="inline"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="job_id" value="<?=h($job['id'])?>"><button class="secondary" name="action" value="rotate_link">Rotate customer tracking token</button></form>
<details class="nested"><summary>Audit history</summary><?php foreach(($activityByJob[(int)$job['id']]??[]) as $activity): ?><p class="small"><?=h($activity['created_at'])?> · <?=h($activity['actor_role'])?> · <?=h($activity['event_type'])?> · <?=h($activity['note'])?></p><?php endforeach; ?></details></details><?php endforeach; ?>
<?php if(!$jobs): ?><p>No service cases have been created yet.</p><?php endif; ?></section>
<section class="panel"><h2>Partner accounts</h2><p class="small">Only the owner can create partner accounts. Account passwords are stored as secure hashes and never emailed by the application.</p><form method="post" class="edit-grid"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><label>Partner display name<input name="name" maxlength="120" required></label><label>Partner login email<input name="email" type="email" maxlength="160" required></label><label>Specialties<input name="specialties" maxlength="250" placeholder="AV, data recovery, IT support"></label><label>Initial password (12+ chars)<input name="password" type="password" minlength="12" required autocomplete="new-password"></label><button name="action" value="add_partner">Add partner</button></form>
<div class="list"><?php foreach($partners as $partner): ?><details class="entry"><summary><?=h($partner['display_name'])?> — <?=h($partner['login_email'])?> · <?=h($partner['specialties'])?> <?= $partner['is_active']?'(active)':'(inactive)' ?></summary><form method="post" class="inline"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="partner_id" value="<?=h($partner['id'])?>"><input type="hidden" name="mode" value="<?= $partner['is_active']?'disable':'enable' ?>"><button class="secondary" name="action" value="manage_partner"><?= $partner['is_active']?'Disable partner access':'Enable partner access' ?></button></form><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="partner_id" value="<?=h($partner['id'])?>"><input type="hidden" name="mode" value="password"><label>Reset partner password (12+ characters)<input name="new_password" type="password" minlength="12" required autocomplete="new-password"></label><button name="action" value="manage_partner">Update password hash</button></form></details><?php endforeach; ?></div></section>
<?php else: ?>
<section class="panel"><h2>Your assigned jobs</h2><p class="small">You can report assessment notes, start work only after the owner records customer approval, and report completion. Aaditechs controls all quotations.</p>
<?php if(!$jobs): ?><p>No work has been assigned to your account.</p><?php endif; ?>
<?php foreach($jobs as $job): ?><details class="entry"><summary><strong><?=h($job['case_code'])?></strong> · <?=h($job['title'])?> · <?=h($job['status'])?></summary><pre><?=h($job['description'])?></pre><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="job_id" value="<?=h($job['id'])?>"><label>Report status<select name="status"><option>assessment</option><option>in_progress</option><option>work_completed</option></select></label><label>Work note<textarea name="note" maxlength="1500" placeholder="Status, blockers or parts needed; no sensitive data"></textarea></label><button name="action" value="partner_update">Submit work update</button></form></details><?php endforeach; ?></section>
<?php endif; ?>
</main><footer><div class="shell">Aaditechs Private Operations Hub · Not a payment service · No automatic customer approvals</div></footer></body></html>
