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

require_once dirname(__DIR__).'/public_html/api/creative-transport-core.php';
$token=str_repeat('a',64);
$tcfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'creative_transport'=>['enabled'=>true,'token_sha256'=>hash('sha256',$token)]];
creativeCheck(!creativeTransportAuthorized([],$token),'Transport default OFF');
creativeCheck(creativeTransportAuthorized($tcfg,$token)&&!creativeTransportAuthorized($tcfg,str_repeat('b',64)),'Scoped token authentication');

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
    $id2=creativeCreate($db,'infrastructure','linkedin','education');
    $claim=creativeLeaseClaim($db);
    creativeCheck($claim['job']['task_id']===$id2,'Claim pending task');
    creativeCheck(creativeLeaseClaim($db)===['status'=>'empty'],'Active lease cannot be duplicated');
    $v2=array_replace($result,['task_id'=>$id2]);
    creativeCheck(creativeLeaseFinish($db,$id2,1,str_repeat('b',64),$v2)['status']==='conflict','Wrong lease');
    creativeCheck(creativeLeaseFinish($db,$id2,1,$claim['lease'],$v2)['status']==='pending_review','Complete lease');
    creativeCheck(creativeLeaseFinish($db,$id2,1,$claim['lease'],$v2)['status']==='pending_review','Replay completion');
    creativeCheck(creativeLeaseFinish($db,$id2,1,$claim['lease'],array_replace($v2,['caption'=>'Different draft']))['status']==='conflict','Conflicting replay');
    $id3=creativeCreate($db,'infrastructure','linkedin','education');
    for($i=0;$i<3;$i++){
        $c=creativeLeaseClaim($db);
        creativeCheck($c['job']['task_id']===$id3,'Retry same task');
        creativeCheck(creativeLeaseFinish($db,$id3,1,$c['lease'],null)['status']==='failed','Report failed generation');
    }
    creativeCheck(creativeLeaseClaim($db)===['status'=>'empty'],'Bounded attempts');
    $id4=creativeCreate($db,'infrastructure','linkedin','education');
    $old=creativeLeaseClaim($db);
    $db->prepare("UPDATE growth_creative_leases SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE task_id=?")->execute([$id4]);
    $fresh=creativeLeaseClaim($db);
    creativeCheck($old['lease']!==$fresh['lease'],'Rotate expired lease');
    creativeCheck(creativeLeaseFinish($db,$id4,1,$old['lease'],null)['status']==='conflict','Expired token rejected');
    growthCommand($db,'/pause',$cfg);
    try{creativeLeaseFinish($db,$id4,1,$fresh['lease'],null);throw new LogicException('Pause ignored');}catch(RuntimeException $e){creativeCheck($e->getMessage()==='Growth paused','Pause completion');}
    $db->rollBack();echo "Synthetic creative request, revision, pause, review and coverage: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Creative integration failed: '.$e->getMessage()."\n");exit(1);}
