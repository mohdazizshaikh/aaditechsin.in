<?php
declare(strict_types=1);
require_once __DIR__.'/tender-review-lib.php';
function tenderIntelEnabled(array $cfg):bool{
    return discoveryEnabled($cfg)&&($cfg['discovery']['tender_review_enabled']??false)===true&&($cfg['discovery']['tender_intelligence_enabled']??false)===true;
}
function tenderIntelRank(string $raw,?DateTimeImmutable $now=null):array{
    $scope=tenderShortlist($raw,$now);$rows=[];
    foreach($scope['rows'] as $row){
        $scores=[];
        foreach($row['service_matches'] as $id=>$hits){
            $scores[$id]=min(60,20+10*count($hits));
        }
        arsort($scores,SORT_NUMERIC);
        $location=strtolower($row['location']);
        $region=in_array($location,['mumbai','navi mumbai','thane','mumbai, maharashtra','navi mumbai, maharashtra','thane, maharashtra'],true)?'local_label':(in_array($location,['maharashtra','pune','pune, maharashtra','nagpur','nagpur, maharashtra'],true)?'state_label':'unverified_or_other');
        $row['relevance_scores']=$scores;$row['location_review']=$region;
        $row['review_priority']=$row['state']==='review_candidate'?min(100,($scores?max($scores):0)+($region==='local_label'?20:($region==='state_label'?10:0))+match($row['urgency']){'within_3_days'=>20,'within_7_days'=>10,default=>0}):null;
        $rows[]=$row;
    }
    usort($rows,static fn($a,$b)=>[$a['review_priority']===null,-($a['review_priority']??0),$a['deadline_ist']??'9999',$a['key']]<=>[$b['review_priority']===null,-($b['review_priority']??0),$b['deadline_ist']??'9999',$b['key']]);
    return ['version'=>1,'scope'=>'private-tender-review-priority','review_day_ist'=>$scope['review_day_ist'],'rows'=>$rows,'bid_authorized'=>false,'score_meaning'=>'Keyword match, exact location label and observed deadline urgency only; not qualification, win probability or commercial value.'];
}
function tenderIntelOriginal(array $task,array $row):?array{
    if((int)$task['revision']!==1||!isset(growthCatalog()[$task['service_id']]))return null;
    $prefix="Internal tender eligibility review, not a qualified lead or bid authorization.\nReference: ".$row['reference']."\nOfficial listing: ".$row['source_url']."\n";
    if(!str_starts_with($task['draft'],$prefix))return null;
    $rest=substr($task['draft'],strlen($prefix));
    if(!preg_match('/\AObserved (\d{4}-\d{2}-\d{2}) IST; listed deadline (\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}) IST \(recheck corrigenda\)\.\nScope summary: ([^\n]+)\nLocation: ([^\n]+)\nCheck current official status, full tender\/BOQ, eligibility, partner capacity, OEM authorizations, fees\/EMD, delivery obligations and submission deadline\. Do not send outreach or submit a bid\. Existing tasks are never automatically revised\.\z/D',$rest,$m))return null;
    $tz=new DateTimeZone('Asia/Kolkata');
    tenderTime($m[1],'Y-m-d',$tz);tenderTime($m[2],'Y-m-d\TH:i:s',$tz);
    return ['observed_on'=>$m[1],'deadline_ist'=>$m[2],'title'=>$m[3],'location'=>$m[4]];
}
function tenderIntelChanges(array $old,array $row,DateTimeImmutable $now):array{
    $now=$now->setTimezone(new DateTimeZone('Asia/Kolkata'));
    if($row['observed_on']<$old['observed_on']||$row['observed_on']<$now->setTime(0,0)->modify('-1 day')->format('Y-m-d')||$row['source_state']==='unconfirmed')return [];
    if(in_array($row['source_state'],['cancelled','archived'],true))return [$row['source_state']];
    if($row['source_state']!=='listed')return [];
    $changes=[];
    foreach(['deadline_ist','title','location'] as $key)if($old[$key]!==$row[$key])$changes[]=$key.'_changed';
    if($row['deadline_ist']===null)$changes[]='deadline_unknown';
    return $changes;
}
function tenderIntelPlan(PDO $db,array $cfg,string $raw,bool $lock=false,?DateTimeImmutable $now=null):array{
    if(!$db->inTransaction()||!tenderIntelEnabled($cfg))throw new RuntimeException('Tender intelligence disabled');
    $now=$now??new DateTimeImmutable('now');$scope=tenderIntelRank($raw,$now);
    $suffix=$lock?' FOR UPDATE':'';
    if((string)$db->query('SELECT paused FROM growth_control WHERE id=1'.$suffix)->fetchColumn()!=='0')throw new RuntimeException('Growth paused');
    $find=$db->prepare("SELECT id,service_id,status,revision,draft FROM growth_tasks WHERE task_type='research' AND title=? ORDER BY id LIMIT 2".$suffix);
    $audit=$db->prepare("SELECT COUNT(*) FROM growth_audit WHERE task_id=? AND revision=1 AND action='tender_review_created'");
    $changeAudit=$db->prepare("SELECT COUNT(*) FROM growth_audit WHERE task_id=? AND revision=1 AND action='tender_change_review_created'");
    $items=[];$unverified=0;
    foreach($scope['rows'] as $row){
        $find->execute(['Tender review: '.$row['key']]);$found=$find->fetchAll(PDO::FETCH_ASSOC);
        if(count($found)>1)throw new RuntimeException('Ambiguous original task');
        if(!$found)continue;$task=$found[0];
        $audit->execute([$task['id']]);
        if((int)$audit->fetchColumn()!==1||!($old=tenderIntelOriginal($task,$row))){++$unverified;continue;}
        $changes=tenderIntelChanges($old,$row,$now);if(!$changes)continue;
        // Identity binds material facts, not the day of re-observation.
        $fingerprint=hash('sha256',json_encode([$row['key'],(int)$task['id'],$old,$row['source_state'],$row['deadline_ist'],$row['title'],$row['location'],$changes],JSON_THROW_ON_ERROR));
        $title='Tender change: '.$row['key'].' '.substr($fingerprint,0,24);
        $find->execute([$title]);$existing=$find->fetchAll(PDO::FETCH_ASSOC);
        if(count($existing)>1||($existing&&$existing[0]['service_id']!==$task['service_id']))throw new RuntimeException('Ambiguous change task');
        if($existing){$changeAudit->execute([$existing[0]['id']]);if((int)$changeAudit->fetchColumn()!==1)throw new RuntimeException('Unverified change task');}
        $items[]=['key'=>$row['key'],'original_id'=>(int)$task['id'],'original_revision'=>(int)$task['revision'],'original_status'=>$task['status'],'original_sha256'=>hash('sha256',$task['draft']),'service_id'=>$task['service_id'],'changes'=>$changes,'fingerprint'=>$fingerprint,'title'=>$title,'existing'=>$existing?[ 'id'=>(int)$existing[0]['id'],'revision'=>(int)$existing[0]['revision'],'status'=>$existing[0]['status'],'draft_sha256'=>hash('sha256',$existing[0]['draft'])]:null,'operation'=>$existing?'preserve':'create'];
    }
    $plan=['scope'=>$scope,'items'=>$items,'unverified_originals'=>$unverified];
    $plan['digest']=hash('sha256',json_encode($plan,JSON_THROW_ON_ERROR));return $plan;
}
function tenderIntelApply(PDO $db,array $cfg,string $raw,string $digest,?DateTimeImmutable $now=null):array{
    if(!$db->inTransaction()||!preg_match('/^[a-f0-9]{64}$/D',$digest))throw new InvalidArgumentException('Reviewed digest required');
    $db->exec('SAVEPOINT tender_intel_batch');
    try{
        $p=tenderIntelPlan($db,$cfg,$raw,true,$now);if(!hash_equals($digest,$p['digest']))throw new RuntimeException('Change plan drift');
        $rows=array_column($p['scope']['rows'],null,'key');$ids=[];
        foreach($p['items'] as $item){
            if($item['operation']!=='create')continue;$r=$rows[$item['key']];
            $draft="Internal tender amendment review; not bid authorization.\nOriginal task: ".$item['original_id']."\nReference: ".$r['reference']."\nOfficial listing: ".$r['source_url']."\nObserved ".$r['observed_on']." IST; source state ".$r['source_state'].".\nCurrent listed deadline: ".($r['deadline_ist']??'Unknown')." IST.\nChanges relative to original recorded evidence: ".implode(', ',$item['changes']).".\nRecheck issuer documents and corrigenda; verify actual current status, eligibility and deadline. Original task preserved. Do not submit bids, send outreach or infer cancellation from missing data.";
            $id=growthCreate($db,['service_id'=>$item['service_id'],'task_type'=>'research','title'=>$item['title'],'draft'=>$draft]);growthAudit($db,$id,1,'tender_change_review_created','private_cli');$ids[]=$id;
        }
        $db->exec('RELEASE SAVEPOINT tender_intel_batch');
        return ['created'=>count($ids),'preserved'=>count($p['items'])-count($ids),'unverified_originals'=>$p['unverified_originals'],'task_ids'=>$ids,'bid_authorized'=>false];
    }catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT tender_intel_batch');$db->exec('RELEASE SAVEPOINT tender_intel_batch');throw $e;}
}
