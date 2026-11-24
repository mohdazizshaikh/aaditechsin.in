<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/gmail-renew-lib.php';
require dirname(__DIR__).'/secure-setup/maintenance/communications-retention-lib.php';
function crCheck(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
function crBlocked(callable $fn):void{try{$fn();}catch(RuntimeException $e){return;}throw new LogicException('Expected refusal');}
$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$ts=$now->getTimestamp();
$cfg=['growth'=>['enabled'=>true],'communications'=>['enabled'=>true,'retention_enabled'=>true,'gmail'=>['enabled'=>true,'mailbox'=>'business@example.invalid','revoked'=>false,'renewal'=>['enabled'=>true,'client_id'=>'synthetic-client','client_secret'=>'synthetic-secret','refresh_token'=>'synthetic-refresh']]]];
$grant=['access_token'=>str_repeat('a',40),'token_type'=>'Bearer','expires_in'=>3600,'scope'=>'https://www.googleapis.com/auth/gmail.metadata'];
$read=static fn($p,$resource)=>$resource==='profile'?['emailAddress'=>'business@example.invalid']:['id'=>'INBOX','type'=>'system','messagesTotal'=>12,'messagesUnread'=>2];
$exchange=static function($d)use($grant){crCheck($d['grant_type']==='refresh_token'&&$d['refresh_token']==='synthetic-refresh','Exact refresh request');return $grant;};
$c=gmailRenewCandidate($cfg,$exchange,$read,$ts);
crCheck($c['access_token']===$grant['access_token']&&preg_match('/^[a-f0-9]{32}$/D',$c['generation'])===1&&$c['expires_utc']===gmdate('Y-m-d\TH:i:s\Z',$ts+3540)&&count($c)===4,'Candidate fields');
crBlocked(static fn()=>gmailRenewCandidate([],$exchange,$read,$ts));
foreach([['scope'=>'https://mail.google.com/'],['scope'=>null],['expires_in'=>600],['token_type'=>'Other'],['refresh_token'=>'rotated']] as $bad)crBlocked(static fn()=>gmailRenewCandidate($cfg,static fn()=>array_replace($grant,$bad),$read,$ts));
crBlocked(static fn()=>gmailRenewCandidate($cfg,$exchange,static fn()=>['emailAddress'=>'wrong@example.invalid'],$ts));
$dir=sys_get_temp_dir().'/aaditechs-renew-'.bin2hex(random_bytes(8));mkdir($dir,0700);
try{
    $cfg['communications']['gmail']['renewal']['candidate_directory']=$dir;
    crCheck(gmailRenewDirectory($cfg,dirname(__DIR__).'/public_html')===$dir,'Private directory');
    gmailRenewStore($dir,$c);$path=$dir.'/gmail-renewal-candidate.json';
    crCheck((fileperms($path)&0777)===0600&&json_decode(file_get_contents($path),true)===$c,'Private candidate');
    crBlocked(static fn()=>gmailRenewStore($dir,$c));
}finally{if(isset($path)&&is_file($path))unlink($path);rmdir($dir);}
if(($argv[1]??'')!=='--database'){echo "Credential candidate and private storage rules: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);
    crBlocked(static fn()=>commRetentionPlan($db,$cfg,$now));
    growthCommand($db,'/pause',$cfg);
    $q=$db->prepare("INSERT INTO growth_communication_daily(channel,day_utc,received_count,answered_count,missed_count) VALUES('calls',?,1,0,1)");
    $q->execute([$now->modify('-91 days')->format('Y-m-d')]);$old=(int)$db->lastInsertId();
    $q->execute([$now->modify('-90 days')->format('Y-m-d')]);$keep=(int)$db->lastInsertId();
    $db->prepare('INSERT INTO growth_gmail_snapshot(id,generation,inbox_count,unread_count,captured_at) VALUES(1,?,1,1,?) ON DUPLICATE KEY UPDATE generation=VALUES(generation),captured_at=VALUES(captured_at)')->execute([str_repeat('a',32),$now->modify('-31 days')->format('Y-m-d H:i:s')]);
    $p=commRetentionPlan($db,$cfg,$now);crCheck(in_array($old,$p['ids'],true)&&!in_array($keep,$p['ids'],true)&&$p['gmail']!==null,'Cutoffs');
    crBlocked(static fn()=>commRetentionApply($db,$cfg,str_repeat('0',64),$now));
    $q->execute([$now->modify('-92 days')->format('Y-m-d')]);
    crBlocked(static fn()=>commRetentionApply($db,$cfg,$p['digest'],$now));
    $p=commRetentionPlan($db,$cfg,$now);$r=commRetentionApply($db,$cfg,$p['digest'],$now);
    crCheck($r['aggregate_deleted']===count($p['ids'])&&$r['gmail_deleted']===1,'Exact plan');
    $q=$db->prepare('SELECT COUNT(*) FROM growth_communication_daily WHERE id=?');$q->execute([$keep]);crCheck((int)$q->fetchColumn()===1,'Preserve boundary');
    $db->rollBack();echo "Synthetic retention plan, pause, stale digest and boundary: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Credential/retention test failed: '.$e->getMessage()."\n");exit(1);}
