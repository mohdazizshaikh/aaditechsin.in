<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/communication-review-core.php';
function autoReviewDays(array $cfg):int{
    if(!commReviewEnabled($cfg)||($cfg['communications']['auto_review_enabled']??false)!==true)throw new RuntimeException('Automatic internal review disabled');
    $days=$cfg['communications']['auto_review_lookback_days']??null;
    if(!is_int($days)||$days<1||$days>30)throw new InvalidArgumentException('Lookback must be 1–30 days');
    return $days;
}
function autoReviewPlan(PDO $db,array $cfg,?DateTimeImmutable $now=null,bool $lock=false):array{
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $days=autoReviewDays($cfg);
    if(commReviewControl($db,$lock))throw new RuntimeException('Growth paused');
    $today=($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'))->setTime(0,0);
    $start=$today->modify('-'.$days.' days')->format('Y-m-d');$end=$today->format('Y-m-d');
    $q=$db->prepare("SELECT a.id,a.channel,a.day_utc,a.missed_count,r.status AS review_status FROM growth_communication_daily a LEFT JOIN growth_communication_reviews r ON r.aggregate_id=a.id WHERE a.day_utc>=? AND a.day_utc<? AND a.missed_count>0 ORDER BY a.day_utc,a.channel,a.id LIMIT 121".($lock?' FOR UPDATE':''));
    $q->execute([$start,$end]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    if(count($rows)>120)throw new RuntimeException('Aggregate review cap exceeded');
    $out=[];foreach($rows as $r){
        if($r['review_status']!==null&&!in_array($r['review_status'],['open','completed','cancelled'],true))throw new RuntimeException('Unexpected review status');
        $out[]=['id'=>(int)$r['id'],'channel'=>$r['channel'],'day_utc'=>$r['day_utc'],'missed_count'=>(int)$r['missed_count'],'operation'=>$r['review_status']===null?'create':'preserve','existing'=>$r['review_status']];
    }
    return ['start_utc'=>$start,'end_utc_exclusive'=>$end,'due_on'=>$end,'rows'=>$out,'create_count'=>count(array_filter($out,static fn($r)=>$r['operation']==='create'))];
}
function autoReviewRun(PDO $db,array $cfg,?DateTimeImmutable $now=null):array{
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    return commReviewAtomic($db,static function()use($db,$cfg,$now):array{
        $plan=autoReviewPlan($db,$cfg,$now,true);
        $q=$db->prepare("INSERT INTO growth_communication_reviews(aggregate_id,due_on,status) VALUES(?,?,'open')");
        $created=0;$channels=array_fill_keys(['email','whatsapp','sms','calls'],0);
        foreach($plan['rows'] as $row){
            if($row['operation']!=='create')continue;
            $q->execute([$row['id'],$plan['due_on']]);growthAudit($db,null,null,'communication_auto_review','private_cli');
            ++$created;++$channels[$row['channel']];
        }
        return ['created'=>$created,'preserved'=>count($plan['rows'])-$created,'created_by_channel'=>$channels,'due_on'=>$plan['due_on'],'outreach_sent'=>false];
    });
}
