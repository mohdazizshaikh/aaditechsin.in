<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/live-seo-core.php';
try{
    if(count($argv)!==2||$argv[1]!=='--import')throw new RuntimeException('Explicit import required');
    $raw=stream_get_contents(STDIN,4097);if(!is_string($raw)||strlen($raw)>4096)throw new RuntimeException('Size');
    $r=json_decode($raw,true,8,JSON_THROW_ON_ERROR);if(!is_array($r))throw new RuntimeException('Report');
    $cfg=opsConfig();$db=opsDb($cfg);$db->beginTransaction();liveSeoCapture($db,$cfg,$r);$db->commit();echo "Aggregate public HTML observation imported.\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: authorization, pause, report coverage, freshness or duplicate. No raw report logged.\n");exit(1);}
