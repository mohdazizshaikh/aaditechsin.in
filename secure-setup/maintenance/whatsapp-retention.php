<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/whatsapp-retention-lib.php';
try{
    if(!((count($argv)===1)||(count($argv)===2&&$argv[1]==='--dry-run')||(count($argv)===3&&$argv[1]==='--apply')))throw new RuntimeException('Mode');
    $cfg=opsConfig();$db=opsDb($cfg);$db->beginTransaction();
    if(count($argv)===3){$n=waRetentionApply($db,$cfg,$argv[2]);$db->commit();echo json_encode(['deleted'=>$n],JSON_THROW_ON_ERROR)."\n";}
    else{$p=waRetentionPlan($db,$cfg);$db->rollBack();echo json_encode(['candidates'=>count($p['rows']),'cutoff'=>$p['cutoff'],'plan_sha256'=>$p['digest']],JSON_THROW_ON_ERROR)."\n";}
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: retention, pause or exact plan check failed. No deletion committed.\n");exit(1);}
