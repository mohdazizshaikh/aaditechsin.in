<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/creative-batch-core.php';
try{
    $plan=($argv[1]??'')==='--plan';$apply=($argv[1]??'')==='--apply';
    if(!(($plan&&in_array(count($argv),[3,4],true))||($apply&&in_array(count($argv),[4,5],true))))throw new RuntimeException('Explicit plan required');
    $rawLimit=$argv[$plan?3:4]??'126';
    if(!preg_match('/^[1-9][0-9]{0,2}$/D',$rawLimit)||(int)$rawLimit>126)throw new InvalidArgumentException('Invalid limit');
    $limit=(int)$rawLimit;
    $cfg=opsConfig();$db=opsDb($cfg);$db->beginTransaction();
    if($argv[1]==='--plan'){$p=creativeBatchPlan($db,$cfg,$argv[2],$limit);$db->rollBack();echo json_encode($p,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)."\n";exit;}
    $ids=creativeBatchApply($db,$cfg,$argv[2],$argv[3],$limit);$db->commit();echo json_encode(['created'=>count($ids),'task_ids'=>$ids,'published'=>0],JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: feature, pause, objective or reviewed coverage changed. No batch committed.\n");exit(1);}
