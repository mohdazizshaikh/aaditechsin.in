<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/strict-json.php';
require_once dirname(__DIR__,2).'/public_html/api/opportunity-followup-core.php';
function followBatchScope(array $cfg,string $raw,?DateTimeImmutable $now=null):array{
    if(!followEnabled($cfg)||($cfg['discovery']['followup_batch_enabled']??false)!==true)throw new RuntimeException('Batch disabled');
    if(strlen($raw)>8192)throw new InvalidArgumentException('Scope too large');
    $obj=strictJsonDecode($raw,false,8);
    if(!$obj instanceof stdClass)throw new InvalidArgumentException('Object required');
    $v=get_object_vars($obj);$action=$v['action']??null;
    if(!in_array($action,['set','complete','cancel'],true))throw new InvalidArgumentException('Invalid action');
    $keys=array_keys($v);sort($keys);
    if($keys!==($action==='set'?['action','due_on','ids','version']:['action','ids','version'])||$v['version']!==1||!is_array($v['ids'])||!array_is_list($v['ids'])||count($v['ids'])<1||count($v['ids'])>50)throw new InvalidArgumentException('Invalid scope');
    $ids=$v['ids'];foreach($ids as $id)if(!is_int($id)||$id<1||$id>999999999999999)throw new InvalidArgumentException('Invalid ID');
    if(count(array_unique($ids))!==count($ids))throw new InvalidArgumentException('Duplicate ID');
    sort($ids,SORT_NUMERIC);
    $due=$v['due_on']??null;
    if($action==='set'&&(!is_string($due)||!followDate($due,$now)))throw new InvalidArgumentException('Invalid due date');
    return ['version'=>1,'action'=>$action,'ids'=>$ids,'due_on'=>$due];
}
function followBatchPlan(PDO $db,array $cfg,string $raw,bool $lock=false,?DateTimeImmutable $now=null):array{
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $scope=followBatchScope($cfg,$raw,$now);
    $suffix=$lock?' FOR UPDATE':'';
    $paused=$db->query('SELECT paused FROM growth_control WHERE id=1'.$suffix)->fetchColumn();
    if($paused===false||!in_array((string)$paused,['0','1'],true))throw new RuntimeException('Control unavailable');
    if($scope['action']==='set'&&(int)$paused!==0)throw new RuntimeException('Growth paused');
    $oq=$db->prepare('SELECT status,service_id FROM growth_opportunities WHERE id=?'.$suffix);
    $fq=$db->prepare('SELECT status,due_on FROM growth_opportunity_followups WHERE opportunity_id=?'.$suffix);
    $rows=[];
    foreach($scope['ids'] as $id){
        $oq->execute([$id]);$o=$oq->fetch(PDO::FETCH_ASSOC);
        if(!$o||!isset(growthCatalog()[$o['service_id']]))throw new RuntimeException('Unknown opportunity');
        $fq->execute([$id]);$f=$fq->fetch(PDO::FETCH_ASSOC);
        if($scope['action']==='set'){
            if($o['status']!=='qualified')throw new RuntimeException('Qualification required');
            if($f&&$f['status']==='open'&&$f['due_on']===$scope['due_on'])throw new RuntimeException('Unchanged reminder');
        }elseif(!$f||$f['status']!=='open')throw new RuntimeException('Open reminder required');
        $rows[]=['id'=>$id,'service_id'=>$o['service_id'],'opportunity_status'=>$o['status'],'reminder_status'=>$f?$f['status']:null,'due_on'=>$f?$f['due_on']:null];
    }
    $plan=['scope'=>$scope,'paused'=>(bool)$paused,'rows'=>$rows];
    $plan['digest']=hash('sha256',json_encode($plan,JSON_THROW_ON_ERROR));
    return $plan;
}
function followBatchApply(PDO $db,array $cfg,string $raw,string $digest,?DateTimeImmutable $now=null):array{
    if(!preg_match('/^[a-f0-9]{64}$/D',$digest))throw new InvalidArgumentException('Invalid digest');
    return followAtomic($db,static function()use($db,$cfg,$raw,$digest,$now):array{
        $plan=followBatchPlan($db,$cfg,$raw,true,$now);
        if(!hash_equals($plan['digest'],$digest))throw new RuntimeException('Plan changed');
        $scope=$plan['scope'];$action=$scope['action'];
        $set=$db->prepare("INSERT INTO growth_opportunity_followups(opportunity_id,due_on,status) VALUES(?,?,'open') ON DUPLICATE KEY UPDATE due_on=VALUES(due_on),status='open'");
        $close=$db->prepare("UPDATE growth_opportunity_followups SET status=? WHERE opportunity_id=? AND status='open'");
        foreach($scope['ids'] as $id){
            if($action==='set'){$set->execute([$id,$scope['due_on']]);$state='set';}
            else{$state=$action==='complete'?'completed':'cancelled';$close->execute([$state,$id]);if($close->rowCount()!==1)throw new RuntimeException('Reminder changed');}
            growthAudit($db,null,null,'followup_'.$state.'_'.$id,'owner');
        }
        return ['action'=>$action,'applied'=>count($scope['ids']),'digest'=>$digest];
    });
}
