<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/search-console-core.php';
function seoRetentionPlan(PDO $db,array $cfg,?DateTimeImmutable $now=null):array{
    if(($cfg['seo']['history_retention_enabled']??false)!==true||!$db->inTransaction())throw new RuntimeException('Disabled');
    $pause=$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn();
    if($pause===false||(int)$pause!==1)throw new RuntimeException('Pause required');
    $cut=($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('America/Los_Angeles'))->setTime(0,0)->modify('-180 days')->format('Y-m-d');
    $q=$db->prepare('SELECT property_hash,generation,day_pt,clicks,impressions,average_position,captured_at FROM growth_search_console_daily WHERE day_pt<? ORDER BY day_pt,property_hash,generation LIMIT 1000 FOR UPDATE');
    $q->execute([$cut]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    return ['cutoff'=>$cut,'rows'=>$rows,'digest'=>hash('sha256',json_encode(['seo-history-v1',$cut,$rows],JSON_THROW_ON_ERROR))];
}
function seoRetentionApply(PDO $db,array $cfg,string $digest,?DateTimeImmutable $now=null):int{
    $p=seoRetentionPlan($db,$cfg,$now);
    if(!preg_match('/^[a-f0-9]{64}$/D',$digest)||!hash_equals($p['digest'],$digest))throw new RuntimeException('Plan changed');
    $q=$db->prepare('DELETE FROM growth_search_console_daily WHERE property_hash=? AND generation=? AND day_pt=? AND day_pt<?');
    $db->exec('SAVEPOINT seo_history_retention');
    try{
        foreach($p['rows'] as $r){$q->execute([$r['property_hash'],$r['generation'],$r['day_pt'],$p['cutoff']]);if($q->rowCount()!==1)throw new RuntimeException('Row changed');}
        if($p['rows'])growthAudit($db,null,null,'seo_history_retention','private_cli');
        $db->exec('RELEASE SAVEPOINT seo_history_retention');
    }catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT seo_history_retention');$db->exec('RELEASE SAVEPOINT seo_history_retention');throw $e;}
    return count($p['rows']);
}
