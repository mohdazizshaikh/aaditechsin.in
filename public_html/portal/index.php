<?php
declare(strict_types=1);
require __DIR__ . '/../api/operations.php';
opsHeaders();
try {
    $cfg=opsConfig();
    if (($cfg['portal']['enabled']??false)!==true || !opsSsl()) throw new RuntimeException('Operations portal is not enabled or HTTPS is unavailable');
    opsSession();
    $pdo=opsDb($cfg);
    // 30-minute inactivity expiration and immediate disabled-partner revocation.
    if(isset($_SESSION['role']) && time()-(int)($_SESSION['last_seen']??0)>1800) {
        $_SESSION=[];session_regenerate_id(true);
    }
    $_SESSION['last_seen']=time();
    if(($_SESSION['role']??null)==='partner') {
        $check=$pdo->prepare('SELECT id FROM partners WHERE id=? AND is_active=1');
        $check->execute([(int)($_SESSION['partner_id']??0)]);
        if(!$check->fetch()) {$_SESSION=[];session_regenerate_id(true);}
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
        $role=null;$partner=null;
        if($email!=='' && hash_equals(strtolower((string)($cfg['portal']['admin_email']??'')),$email)
            && !str_starts_with((string)($cfg['portal']['admin_password_hash']??''),'REPLACE_')
            && password_verify($password,(string)($cfg['portal']['admin_password_hash']??''))) {
            $role='admin';
        } elseif($email!=='') {
            $stmt=$pdo->prepare('SELECT id,display_name,password_hash FROM partners WHERE login_email=? AND is_active=1 LIMIT 1');
            $stmt->execute([$email]);$partner=$stmt->fetch();
            if($partner && password_verify($password,(string)$partner['password_hash']))$role='partner';
        }
        if(!$role){$attempts['count']++;rewind($fp);ftruncate($fp,0);fwrite($fp,json_encode($attempts));fflush($fp);flock($fp,LOCK_UN);fclose($fp);$_SESSION['flash']='Invalid sign-in details';header('Location: /portal/',true,303);exit;}
        rewind($fp);ftruncate($fp,0);fwrite($fp,json_encode(['started'=>time(),'count'=>0]));fflush($fp);flock($fp,LOCK_UN);fclose($fp);
        session_regenerate_id(true);
        $_SESSION['role']=$role;$_SESSION['account_email']=$email;$_SESSION['partner_id']=$role==='partner'?(int)$partner['id']:null;
        redirectPortal('Signed in successfully');
    }
    requireSignedIn();
    try {
        if($action==='add_partner') {
            requireAdmin();
            $name=trim((string)($_POST['name']??''));$email=strtolower(trim((string)($_POST['email']??'')));
            $specialties=trim((string)($_POST['specialties']??''));$password=(string)($_POST['password']??'');
            if($name===''||opsLen($name)>120||!filter_var($email,FILTER_VALIDATE_EMAIL)||opsLen($email)>160||opsLen($specialties)>250||strlen($password)<12||strlen($password)>200)throw new InvalidArgumentException('Check partner name, email, and 12+ character password');
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
                $stmt=$pdo->prepare('UPDATE partners SET password_hash=? WHERE id=?');
                $stmt->execute([password_hash($pass,PASSWORD_DEFAULT),$id]);
            } else {
                $stmt=$pdo->prepare('UPDATE partners SET is_active=? WHERE id=?');
                $stmt->execute([$mode==='enable'?1:0,$id]);
            }
            redirectPortal('Partner access updated. Disabling a partner blocks their next authenticated request.');
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
            $pdo->beginTransaction();
            $check=$pdo->prepare('SELECT id FROM leads WHERE id=? FOR UPDATE');$check->execute([$leadId]);
            if(!$check->fetch())throw new InvalidArgumentException('Lead not found');
            if($partnerId!==null){$check=$pdo->prepare('SELECT id FROM partners WHERE id=? AND is_active=1');$check->execute([$partnerId]);if(!$check->fetch())throw new InvalidArgumentException('Partner not found');}
            $token=bin2hex(random_bytes(32));$case=opsRef('ATC');
            $stmt=$pdo->prepare('INSERT INTO jobs(case_code,lead_id,partner_id,title,description,tracking_token_hash) VALUES(?,?,?,?,?,?)');
            $stmt->execute([$case,$leadId,$partnerId,$title,$description,hash('sha256',$token)]);
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
            $cost=($_POST['partner_cost']??'')===''?null:(string)$_POST['partner_cost'];
            $note=trim((string)($_POST['note']??''));
            foreach([$quote,$cost] as $v){if($v!==null&&(!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/',$v)||(float)$v>9999999999.99))throw new InvalidArgumentException('Invalid amount');}
            if(!$id||!in_array($status,$states,true)||($partnerId!==null&&!$partnerId)||opsLen($note)>1500)throw new InvalidArgumentException('Invalid case update');
            if($partnerId!==null){$stmt=$pdo->prepare('SELECT id FROM partners WHERE id=? AND is_active=1');$stmt->execute([$partnerId]);if(!$stmt->fetch())throw new InvalidArgumentException('Partner not found');}
            $pdo->beginTransaction();
            $stmt=$pdo->prepare('UPDATE jobs SET status=?,partner_id=?,customer_quote=?,partner_cost=? WHERE id=?');
            $stmt->execute([$status,$partnerId,$quote,$cost,$id]);
            if($stmt->rowCount()===0){$check=$pdo->prepare('SELECT id FROM jobs WHERE id=?');$check->execute([$id]);if(!$check->fetch())throw new InvalidArgumentException('Case not found');}
            opsEvent($pdo,$id,'admin',(string)$_SESSION['account_email'],'updated',$note?:'Case information updated');
            $pdo->commit();
            redirectPortal('Case updated; customer approval is never inferred from a website status');
        }
        if($action==='rotate_link') {
            requireAdmin();$id=filter_var($_POST['job_id']??'',FILTER_VALIDATE_INT);
            if(!$id)throw new InvalidArgumentException('Invalid case');
            $token=bin2hex(random_bytes(32));
            $stmt=$pdo->prepare('UPDATE jobs SET tracking_token_hash=? WHERE id=?');$stmt->execute([hash('sha256',$token),$id]);
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
            $allowedCurrent=match($status) {
                'assessment'=>['assessment'],
                'in_progress'=>['assessment','approved','in_progress'],
                'work_completed'=>['in_progress','work_completed'],
            };
            $qmarks=implode(',',array_fill(0,count($allowedCurrent),'?'));
            $stmt=$pdo->prepare("UPDATE jobs SET status=? WHERE id=? AND partner_id=? AND status IN ($qmarks)");
            $stmt->execute(array_merge([$status,$id,(int)$_SESSION['partner_id']],$allowedCurrent));
            if(!$stmt->rowCount())throw new InvalidArgumentException('Case not assignable or already in this status');
            opsEvent($pdo,$id,'partner',(string)$_SESSION['account_email'],'partner_update',$note?:'Partner updated work status');
            redirectPortal('Work status sent to Aaditechs. Final customer acceptance and pricing remain owner-controlled.');
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
$partners=[];$leads=[];$jobs=[];$activityByJob=[];
if($role==='admin') {
    $partners=$pdo->query('SELECT id,display_name,login_email,specialties,is_active FROM partners ORDER BY display_name LIMIT 200')->fetchAll();
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
<?php if($tracking): ?><section class="panel alert"><h2>Customer tracking link — copy now</h2><p>The token is shown once and only a SHA-256 hash is saved. Send privately to the correct customer; anyone with this link can view the limited case status.</p><code><?=h('https://'.($_SERVER['HTTP_HOST']??'aaditechs.in').$tracking)?></code></section><?php endif; ?>
<?php if(!$role): ?>
<section class="panel narrow"><h2>Sign in</h2><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><label>Email<input name="email" type="email" maxlength="160" required autocomplete="username"></label><label>Password<input name="password" type="password" required autocomplete="current-password"></label><button name="action" value="login">Sign in</button></form><p class="small">Accounts are provisioned by Aaditechs. No public registration. Contact the owner for account access.</p></section>
<?php elseif($role==='admin'): ?>
<section class="panel"><h2>Recent leads <span class="count"><?=count($leads)?></span></h2><p class="small">Newest 75 leads. Review requirements before quoting. Do not share customer personal details with a partner without an appropriate basis.</p>
<?php if(!$leads): ?><p>No leads yet. The website lead feature is disabled until Hostinger database and privacy setup are complete.</p><?php endif; ?>
<?php foreach($leads as $lead): ?><details class="entry"><summary><strong><?=h($lead['reference'])?></strong> · <?=h($lead['service'])?> · <?=h($lead['status'])?> <span><?=h($lead['created_at'])?></span></summary><p>Name: <?=h($lead['full_name'])?> · <?=h($lead['email'])?> · <?=h($lead['phone'])?></p><p>Device: <?=h($lead['device'])?> · Source: <?=h($lead['source'])?></p><pre><?=h($lead['message'])?></pre><form class="inline" method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="lead_id" value="<?=h($lead['id'])?>"><label>Lead status<select name="status"><?php foreach(['new','qualified','responded','converted','closed'] as $s): ?><option value="<?=h($s)?>" <?= $lead['status']===$s?'selected':'' ?>><?=h($s)?></option><?php endforeach; ?></select></label><button name="action" value="set_lead_status">Update</button></form>
<details class="nested"><summary>Create case / work order</summary><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="lead_id" value="<?=h($lead['id'])?>"><label>Title<input name="title" maxlength="160" required value="<?=h($lead['service'])?> assessment"></label><label>Scope summary (no unnecessary customer data)<textarea name="description" maxlength="2000"></textarea></label><label>Partner<select name="partner_id"><option value="">Not assigned</option><?php foreach($partners as $partner):if(!$partner['is_active'])continue; ?><option value="<?=h($partner['id'])?>"><?=h($partner['display_name'])?></option><?php endforeach; ?></select></label><button name="action" value="create_job">Create case and tracking token</button></form></details></details><?php endforeach; ?></section>
<section class="panel"><h2>Service cases <span class="count"><?=count($jobs)?></span></h2>
<?php foreach($jobs as $job): ?><details class="entry"><summary><strong><?=h($job['case_code'])?></strong> · <?=h($job['title'])?> · <?=h($job['status'])?> <span><?=h($job['updated_at'])?></span></summary><p>Lead: <?=h($job['lead_reference'])?> · Assigned: <?=h($job['partner_name']??'Unassigned')?></p><pre><?=h($job['description'])?></pre><form method="post" class="edit-grid"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="job_id" value="<?=h($job['id'])?>"><label>Status<select name="status"><?php foreach($states as $s): ?><option value="<?=h($s)?>" <?= $job['status']===$s?'selected':'' ?>><?=h(str_replace('_',' ',$s))?></option><?php endforeach; ?></select></label><label>Assigned partner<select name="partner_id"><option value="">Unassigned</option><?php foreach($partners as $partner):if(!$partner['is_active'])continue; ?><option value="<?=h($partner['id'])?>" <?= (int)$job['partner_id']===(int)$partner['id']?'selected':'' ?>><?=h($partner['display_name'])?></option><?php endforeach; ?></select></label><label>Customer quote (INR)<input name="customer_quote" inputmode="decimal" value="<?=h($job['customer_quote'])?>" placeholder="After manual review"></label><label>Partner cost (INR)<input name="partner_cost" inputmode="decimal" value="<?=h($job['partner_cost'])?>" placeholder="Internal only"></label><label class="full">Internal activity note<textarea name="note" maxlength="1500" placeholder="Notes for owner and assigned partner; not shared with customer"></textarea></label><button name="action" value="update_job">Save reviewed update</button></form>
<form method="post" class="inline"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="job_id" value="<?=h($job['id'])?>"><button class="secondary" name="action" value="rotate_link">Rotate customer tracking token</button></form>
<details class="nested"><summary>Audit history</summary><?php foreach(($activityByJob[(int)$job['id']]??[]) as $activity): ?><p class="small"><?=h($activity['created_at'])?> · <?=h($activity['actor_role'])?> · <?=h($activity['event_type'])?> · <?=h($activity['note'])?></p><?php endforeach; ?></details></details><?php endforeach; ?>
<?php if(!$jobs): ?><p>No service cases have been created yet.</p><?php endif; ?></section>
<section class="panel"><h2>Partner accounts</h2><p class="small">Only the owner can create partner accounts. Account passwords are stored as secure hashes and never emailed by the application.</p><form method="post" class="edit-grid"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><label>Partner display name<input name="name" maxlength="120" required></label><label>Partner login email<input name="email" type="email" maxlength="160" required></label><label>Specialties<input name="specialties" maxlength="250" placeholder="AV, data recovery, IT support"></label><label>Initial password (12+ chars)<input name="password" type="password" minlength="12" required autocomplete="new-password"></label><button name="action" value="add_partner">Add partner</button></form>
<div class="list"><?php foreach($partners as $partner): ?><details class="entry"><summary><?=h($partner['display_name'])?> — <?=h($partner['login_email'])?> · <?=h($partner['specialties'])?> <?= $partner['is_active']?'(active)':'(inactive)' ?></summary><form method="post" class="inline"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="partner_id" value="<?=h($partner['id'])?>"><input type="hidden" name="mode" value="<?= $partner['is_active']?'disable':'enable' ?>"><button class="secondary" name="action" value="manage_partner"><?= $partner['is_active']?'Disable partner access':'Enable partner access' ?></button></form><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="partner_id" value="<?=h($partner['id'])?>"><input type="hidden" name="mode" value="password"><label>Reset partner password (12+ characters)<input name="new_password" type="password" minlength="12" required autocomplete="new-password"></label><button name="action" value="manage_partner">Update password hash</button></form></details><?php endforeach; ?></div></section>
<?php else: ?>
<section class="panel"><h2>Your assigned jobs</h2><p class="small">You can report assessment, work-in-progress and work completed. Aaditechs owner approves quotes and all customer commitments.</p>
<?php if(!$jobs): ?><p>No work has been assigned to your account.</p><?php endif; ?>
<?php foreach($jobs as $job): ?><details class="entry"><summary><strong><?=h($job['case_code'])?></strong> · <?=h($job['title'])?> · <?=h($job['status'])?></summary><pre><?=h($job['description'])?></pre><form method="post"><input type="hidden" name="csrf" value="<?=h($csrf)?>"><input type="hidden" name="job_id" value="<?=h($job['id'])?>"><label>Report status<select name="status"><option>assessment</option><option>in_progress</option><option>work_completed</option></select></label><label>Work note<textarea name="note" maxlength="1500" placeholder="Status, blockers or parts needed; no sensitive data"></textarea></label><button name="action" value="partner_update">Submit work update</button></form></details><?php endforeach; ?></section>
<?php endif; ?>
</main><footer><div class="shell">Aaditechs Private Operations Hub · Not a payment service · No automatic customer approvals</div></footer></body></html>
