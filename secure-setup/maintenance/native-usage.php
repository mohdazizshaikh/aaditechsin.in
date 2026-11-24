<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/native-usage-read.php';
try{
    if(count($argv)!==3||!in_array($argv[1],['--check','--capture'],true))throw new RuntimeException('Explicit command required');
    $cfg=opsConfig();$source=$argv[2];$p=nativeUsageConfig($cfg,$source);if(!$p)throw new RuntimeException('Disabled');
    if($argv[1]==='--check'){echo "Local configuration valid; no network access.\n";exit;}
    $db=opsDb($cfg);$db->beginTransaction();creativeActive($db);$db->commit();
    $day=nativeUsageDay();$counts=nativeUsageParse($p,$day,nativeUsageRequest($p,$day));
    $db->beginTransaction();nativeUsageCapture($db,$cfg,$source,$day,$counts);$db->commit();echo "Provisional provider usage snapshot saved; no message or call details stored.\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: authorization, provider response, exact period, pause or credential unavailable. No raw response logged.\n");exit(1);}
