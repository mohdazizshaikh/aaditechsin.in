<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/creative-core.php';
function creativeCheck(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
creativeCheck(!creativeEnabled([]),'Default OFF');
creativeCheck(count(creativeChannels())===9&&count(growthCatalog())===14,'Coverage');
$result=['version'=>1,'task_id'=>1,'revision'=>1,'model'=>'local-test:1b','caption'=>'Plan your office network with an infrastructure assessment.','visual_brief'=>'Illustrative rack and cabling diagram.','video_brief'=>'Show a network planning checklist.','review_notes'=>'Verify technical details before publication.'];
creativeCheck(creativeValidate($result)===$result,'Valid draft');
foreach([['caption'=>'100% guaranteed data recovery'],['caption'=>'We are a certified partner'],['caption'=>'Email someone@example.com'],['caption'=>str_repeat('x',901)],['task_id'=>'1'],['model'=>'../invalid']] as $patch){
    try{creativeValidate(array_replace($result,$patch));throw new RuntimeException('Invalid accepted');}catch(InvalidArgumentException $ignored){}
}
if(($argv[1]??'')!=='--database'){echo "Creative result and claims rules: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$cfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true]];
$db->beginTransaction();
try{
    growthCommand($db,'/pause',$cfg);
    creativeCheck(growthCommand($db,'/createcontent infrastructure linkedin education',$cfg)==='Growth paused.','Pause gate');
    growthCommand($db,'/resume',$cfg);
    $id=creativeCreate($db,'infrastructure','linkedin','education');
    $job=creativeExport($db,$id);
    creativeCheck($job['revision']===1&&$job['service']['id']==='infrastructure'&&count($job)===6,'Catalog-only export');
    creativeCheck(str_contains(growthCommand($db,"/approve $id 1",$cfg),'not pending'),'Cannot approve placeholder');
    $v=array_replace($result,['task_id'=>$id]);
    creativeCheck(creativeImport($db,$v),'Import draft');
    creativeCheck(!creativeImport($db,$v),'Duplicate result blocked');
    creativeCheck(str_contains(growthCommand($db,"/task $id",$cfg),'Caption:'),'Review draft');
    creativeCheck(str_contains(growthCommand($db,"/regenerate $id 1",$cfg),'Revision requested'),'Regenerate');
    creativeCheck(creativeExport($db,$id)['revision']===2,'Export new revision');
    creativeCheck(!creativeImport($db,$v),'Old result denied');
    creativeCheck(creativeImport($db,array_replace($v,['revision'=>2])),'Revised result');
    creativeCheck(str_contains(growthCommand($db,"/approve $id 2",$cfg),'approved'),'Approval');
    creativeCheck(str_contains(growthCommand($db,'/coverage',$cfg),'infrastructure: 1 tasks / 1 approved'),'Coverage inventory');
    $q=$db->prepare('SELECT COUNT(*) FROM growth_creative_results WHERE task_id=?');$q->execute([$id]);
    creativeCheck((int)$q->fetchColumn()===2,'Preserve revision history');
    $db->rollBack();echo "Synthetic creative request, revision, pause, review and coverage: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Creative integration failed: '.$e->getMessage()."\n");exit(1);}
