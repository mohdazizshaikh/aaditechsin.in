<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/native-review-lib.php';
try{
    $mode=$argv[1]??'';
    if(!(($mode==='--plan'&&count($argv)===3)||($mode==='--apply'&&count($argv)===4)))throw new InvalidArgumentException('Explicit mode and digest required');
    $raw=stream_get_contents(STDIN,65537);if(!is_string($raw))throw new RuntimeException('Packet unavailable');
    $cfg=opsConfig();nativeReviewScope($cfg,$raw,$argv[2]);
    $db=opsDb($cfg);$db->beginTransaction();
    if($mode==='--plan'){$r=nativeReviewPlan($db,$cfg,$raw,$argv[2]);$db->rollBack();}
    else{$r=nativeReviewApply($db,$cfg,$raw,$argv[2],$argv[3]);$db->commit();}
    echo json_encode($r,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: private review packet, gate, stale source, plan or database. No media publication.\n");exit(1);}
