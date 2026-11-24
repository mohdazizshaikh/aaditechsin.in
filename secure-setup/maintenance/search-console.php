<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/search-console-read.php';
try{
    if(count($argv)!==2||!in_array($argv[1],['--check','--capture'],true))throw new RuntimeException('Explicit mode required');
    $cfg=opsConfig();$p=scConfig($cfg);if(!$p)throw new RuntimeException('Disabled');
    if($argv[1]==='--check'){echo "Local configuration valid; no network access.\n";exit;}
    $db=opsDb($cfg);$db->beginTransaction();creativeActive($db);$db->commit();
    $window=scWindow();$r=scCollect($p,$window,'gmailRenewRequest','scRequest');
    $db->beginTransaction();scCapture($db,$cfg,$window,$r);$db->commit();echo "Finalized search analytics aggregate recorded; no credentials or search terms stored.\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: authorization, pause, read-only scope, property, period or provider response unverified. No raw response logged.\n");exit(1);}
