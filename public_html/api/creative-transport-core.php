<?php
declare(strict_types=1);
require_once __DIR__.'/creative-core.php';

function creativeTransportEnabled(array $cfg):bool {
    return creativeEnabled($cfg)&&($cfg['creative_transport']['enabled']??false)===true;
}
function creativeTransportAuthorized(array $cfg,string $token):bool {
    $hash=$cfg['creative_transport']['token_sha256']??'';
    return creativeTransportEnabled($cfg)&&is_string($hash)&&preg_match('/^[a-f0-9]{64}$/D',$hash)===1
        &&preg_match('/^[a-f0-9]{64}$/D',$token)===1&&hash_equals($hash,hash('sha256',$token));
}
function creativeLeaseClaim(PDO $db):array {
    // Serialize claims with the existing growth pause/owner mutation lock.
    creativeActive($db);
    $r=$db->query("SELECT t.id,t.revision FROM growth_tasks t JOIN growth_creative c ON c.task_id=t.id LEFT JOIN growth_creative_leases l ON l.task_id=t.id AND l.revision=t.revision WHERE t.status='regeneration_requested' AND (l.task_id IS NULL OR (l.attempts<3 AND (l.status='failed' OR (l.status='leased' AND l.expires_at<=UTC_TIMESTAMP())))) ORDER BY t.id LIMIT 1 FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
    if(!$r)return ['status'=>'empty'];
    $id=(int)$r['id'];$rev=(int)$r['revision'];$token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
    $job=creativeExport($db,$id);
    $db->prepare("INSERT INTO growth_creative_leases(task_id,revision,token_hash,status,expires_at) VALUES(?,?,?,'leased',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE)) ON DUPLICATE KEY UPDATE attempts=attempts+1,token_hash=VALUES(token_hash),status='leased',expires_at=VALUES(expires_at),result_hash=NULL")->execute([$id,$rev,$hash]);
    return ['status'=>'leased','lease'=>$token,'job'=>$job];
}
function creativeLeaseFinish(PDO $db,int $id,int $rev,string $token,?array $result):array {
    creativeActive($db);
    if($id<1||$rev<1||preg_match('/^[a-f0-9]{64}$/D',$token)!==1)throw new InvalidArgumentException('Invalid lease');
    $hash=null;
    if($result!==null){
        $result=creativeValidate($result);
        if($result['task_id']!==$id||$result['revision']!==$rev)throw new InvalidArgumentException('Result scope mismatch');
        ksort($result);
        $hash=hash('sha256',json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
    }
    $q=$db->prepare("SELECT token_hash,status,result_hash,expires_at>UTC_TIMESTAMP() AS valid FROM growth_creative_leases WHERE task_id=? AND revision=? FOR UPDATE");
    $q->execute([$id,$rev]);$r=$q->fetch(PDO::FETCH_ASSOC);
    if(!$r||!hash_equals($r['token_hash'],hash('sha256',$token)))return ['status'=>'conflict'];
    if($r['status']==='completed')return ['status'=>$hash!==null&&hash_equals($r['result_hash'],$hash)?'pending_review':'conflict'];
    if($r['status']==='failed')return ['status'=>$result===null?'failed':'conflict'];
    if(!(int)$r['valid'])return ['status'=>'conflict'];
    if($result!==null&&!creativeImport($db,$result))return ['status'=>'conflict'];
    $state=$result===null?'failed':'completed';
    $db->prepare("UPDATE growth_creative_leases SET status=?,result_hash=? WHERE task_id=? AND revision=?")->execute([$state,$hash,$id,$rev]);
    return ['status'=>$result===null?'failed':'pending_review'];
}
