<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/meta-core.php';
require __DIR__.'/meta-send.php';
try{
    $mode=$argv[1]??'';$provider=$argv[2]??'';
    if(count($argv)!==3||!in_array($mode,['--check-target','--run'],true))throw new InvalidArgumentException('Explicit mode required');
    $cfg=opsConfig();$p=metaTarget($cfg,$provider);if(!$p)throw new RuntimeException('Disabled');
    if($mode==='--check-target'){
        if(!metaIdentity($p,'metaRequest'))throw new RuntimeException('Identity not verified');
        echo "Configured account ID verified; publish permission not proven.\n";exit;
    }
    $db=opsDb($cfg);$db->beginTransaction();$row=metaClaim($db,$cfg,$provider);$db->commit();
    if(!$row){echo "No due approved post.\n";exit;}
    // Once claimed, never requeue on ambiguous network failure or process crash.
    $result=metaSend($p,$row,'metaRequest');
    $db->beginTransaction();metaFinish($db,$row,$result);$db->commit();
    echo 'Publication #'.$row['id'].' '.$result['status']."\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: Meta configuration, pause, transport or ledger unavailable. Inspect sending/unknown manually. Never blindly retry.\n");exit(1);}
