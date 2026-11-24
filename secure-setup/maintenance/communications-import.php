<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/communications-core.php';
try{
    if(count($argv)!==2||$argv[1]!=='--import')throw new RuntimeException('Explicit import required');
    $cfg=opsConfig();if(!communicationsEnabled($cfg))throw new RuntimeException('Disabled');
    $raw=stream_get_contents(STDIN,4097);if(!is_string($raw)||strlen($raw)>4096)throw new RuntimeException('Size');
    $row=json_decode($raw,true,8,JSON_THROW_ON_ERROR);if(!is_array($row))throw new RuntimeException('Invalid');
    $db=opsDb($cfg);$db->beginTransaction();$new=communicationsImport($db,$cfg,$row);$db->commit();echo $new?"Aggregate imported.\n":"Exact aggregate already recorded.\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: invalid, disabled, paused or conflicting aggregate. No contents logged.\n");exit(1);}
