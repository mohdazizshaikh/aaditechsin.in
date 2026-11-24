<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/reports-core.php';
function reportCheck(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
$token='123456:'.str_repeat('a',30);
$cfg=['growth'=>['enabled'=>true,'telegram'=>['enabled'=>true,'reports_enabled'=>true,'owner_user_id'=>'12345','owner_chat_id'=>'12345','bot_token'=>$token,'daily_after_ist'=>'00:00']]];
reportCheck(ownerReportTarget([])===null&&ownerReportTarget($cfg)==='12345','Default OFF and owner target');
$bad=$cfg;$bad['growth']['telegram']['owner_chat_id']='12346';reportCheck(ownerReportTarget($bad)===null,'Mismatch');
$bad=$cfg;$bad['social']['telegram_channel']['bot_token']=$token;reportCheck(ownerReportTarget($bad)===null,'Separate credentials');
if(($argv[1]??'')!=='--database'){echo "Owner notification configuration rules: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    $now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
    growthCommand($db,'/notifydaily off',$cfg);
    reportCheck(reportClaim($db,$cfg,'daily',$now)===null,'Daily opt out');
    growthCommand($db,'/notifydaily on',$cfg);
    $row=reportClaim($db,$cfg,'daily',$now);
    reportCheck($row!==null&&$row['chat']==='12345'&&str_contains($row['text'],'day-to-date'),'Daily snapshot');
    reportFinish($db,$row['id'],['status'=>'accepted','receipt'=>'42','error'=>'']);
    reportCheck(reportClaim($db,$cfg,'daily',$now)===null,'Period deduplication');
    $hour=$now->setTime((int)$now->format('H'),0)->modify('-30 minutes');
    $db->prepare("INSERT INTO growth_source_runs(source_id,status,error_code,finished_at) VALUES('ci-report','failed','transport_failed',?)")->execute([$hour->format('Y-m-d H:i:s')]);
    growthCommand($db,'/notifyerrors off',$cfg);reportCheck(reportClaim($db,$cfg,'errors',$now)===null,'Error opt out');
    growthCommand($db,'/notifyerrors on',$cfg);
    $error=reportClaim($db,$cfg,'errors',$now);
    reportCheck($error!==null&&str_contains($error['text'],'Discovery partial/failed: 1'),'Hourly error digest');
    reportFinish($db,$error['id'],['status'=>'unknown','receipt'=>null,'error'=>'transport_unconfirmed']);
    reportCheck(reportClaim($db,$cfg,'errors',$now)===null,'Unknown never silently retried');
    reportCheck(str_contains(growthCommand($db,'/notifications',$cfg),'ON'),'Preferences report');
    $db->rollBack();echo "Synthetic owner notification preferences, snapshots, receipts and duplicate prevention: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Report integration failed: '.$e->getMessage()."\n");exit(1);}
