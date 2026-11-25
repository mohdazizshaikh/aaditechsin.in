<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/strict-json.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/communications-core.php';
try{
    if(count($argv)!==2||!in_array($argv[1],['--import','--batch'],true))throw new RuntimeException('Explicit import required');
    $cfg=opsConfig();if(!communicationsEnabled($cfg))throw new RuntimeException('Disabled');
    $max=$argv[1]==='--batch'?65536:4096;
    $raw=stream_get_contents(STDIN,$max+1);if(!is_string($raw)||strlen($raw)>$max)throw new RuntimeException('Size');
    $row=strictJsonDecode($raw,true,8);if(!is_array($row))throw new RuntimeException('Invalid');
    $db=opsDb($cfg);$db->beginTransaction();if($argv[1]==='--batch'){$r=communicationsBatch($db,$cfg,$row);$db->commit();echo json_encode($r,JSON_THROW_ON_ERROR)."\n";exit;}$new=communicationsImport($db,$cfg,$row);$db->commit();echo $new?"Aggregate imported.\n":"Exact aggregate already recorded.\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: invalid, disabled, paused or conflicting aggregate. No contents logged.\n");exit(1);}
