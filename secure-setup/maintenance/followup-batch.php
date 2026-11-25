<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/followup-batch-lib.php';
try{
    $mode=$argv[1]??'';
    if(!(($mode==='--plan'&&count($argv)===2)||($mode==='--apply'&&count($argv)===3)))throw new InvalidArgumentException('Explicit command required');
    $raw=stream_get_contents(STDIN,8193);if(!is_string($raw))throw new RuntimeException('Scope unavailable');
    $cfg=opsConfig();followBatchScope($cfg,$raw);
    $db=opsDb($cfg);$db->beginTransaction();
    if($mode==='--plan'){$r=followBatchPlan($db,$cfg,$raw);$db->rollBack();}
    else{$r=followBatchApply($db,$cfg,$raw,$argv[2]);$db->commit();}
    echo json_encode($r,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: scope, feature gate, pause, stale plan or database failure. Inspect state before retry.\n");exit(1);}
