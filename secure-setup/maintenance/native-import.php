<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/native-import-lib.php';
try{
    $mode=$argv[1]??'';
    if(!(($mode==='--plan'&&count($argv)===3)||($mode==='--apply'&&count($argv)===4)))throw new InvalidArgumentException('Explicit mode required');
    $cfg=opsConfig();if(!nativeImportEnabled($cfg))throw new RuntimeException('Disabled');
    $inbox=$cfg['media_upload']['private_inbox']??'';$root=$cfg['media_upload']['private_root']??'';
    $raw=stream_get_contents(STDIN,65537);if(!is_string($raw))throw new RuntimeException('Packet unavailable');
    $db=opsDb($cfg);$db->beginTransaction();
    if($mode==='--plan'){$r=nativeImportPlan($db,$cfg,$inbox,$root,$raw,$argv[2]);$db->rollBack();}
    else{$r=nativeImportApply($db,$cfg,$inbox,$root,$raw,$argv[2],$argv[3]);$db->commit();}
    echo json_encode($r,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: verify approved native review, packet, private files and reviewed plan. Source retained; inspect destination before retry. No publication staged.\n");exit(1);}
