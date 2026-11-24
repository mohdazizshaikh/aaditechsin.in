<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/discovery-core.php';
function assertDiscovery(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
$terms=discoveryTerms();$registry=growthCatalog();
assertDiscovery(count($terms)===14 && array_keys($terms)===array_keys($registry),'All registry services have discovery rules');
foreach($terms as $id=>$phrases)assertDiscovery(isset(discoveryMatch($phrases[0])[$id]),'Match '.$id);
$sample=['source_type'=>'company_announcement','source_url'=>'https://example.com/ci-synthetic','organization'=>'Synthetic CI Organization','location_label'=>'Mumbai','evidence'=>'Planned conference room and video conferencing upgrade','observed_on'=>gmdate('Y-m-d'),'signal_type'=>'upgrade','service_id'=>'video-conferencing'];
$v=discoveryNormalize($sample);
assertDiscovery($v['relevance_score']===65,'Heuristic score');
foreach([['source_url'=>'https://example.com/?token=secret'],['source_url'=>'https://127.0.0.1/test'],['observed_on'=>'2000-01-01'],['service_id'=>'data-recovery'],['evidence'=>'Unrelated text'],['evidence'=>'Conference room user@example.com'],['contact'=>'not allowed']] as $bad){
    try{discoveryNormalize(array_replace($sample,$bad));throw new RuntimeException('Bad evidence accepted');}catch(InvalidArgumentException $ignored){}
}
assertDiscovery(!discoveryEnabled([]),'Default disabled');
if(($argv[1]??'')!=='--database'){echo "V9.3 all-service matching, validation and scoring: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$cfg=['growth'=>['enabled'=>true],'discovery'=>['enabled'=>true]];
$db->beginTransaction();
try{
    $first=discoveryImport($db,$sample);$id=$first['id'];
    assertDiscovery($first['status']==='pending_review','New record pending');
    assertDiscovery(discoveryImport($db,$sample)===['id'=>$id,'status'=>'duplicate'],'Duplicate evidence');
    assertDiscovery(str_contains(growthCommand($db,'/opportunities',$cfg),'#'.$id),'List command');
    assertDiscovery(str_contains(growthCommand($db,"/opportunity $id",$cfg),'not verified buying intent'),'Evidence warning');
    assertDiscovery(str_contains(growthCommand($db,"/draftoutreach $id",$cfg),'Qualify opportunity first'),'Reject unqualified draft');
    assertDiscovery(str_contains(growthCommand($db,"/qualify $id",$cfg),'qualified'),'Qualification');
    growthCommand($db,'/pause',$cfg);
    assertDiscovery(str_contains(growthCommand($db,"/draftoutreach $id",$cfg),'Growth paused'),'Pause gate');
    growthCommand($db,'/resume',$cfg);
    assertDiscovery(str_contains(growthCommand($db,"/draftoutreach $id",$cfg),'no message sent'),'Create review-only draft');
    $q=$db->prepare('SELECT outreach_task_id FROM growth_opportunities WHERE id=?');$q->execute([$id]);$task=(int)$q->fetchColumn();
    assertDiscovery($task>0,'Linked task');
    assertDiscovery(str_contains(growthCommand($db,"/draftoutreach $id",$cfg),"Existing outreach draft /task $task"),'Single draft');
    assertDiscovery(str_contains(growthCommand($db,"/approve $task 1",$cfg),'no external action sent'),'Approval no send');
    assertDiscovery(str_contains(growthCommand($db,'/outreach',$cfg),'approved: 1'),'Report linked state');
    assertDiscovery(str_contains(growthCommand($db,'/outreach',[]),'disabled'),'Disabled gate');
    $second=discoveryImport($db,array_replace($sample,['source_url'=>'https://example.com/ci-dismiss']));
    $sid=$second['id'];growthCommand($db,"/dismiss $sid",$cfg);
    assertDiscovery(str_contains(growthCommand($db,"/qualify $sid",$cfg),'no longer pending'),'Dismissal terminal');
    $db->rollBack();
    echo "V9.3 synthetic intake, deduplication, qualification, pause and reviewed outreach: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Discovery integration failed: '.$e->getMessage()."\n");exit(1);}
