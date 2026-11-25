<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/twilio-retention-lib.php';
try{
    if(!(count($argv)===1||(count($argv)===2&&$argv[1]==='--dry-run')||(count($argv)===3&&$argv[1]==='--apply')))throw new RuntimeException('Explicit plan');
    $cfg=opsConfig();$db=opsDb($cfg);$db->beginTransaction();
    if(count($argv)===3){$n=twRetentionApply($db,$cfg,$argv[2]);$db->commit();echo json_encode(['compacted'=>$n],JSON_THROW_ON_ERROR)."\n";}
    else{$p=twRetentionPlan($db,$cfg);$db->rollBack();echo json_encode(['candidates'=>count($p['rows']),'cutoff'=>$p['cutoff'],'replay_count'=>$p['replay_count'],'plan_sha256'=>$p['digest']],JSON_THROW_ON_ERROR)."\n";}
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: retention, pause, replay capacity or plan verification failed. No cleanup committed.\n");exit(1);}
