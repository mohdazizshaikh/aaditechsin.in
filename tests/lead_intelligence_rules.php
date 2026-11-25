<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/lead-intelligence-core.php';
function liCheck(bool $v,string $m):void{if(!$v)throw new LogicException($m);}
function liBlocked(callable $f):void{try{$f();}catch(RuntimeException|InvalidArgumentException $e){return;}throw new LogicException('Expected refusal');}
$cfg=['portal'=>['enabled'=>true,'admin_email'=>'synthetic@example.com','admin_password_hash'=>'synthetic'],'database'=>['enabled'=>true],'growth'=>['enabled'=>true],'leads'=>['enabled'=>true,'response_queue_enabled'=>true,'response_target_hours'=>24,'intelligence_enabled'=>true]];
$now=new DateTimeImmutable('2026-11-26 00:00:00',new DateTimeZone('UTC'));
$cases=0;
foreach(growthCatalog() as $id=>$service){
    liCheck(leadIntelService($id)===$id&&leadIntelService($service['title'])===$id,'Exact service');
    foreach([-1,0,1,7,30,90,91] as $offset){
        $day=$now->modify(($offset>=0?'+':'').$offset.' days')->format('Y-m-d');
        liCheck(leadIntelDate($day,$now)===($offset>=0&&$offset<=90),'Date bounds');++$cases;
    }
}
liCheck($cases===98&&leadIntelService('Unmapped')===null,'Service/date coverage');
foreach(['2026-02-30','2026-11-6','2026-11-26T00:00:00',''] as $bad)liCheck(!leadIntelDate($bad,$now),'Invalid date');
foreach(['portal','database','growth','leads'] as $group){$bad=$cfg;$bad[$group]['enabled']=false;liCheck(!leadIntelEnabled($bad),'Feature dependency');}
$bad=$cfg;$bad['leads']['intelligence_enabled']=false;liCheck(!leadIntelEnabled($bad),'Default off');
echo "Lead intelligence: $cases service/date scenarios and feature gates PASS\n";
if(($argv[1]??'')!=='--database')exit;
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    $db->exec('UPDATE growth_control SET paused=0 WHERE id=1');
    $q=$db->prepare("INSERT INTO leads(reference,full_name,email,service,message,status,consent_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)");
    $stamp='2026-11-25 00:00:00';$day='2026-11-26';
    $before=leadIntelSnapshot($db,$cfg,$now)['counts'];$ids=[];
    foreach(growthCatalog() as $id=>$service){
        $q->execute(['LI-CI-'.count($ids),'Synthetic Customer','synthetic@example.com',$service['title'],'Synthetic private text','new',$stamp,$stamp,$stamp]);
        $lead=(int)$db->lastInsertId();$ids[]=$lead;
        liBlocked(static fn()=>leadIntelQualify($db,$cfg,$lead,'new',$stamp,'no'));
        liCheck(!leadIntelQualify($db,$cfg,$lead,'new','2026-11-24 00:00:00','yes'),'Stale qualification');
        liCheck(leadIntelQualify($db,$cfg,$lead,'new',$stamp,'yes'),'Qualification');
        liCheck(!leadIntelQualify($db,$cfg,$lead,'new',$stamp,'yes'),'Replay');
        $s=$db->prepare('SELECT updated_at FROM leads WHERE id=?');$s->execute([$lead]);$updated=$s->fetchColumn();
        foreach(array_keys(leadIntelReasons()) as $reason){
            $r=leadIntelReminder($db,$cfg,$lead,'qualified',$updated,$day,$reason,'yes',$now);
            liCheck($r['status']==='created','Reminder created');
            liCheck(leadIntelReminder($db,$cfg,$lead,'qualified',$updated,$day,$reason,'yes',$now)['status']==='existing_open','No duplicate open');
            liCheck(leadIntelClose($db,$cfg,$r['task_id'],1,'reviewed','yes'),'Review disposition');
            liCheck(!leadIntelClose($db,$cfg,$r['task_id'],1,'reviewed','yes'),'Disposition replay');
            liCheck(leadIntelReminder($db,$cfg,$lead,'qualified',$updated,$day,$reason,'yes',$now)['status']==='existing_history','No history replay');
        }
        $r=leadIntelReminder($db,$cfg,$lead,'qualified',$updated,'2026-11-27','scope','yes',$now);
        liCheck($r['status']==='created','New future reminder');
    }
    $snapshot=leadIntelSnapshot($db,$cfg,$now,true);
    liCheck($snapshot['counts']['open']===$before['open']+14,'14 open reminders');
    $id=$ids[0];$db->prepare("UPDATE leads SET status='converted' WHERE id=?")->execute([$id]);
    $snapshot=leadIntelSnapshot($db,$cfg,$now,true);
    liCheck($snapshot['counts']['inactive_lead']===$before['inactive_lead']+1&&$snapshot['counts']['open']===$before['open']+13,'Inactive lead suppressed');
    $summary=leadIntelSummary($db,$cfg,$now);
    foreach(['Synthetic Customer','synthetic@example.com','Synthetic private text','LI-CI-'] as $pii)liCheck(!str_contains($summary,$pii),'Aggregate redaction');
    $db->exec('UPDATE growth_control SET paused=1 WHERE id=1');
    $id=$ids[1];$s=$db->prepare('SELECT updated_at FROM leads WHERE id=?');$s->execute([$id]);$updated=$s->fetchColumn();
    liBlocked(static fn()=>leadIntelReminder($db,$cfg,$id,'qualified',$updated,$day,'scope','yes',$now));
    $row=array_values(array_filter($snapshot['rows'],static fn($r)=>$r['lead_id']===$id))[0];
    liCheck(leadIntelClose($db,$cfg,$row['task_id'],1,'cancelled','yes'),'Safe closure while paused');
    $db->rollBack();
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
echo "Disposable lead intelligence: 56 reminder lifecycle cases, 14 qualifications, stale/replay, inactive lead, pause and privacy PASS\n";
