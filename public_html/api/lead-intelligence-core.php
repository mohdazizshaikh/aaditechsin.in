<?php
declare(strict_types=1);
require_once __DIR__.'/inquiry-queue-core.php';
function leadIntelEnabled(array $cfg):bool{
    return inquiryQueueEnabled($cfg)&&($cfg['growth']['enabled']??false)===true&&($cfg['leads']['intelligence_enabled']??false)===true;
}
function leadIntelDate(string $day,?DateTimeImmutable $now=null):bool{
    $utc=new DateTimeZone('UTC');$today=($now??new DateTimeImmutable('now'))->setTimezone($utc)->setTime(0,0);
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$day,$utc);
    return $d!==false&&$d->format('Y-m-d')===$day&&$d>=$today&&$d<=$today->modify('+90 days');
}
function leadIntelService(string $value):?string{
    foreach(growthCatalog() as $id=>$service)if($value===$id||$value===$service['title'])return $id;
    return null;
}
function leadIntelReasons():array{return ['scope'=>'Clarify service scope and requirements','assessment'=>'Review assessment or proposal next step','response'=>'Review previously recorded response','customer-request'=>'Review customer-requested follow-up'];}
function leadIntelLock(PDO $db,array $cfg,bool $active=true):void{
    if(!leadIntelEnabled($cfg)||!$db->inTransaction())throw new RuntimeException('Lead intelligence unavailable');
    $p=$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn();
    if(!in_array((string)$p,['0','1'],true)||($active&&(string)$p!=='0'))throw new RuntimeException('Growth paused or unavailable');
}
function leadIntelLead(PDO $db,int $id,string $status,string $updated):?array{
    if($id<1||!in_array($status,['new','qualified','responded'],true)||!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$updated))throw new InvalidArgumentException('Expected lead state required');
    $q=$db->prepare('SELECT id,service,status,updated_at,consent_at FROM leads WHERE id=? FOR UPDATE');$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
    return $r&&$r['status']===$status&&$r['updated_at']===$updated?$r:null;
}
function leadIntelAtomic(PDO $db,callable $fn):mixed{
    $db->exec('SAVEPOINT lead_intelligence_action');
    try{$r=$fn();$db->exec('RELEASE SAVEPOINT lead_intelligence_action');return $r;}
    catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT lead_intelligence_action');$db->exec('RELEASE SAVEPOINT lead_intelligence_action');throw $e;}
}
function leadIntelQualify(PDO $db,array $cfg,int $id,string $status,string $updated,string $confirmed):bool{
    if($status!=='new'||$confirmed!=='yes')throw new InvalidArgumentException('Explicit qualification review required');
    leadIntelLock($db,$cfg);
    return leadIntelAtomic($db,static function()use($db,$id,$status,$updated):bool{
        $r=leadIntelLead($db,$id,$status,$updated);if(!$r||!leadIntelService($r['service']))return false;
        $q=$db->prepare("UPDATE leads SET status='qualified' WHERE id=? AND status='new' AND updated_at=?");$q->execute([$id,$updated]);
        if($q->rowCount()!==1)return false;
        growthAudit($db,null,null,'lead_qualified_'.$id,'owner');return true;
    });
}
function leadIntelReminder(PDO $db,array $cfg,int $id,string $status,string $updated,string $day,string $reason,string $confirmed,?DateTimeImmutable $now=null):array{
    if(!leadIntelDate($day,$now)||!isset(leadIntelReasons()[$reason])||$confirmed!=='yes')throw new InvalidArgumentException('Reviewed reminder required');
    leadIntelLock($db,$cfg);
    return leadIntelAtomic($db,static function()use($db,$id,$status,$updated,$day,$reason):array{
        $r=leadIntelLead($db,$id,$status,$updated);if(!$r||!($service=leadIntelService($r['service'])))return ['status'=>'changed_or_unmapped'];
        $prefix='Lead follow-up: '.$id.' ';
        $q=$db->prepare("SELECT id,status,revision,title,service_id FROM growth_tasks WHERE task_type='system' AND title LIKE ? AND status IN ('pending_review','regeneration_requested') ORDER BY id LIMIT 2 FOR UPDATE");$q->execute([$prefix.'%']);$open=$q->fetchAll(PDO::FETCH_ASSOC);
        if(count($open)>1)throw new RuntimeException('Ambiguous open reminder');
        if($open)return ['status'=>'existing_open'];
        $title=$prefix.$day.' '.$reason;
        $q=$db->prepare("SELECT id FROM growth_tasks WHERE task_type='system' AND title=? LIMIT 2 FOR UPDATE");$q->execute([$title]);if($q->fetchColumn()!==false)return ['status'=>'existing_history'];
        $draft="Internal inquiry follow-up review only.\nDue UTC: ".$day."\nReason: ".leadIntelReasons()[$reason].".\nOpen the authorized inquiry portal and independently confirm current status, requested scope and consent. No automatic contact, pricing, case creation or outreach.";
        $task=growthCreate($db,['service_id'=>$service,'task_type'=>'system','title'=>$title,'draft'=>$draft]);
        growthAudit($db,$task,1,'lead_followup_created','owner');
        return ['status'=>'created','task_id'=>$task];
    });
}
function leadIntelClose(PDO $db,array $cfg,int $task,int $revision,string $decision,string $confirmed):bool{
    if($task<1||$revision<1||!in_array($decision,['reviewed','cancelled'],true)||$confirmed!=='yes')throw new InvalidArgumentException('Explicit reminder disposition required');
    leadIntelLock($db,$cfg,false);
    return leadIntelAtomic($db,static function()use($db,$task,$revision,$decision):bool{
        $q=$db->prepare("SELECT title,status,revision FROM growth_tasks WHERE id=? AND task_type='system' FOR UPDATE");$q->execute([$task]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!$r||$r['status']!=='pending_review'||(int)$r['revision']!==$revision||!preg_match('/^Lead follow-up: [1-9][0-9]{0,14} \d{4}-\d{2}-\d{2} (?:scope|assessment|response|customer-request)$/D',$r['title']))return false;
        $q=$db->prepare("SELECT COUNT(*) FROM growth_audit WHERE task_id=? AND revision=1 AND action='lead_followup_created'");$q->execute([$task]);if((int)$q->fetchColumn()!==1)return false;
        $q=$db->prepare("UPDATE growth_tasks SET status=? WHERE id=? AND status='pending_review' AND revision=?");$q->execute([$decision==='reviewed'?'approved':'rejected',$task,$revision]);if($q->rowCount()!==1)return false;
        growthAudit($db,$task,$revision,'lead_followup_'.$decision,'owner');return true;
    });
}
function leadIntelSnapshot(PDO $db,array $cfg,?DateTimeImmutable $now=null,bool $details=false):array{
    if(!leadIntelEnabled($cfg))throw new RuntimeException('Lead intelligence unavailable');
    $day=($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
    // Exact reserved title and audit bind the reminder to the existing inquiry; no contact fields in report.
    $rows=$db->query("SELECT t.id,t.revision,t.title,t.status,l.id lead_id,l.status lead_status,l.service FROM growth_tasks t JOIN leads l ON t.title LIKE CONCAT('Lead follow-up: ',l.id,' %') WHERE t.task_type='system' AND t.status IN ('pending_review','regeneration_requested') AND EXISTS(SELECT 1 FROM growth_audit a WHERE a.task_id=t.id AND a.revision=1 AND a.action='lead_followup_created') ORDER BY t.id DESC LIMIT 251")->fetchAll(PDO::FETCH_ASSOC);
    $truncated=count($rows)>250;$rows=array_slice($rows,0,250);
    $counts=['open'=>0,'due'=>0,'inactive_lead'=>0,'unverified'=>0];$out=[];
    foreach($rows as $r){
        if(!preg_match('/^Lead follow-up: ([1-9][0-9]{0,14}) (\d{4}-\d{2}-\d{2}) (scope|assessment|response|customer-request)$/D',$r['title'],$m)||$m[1]!== (string)$r['lead_id']||$r['status']!=='pending_review'||(int)$r['revision']!==1){++$counts['unverified'];continue;}
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$m[2],new DateTimeZone('UTC'));
        if(!$date||$date->format('Y-m-d')!==$m[2]){++$counts['unverified'];continue;}
        if(!in_array($r['lead_status'],['new','qualified','responded'],true)){++$counts['inactive_lead'];}
        else{++$counts['open'];if($m[2]<=$day)++$counts['due'];}
        if($details)$out[]=['task_id'=>(int)$r['id'],'revision'=>(int)$r['revision'],'lead_id'=>(int)$r['lead_id'],'service'=>$r['service'],'lead_status'=>$r['lead_status'],'day'=>$m[2],'reason'=>$m[3]];
    }
    usort($out,static fn($a,$b)=>[$a['day'],$a['task_id']]<=>[$b['day'],$b['task_id']]);
    return ['counts'=>$counts,'rows'=>$out,'truncated'=>$truncated,'contact_authorized'=>false];
}
function leadIntelSummary(PDO $db,array $cfg,?DateTimeImmutable $now=null):string{
    $r=leadIntelSnapshot($db,$cfg,$now);$c=$r['counts'];
    return 'Inquiry follow-up review: '.$c['due'].' due, '.$c['open'].' active open, '.$c['inactive_lead'].' inactive inquiry, '.$c['unverified'].' require manual reconciliation.'.($r['truncated']?' Latest 250 only.':'').' Internal review only; no contact sent.';
}
