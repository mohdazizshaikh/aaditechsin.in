<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/discovery-recovery-lib.php';
try{
    if(!((count($argv)===3&&$argv[1]==='--inspect')||(count($argv)===4&&$argv[1]==='--confirm-stopped'))||!preg_match('/^[1-9][0-9]{0,14}$/D',$argv[2]))throw new RuntimeException('Explicit mode');
    $cfg=opsConfig();$db=opsDb($cfg);$db->beginTransaction();
    if($argv[1]==='--inspect'){$r=feedRecoveryState($db,$cfg,(int)$argv[2]);$db->rollBack();echo json_encode($r,JSON_THROW_ON_ERROR)."\n";exit;}
    feedRecoveryApply($db,$cfg,(int)$argv[2],$argv[3]);$db->commit();echo "Interrupted run marked failed by operator; imported opportunities unchanged. Counts may be incomplete. No retry scheduled.\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: confirm stopped workers, pause and exact reviewed run digest.\n");exit(1);}
