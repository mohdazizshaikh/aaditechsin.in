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
    $s=dashboardSnapshot($db,$cfg,7,$now);
    dashCheck($s['communications']['calls']['received']===0&&$s['communications']['calls']['days_reported']===1&&$s['communications']['sms']['received']===null&&$s['communications']['email']['received']===9,'Missing vs zero');
    dashCheck($s['opportunities']['qualified']>=1&&$s['services']['video-conferencing']['qualified']>=1&&in_array($id,array_map('intval',array_column($s['due'],'opportunity_id')),true),'Pipeline and due reviews');
    dashCheck(in_array($task,array_map('intval',array_column($s['reviews'],'id')),true),'Review queue');
    $encoded=json_encode($s,JSON_THROW_ON_ERROR);foreach(['Private synthetic title','Private synthetic draft','Synthetic dashboard company','https://example.com/dashboard-test'] as $secret)dashCheck(!str_contains($encoded,$secret),'Aggregate view leaked detail');
    $off=$cfg;$off['communications']['enabled']=false;$off['discovery']['enabled']=false;$s=dashboardSnapshot($db,$off,30,$now);dashCheck($s['communications']===null&&$s['opportunities']===null,'Disabled modules hidden');
    $db->rollBack();echo "Synthetic dashboard pipeline and privacy: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Dashboard test failed: '.$e->getMessage()."\n");exit(1);}
