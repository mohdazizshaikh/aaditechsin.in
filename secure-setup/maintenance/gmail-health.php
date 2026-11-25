<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/gmail-health-read.php';
try{
    if(count($argv)!==2||!in_array($argv[1],['--status','--capture'],true))throw new RuntimeException('Explicit command required');
    $cfg=opsConfig();$db=opsDb($cfg);
    if($argv[1]==='--status'){echo gmailSummary($db,$cfg)."\n";exit;}
    if(($cfg['communications']['gmail']['renewal']['scheduled_capture_enabled']??false)===true)throw new RuntimeException('Use scheduled Google capture');
    $p=gmailConfig($cfg);if(!$p)throw new RuntimeException('Disabled');
    $db->beginTransaction();creativeActive($db);$db->commit();
    $counts=gmailCollect($p,'gmailRead');
    $db->beginTransaction();gmailCapture($db,$cfg,$counts);$db->commit();echo "Sanitized inbox snapshot recorded. No messages read or sent.\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: mailbox, consent, expiry, pause or read unavailable. No provider response logged.\n");exit(1);}
