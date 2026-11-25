<?php
declare(strict_types=1);
require_once __DIR__.'/operations.php';
require_once __DIR__.'/growth-core.php';
function inquiryQueueEnabled(array $cfg):bool{
    return ($cfg['portal']['enabled']??false)===true&&($cfg['database']['enabled']??false)===true&&($cfg['leads']['enabled']??false)===true&&($cfg['leads']['response_queue_enabled']??false)===true;
}
function inquiryQueueOwner(array $cfg,array $session,int $now):bool{
    return inquiryQueueEnabled($cfg)&&($session['role']??null)==='admin'&&is_int($session['last_seen']??null)&&$session['last_seen']<=$now&&$now-$session['last_seen']<=1800&&is_string($session['admin_fingerprint']??null)&&hash_equals(opsAdminSessionFingerprint($cfg),$session['admin_fingerprint']);
}
function inquiryQueueHours(array $cfg):int{
    $h=$cfg['leads']['response_target_hours']??null;
    if(!is_int($h)||$h<1||$h>168)throw new InvalidArgumentException('Response target must be 1–168 hours');
    return $h;
}
function inquiryQueueSnapshot(PDO $db,array $cfg,string $filter='all',int $offset=0,?DateTimeImmutable $now=null,bool $details=false):array{
    if(!inquiryQueueEnabled($cfg)||!in_array($filter,['all','due','new','qualified'],true)||$offset<0||$offset>10000||$offset%25!==0)throw new InvalidArgumentException('Queue unavailable');
    $now=($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'));
    $cutoff=$now->modify('-'.inquiryQueueHours($cfg).' hours')->format('Y-m-d H:i:s');
    $q=$db->prepare("SELECT COUNT(*) AS total,COALESCE(SUM(status='new'),0) AS new_count,COALESCE(SUM(status='qualified'),0) AS qualified_count,COALESCE(SUM(created_at<=?),0) AS due_count FROM leads WHERE status IN ('new','qualified')");
    $q->execute([$cutoff]);$counts=array_map('intval',$q->fetch(PDO::FETCH_ASSOC));
    $rows=[];$hasMore=false;
    if($details){
        $where="status IN ('new','qualified')";$params=[];
        if($filter==='due'){$where.=' AND created_at<=?';$params[]=$cutoff;}
        elseif($filter!=='all'){$where.=' AND status=?';$params[]=$filter;}
        $q=$db->prepare("SELECT id,reference,full_name,email,phone,service,source,message,status,created_at,updated_at FROM leads WHERE ".$where." ORDER BY created_at,id LIMIT 26 OFFSET ".$offset);
        $q->execute($params);$rows=$q->fetchAll(PDO::FETCH_ASSOC);$hasMore=count($rows)>25;$rows=array_slice($rows,0,25);
        foreach($rows as &$r)$r['due']=$r['created_at']<=$cutoff;unset($r);
    }
    return ['counts'=>$counts,'rows'=>$rows,'has_more'=>$hasMore,'target_hours'=>inquiryQueueHours($cfg)];
}
function inquiryQueueRecord(PDO $db,array $cfg,int $id,string $status,string $updated,string $confirmed):bool{
    if(!inquiryQueueEnabled($cfg)||!$db->inTransaction()||$id<1||!in_array($status,['new','qualified'],true)||$confirmed!=='yes'||!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$updated))throw new InvalidArgumentException('Explicit response confirmation required');
    $db->exec('SAVEPOINT inquiry_response');
    try{
        $q=$db->prepare("UPDATE leads SET status='responded' WHERE id=? AND status=? AND updated_at=?");
        $q->execute([$id,$status,$updated]);$changed=$q->rowCount()===1;
        if($changed)growthAudit($db,null,null,'lead_response_'.$id,'owner');
        $db->exec('RELEASE SAVEPOINT inquiry_response');return $changed;
    }catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT inquiry_response');$db->exec('RELEASE SAVEPOINT inquiry_response');throw $e;}
}
function inquiryQueueCommand(PDO $db,array $cfg,string $command):?string{
    if($command!=='/inquiryqueue')return null;
    if(!inquiryQueueEnabled($cfg))return 'Inquiry response queue disabled.';
    $s=inquiryQueueSnapshot($db,$cfg);
    return 'Website inquiry response queue: '.$s['counts']['total'].' awaiting recorded response; '.$s['counts']['due_count'].' beyond internal '.$s['target_hours'].'h target. New '.$s['counts']['new_count'].', qualified '.$s['counts']['qualified_count'].".\nReview in private operations portal. Status-based; not proof of missed contact or delivery.";
}
