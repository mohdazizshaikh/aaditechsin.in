<?php
declare(strict_types=1);
require_once __DIR__.'/communications-core.php';
require_once __DIR__.'/opportunity-followup-core.php';
function commReviewEnabled(array $cfg):bool{return communicationsEnabled($cfg)&&($cfg['communications']['reviews_enabled']??false)===true;}
function commReviewSummary(PDO $db,?DateTimeImmutable $now=null):string{
    $day=($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
    $q=$db->prepare("SELECT COUNT(*) FROM growth_communication_reviews WHERE status='open' AND due_on<=?");$q->execute([$day]);$due=(int)$q->fetchColumn();
    $open=(int)$db->query("SELECT COUNT(*) FROM growth_communication_reviews WHERE status='open'")->fetchColumn();
    return 'Communication review reminders: '.$due.' due/overdue, '.$open.' open. Aggregate review only; no customer outreach.';
}
function commReviewCommand(PDO $db,array $cfg,string $cmd):?string{
    if(!preg_match('/^\/(?:commreviews|commreview|completecommreview|cancelcommreview)(?: |$)/D',$cmd))return null;
    if(!commReviewEnabled($cfg))return 'Communication reviews disabled.';
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    if($cmd==='/commreviews'){
        $lines=[commReviewSummary($db)];
        foreach($db->query("SELECT a.channel,a.day_utc,a.missed_count,r.due_on FROM growth_communication_reviews r JOIN growth_communication_daily a ON a.id=r.aggregate_id WHERE r.status='open' ORDER BY r.due_on,a.id LIMIT 10")->fetchAll(PDO::FETCH_ASSOC) as $r)$lines[]=$r['channel'].' '.$r['day_utc'].' missed '.$r['missed_count'].' review '.$r['due_on'].' UTC';
        return implode("\n",$lines);
    }
    if(preg_match('/^\/commreview (email|whatsapp|sms|calls) (\d{4}-\d{2}-\d{2}) (\d{4}-\d{2}-\d{2})$/D',$cmd,$m)){
        if(!followDate($m[3]))return 'Choose review date within 90 days UTC.';
        try{creativeActive($db);}catch(RuntimeException $e){return 'Growth paused.';}
        $q=$db->prepare('SELECT id,missed_count FROM growth_communication_daily WHERE channel=? AND day_utc=? FOR UPDATE');$q->execute([$m[1],$m[2]]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!$r||(int)$r['missed_count']<1)return 'Imported missed-count evidence required.';
        commReviewAtomic($db,static function()use($db,$r,$m):void{
            $db->prepare("INSERT INTO growth_communication_reviews(aggregate_id,due_on,status) VALUES(?,?,'open') ON DUPLICATE KEY UPDATE due_on=VALUES(due_on),status='open'")->execute([$r['id'],$m[3]]);
            growthAudit($db,null,null,'communication_review_set','owner');
        });return 'Internal aggregate review set. No outreach sent.';
    }
    if(preg_match('/^\/(completecommreview|cancelcommreview) (email|whatsapp|sms|calls) (\d{4}-\d{2}-\d{2})$/D',$cmd,$m)){
        $state=$m[1]==='completecommreview'?'completed':'cancelled';
        commReviewControl($db,true);
        $closed=commReviewAtomic($db,static function()use($db,$state,$m):bool{
            $q=$db->prepare("UPDATE growth_communication_reviews r JOIN growth_communication_daily a ON a.id=r.aggregate_id SET r.status=? WHERE a.channel=? AND a.day_utc=? AND r.status='open'");$q->execute([$state,$m[2],$m[3]]);
            if($q->rowCount()!==1)return false;
            growthAudit($db,null,null,'communication_review_'.$state,'owner');return true;
        });
        return $closed?'Internal review '.$state.'. No outreach sent.':'No open review.';
    }
    return 'Use /commreviews /commreview CHANNEL DAY DUE_DAY /completecommreview CHANNEL DAY /cancelcommreview CHANNEL DAY.';
}

function commReviewControl(PDO $db,bool $lock=false):bool{
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $p=$db->query('SELECT paused FROM growth_control WHERE id=1'.($lock?' FOR UPDATE':''))->fetchColumn();
    if($p===false||!in_array((string)$p,['0','1'],true))throw new RuntimeException('Control unavailable');
    return (bool)$p;
}
function commReviewAtomic(PDO $db,callable $fn):mixed{
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $db->exec('SAVEPOINT communication_review_action');
    try{$r=$fn();$db->exec('RELEASE SAVEPOINT communication_review_action');return $r;}
    catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT communication_review_action');$db->exec('RELEASE SAVEPOINT communication_review_action');throw $e;}
}
