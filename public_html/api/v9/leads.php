<?php
declare(strict_types=1);
// V9 lead intake — OFF by default. Do not route public forms here until staging acceptance.
require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/operations.php';
require __DIR__ . '/lead_rules.php';
header('X-Robots-Tag: noindex, nofollow');
$requestId='req_'.bin2hex(random_bytes(8));
$error=function(int $status,string $code,string $message) use($requestId): never {
    jsonResponse($status,['ok'=>false,'error'=>['code'=>$code,'message'=>$message],'request_id'=>$requestId]);
};
if (($_SERVER['REQUEST_METHOD']??'')!=='POST') $error(405,'VALIDATION_ERROR','Method not allowed');
if (!opsSsl()) $error(403,'NOT_AUTHORIZED','HTTPS required');
if ((int)($_SERVER['CONTENT_LENGTH']??0)>8192) $error(413,'VALIDATION_ERROR','Request too large');
if (strtolower(trim(explode(';',(string)($_SERVER['CONTENT_TYPE']??''))[0]))!=='application/json') $error(415,'VALIDATION_ERROR','JSON required');
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
if ($origin!=='') {
    $parts=parse_url($origin);
    if (!is_array($parts) || ($parts['scheme']??'')!=='https'
        || strtolower((string)($parts['host']??''))!==$host || isset($parts['user']) || isset($parts['pass'])) {
        $error(403,'NOT_AUTHORIZED','Origin not allowed');
    }
}
if (($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site') $error(403,'NOT_AUTHORIZED','Cross-site request not allowed');
$key=trim((string)($_SERVER['HTTP_IDEMPOTENCY_KEY']??''));
if (!v9LeadKeyValid($key)) $error(422,'VALIDATION_ERROR','Valid Idempotency-Key required');
$raw=file_get_contents('php://input',false,null,0,8193);
$data=is_string($raw)?json_decode($raw,true):null;
if (!is_array($data)) $error(400,'VALIDATION_ERROR','Invalid JSON form');
if (!empty($data['company_website'])) jsonResponse(200,['ok'=>true,'request_id'=>$requestId]); // Bot honeypot; no storage.
try {
    $lead=v9LeadNormalize($data);
    $attr=v9LeadAttribution(is_array($data['attribution']??null)?$data['attribution']:[],$lead['source']);
    $leadMessage=v9LeadCombinedMessage($lead);
} catch(InvalidArgumentException $e) {$error(422,'VALIDATION_ERROR','Please check the required fields and contact consent');}
try {$cfg=opsConfig();} catch(Throwable $e) {$error(503,'DEPENDENCY_UNAVAILABLE','Inquiry service not configured');}
$v9=$cfg['v9']??[];
$approvedNotice=(string)($v9['privacy_notice_version']??'');
if (($v9['lead_intake_enabled']??false)!==true || ($cfg['leads']['enabled']??false)!==true
    || ($cfg['database']['enabled']??false)!==true) $error(503,'DISABLED','Inquiry service not available');
if ($approvedNotice==='' || str_starts_with($approvedNotice,'REPLACE_') || $lead['notice_version']!==$approvedNotice) {
    $error(422,'CONSENT_REQUIRED','Privacy notice version needs confirmation');
}
$salt=(string)($cfg['rate_limit_salt']??'');
try {
    $keyHash=v9LeadHash('idempotency',$key,$salt);
    $payloadHash=v9LeadPayloadHash($lead,$attr,$salt);
    $ipHash=v9LeadHash('rate',(string)($_SERVER['REMOTE_ADDR']??''),$salt);
    $pdo=opsDb($cfg);
} catch(Throwable $e) {$error(503,'DEPENDENCY_UNAVAILABLE','Inquiry service not configured');}

// Replays are checked before the rate limit: a retry must not create a second lead.
$lookup=function() use($pdo,$keyHash,$payloadHash): array {
    $q=$pdo->prepare('SELECT r.payload_hash,l.reference FROM lead_requests r JOIN leads l ON l.id=r.lead_id WHERE r.idempotency_hash=? LIMIT 1');
    $q->execute([$keyHash]);
    $row=$q->fetch();
    if (!$row) return ['state'=>'new'];
    return hash_equals((string)$row['payload_hash'],$payloadHash)
        ? ['state'=>'replay','reference'=>(string)$row['reference']] : ['state'=>'conflict'];
};
$replyReplay=function(array $record) use($requestId,$error): void {
    if ($record['state']==='conflict') $error(409,'CONFLICT','This request key was already used for different details');
    if ($record['state']==='replay') jsonResponse(200,['ok'=>true,'data'=>['reference'=>$record['reference'],'next_step'=>'Owner review pending'],'request_id'=>$requestId]);
};
try {$replyReplay($lookup());} catch(PDOException $e) {$error(503,'DEPENDENCY_UNAVAILABLE','Inquiry service unavailable');}

// Keep the V8 privacy-preserving, file-backed 5-minute per-IP throttle.
$dir=dirname(__DIR__,3).'/secure/rate';
if (!is_dir($dir)) @mkdir($dir,0700,true);
if (!is_dir($dir) || !is_writable($dir)) $error(503,'DEPENDENCY_UNAVAILABLE','Spam protection unavailable');
$lock=@fopen($dir.'/v9-'.$ipHash,'c+');
if (!$lock || !flock($lock,LOCK_EX)) $error(503,'DEPENDENCY_UNAVAILABLE','Spam protection unavailable');
$unlock=function() use($lock): void {flock($lock,LOCK_UN);fclose($lock);};
$last=(int)trim((string)stream_get_contents($lock));
if ($last>0 && time()-$last<300) {
    try {$existing=$lookup();} catch(PDOException $e) {$existing=['state'=>'new'];}
    $unlock();$replyReplay($existing);
    $error(429,'RATE_LIMITED','Please wait before sending another inquiry');
}
try {
    // Everything customer-related is committed atomically. No external email/send in transaction.
    $pdo->beginTransaction();
    $reference=opsRef('ATL');
    $insert=$pdo->prepare('INSERT INTO leads(reference,full_name,email,phone,service,device,message,source,consent_at) VALUES(?,?,?,?,?,?,?,?,UTC_TIMESTAMP())');
    $insert->execute([$reference,$lead['fullName'],$lead['email'],$lead['phone'],$lead['service_code'],$lead['device'],$leadMessage,$lead['source']]);
    $leadId=(int)$pdo->lastInsertId();
    $q=$pdo->prepare('INSERT INTO lead_requests(idempotency_hash,payload_hash,lead_id,expires_at) VALUES(?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 7 DAY))');
    $q->execute([$keyHash,$payloadHash,$leadId]);
    $q=$pdo->prepare("INSERT INTO lead_events(lead_id,event_type,actor_type,actor_ref) VALUES(?,'created','customer','public')");
    $q->execute([$leadId]);
    $q=$pdo->prepare("INSERT INTO lead_consent(lead_id,purpose,decision,notice_version,source) VALUES(?,'inquiry_contact','granted',?,?)");
    $q->execute([$leadId,$lead['notice_version'],$lead['source']]);
    $q=$pdo->prepare('INSERT INTO lead_attribution(lead_id,landing_path,source,utm_source,medium,campaign,content,referrer_host) VALUES(?,?,?,?,?,?,?,?)');
    $q->execute([$leadId,$attr['landing_path'],$attr['source'],$attr['utm_source'],$attr['medium'],$attr['campaign'],$attr['content'],$attr['referrer_host']]);
    $pdo->commit();
    rewind($lock);ftruncate($lock,0);fwrite($lock,(string)time());fflush($lock);$unlock();
    jsonResponse(201,['ok'=>true,'data'=>['reference'=>$reference,'next_step'=>'Owner review pending'],'request_id'=>$requestId]);
} catch(Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $unlock();
    // Different-IP concurrent requests may race on unique idempotency_hash.
    // On the losing transaction, return the winner's reference when available.
    try {$replyReplay($lookup());} catch(Throwable $ignored) {}
    error_log('Aaditechs V9 lead persistence failed');
    $error(503,'DEPENDENCY_UNAVAILABLE','Could not save inquiry');
}
