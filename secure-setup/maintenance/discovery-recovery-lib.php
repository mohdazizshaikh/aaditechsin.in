<?php
declare(strict_types=1);
require_once __DIR__.'/discovery-feed-lib.php';
function feedRecoveryState(PDO $db,array $cfg,int $id,?DateTimeImmutable $now=null):array{
    if(($cfg['discovery']['recovery_enabled']??false)!==true||!$db->inTransaction()||$id<1)throw new RuntimeException('Disabled or invalid ID');
    $paused=$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn();
    if($paused===false||(int)$paused!==1)throw new RuntimeException('Pause required');
    $q=$db->prepare("SELECT id,source_id,status,imported_count,duplicate_count,skipped_count,error_code,started_at,finished_at FROM growth_source_runs WHERE id=? AND status='running' FOR UPDATE");$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
    $ts=$r?strtotime($r['started_at'].' UTC'):false;
    if($ts===false||($now??new DateTimeImmutable('now'))->getTimestamp()-$ts<3600)throw new RuntimeException('Only interrupted runs older than one hour');
    return ['id'=>$id,'source_id'=>$r['source_id'],'started_at'=>$r['started_at'],'digest'=>hash('sha256',json_encode(['feed-recovery-v1',$r],JSON_THROW_ON_ERROR))];
}
function feedRecoveryApply(PDO $db,array $cfg,int $id,string $digest,?DateTimeImmutable $now=null):void{
    $r=feedRecoveryState($db,$cfg,$id,$now);
    if(!preg_match('/^[a-f0-9]{64}$/D',$digest)||!hash_equals($r['digest'],$digest))throw new RuntimeException('State changed');
    $db->exec('SAVEPOINT feed_recovery');
    try{
        $q=$db->prepare("UPDATE growth_source_runs SET status='failed',error_code='operator_interrupted',finished_at=UTC_TIMESTAMP() WHERE id=? AND status='running'");$q->execute([$id]);
        if($q->rowCount()!==1)throw new RuntimeException('State changed');
        growthAudit($db,null,null,'source_run_reviewed','private_cli');
        $db->exec('RELEASE SAVEPOINT feed_recovery');
    }catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT feed_recovery');$db->exec('RELEASE SAVEPOINT feed_recovery');throw $e;}
}
