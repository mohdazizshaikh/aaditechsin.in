<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/whatsapp-inbound-core.php';
function waRetentionPlan(PDO $db,array $cfg,?DateTimeImmutable $now=null):array{
    if(($cfg['communications']['whatsapp_retention_enabled']??false)!==true||!$db->inTransaction())throw new RuntimeException('Disabled');
    $pause=$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn();if($pause===false||(int)$pause!==1)throw new RuntimeException('Pause required');
    $cut=($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'))->setTime(0,0)->modify('-30 days')->format('Y-m-d');
    $q=$db->prepare('SELECT event_hash,account_hash,day_utc FROM growth_whatsapp_inbound WHERE day_utc<? ORDER BY day_utc,event_hash LIMIT 5000 FOR UPDATE');$q->execute([$cut]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    return ['cutoff'=>$cut,'rows'=>$rows,'digest'=>hash('sha256',json_encode(['wa-retention-v1',$cut,$rows],JSON_THROW_ON_ERROR))];
}
function waRetentionApply(PDO $db,array $cfg,string $digest,?DateTimeImmutable $now=null):int{
    $p=waRetentionPlan($db,$cfg,$now);if(!preg_match('/^[a-f0-9]{64}$/D',$digest)||!hash_equals($p['digest'],$digest))throw new RuntimeException('Plan changed');
    $q=$db->prepare('DELETE FROM growth_whatsapp_inbound WHERE event_hash=? AND account_hash=? AND day_utc=? AND day_utc<?');
    $db->exec('SAVEPOINT wa_retention');
    try{
        foreach($p['rows'] as $r){$q->execute([$r['event_hash'],$r['account_hash'],$r['day_utc'],$p['cutoff']]);if($q->rowCount()!==1)throw new RuntimeException('Changed row');}
        if($p['rows'])growthAudit($db,null,null,'whatsapp_event_retention','private_cli');$db->exec('RELEASE SAVEPOINT wa_retention');
    }catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT wa_retention');$db->exec('RELEASE SAVEPOINT wa_retention');throw $e;}
    return count($p['rows']);
}
