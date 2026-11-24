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
    $q=$db->prepare('SELECT id FROM growth_communication_daily WHERE day_utc<? ORDER BY id LIMIT 1001 FOR UPDATE');$q->execute([$cut]);$ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
    if(count($ids)>1000)throw new RuntimeException('Excessive candidates; review manually');
    $q=$db->prepare("SELECT COUNT(*) FROM growth_communication_reviews r JOIN growth_communication_daily a ON a.id=r.aggregate_id WHERE a.day_utc<? AND r.status='open'");$q->execute([$cut]);if((int)$q->fetchColumn()>0)throw new RuntimeException('Close eligible open reviews before retention');
    $q=$db->prepare('SELECT generation,captured_at FROM growth_gmail_snapshot WHERE id=1 AND captured_at<? FOR UPDATE');$q->execute([$gmailCut]);$gmail=$q->fetch(PDO::FETCH_ASSOC)?:null;
    $digest=hash('sha256',json_encode([$cut,$gmailCut,$ids,$gmail],JSON_THROW_ON_ERROR));
    return ['cutoff'=>$cut,'gmail_cutoff'=>$gmailCut,'ids'=>$ids,'gmail'=>$gmail,'digest'=>$digest];
}
function commRetentionApply(PDO $db,array $cfg,string $digest,?DateTimeImmutable $now=null):array{
    $p=commRetentionPlan($db,$cfg,$now);
    if(!preg_match('/^[a-f0-9]{64}$/D',$digest)||!hash_equals($p['digest'],$digest))throw new RuntimeException('Plan changed');
    $n=0;foreach($p['ids'] as $id){$q=$db->prepare('DELETE FROM growth_communication_daily WHERE id=? AND day_utc<?');$q->execute([$id,$p['cutoff']]);$n+=$q->rowCount();}
    if($n!==count($p['ids']))throw new RuntimeException('Candidate changed');
    $g=0;if($p['gmail']!==null){$q=$db->prepare('DELETE FROM growth_gmail_snapshot WHERE id=1 AND generation=? AND captured_at=? AND captured_at<?');$q->execute([$p['gmail']['generation'],$p['gmail']['captured_at'],$p['gmail_cutoff']]);$g=$q->rowCount();if($g!==1)throw new RuntimeException('Candidate changed');}
    growthAudit($db,null,null,'communications_retention','private_cli');return ['aggregate_deleted'=>$n,'gmail_deleted'=>$g];
}
