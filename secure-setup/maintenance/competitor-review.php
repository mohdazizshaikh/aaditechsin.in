<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/competitor-review-lib.php';
try{
    $mode=$argv[1]??'';
    if(!(($mode==='--plan'&&count($argv)===2)||($mode==='--apply'&&count($argv)===3)))throw new InvalidArgumentException('Explicit command required');
    $raw=stream_get_contents(STDIN,16385);if(!is_string($raw))throw new RuntimeException('Scope unavailable');
    $cfg=opsConfig();competitorReviewScope($cfg,$raw);
    $db=opsDb($cfg);$db->beginTransaction();
    if($mode==='--plan'){$r=competitorReviewPlan($db,$cfg,$raw);$db->rollBack();}
    else{$r=competitorReviewApply($db,$cfg,$raw,$argv[2]);$db->commit();}
    echo json_encode($r,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: review scope, gate, stale plan or database error. Inspect current state before retry.\n");exit(1);}
