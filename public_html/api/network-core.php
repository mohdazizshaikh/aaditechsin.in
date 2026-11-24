<?php
declare(strict_types=1);
require_once __DIR__.'/oauth-policy.php';
require_once __DIR__.'/creative-core.php';
function networkTarget(array $cfg,string $provider):?array {
    if(!oauthAllowed($cfg,$provider))return null;
    if(!creativeEnabled($cfg)||($cfg['social']['enabled']??false)!==true||($cfg['social']['network']['enabled']??false)!==true||!in_array($provider,['linkedin','x','google-business'],true))return null;
    $p=$cfg['social']['network'][$provider]??[];
    $id=$p['account_id']??null;$token=$p['access_token']??null;$version=$p['api_version']??'';
    if(($p['enabled']??false)!==true||!is_string($id)||!is_string($token)||!preg_match('/^[A-Za-z0-9._~+\/=-]{20,4096}$/D',$token)||str_starts_with($token,'REPLACE_'))return null;
    if($provider==='google-business'){
        if(!preg_match('/^[1-9][0-9]{0,30}:[1-9][0-9]{0,30}$/D',$id))return null;
    }elseif(!preg_match('/^[1-9][0-9]{0,30}$/D',$id))return null;
    if($provider==='linkedin'&&(!is_string($version)||!preg_match('/^20[2-9][0-9](?:0[1-9]|1[0-2])$/D',$version)))return null;
    return ['provider'=>$provider,'id'=>$id,'token'=>$token,'version'=>$version];
}
function networkSchedule(PDO $db,array $cfg,string $provider,int $id,int $rev,string $target,string $when,?DateTimeImmutable $now=null):int {
    creativeActive($db);
    $p=networkTarget($cfg,$provider);
    if(!$p||$p['id']!==$target)throw new InvalidArgumentException('Destination not configured');
    $utc=new DateTimeZone('UTC');$date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i\Z',$when,$utc);$now=$now??new DateTimeImmutable('now',$utc);
    if(!$date||$date->format('Y-m-d\TH:i\Z')!==$when||$date<$now||$date>$now->modify('+30 days'))throw new InvalidArgumentException('Schedule outside window');
    $q=$db->prepare("SELECT t.id FROM growth_tasks t JOIN growth_creative c ON c.task_id=t.id JOIN growth_creative_results r ON r.task_id=t.id AND r.revision=t.revision WHERE t.id=? AND t.revision=? AND t.status='approved' AND c.channel=? FOR UPDATE");
    $q->execute([$id,$rev,$provider]);if(!$q->fetchColumn())throw new InvalidArgumentException('Approved current matching revision required');
    if(in_array($provider,['linkedin','x'],true)){$q=$db->prepare('SELECT id FROM growth_network_media WHERE task_id=? AND revision=?');$q->execute([$id,$rev]);if($q->fetchColumn()!==false)throw new InvalidArgumentException('Revision reserved for media');}
    if($provider==='linkedin'){$q=$db->prepare('SELECT id FROM growth_linkedin_video WHERE task_id=? AND revision=?');$q->execute([$id,$rev]);if($q->fetchColumn()!==false)throw new InvalidArgumentException('Revision reserved for video');}
    $q=$db->prepare('SELECT id FROM growth_network_publications WHERE task_id=? AND revision=?');$q->execute([$id,$rev]);if($q->fetchColumn()!==false)throw new InvalidArgumentException('Already scheduled');
    $db->prepare('INSERT INTO growth_network_publications(task_id,revision,provider,target_id,due_at) VALUES(?,?,?,?,?)')->execute([$id,$rev,$provider,$target,$date->format('Y-m-d H:i:s')]);
    $pub=(int)$db->lastInsertId();growthAudit($db,$id,$rev,'network_scheduled','owner');return $pub;
}
function networkCommand(PDO $db,array $cfg,string $cmd):?string {
    if(!preg_match('/^\/(?:network|networktarget|networkschedule|networkcancel)(?: |$)/D',$cmd))return null;
    if(!creativeEnabled($cfg)||($cfg['social']['enabled']??false)!==true||($cfg['social']['network']['enabled']??false)!==true)return 'Network publishing disabled.';
    if($cmd==='/networktarget'){
        $lines=['Configured targets (not live authorization):'];
        foreach(['linkedin','x','google-business'] as $provider){$p=networkTarget($cfg,$provider);$lines[]=$provider.': '.($p?$p['id']:'disabled');}
        $lines[]='YouTube and Instagram: secure media transfer pending.';return implode("\n",$lines);
    }
    if($cmd==='/network'){
        $lines=['Network text publication ledger (not reach or delivery metrics):'];
        foreach($db->query('SELECT id,provider,task_id,revision,status,due_at,receipt,error_code FROM growth_network_publications ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC) as $r)$lines[]='#'.$r['id'].' '.$r['provider'].' task '.$r['task_id'].' rev '.$r['revision'].' '.$r['status'].' '.$r['due_at'].' UTC'.($r['receipt']?' receipt '.$r['receipt']:'').($r['error_code']?' '.$r['error_code']:'');
        return count($lines)===1?'No Network publications.':implode("\n",$lines);
    }
    if(preg_match('/^\/networkcancel ([1-9][0-9]{0,14})$/D',$cmd,$m)){
        $q=$db->prepare("SELECT task_id,revision FROM growth_network_publications WHERE id=? AND status='queued' FOR UPDATE");$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!$r)return 'Only queued posts can be cancelled. Reconcile sending/unknown manually.';
        $db->prepare("UPDATE growth_network_publications SET status='cancelled',finished_at=UTC_TIMESTAMP() WHERE id=?")->execute([(int)$m[1]]);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'network_cancelled','owner');return 'Queued Network post cancelled.';
    }
    if(preg_match('/^\/networkschedule (linkedin|x|google-business) ([1-9][0-9]{0,14}) ([1-9][0-9]{0,8}) ([1-9][0-9]{0,30}(?::[1-9][0-9]{0,30})?) (\d{4}-\d{2}-\d{2}T\d{2}:\d{2}Z)$/D',$cmd,$m)){
        try{$id=networkSchedule($db,$cfg,$m[1],(int)$m[2],(int)$m[3],$m[4],$m[5]);return 'Network publication #'.$id.' queued, not sent.';}
        catch(InvalidArgumentException $e){return 'Scheduling refused: current approved revision, matching channel, exact target, future UTC time and no duplicate required.';}
        catch(RuntimeException $e){if($e->getMessage()==='Growth paused')return 'Growth paused.';throw $e;}
    }
    return 'Use /networktarget, /network, /networkschedule PROVIDER TASK REV ACCOUNT_ID YYYY-MM-DDTHH:MMZ or /networkcancel ID.';
}
function networkClaim(PDO $db,array $cfg,string $provider):?array {
    creativeActive($db);$p=networkTarget($cfg,$provider);if(!$p)throw new RuntimeException('Network disabled');
    $q=$db->prepare("SELECT p.id,p.task_id,p.revision,p.provider,p.target_id,r.caption FROM growth_network_publications p JOIN growth_tasks t ON t.id=p.task_id AND t.revision=p.revision AND t.status='approved' JOIN growth_creative c ON c.task_id=t.id AND c.channel=p.provider JOIN growth_creative_results r ON r.task_id=t.id AND r.revision=t.revision WHERE p.status='queued' AND p.provider=? AND p.target_id=? AND p.due_at<=UTC_TIMESTAMP() ORDER BY p.due_at,p.id LIMIT 1 FOR UPDATE");$q->execute([$provider,$p['id']]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return null;
    $db->prepare("UPDATE growth_network_publications SET status='sending',claimed_at=UTC_TIMESTAMP() WHERE id=?")->execute([$r['id']]);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'network_claimed','private_cli');return $r;
}
function networkFinish(PDO $db,array $row,array $result):void {
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $s=$result['status']??null;$r=$result['receipt']??null;$e=$result['error']??null;
    if(!in_array($s,['accepted','failed','unknown'],true)||!is_string($e)||!preg_match('/^[a-z_]{0,32}$/D',$e)||($s==='accepted'&&(!is_string($r)||!preg_match('/^[A-Za-z0-9:_\/-]{1,160}$/D',$r)))||($s!=='accepted'&&$r!==null))throw new InvalidArgumentException('Invalid receipt');
    $q=$db->prepare("UPDATE growth_network_publications SET status=?,receipt=?,error_code=?,finished_at=UTC_TIMESTAMP() WHERE id=? AND status='sending'");$q->execute([$s,$r,$e,$row['id']]);if($q->rowCount()!==1)throw new RuntimeException('Publication state changed');
    growthAudit($db,(int)$row['task_id'],(int)$row['revision'],'network_'.$s,'private_cli');
}
