<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/dashboard-core.php';
function dashCheck(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$ts=$now->getTimestamp();
$cfg=['portal'=>['enabled'=>true,'admin_password_hash'=>'synthetic-hash'],'growth'=>['enabled'=>true],'intelligence_dashboard'=>['enabled'=>true],'discovery'=>['enabled'=>true,'followups_enabled'=>true],'communications'=>['enabled'=>true]];
$session=['role'=>'admin','last_seen'=>$ts,'admin_fingerprint'=>opsAdminSessionFingerprint($cfg)];
dashCheck(!dashboardEnabled([])&&dashboardOwner($cfg,$session,$ts),'Owner gate');
foreach([['role'=>'partner'],['last_seen'=>$ts-1801],['last_seen'=>$ts+1],['admin_fingerprint'=>'wrong']] as $v)dashCheck(!dashboardOwner($cfg,array_replace($session,$v),$ts),'Session rejected');
$changed=$cfg;$changed['portal']['admin_password_hash']='changed';dashCheck(!dashboardOwner($changed,$session,$ts),'Password rotation');
foreach(['365','-1',7,['7']] as $v){try{dashboardDays($v);throw new LogicException('Invalid period accepted');}catch(InvalidArgumentException $e){}}
dashCheck(dashboardDays('30')===30&&dashboardPeriod(7,new DateTimeImmutable('2026-11-25T01:30:00+05:30'))===['start'=>'2026-11-17','end'=>'2026-11-24','days'=>7],'UTC boundary');
dashCheck(dashboardCsvCell('=1+1')==="'=1+1"&&dashboardCsvCell(' +1')==="' +1"&&dashboardCsvCell("\ttext")==="'\ttext"&&dashboardCsvCell(null)===''&&dashboardCsvCell(0)==='0','CSV cells');
if(($argv[1]??'')!=='--database'){echo "Dashboard access and completed period rules: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);
    $day=$now->modify('-1 day')->format('Y-m-d');
    communicationsImport($db,$cfg,['channel'=>'calls','day_utc'=>$day,'received'=>0,'answered'=>0,'missed'=>0],$now);
    communicationsImport($db,$cfg,['channel'=>'email','day_utc'=>$day,'received'=>9,'answered'=>5,'missed'=>2],$now);
    $task=growthCreate($db,['service_id'=>'infrastructure','task_type'=>'research','title'=>'Private synthetic title','draft'=>'Private synthetic draft']);
    $opp=discoveryImport($db,['source_type'=>'company_announcement','source_url'=>'https://example.com/dashboard-test','organization'=>'Synthetic dashboard company','location_label'=>'Mumbai','evidence'=>'Video conferencing upgrade','observed_on'=>$day,'signal_type'=>'upgrade','service_id'=>'video-conferencing']);
    $id=$opp['id'];growthCommand($db,"/qualify $id",$cfg);growthCommand($db,"/followup $id ".$now->format('Y-m-d'),$cfg);
    $cfg['analytics']=['enabled'=>true,'salt'=>str_repeat('a',40)];
    $ist=$now->setTimezone(new DateTimeZone('Asia/Kolkata'))->modify('-1 day')->format('Y-m-d');
    $q=$db->prepare("INSERT INTO growth_analytics_events(event_day,event_id,session_hash,page_id,event_type,service_id,channel,automation_signal) VALUES(?,?,?,'home',?,'','direct',?)");
    $q->execute([$ist,str_repeat('e',32),str_repeat('f',64),'page_view',1]);
    $q->execute([$ist,str_repeat('d',32),str_repeat('f',64),'page_view',0]);
    $q->execute([$ist,str_repeat('c',32),str_repeat('f',64),'call_click',0]);
    $cfg['creative']=['enabled'=>true,'batch_enabled'=>true];
    // 56 independent objective/service coverage rows; fixture has one task in each.
    $expected=[];$cases=0;
    foreach(creativeObjectives() as $objective){
        foreach(growthCatalog() as $service=>$entry){
            $cid=creativeCreate($db,$service,'linkedin',$objective);
            $v=['version'=>1,'task_id'=>$cid,'revision'=>1,'model'=>'synthetic:1b','caption'=>'Review requirements.','visual_brief'=>'Illustrative diagram','video_brief'=>'Illustrative motion','review_notes'=>'Synthetic review only'];
            $mode=$cases%4;
            if($mode!==0){
                dashCheck(creativeImport($db,$v),'Synthetic import');
                if($mode===2)dashCheck(str_contains(growthCommand($db,"/approve $cid 1",$cfg),'approved'),'Review approved');
                if($mode===3)dashCheck(str_contains(growthCommand($db,"/reject $cid 1",$cfg),'rejected'),'Review rejected');
            }
            $expected[$objective][$service]=['channels'=>9,'requested'=>1,'approved'=>(int)($mode===2),'awaiting_generation'=>(int)($mode===0),'awaiting_review'=>(int)($mode===1),'rejected_only'=>(int)($mode===3)];
            ++$cases;
        }
    }
    dashCheck($cases===56,'Four objectives times fourteen services');
    $s=dashboardSnapshot($db,$cfg,7,$now);
    dashCheck($s['creative_coverage']===$expected,'All 56 objective/service aggregate rows');
    dashCheck(count($s['services'])===14,'Complete opportunity service catalog');
    $b=$s['web_breakdown'];
    dashCheck($b['pages']['home']>=2&&$b['sources']['direct']>=2&&count($b['pages'])===7&&count($b['sources'])===6&&count($b['services'])===14,'Page/source dimensions');
    // A distant synthetic window has no observations in any category.
    $none=dashboardWebBreakdown($db,['start'=>'2000-01-01','end'=>'2000-01-08']);
    foreach($none as $rows)foreach($rows as $n)dashCheck($n===null,'Missing data distinct from zero');
    $q=$db->prepare("INSERT INTO growth_analytics_events(event_day,event_id,session_hash,page_id,event_type,service_id,channel,automation_signal) VALUES(?,?,?,'home','service_interest',?,'direct',0)");
    $q->execute([$ist,str_repeat('b',32),str_repeat('f',64),'infrastructure']);
    $s=dashboardSnapshot($db,$cfg,7,$now);
    dashCheck($s['web_breakdown']['services']['infrastructure']>=1&&$s['web_breakdown']['services']['av-maintenance']===0,'Service recorded vs zero');
    $extraCsv=dashboardCsv($s);
    dashCheck(str_contains($extraCsv,'creative_coverage_all_time')&&str_contains($extraCsv,'consented_web_breakdown_ist'),'New aggregate CSV sections');
    echo "Synthetic owner coverage: $cases/56 objective/service rows PASS\n";
    $a=$s['web_analytics'];$r=$a['daily'][$ist];dashCheck($r['views']>=2&&$r['tab_days']>=1&&$r['signals']>=1&&$r['call_clicks']>=1&&count($a['daily'])===7,'IST aggregates');
    $csv=dashboardCsv($s);dashCheck(str_contains($csv,'consented_web_ist')&&str_contains($csv,'communications_utc')&&str_contains($csv,'sms,received,')&&str_contains($csv,'calls,received,0'),'CSV sections and missing vs zero');
    foreach(['Private synthetic draft','Synthetic dashboard company','https://example.com/dashboard-test',str_repeat('f',64),str_repeat('e',32)] as $secret)dashCheck(!str_contains($csv,$secret),'CSV private fields');
    dashCheck($s['communications']['calls']['received']===0&&$s['communications']['calls']['days_reported']===1&&$s['communications']['sms']['received']===null&&$s['communications']['email']['received']===9,'Missing vs zero');
    dashCheck($s['opportunities']['qualified']>=1&&$s['services']['video-conferencing']['qualified']>=1&&in_array($id,array_map('intval',array_column($s['due'],'opportunity_id')),true),'Pipeline and due reviews');
    dashCheck(in_array($task,array_map('intval',array_column($s['reviews'],'id')),true),'Review queue');
    $encoded=json_encode($s,JSON_THROW_ON_ERROR);foreach(['Private synthetic title','Private synthetic draft','Synthetic dashboard company','https://example.com/dashboard-test'] as $secret)dashCheck(!str_contains($encoded,$secret),'Aggregate view leaked detail');
    $off=$cfg;$off['communications']['enabled']=false;$off['discovery']['enabled']=false;$off['creative']['batch_enabled']=false;$off['analytics']['enabled']=false;$s=dashboardSnapshot($db,$off,30,$now);dashCheck($s['communications']===null&&$s['opportunities']===null&&$s['creative_coverage']===null&&$s['web_breakdown']===null,'Disabled modules hidden');
    $db->rollBack();echo "Synthetic dashboard pipeline and privacy: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Dashboard test failed: '.$e->getMessage()."\n");exit(1);}
