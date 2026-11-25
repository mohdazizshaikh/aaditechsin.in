<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/discovery-batch-lib.php';
try{
    if(count($argv)!==3||$argv[1]!=='--import')throw new RuntimeException('Explicit import required');
    $cfg=opsConfig();discoveryBatchSource($cfg,$argv[2]);$raw=stream_get_contents(STDIN,262145);if(!is_string($raw))throw new RuntimeException('Invalid batch');
    $db=opsDb($cfg);$db->beginTransaction();$r=discoveryBatch($db,$cfg,$argv[2],$raw);$db->commit();echo json_encode($r,JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: source authorization, pause, batch validation or database error; no records committed.\n");exit(1);}
