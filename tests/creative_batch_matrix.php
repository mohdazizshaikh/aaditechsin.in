<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/creative-batch-core.php';
function cbCheck(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
function cbBlocked(callable $f):void{try{$f();}catch(RuntimeException|InvalidArgumentException $e){return;}throw new LogicException('Expected refusal');}
$cfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true,'batch_enabled'=>true]];
cbCheck(creativeBatchEnabled($cfg)&&!creativeBatchEnabled([]),'Explicit opt-in');
$catalog=growthCatalog();$channels=creativeChannels();cbCheck(count($catalog)===14&&count($channels)===9&&count(array_unique($channels))===9,'126 supported combinations');
if(($argv[1]??'')!=='--database'){echo "Creative batch default-off and catalog dimensions: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password'||getenv('CI_DB_PORT')!=='3306')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port=3306;dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/pause',$cfg);cbBlocked(static fn()=>creativeBatchPlan($db,$cfg,'case-planning'));
    growthCommand($db,'/resume',$cfg);
    $objective='case-planning';$p=creativeBatchPlan($db,$cfg,$objective);cbCheck($p['total']===126&&count($p['missing'])===126,'Clean objective fixture required');
    cbBlocked(static fn()=>creativeBatchApply($db,$cfg,$objective,str_repeat('0',64)));
    cbBlocked(static fn()=>creativeBatchPlan($db,$cfg,'unknown'));
    $off=$cfg;$off['creative']['batch_enabled']=false;cbBlocked(static fn()=>creativeBatchPlan($db,$off,$objective));
    // One savepoint isolates an intentionally changed plan; no persistent synthetic data.
    $db->exec('SAVEPOINT changed_plan');creativeCreate($db,'infrastructure','linkedin',$objective);
    cbBlocked(static fn()=>creativeBatchApply($db,$cfg,$objective,$p['digest']));
    $db->exec('ROLLBACK TO SAVEPOINT changed_plan');$db->exec('RELEASE SAVEPOINT changed_plan');
    $ids=creativeBatchApply($db,$cfg,$objective,$p['digest']);cbCheck(count($ids)===126&&count(array_unique($ids))===126,'Bulk exact plan');
    $cases=0;
    foreach($p['missing'] as $i=>$cell){
        $id=$ids[$i];$job=creativeExport($db,$id);
        cbCheck($job['task_id']===$id&&$job['service']['id']===$cell['service']&&$job['channel']===$cell['channel']&&$job['objective']===$objective&&$job['revision']===1&&count($job)===6,'Catalog-only job');
        cbCheck(str_contains(growthCommand($db,"/approve $id 1",$cfg),'not pending'),'Placeholder cannot be approved');
        $result=['version'=>1,'task_id'=>$id,'revision'=>1,'model'=>'synthetic:1b','caption'=>'Review requirements before planning.','visual_brief'=>'Illustrative diagram','video_brief'=>'Illustrative motion','review_notes'=>'Synthetic review only'];
        cbCheck(creativeImport($db,$result)&&!creativeImport($db,$result),'Draft and duplicate guard');
        cbCheck(str_contains(growthCommand($db,"/approve $id 1",$cfg),'approved'),'Explicit review transition');
        ++$cases;
    }
    $p2=creativeBatchPlan($db,$cfg,$objective);cbCheck($p2['missing']===[]&&creativeBatchApply($db,$cfg,$objective,$p2['digest'])===[],'No duplicate coverage');
    cbBlocked(static fn()=>creativeBatchApply($db,$cfg,$objective,$p['digest']));
    $matrix=creativeBatchMatrix($db,$cfg,$objective);
    foreach($matrix as $rows)foreach($rows as $cell)cbCheck($cell['approved']===1&&array_sum($cell)===1,'One approved synthetic task per cell');
    cbCheck(str_contains(growthCommand($db,'/coveragegrid case-planning',$cfg),'9/9 channels requested, 9 with approved draft'),'Owner coverage');
    $db->rollBack();echo "Synthetic service/channel workflows: $cases/126 PASS (rolled back; no model calls or publication)\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Creative matrix failed: '.$e->getMessage()."\n");exit(1);}
