<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/reconciliation-lib.php';
try{
    $cfg=opsConfig();if(($cfg['reconciliation']['enabled']??false)!==true)throw new RuntimeException('Disabled');
    $db=opsDb($cfg);$db->beginTransaction();recPause($db);
    if(count($argv)===2&&$argv[1]==='--summary'){$r=recUnknownCounts($db);$db->rollBack();echo json_encode($r,JSON_THROW_ON_ERROR)."\n";exit;}
    if(!in_array(count($argv),[4,7],true)||!preg_match('/^[1-9][0-9]{0,14}$/D',$argv[3]))throw new RuntimeException('Explicit review required');
    $id=(int)$argv[3];
    if(count($argv)===4&&$argv[1]==='--inspect'){$r=recState($db,$argv[2],$id,true);$db->rollBack();echo json_encode($r,JSON_THROW_ON_ERROR)."\n";exit;}
    if(count($argv)!==7||$argv[1]!=='--record')throw new RuntimeException('Explicit review required');
    recRecord($db,$cfg,$argv[2],$id,$argv[4],$argv[5],$argv[6]);$db->commit();echo "Owner review evidence recorded; original status unchanged. No retry or publication authorized.\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: verify pause, exact state digest and independent provider evidence. No original publication modified.\n");exit(1);}
