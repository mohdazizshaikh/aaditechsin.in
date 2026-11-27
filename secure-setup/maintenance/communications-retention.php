<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/communications-retention-lib.php';
try{
    if(count($argv)>3||(count($argv)===2&&$argv[1]!=='--dry-run')||(count($argv)===3&&$argv[1]!=='--apply'))throw new RuntimeException('Explicit plan required');
    $cfg=opsConfig();$db=opsDb($cfg);$db->beginTransaction();
    if(count($argv)===3){$r=commRetentionApply($db,$cfg,$argv[2]);$db->commit();echo json_encode($r,JSON_THROW_ON_ERROR)."\n";}
    else{$p=commRetentionPlan($db,$cfg);$db->rollBack();echo json_encode(['aggregate_candidates'=>count($p['ids']),'gmail_candidates'=>$p['gmail']===null?0:1,'cutoff'=>$p['cutoff'],'gmail_cutoff'=>$p['gmail_cutoff'],'plan_sha256'=>$p['digest']],JSON_THROW_ON_ERROR)."\n";}
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: retention disabled, growth not paused, or candidate plan changed. No deletion committed.\n");exit(1);}
