<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/communications-core.php';
function commRetentionPlan(PDO $db,array $cfg,?DateTimeImmutable $now=null):array{
    if(($cfg['communications']['retention_enabled']??false)!==true||!$db->inTransaction())throw new RuntimeException('Retention disabled');
    $pause=$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn();
    if($pause===false||(int)$pause!==1)throw new RuntimeException('Pause required');
    $utc=new DateTimeZone('UTC');$now=($now??new DateTimeImmutable('now',$utc))->setTimezone($utc);
    $cut=$now->setTime(0,0)->modify('-90 days')->format('Y-m-d');
    $gmailCut=$now->setTime(0,0)->modify('-30 days')->format('Y-m-d H:i:s');
    $q=$db->prepare('SELECT id,channel,day_utc,received_count,answered_count,missed_count,imported_at FROM growth_communication_daily WHERE day_utc<? ORDER BY id LIMIT 1001 FOR UPDATE');$q->execute([$cut]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
    if(count($rows)>1000)throw new RuntimeException('Excessive candidates; review manually');
    $ids=array_map('intval',array_column($rows,'id'));$reviews=[];
    $q=$db->prepare('SELECT aggregate_id,status,due_on,updated_at FROM growth_communication_reviews WHERE aggregate_id=? FOR UPDATE');
    foreach($ids as $id){$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);if($r&&$r['status']==='open')throw new RuntimeException('Close eligible open reviews before retention');if($r)$reviews[]=$r;}
    $q=$db->prepare('SELECT generation,captured_at,inbox_count,unread_count FROM growth_gmail_snapshot WHERE id=1 AND captured_at<? FOR UPDATE');$q->execute([$gmailCut]);$gmail=$q->fetch(PDO::FETCH_ASSOC)?:null;
    $digest=hash('sha256',json_encode(['communications-retention-v2',$cut,$gmailCut,$rows,$reviews,$gmail],JSON_THROW_ON_ERROR));
    return ['cutoff'=>$cut,'gmail_cutoff'=>$gmailCut,'ids'=>$ids,'gmail'=>$gmail,'digest'=>$digest];
}
function commRetentionApply(PDO $db,array $cfg,string $digest,?DateTimeImmutable $now=null):array{
    $p=commRetentionPlan($db,$cfg,$now);
    if(!preg_match('/^[a-f0-9]{64}$/D',$digest)||!hash_equals($p['digest'],$digest))throw new RuntimeException('Plan changed');
    $db->exec('SAVEPOINT communications_retention_apply');
    try{
    $n=0;foreach($p['ids'] as $id){$q=$db->prepare('DELETE FROM growth_communication_daily WHERE id=? AND day_utc<?');$q->execute([$id,$p['cutoff']]);$n+=$q->rowCount();}
    if($n!==count($p['ids']))throw new RuntimeException('Candidate changed');
    $g=0;if($p['gmail']!==null){$q=$db->prepare('DELETE FROM growth_gmail_snapshot WHERE id=1 AND generation=? AND captured_at=? AND captured_at<?');$q->execute([$p['gmail']['generation'],$p['gmail']['captured_at'],$p['gmail_cutoff']]);$g=$q->rowCount();if($g!==1)throw new RuntimeException('Candidate changed');}
    growthAudit($db,null,null,'communications_retention','private_cli');
    $db->exec('RELEASE SAVEPOINT communications_retention_apply');return ['aggregate_deleted'=>$n,'gmail_deleted'=>$g];
    }catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT communications_retention_apply');$db->exec('RELEASE SAVEPOINT communications_retention_apply');throw $e;}
}
