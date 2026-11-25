<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/communications-bridge-lib.php';
try{
    if(count($argv)!==3||!in_array($argv[1],['--check','--fetch'],true))throw new RuntimeException('Explicit mode required');
    $cfg=opsConfig();$src=commBridgeConfig($cfg,$argv[2]);
    if($argv[1]==='--check'){echo "Local source configuration valid. No network access.\n";exit;}
    $db=opsDb($cfg);$db->beginTransaction();creativeActive($db);$db->commit();
    $raw=commBridgeFetch($src);
    $db->beginTransaction();$new=commBridgeImport($db,$cfg,$argv[2],$raw);$db->commit();
    echo $new?"Signed aggregate imported.\n":"Exact aggregate already recorded.\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: source authorization, transport, signature, freshness, pause or conflicting counts. No payload or secret logged.\n");exit(1);}
