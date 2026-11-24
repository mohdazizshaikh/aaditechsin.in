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

function creativeTransportCommand(PDO $db,array $cfg,string $command):?string {
    if($command!=='/creativeworker'&&!preg_match('/^\\/retrycreative(?: |$)/D',$command))return null;
    if(!creativeTransportEnabled($cfg))return 'Creative transport disabled or unconfigured.';
    if($command==='/creativeworker'){
        $lines=['Creative transport (latest pending generation tasks, not PC connectivity):'];
        $q=$db->query("SELECT t.id,t.revision,l.attempts,l.status,l.expires_at<=UTC_TIMESTAMP() AS expired FROM growth_tasks t JOIN growth_creative c ON c.task_id=t.id LEFT JOIN growth_creative_leases l ON l.task_id=t.id AND l.revision=t.revision WHERE t.status='regeneration_requested' ORDER BY t.id DESC LIMIT 10");
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
            $n=(int)($r['attempts']??0);
            $state=$r['status']??'unclaimed';
            if($state==='leased'&&(int)$r['expired']===1)$state='expired';
            if($n>=3&&in_array($state,['failed','expired'],true))$state='exhausted — /retrycreative '.$r['id'].' '.$r['revision'];
            $lines[]='#'.$r['id'].' rev '.$r['revision'].' attempts '.$n.'/3 | '.$state;
        }
        return count($lines)===1?'No pending creative generation tasks.':implode("\n",$lines);
    }
    if(!preg_match('/^\\/retrycreative ([1-9][0-9]{0,14}) ([1-9][0-9]{0,8})$/D',$command,$m))return 'Use /retrycreative ID REV.';
    $paused=$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn();
    if($paused===false||(int)$paused!==0)return 'Growth paused.';
    $id=(int)$m[1];$rev=(int)$m[2];
    $q=$db->prepare("SELECT t.id FROM growth_tasks t JOIN growth_creative c ON c.task_id=t.id JOIN growth_creative_leases l ON l.task_id=t.id AND l.revision=t.revision WHERE t.id=? AND t.revision=? AND t.status='regeneration_requested' AND l.attempts>=3 AND (l.status='failed' OR (l.status='leased' AND l.expires_at<=UTC_TIMESTAMP())) FOR UPDATE");
    $q->execute([$id,$rev]);
    if(!$q->fetchColumn())return 'Task not exhausted at that revision. Refresh /creativeworker.';
    $db->prepare("UPDATE growth_tasks SET revision=revision+1 WHERE id=? AND revision=? AND status='regeneration_requested'")->execute([$id,$rev]);
    growthAudit($db,$id,$rev,'creative_retry','owner');
    return 'New creative revision requested. Previous lease cannot submit a draft.';
}
