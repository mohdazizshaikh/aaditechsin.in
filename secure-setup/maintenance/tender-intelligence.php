<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/tender-intelligence-lib.php';
require dirname(__DIR__,2).'/public_html/api/tender-intelligence-core.php';
try{
    $mode=$argv[1]??'';
    if(!in_array($mode,['--rank','--deadlines','--plan','--apply'],true)||count($argv)!==($mode==='--apply'?3:2))throw new InvalidArgumentException('Explicit mode');
    $raw=$mode==='--deadlines'?'':stream_get_contents(STDIN,65537);
    if(!is_string($raw))throw new RuntimeException('Scope unavailable');
    if($mode==='--rank'){echo json_encode(tenderIntelRank($raw),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";exit;}
    $cfg=opsConfig();if(!tenderIntelEnabled($cfg))throw new RuntimeException('Disabled');
    $db=opsDb($cfg);$db->beginTransaction();
    if($mode==='--deadlines')$r=tenderDeadlineReport($db);
    elseif($mode==='--plan')$r=tenderIntelPlan($db,$cfg,$raw);
    else{$r=tenderIntelApply($db,$cfg,$raw,$argv[2]);if(opsConfig()!==$cfg)throw new RuntimeException('Config changed');}
    if($mode==='--apply')$db->commit();else $db->rollBack();
    echo json_encode($r,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: tender intelligence scope, pause, evidence or reviewed plan changed.\n");exit(1);}
