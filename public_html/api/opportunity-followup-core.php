<?php
declare(strict_types=1);
require_once __DIR__.'/discovery-core.php';
require_once __DIR__.'/creative-core.php';
function followEnabled(array $cfg):bool{return discoveryEnabled($cfg)&&($cfg['discovery']['followups_enabled']??false)===true;}
function followDate(string $date,?DateTimeImmutable $now=null):bool{
    $utc=new DateTimeZone('UTC');$day=($now??new DateTimeImmutable('now'))->setTimezone($utc)->setTime(0,0);$d=DateTimeImmutable::createFromFormat('!Y-m-d',$date,$utc);
    return $d!==false&&$d->format('Y-m-d')===$date&&$d>=$day&&$d<=$day->modify('+90 days');
}
function followSummary(PDO $db,?DateTimeImmutable $now=null):string{
    $day=($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
    $q=$db->prepare("SELECT COUNT(*) FROM growth_opportunity_followups WHERE status='open' AND due_on<=?");$q->execute([$day]);$due=(int)$q->fetchColumn();
    $open=(int)$db->query("SELECT COUNT(*) FROM growth_opportunity_followups WHERE status='open'")->fetchColumn();
    return 'Opportunity review reminders: '.$due.' due/overdue, '.$open.' open. Internal review only; no outreach sent.';
}
function followCommand(PDO $db,array $cfg,string $cmd):?string{
    if(!preg_match('/^\/(?:followups|followup|completefollowup|cancelfollowup)(?: |$)/D',$cmd))return null;
    if(!followEnabled($cfg))return 'Opportunity reminders disabled.';
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    if($cmd==='/followups'){
        $lines=[followSummary($db)];
        foreach($db->query("SELECT f.opportunity_id,f.due_on,o.service_id FROM growth_opportunity_followups f JOIN growth_opportunities o ON o.id=f.opportunity_id WHERE f.status='open' ORDER BY f.due_on,f.opportunity_id LIMIT 10")->fetchAll(PDO::FETCH_ASSOC) as $r)$lines[]='#'.$r['opportunity_id'].' '.$r['due_on'].' UTC '.$r['service_id'];
        return implode("\n",$lines);
    }
    if(preg_match('/^\/followup ([1-9][0-9]{0,14}) (\d{4}-\d{2}-\d{2})$/D',$cmd,$m)){
        if(!followDate($m[2]))return 'Choose a UTC date within 90 days.';
        try{creativeActive($db);}catch(RuntimeException $e){return 'Growth paused.';}
        $q=$db->prepare("SELECT status FROM growth_opportunities WHERE id=? FOR UPDATE");$q->execute([(int)$m[1]]);if($q->fetchColumn()!=='qualified')return 'Qualified opportunity required.';
        followAtomic($db,static function()use($db,$m):void{
            $db->prepare("INSERT INTO growth_opportunity_followups(opportunity_id,due_on,status) VALUES(?,?,'open') ON DUPLICATE KEY UPDATE due_on=VALUES(due_on),status='open'")->execute([(int)$m[1],$m[2]]);
            growthAudit($db,null,null,'followup_set_'.$m[1],'owner');
        });return 'Internal review reminder set. No outreach sent.';
    }
    if(preg_match('/^\/(completefollowup|cancelfollowup) ([1-9][0-9]{0,14})$/D',$cmd,$m)){
        $state=$m[1]==='completefollowup'?'completed':'cancelled';
        $closed=followAtomic($db,static function()use($db,$m,$state):bool{
            $q=$db->prepare("UPDATE growth_opportunity_followups SET status=? WHERE opportunity_id=? AND status='open'");$q->execute([$state,(int)$m[2]]);if($q->rowCount()!==1)return false;
            growthAudit($db,null,null,'followup_'.$state.'_'.$m[2],'owner');return true;
        });
        return $closed?'Internal reminder '.$state.'. No outreach sent.':'No open reminder.';
    }
    return 'Use /followups, /followup ID YYYY-MM-DD, /completefollowup ID or /cancelfollowup ID.';
}

function followAtomic(PDO $db,callable $work):mixed{
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $db->exec('SAVEPOINT followup_action');
    try{$result=$work();$db->exec('RELEASE SAVEPOINT followup_action');return $result;}
    catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT followup_action');$db->exec('RELEASE SAVEPOINT followup_action');throw $e;}
}
