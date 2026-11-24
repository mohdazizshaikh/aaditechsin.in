<?php
declare(strict_types=1);
require_once __DIR__.'/creative-core.php';
function creativeBatchEnabled(array $cfg):bool{return creativeEnabled($cfg)&&($cfg['creative']['batch_enabled']??false)===true;}
function creativeBatchMatrix(PDO $db,array $cfg,string $objective):array{
    if(!creativeBatchEnabled($cfg)||!in_array($objective,creativeObjectives(),true))throw new InvalidArgumentException('Disabled or invalid objective');
    $cells=[];foreach(growthCatalog() as $service=>$entry)foreach(creativeChannels() as $channel)$cells[$service][$channel]=['pending_review'=>0,'regeneration_requested'=>0,'approved'=>0,'rejected'=>0];
    $q=$db->prepare('SELECT t.service_id,c.channel,t.status,COUNT(*) AS n FROM growth_tasks t JOIN growth_creative c ON c.task_id=t.id WHERE c.objective=? GROUP BY t.service_id,c.channel,t.status');
    $q->execute([$objective]);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
        if(isset($cells[$r['service_id']][$r['channel']][$r['status']]))$cells[$r['service_id']][$r['channel']][$r['status']]=(int)$r['n'];
    }
    return $cells;
}
function creativeBatchPlan(PDO $db,array $cfg,string $objective,int $limit=126):array{
    if($limit<1||$limit>126)throw new InvalidArgumentException('Batch limit must be 1–126');
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    creativeActive($db);$cells=creativeBatchMatrix($db,$cfg,$objective);$missing=[];$total=0;
    foreach($cells as $service=>$channels)foreach($channels as $channel=>$counts){++$total;if(array_sum($counts)===0)$missing[]=['service'=>$service,'channel'=>$channel];}
    if($total>126)throw new RuntimeException('Review changed catalog');
    return ['objective'=>$objective,'total'=>$total,'missing'=>$missing,'limit'=>$limit,'selected'=>array_slice($missing,0,$limit),'digest'=>hash('sha256',json_encode(['creative-batch-v2',$objective,$limit,$cells],JSON_THROW_ON_ERROR))];
}
function creativeBatchApply(PDO $db,array $cfg,string $objective,string $digest,int $limit=126):array{
    $p=creativeBatchPlan($db,$cfg,$objective,$limit);
    if(!preg_match('/^[a-f0-9]{64}$/D',$digest)||!hash_equals($p['digest'],$digest))throw new RuntimeException('Coverage changed; review a new plan');
    $db->exec('SAVEPOINT creative_batch');
    try{
        $ids=[];foreach($p['selected'] as $cell)$ids[]=creativeCreate($db,$cell['service'],$cell['channel'],$objective);
        if($ids)growthAudit($db,null,null,'creative_batch_created','private_cli');
        $db->exec('RELEASE SAVEPOINT creative_batch');
        return $ids;
    }catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT creative_batch');$db->exec('RELEASE SAVEPOINT creative_batch');throw $e;}
}
function creativeBatchSummary(PDO $db,array $cfg,string $objective):string{
    if(!creativeBatchEnabled($cfg))return 'Creative batch disabled.';
    if(!in_array($objective,creativeObjectives(),true))return 'Use /coveragegrid education|awareness|enquiry|case-planning.';
    $cells=creativeBatchMatrix($db,$cfg,$objective);$lines=['Creative inventory by service for '.$objective.' (not published posts):'];
    foreach($cells as $service=>$channels){
        $covered=0;$approved=0;foreach($channels as $counts){$covered+=(int)(array_sum($counts)>0);$approved+=(int)($counts['approved']>0);}
        $lines[]=$service.': '.$covered.'/'.count($channels).' channels requested, '.$approved.' with approved draft';
    }
    return implode("\n",$lines);
}
