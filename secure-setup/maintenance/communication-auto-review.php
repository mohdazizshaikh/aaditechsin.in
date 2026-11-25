<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/communication-auto-review-lib.php';
try{
    $mode=$argv[1]??'';
    if(count($argv)!==2||!in_array($mode,['--check','--plan','--scheduled-run'],true))throw new InvalidArgumentException('Explicit mode');
    $cfg=opsConfig();$days=autoReviewDays($cfg);
    if($mode==='--check'){echo json_encode(['lookback_days'=>$days,'network'=>false,'database'=>false],JSON_THROW_ON_ERROR)."\n";exit;}
    $db=opsDb($cfg);$db->beginTransaction();
    if($mode==='--plan'){$r=autoReviewPlan($db,$cfg);$db->rollBack();}
    else{$r=autoReviewRun($db,$cfg);$db->commit();}
    echo json_encode($r,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)."\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: communication review configuration, pause or database error. No message sent.\n");exit(1);}
