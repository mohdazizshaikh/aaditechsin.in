<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/media-restore-lib.php';
try{
    $cfg=opsConfig();if(!storageEnabled($cfg))throw new RuntimeException('Disabled');
    $db=opsDb($cfg);$root=miRoot($cfg);$mode=$argv[1]??'';
    if(in_array($mode,['--capture-restore','--compare-restore'],true)&&count($argv)===($mode==='--capture-restore'?3:4)){
        $key=restoreContentKey((string)getenv('AADITECHS_RESTORE_HMAC_KEY_HEX'));
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
        $db->beginTransaction();$m=mediaRestoreCapture($db,$cfg,$argv[2],$key);$db->rollBack();
        if($mode==='--capture-restore'){echo mediaRestoreBytes($m);exit;}
        $raw=stream_get_contents(STDIN,65537);if(!is_string($raw))throw new RuntimeException('Reference unavailable');
        $result=mediaRestoreCompare($m,$raw,$argv[3]);
        foreach($result as $label=>$ok)echo $label.': '.($ok?'MATCH':'DRIFT')."\n";
        $ready=mediaRestoreReady($m);
        echo 'Reference availability: '.($ready?'ALL ACTIVE':'MISSING OR QUARANTINED')."\n";
        if(!$ready||in_array(false,$result,true))exit(1);
        echo "READ ONLY: bounded media parity observed; archive provenance, worker-stop and publication readiness not asserted.\n";
    }elseif($mode==='--inventory'&&count($argv)===2){
        echo json_encode(storageInventory($db,$root,500),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
    }elseif($mode==='--reference-audit'&&count($argv)===2){
        // Read-only bounded diagnostic; stop workers for a stable observation.
        echo json_encode(storageReferenceAudit($db,$root),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
    }elseif($mode==='--quarantine-plan'&&count($argv)===2){
        $raw=stream_get_contents(STDIN,8193);if(!is_string($raw))throw new RuntimeException('Plan unavailable');
        $db->beginTransaction();$plan=storageQuarantinePlan($db,$cfg,$raw);$db->rollBack();
        echo json_encode($plan,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
    }elseif(count($argv)===3&&in_array($mode,['--quarantine','--restore'],true)){
        $db->beginTransaction();storageMove($db,$cfg,$argv[2],$mode==='--restore');$db->commit();echo ($mode==='--restore'?'Restored ':'Quarantined ').$argv[2]."\n";
    }else throw new InvalidArgumentException('Explicit command required');
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: inventory, pause, protected asset, filesystem or audit failure. Check both private directories and audit before repeating.\n");exit(1);}
