<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/strict-json.php';
require_once dirname(__DIR__,2).'/public_html/api/seo-core.php';
require_once dirname(__DIR__,2).'/public_html/api/creative-core.php';
function competitorReviewSignals():array{
    return [
        'service_scope'=>'Review and clarify actual service scope.',
        'process_explained'=>'Review the actual assessment and delivery process.',
        'limitations_explained'=>'Review real exclusions, dependencies and limitations.',
        'contact_route'=>'Review an authorized inquiry route.',
        'case_evidence_visible'=>'Review permissioned and substantiated case evidence; never invent proof.',
        'maintenance_options'=>'Review actual maintenance options and eligibility.'
    ];
}
function competitorReviewScope(array $cfg,string $raw,?DateTimeImmutable $now=null):array{
    if(!seoEnabled($cfg)||($cfg['seo']['competitor_review_batch_enabled']??false)!==true)throw new RuntimeException('Disabled');
    if(strlen($raw)>16384)throw new InvalidArgumentException('Scope cap');
    $o=strictJsonDecode($raw,false,8);
    if(!$o instanceof stdClass)throw new InvalidArgumentException('Scope object');
    $v=get_object_vars($o);$keys=array_keys($v);sort($keys);
    if($keys!==['evidence_sha256','items','review_day_utc','scope','version']||$v['version']!==1||$v['scope']!=='competitor-editorial-review'||!is_string($v['evidence_sha256'])||!preg_match('/^[a-f0-9]{64}$/D',$v['evidence_sha256'])||!is_string($v['review_day_utc'])||!is_array($v['items'])||count($v['items'])<1||count($v['items'])>84)throw new InvalidArgumentException('Invalid scope');
    $day=($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
    if($v['review_day_utc']!==$day)throw new InvalidArgumentException('Regenerate and review current UTC day');
    $catalog=growthCatalog();$signals=competitorReviewSignals();$items=[];
    foreach($v['items'] as $o){
        if(!$o instanceof stdClass)throw new InvalidArgumentException('Item object');
        $r=get_object_vars($o);$keys=array_keys($r);sort($keys);
        if($keys!==['service_id','signal']||!is_string($r['service_id'])||!is_string($r['signal'])||!isset($catalog[$r['service_id']],$signals[$r['signal']]))throw new InvalidArgumentException('Unknown cell');
        $key=$r['service_id'].':'.$r['signal'];
        if(isset($items[$key]))throw new InvalidArgumentException('Duplicate cell');
        $items[$key]=['service_id'=>$r['service_id'],'signal'=>$r['signal']];
    }
    ksort($items,SORT_STRING);
    return ['version'=>1,'scope'=>$v['scope'],'evidence_sha256'=>$v['evidence_sha256'],'review_day_utc'=>$day,'items'=>array_values($items)];
}
function competitorReviewTitle(string $signal):string{return 'Competitor editorial review: '.$signal;}
function competitorReviewPlan(PDO $db,array $cfg,string $raw,bool $lock=false,?DateTimeImmutable $now=null):array{
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $scope=competitorReviewScope($cfg,$raw,$now);
    $suffix=$lock?' FOR UPDATE':'';
    $paused=$db->query('SELECT paused FROM growth_control WHERE id=1'.$suffix)->fetchColumn();
    if($paused===false||(string)$paused!=='0')throw new RuntimeException('Growth paused');
    $q=$db->prepare("SELECT id,status,revision FROM growth_tasks WHERE service_id=? AND task_type='seo' AND title=? ORDER BY id LIMIT 2".$suffix);
    $rows=[];
    foreach($scope['items'] as $item){
        $title=competitorReviewTitle($item['signal']);
        $q->execute([$item['service_id'],$title]);$found=$q->fetchAll(PDO::FETCH_ASSOC);
        if(count($found)>1)throw new RuntimeException('Ambiguous existing task; manual review');
        $old=$found[0]??null;
        $rows[]=$item+['title'=>$title,'existing'=>$old,'operation'=>$old?'preserve':'create'];
    }
    $plan=['scope'=>$scope,'rows'=>$rows,'create_count'=>count(array_filter($rows,static fn($r)=>$r['operation']==='create'))];
    $plan['digest']=hash('sha256',json_encode($plan,JSON_THROW_ON_ERROR));return $plan;
}
function competitorReviewApply(PDO $db,array $cfg,string $raw,string $digest,?DateTimeImmutable $now=null):array{
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    if(!preg_match('/^[a-f0-9]{64}$/D',$digest))throw new InvalidArgumentException('Invalid digest');
    $db->exec('SAVEPOINT competitor_review_batch');
    try{
        $plan=competitorReviewPlan($db,$cfg,$raw,true,$now);
        if(!hash_equals($plan['digest'],$digest))throw new RuntimeException('Plan changed');
        $ids=[];$signals=competitorReviewSignals();
        foreach($plan['rows'] as $r){
            if($r['operation']!=='create')continue;
            $draft="Internal editorial review only.\n".$signals[$r['signal']]."\nOperator-supplied comparison reference SHA-256: ".$plan['scope']['evidence_sha256']."\nReview day UTC: ".$plan['scope']['review_day_utc']."\nVerify original private evidence, relevance, rights, service availability and factual wording. No verified competitor claim, ranking or permission to copy. No publication authorized.";
            $id=growthCreate($db,['service_id'=>$r['service_id'],'task_type'=>'seo','title'=>$r['title'],'draft'=>$draft]);
            growthAudit($db,$id,1,'competitor_review_created','private_cli');$ids[]=$id;
        }
        $db->exec('RELEASE SAVEPOINT competitor_review_batch');
        return ['created'=>count($ids),'preserved'=>count($plan['rows'])-count($ids),'task_ids'=>$ids,'digest'=>$digest];
    }catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT competitor_review_batch');$db->exec('RELEASE SAVEPOINT competitor_review_batch');throw $e;}
}
