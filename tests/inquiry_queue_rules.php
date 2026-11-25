<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/inquiry-queue-core.php';
function iqCheck(bool $ok,string $m):void{if(!$ok)throw new LogicException($m);}
function iqBlocked(callable $f):void{try{$f();}catch(InvalidArgumentException|RuntimeException $e){return;}throw new LogicException('Expected refusal');}
$cfg=['portal'=>['enabled'=>true,'admin_email'=>'synthetic@example.com','admin_password_hash'=>'synthetic'],'database'=>['enabled'=>true],'leads'=>['enabled'=>true,'response_queue_enabled'=>true,'response_target_hours'=>24]];
$now=time();$session=['role'=>'admin','last_seen'=>$now,'admin_fingerprint'=>opsAdminSessionFingerprint($cfg)];
iqCheck(inquiryQueueOwner($cfg,$session,$now),'Owner');
foreach([['role'=>'partner'],['last_seen'=>$now-1801],['last_seen'=>$now+1],['admin_fingerprint'=>'bad']] as $p)iqCheck(!inquiryQueueOwner($cfg,array_replace($session,$p),$now),'Refuse session');
foreach([0,169,'24',true] as $h){$bad=$cfg;$bad['leads']['response_target_hours']=$h;iqBlocked(static fn()=>inquiryQueueHours($bad));}
iqCheck(!inquiryQueueEnabled([]),'Default off');
echo "Inquiry queue owner and target rules PASS\n";
if(($argv[1]??'')!=='--database')exit;
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    $time=new DateTimeImmutable('2026-11-26 00:00:00',new DateTimeZone('UTC'));
    $before=inquiryQueueSnapshot($db,$cfg,'all',0,$time)['counts'];
    $q=$db->prepare("INSERT INTO leads(reference,full_name,email,service,message,status,consent_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?, ?,?)");
    $ids=[];
    foreach([['new','2026-11-24 00:00:00'],['qualified','2026-11-25 23:00:00'],['responded','2026-11-24 00:00:00']] as $i=>[$state,$date]){
        $q->execute(['IQ-CI-'.$i,'Synthetic Name','synthetic@example.com','Data Recovery','Synthetic message',$state,$date,$date,$date]);$ids[]=(int)$db->lastInsertId();
    }
    $s=inquiryQueueSnapshot($db,$cfg,'all',0,$time,true);
    iqCheck($s['counts']['total']===$before['total']+2&&$s['counts']['due_count']===$before['due_count']+1,'Queue counts');
    $due=inquiryQueueSnapshot($db,$cfg,'due',0,$time,true);
    iqCheck(in_array($ids[0],array_map('intval',array_column($due['rows'],'id')),true)&&!in_array($ids[1],array_map('intval',array_column($due['rows'],'id')),true),'Due filter');
    iqBlocked(static fn()=>inquiryQueueRecord($db,$cfg,$ids[0],'new','2026-11-24 00:00:00','no'));
    iqCheck(!inquiryQueueRecord($db,$cfg,$ids[0],'new','2026-11-23 00:00:00','yes'),'Stale form');
    iqCheck(inquiryQueueRecord($db,$cfg,$ids[0],'new','2026-11-24 00:00:00','yes'),'Response recorded');
    iqCheck(!inquiryQueueRecord($db,$cfg,$ids[0],'new','2026-11-24 00:00:00','yes'),'Replay refused');
    $text=inquiryQueueCommand($db,$cfg,'/inquiryqueue');
    iqCheck(!str_contains($text,'synthetic@example.com')&&!str_contains($text,'Synthetic Name')&&!str_contains($text,'IQ-CI-'),'Aggregate only');
    iqCheck(inquiryQueueSnapshot($db,$cfg,'all',0,$time)['counts']['total']===$before['total']+1,'Response removed from queue');
    $db->rollBack();echo "Disposable inquiry queue filtering, recording, stale/replay, privacy PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Inquiry queue test failed: '.$e->getMessage()."\n");exit(1);}
