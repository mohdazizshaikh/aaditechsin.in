<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/twilio-inbound-core.php';
function twRetentionPlan(PDO $db,array $cfg,?DateTimeImmutable $now=null):array{
    if(($cfg['communications']['twilio_retention_enabled']??false)!==true||!$db->inTransaction())throw new RuntimeException('Disabled');
    $pause=$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn();
    if($pause===false||(int)$pause!==1)throw new RuntimeException('Pause required');
    $cut=($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'))->setTime(0,0)->modify('-30 days')->format('Y-m-d');
    $q=$db->prepare('SELECT event_hash,account_hash,channel,day_utc FROM growth_twilio_inbound WHERE day_utc<? ORDER BY day_utc,event_hash LIMIT 1000 FOR UPDATE');
    $q->execute([$cut]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    $n=(int)$db->query('SELECT COUNT(*) FROM growth_twilio_replay')->fetchColumn();
    if($n+count($rows)>500000)throw new RuntimeException('Replay ledger cap; review policy');
    return ['cutoff'=>$cut,'rows'=>$rows,'replay_count'=>$n,'digest'=>hash('sha256',json_encode(['twilio-retention-v1',$cut,$n,$rows],JSON_THROW_ON_ERROR))];
}
function twRetentionApply(PDO $db,array $cfg,string $digest,?DateTimeImmutable $now=null):int{
    $p=twRetentionPlan($db,$cfg,$now);
    if(!preg_match('/^[a-f0-9]{64}$/D',$digest)||!hash_equals($p['digest'],$digest))throw new RuntimeException('Plan changed');
    $put=$db->prepare('INSERT INTO growth_twilio_replay(event_hash) VALUES(?)');
    $del=$db->prepare('DELETE FROM growth_twilio_inbound WHERE event_hash=? AND account_hash=? AND channel=? AND day_utc=? AND day_utc<?');
    $db->exec('SAVEPOINT tw_retention');
    try{
        foreach($p['rows'] as $r){$put->execute([$r['event_hash']]);$del->execute([$r['event_hash'],$r['account_hash'],$r['channel'],$r['day_utc'],$p['cutoff']]);if($del->rowCount()!==1)throw new RuntimeException('Row changed');}
        if($p['rows'])growthAudit($db,null,null,'twilio_replay_retention','private_cli');
        $db->exec('RELEASE SAVEPOINT tw_retention');
    }catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT tw_retention');$db->exec('RELEASE SAVEPOINT tw_retention');throw $e;}
    return count($p['rows']);
}
