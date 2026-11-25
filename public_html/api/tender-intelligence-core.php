<?php
declare(strict_types=1);
// Stored review evidence only. No issuer request or automatic change to original tasks.
function tenderDeadlineBucket(string $deadline,DateTimeImmutable $now):?string{
    $tz=new DateTimeZone('Asia/Kolkata');$now=$now->setTimezone($tz);
    $d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s',$deadline,$tz);
    if(!$d||$d->format('Y-m-d\TH:i:s')!==$deadline)return null;
    return $d<=$now?'recorded_deadline_elapsed':($d<=$now->modify('+3 days')?'within_3_days':($d<=$now->modify('+7 days')?'within_7_days':'later'));
}
function tenderDeadlineReport(PDO $db,?DateTimeImmutable $now=null):array{
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $now=($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('Asia/Kolkata'));
    $rows=$db->query("SELECT t.id,t.title,t.service_id,t.draft,t.revision FROM growth_tasks t WHERE t.task_type='research' AND t.status='pending_review' AND t.title LIKE 'Tender review: %' AND EXISTS (SELECT 1 FROM growth_audit a WHERE a.task_id=t.id AND a.revision=1 AND a.action='tender_review_created') ORDER BY t.id DESC LIMIT 251")->fetchAll(PDO::FETCH_ASSOC);
    $truncated=count($rows)>250;$rows=array_slice($rows,0,250);$out=[];$counts=['recorded_deadline_elapsed'=>0,'within_3_days'=>0,'within_7_days'=>0,'later'=>0,'unverified'=>0];
    foreach($rows as $r){
        if((int)$r['revision']!==1||!preg_match('/\AInternal tender eligibility review, not a qualified lead or bid authorization\.\nReference: [^\n]+\nOfficial listing: [^\n]+\nObserved (\d{4}-\d{2}-\d{2}) IST; listed deadline (\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}) IST \(recheck corrigenda\)\.\n/',$r['draft'],$m)||!($bucket=tenderDeadlineBucket($m[2],$now))){++$counts['unverified'];continue;}
        $observed=DateTimeImmutable::createFromFormat('!Y-m-d',$m[1],$now->getTimezone());
        if(!$observed||$observed->format('Y-m-d')!==$m[1]||$observed>$now){++$counts['unverified'];continue;}
        ++$counts[$bucket];$out[]=['id'=>(int)$r['id'],'service_id'=>$r['service_id'],'title'=>$r['title'],'deadline_ist'=>$m[2],'bucket'=>$bucket,'stale_observation'=>$observed<$now->setTime(0,0)->modify('-1 day')];
    }
    usort($out,static fn($a,$b)=>[$a['deadline_ist'],$a['id']]<=>[$b['deadline_ist'],$b['id']]);
    return ['rows'=>$out,'counts'=>$counts,'truncated'=>$truncated,'stored_evidence_only'=>true,'bid_authorized'=>false];
}
function tenderIntelligenceCommand(PDO $db,array $cfg,string $cmd):?string{
    if(!in_array($cmd,['/tenderdeadlines','/tenderchanges'],true))return null;
    if(($cfg['growth']['enabled']??false)!==true||($cfg['discovery']['enabled']??false)!==true||($cfg['discovery']['tender_review_enabled']??false)!==true||($cfg['discovery']['tender_intelligence_enabled']??false)!==true)return 'Tender intelligence disabled.';
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    if($cmd==='/tenderchanges'){
        $lines=['Pending tender amendment reviews (up to 10; source assertions only):'];
        foreach($db->query("SELECT t.id,t.title,t.service_id FROM growth_tasks t WHERE t.task_type='research' AND t.status='pending_review' AND t.title LIKE 'Tender change: %' AND EXISTS (SELECT 1 FROM growth_audit a WHERE a.task_id=t.id AND a.revision=1 AND a.action='tender_change_review_created') ORDER BY t.id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC) as $r)$lines[]=$r['title'].' | '.$r['service_id'].' — /task '.$r['id'];
        $lines[]='Verify issuer amendments before acting. Original tasks are unchanged.';return implode("\n",$lines);
    }
    $r=tenderDeadlineReport($db);$lines=['Recorded tender deadlines, IST (not live bid status):'];
    foreach($r['counts'] as $k=>$v)$lines[]=$k.': '.$v;
    if($r['truncated'])$lines[]='Counts limited to latest 250 pending original tasks.';
    foreach(array_slice($r['rows'],0,10) as $v)$lines[]=$v['deadline_ist'].' | '.$v['bucket'].($v['stale_observation']?' | stale evidence':'').' — /task '.$v['id'];
    $lines[]='Recheck current issuer notice and corrigenda. See /tenderchanges. No bid authorized.';return implode("\n",$lines);
}
