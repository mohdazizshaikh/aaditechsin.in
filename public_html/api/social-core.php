<?php
declare(strict_types=1);
require_once __DIR__.'/creative-core.php';
function socialTarget(array $cfg):?string {
    if(!creativeEnabled($cfg)||($cfg['social']['enabled']??false)!==true||($cfg['social']['telegram_channel']['enabled']??false)!==true)return null;
    $target=$cfg['social']['telegram_channel']['chat_id']??null;
    $token=$cfg['social']['telegram_channel']['bot_token']??null;
    if(!is_string($target)||!preg_match('/^-100[0-9]{6,15}$/D',$target)||!is_string($token)||!preg_match('/^[0-9]{5,16}:[A-Za-z0-9_-]{20,128}$/D',$token))return null;
    $ownerToken=$cfg['growth']['telegram']['bot_token']??'';
    if(is_string($ownerToken)&&$ownerToken!==''&&hash_equals($ownerToken,$token))return null;
    return $target;
}
function socialSchedule(PDO $db,array $cfg,int $task,int $rev,string $target,string $when,?DateTimeImmutable $now=null):int {
    creativeActive($db);
    if(socialTarget($cfg)!==$target)throw new InvalidArgumentException('Destination not authorized');
    $utc=new DateTimeZone('UTC');
    $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i\Z',$when,$utc);
    $now=$now??new DateTimeImmutable('now',$utc);
    if(!$date||$date->format('Y-m-d\TH:i\Z')!==$when||$date<$now||$date>$now->modify('+30 days'))throw new InvalidArgumentException('Schedule outside window');
    $q=$db->prepare("SELECT c.channel,t.status,t.revision,r.caption FROM growth_tasks t JOIN growth_creative c ON c.task_id=t.id JOIN growth_creative_results r ON r.task_id=t.id AND r.revision=t.revision WHERE t.id=? FOR UPDATE");$q->execute([$task]);$r=$q->fetch(PDO::FETCH_ASSOC);
    if(!$r||$r['channel']!=='telegram'||$r['status']!=='approved'||(int)$r['revision']!==$rev)throw new InvalidArgumentException('Approved Telegram revision required');
    $q=$db->prepare('SELECT id FROM growth_publications WHERE task_id=? AND revision=?');$q->execute([$task,$rev]);
    if($q->fetchColumn()!==false)throw new InvalidArgumentException('Publication already recorded');
    $q=$db->prepare('INSERT INTO growth_publications(task_id,revision,target_chat_id,due_at) VALUES(?,?,?,?)');
    $q->execute([$task,$rev,$target,$date->format('Y-m-d H:i:s')]);
    $id=(int)$db->lastInsertId();growthAudit($db,$task,$rev,'publication_scheduled','owner');return $id;
}
function socialCommand(PDO $db,array $cfg,string $cmd):?string {
    if(!preg_match('/^\/(?:social|socialtarget|schedulepost|cancelpost)(?: |$)/',$cmd))return null;
    $target=socialTarget($cfg);
    if($target===null)return 'Telegram business publishing disabled or unconfigured. Other providers not connected.';
    if($cmd==='/socialtarget')return 'Configured Telegram channel ID: '.$target."\nVerify this exact channel before scheduling. No other destination allowed.";
    if($cmd==='/social'){
        $lines=['Telegram text publications (latest 10, not reach metrics):'];
        foreach($db->query('SELECT id,task_id,revision,status,due_at,provider_message_id,error_code FROM growth_publications ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC) as $r)
            $lines[]='#'.$r['id'].' task '.$r['task_id'].' rev '.$r['revision'].' '.$r['status'].' '.$r['due_at'].' UTC'.($r['provider_message_id']?' receipt '.$r['provider_message_id']:'').($r['error_code']?' '.$r['error_code']:'');
        return count($lines)===1?'No publication recorded.':implode("\n",$lines);
    }
    if(preg_match('/^\/cancelpost ([1-9][0-9]{0,14})$/D',$cmd,$m)){
        $q=$db->prepare("SELECT task_id,revision FROM growth_publications WHERE id=? AND status='queued' FOR UPDATE");$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!$r)return 'Only queued posts can be cancelled. Check /social.';
        $db->prepare("UPDATE growth_publications SET status='cancelled',finished_at=UTC_TIMESTAMP() WHERE id=?")->execute([(int)$m[1]]);
        growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'publication_cancelled','owner');return 'Queued post cancelled.';
    }
    if(preg_match('/^\/schedulepost ([1-9][0-9]{0,14}) ([1-9][0-9]{0,8}) (-100[0-9]{6,15}) (\d{4}-\d{2}-\d{2}T\d{2}:\d{2}Z)$/D',$cmd,$m)){
        try{$id=socialSchedule($db,$cfg,(int)$m[1],(int)$m[2],$m[3],$m[4]);return 'Publication #'.$id.' queued, not sent. UTC: '.$m[4].' / channel '.$m[3];}
        catch(InvalidArgumentException $e){return 'Scheduling refused: approved current Telegram revision, exact configured channel and future UTC time within 30 days required; no duplicate schedule.';}
        catch(RuntimeException $e){if($e->getMessage()==='Growth paused')return 'Growth paused.';throw $e;}
    }
    return 'Use /socialtarget then /schedulepost TASK REV CHANNEL_ID YYYY-MM-DDTHH:MMZ, /social or /cancelpost ID.';
}
function socialClaim(PDO $db,array $cfg):?array {
    $target=socialTarget($cfg);if($target===null)throw new RuntimeException('Publishing disabled');
    creativeActive($db);
    $q=$db->prepare("SELECT p.id,p.task_id,p.revision,p.target_chat_id,r.caption FROM growth_publications p JOIN growth_tasks t ON t.id=p.task_id AND t.revision=p.revision AND t.status='approved' JOIN growth_creative c ON c.task_id=t.id AND c.channel='telegram' JOIN growth_creative_results r ON r.task_id=t.id AND r.revision=t.revision WHERE p.status='queued' AND p.target_chat_id=? AND p.due_at<=UTC_TIMESTAMP() ORDER BY p.due_at,p.id LIMIT 1 FOR UPDATE");
    $q->execute([$target]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return null;
    $db->prepare("UPDATE growth_publications SET status='sending',claimed_at=UTC_TIMESTAMP() WHERE id=?")->execute([$r['id']]);
    growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'publication_claimed','private_cli');return $r;
}
function socialFinish(PDO $db,array $row,array $result):void {
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $status=$result['status']??null;$receipt=$result['receipt']??null;$error=$result['error']??'';
    if(!in_array($status,['accepted','failed','unknown'],true)||!is_string($error)||!preg_match('/^[a-z_]{0,32}$/D',$error)||($status==='accepted'&&(!is_string($receipt)||!preg_match('/^[1-9][0-9]{0,20}$/D',$receipt)))||($status!=='accepted'&&$receipt!==null))throw new InvalidArgumentException('Invalid receipt');
    $q=$db->prepare("UPDATE growth_publications SET status=?,provider_message_id=?,error_code=?,finished_at=UTC_TIMESTAMP() WHERE id=? AND status='sending'");$q->execute([$status,$receipt,$error,$row['id']]);
    if($q->rowCount()!==1)throw new RuntimeException('Publication state changed');
    growthAudit($db,(int)$row['task_id'],(int)$row['revision'],'publication_'.$status,'private_cli');
}
