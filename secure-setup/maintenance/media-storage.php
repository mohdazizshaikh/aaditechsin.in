<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/media-storage-core.php';
try{
    $cfg=opsConfig();if(!storageEnabled($cfg))throw new RuntimeException('Disabled');
    $db=opsDb($cfg);$root=miRoot($cfg);$mode=$argv[1]??'';
    if($mode==='--inventory'&&count($argv)===2){
        echo json_encode(storageInventory($db,$root,500),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
    }elseif(count($argv)===3&&in_array($mode,['--quarantine','--restore'],true)){
        $db->beginTransaction();storageMove($db,$cfg,$argv[2],$mode==='--restore');$db->commit();echo ($mode==='--restore'?'Restored ':'Quarantined ').$argv[2]."\n";
    }else throw new InvalidArgumentException('Explicit command required');
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: inventory, pause, protected asset, filesystem or audit failure. Check both private directories and audit before repeating.\n");exit(1);}
