<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/strict-json.php';
require_once dirname(__DIR__,2).'/public_html/api/communication-review-core.php';
function commReviewBatchScope(array $cfg,string $raw,?DateTimeImmutable $now=null):array{
    if(!commReviewEnabled($cfg)||($cfg['communications']['review_batch_enabled']??false)!==true)throw new RuntimeException('Batch disabled');
    if(strlen($raw)>16384)throw new InvalidArgumentException('Scope too large');
    $obj=strictJsonDecode($raw,false,8);
    if(!$obj instanceof stdClass)throw new InvalidArgumentException('Object required');
    $v=get_object_vars($obj);$action=$v['action']??null;
    if(!in_array($action,['set','complete','cancel'],true))throw new InvalidArgumentException('Invalid action');
    $keys=array_keys($v);sort($keys);
    if($keys!==($action==='set'?['action','due_on','items','version']:['action','items','version'])||$v['version']!==1||!is_array($v['items'])||count($v['items'])<1||count($v['items'])>50)throw new InvalidArgumentException('Invalid scope');
    $utc=new DateTimeZone('UTC');$now=($now??new DateTimeImmutable('now',$utc))->setTimezone($utc);$today=$now->setTime(0,0);
    $due=$v['due_on']??null;
    if($action==='set'&&(!is_string($due)||!followDate($due,$now)))throw new InvalidArgumentException('Invalid due date');
    $items=[];
    foreach($v['items'] as $obj){
        if(!$obj instanceof stdClass)throw new InvalidArgumentException('Invalid item');
        $row=get_object_vars($obj);$keys=array_keys($row);sort($keys);
        if($keys!==['channel','day_utc']||!in_array($row['channel'],['email','whatsapp','sms','calls'],true)||!is_string($row['day_utc']))throw new InvalidArgumentException('Invalid item');
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$row['day_utc'],$utc);
        if(!$d||$d->format('Y-m-d')!==$row['day_utc']||$d>=$today)throw new InvalidArgumentException('Completed UTC day required');
        if($action==='set'&&$d<$today->modify('-90 days'))throw new InvalidArgumentException('Set only within aggregate import window');
        $key=$row['channel'].':'.$row['day_utc'];
        if(isset($items[$key]))throw new InvalidArgumentException('Duplicate item');
        $items[$key]=['channel'=>$row['channel'],'day_utc'=>$row['day_utc']];
    }
    ksort($items,SORT_STRING);
    return ['version'=>1,'action'=>$action,'items'=>array_values($items),'due_on'=>$due];
}
function commReviewBatchPlan(PDO $db,array $cfg,string $raw,bool $lock=false,?DateTimeImmutable $now=null):array{
    $scope=commReviewBatchScope($cfg,$raw,$now);$paused=commReviewControl($db,$lock);
    if($scope['action']==='set'&&$paused)throw new RuntimeException('Growth paused');
    $suffix=$lock?' FOR UPDATE':'';
    $aq=$db->prepare('SELECT id,received_count,answered_count,missed_count,imported_at FROM growth_communication_daily WHERE channel=? AND day_utc=?'.$suffix);
    $rq=$db->prepare('SELECT status,due_on,updated_at FROM growth_communication_reviews WHERE aggregate_id=?'.$suffix);
    $rows=[];
    foreach($scope['items'] as $item){
        $aq->execute([$item['channel'],$item['day_utc']]);$a=$aq->fetch(PDO::FETCH_ASSOC);
        if(!$a)throw new RuntimeException('Missing aggregate');
        $rq->execute([$a['id']]);$r=$rq->fetch(PDO::FETCH_ASSOC)?:null;
        if($scope['action']==='set'){
            if((int)$a['missed_count']<1)throw new RuntimeException('Missed-count evidence required');
            if($r&&$r['status']==='open'&&$r['due_on']===$scope['due_on'])throw new RuntimeException('Unchanged review');
        }elseif(!$r||$r['status']!=='open')throw new RuntimeException('Open review required');
        $rows[]=$item+['aggregate'=>$a,'review'=>$r];
    }
    $p=['scope'=>$scope,'paused'=>$paused,'rows'=>$rows];
    $p['digest']=hash('sha256',json_encode($p,JSON_THROW_ON_ERROR));return $p;
}
function commReviewBatchApply(PDO $db,array $cfg,string $raw,string $digest,?DateTimeImmutable $now=null):array{
    if(!preg_match('/^[a-f0-9]{64}$/D',$digest))throw new InvalidArgumentException('Invalid digest');
    return commReviewAtomic($db,static function()use($db,$cfg,$raw,$digest,$now):array{
        $p=commReviewBatchPlan($db,$cfg,$raw,true,$now);
        if(!hash_equals($p['digest'],$digest))throw new RuntimeException('Plan changed');
        $action=$p['scope']['action'];
        $set=$db->prepare("INSERT INTO growth_communication_reviews(aggregate_id,due_on,status) VALUES(?,?,'open') ON DUPLICATE KEY UPDATE due_on=VALUES(due_on),status='open'");
        $close=$db->prepare("UPDATE growth_communication_reviews SET status=? WHERE aggregate_id=? AND status='open'");
        foreach($p['rows'] as $r){
            $id=$r['aggregate']['id'];
            if($action==='set'){$set->execute([$id,$p['scope']['due_on']]);$state='set';}
            else{$state=$action==='complete'?'completed':'cancelled';$close->execute([$state,$id]);if($close->rowCount()!==1)throw new RuntimeException('Review changed');}
            growthAudit($db,null,null,'communication_review_'.$state.'_'.$id,'owner');
        }
        return ['action'=>$action,'applied'=>count($p['rows']),'digest'=>$digest];
    });
}
