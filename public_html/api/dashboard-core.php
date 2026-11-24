<?php
declare(strict_types=1);
require_once __DIR__.'/operations.php';
require_once __DIR__.'/intelligence-core.php';
require_once __DIR__.'/publication-overview.php';
function dashboardEnabled(array $cfg):bool{
    return ($cfg['portal']['enabled']??false)===true&&($cfg['growth']['enabled']??false)===true&&($cfg['intelligence_dashboard']['enabled']??false)===true;
}
function dashboardOwner(array $cfg,array $session,int $now):bool{
    return dashboardEnabled($cfg)&&($session['role']??null)==='admin'&&is_int($session['last_seen']??null)&&$session['last_seen']<=$now&&$now-$session['last_seen']<=1800&&is_string($session['admin_fingerprint']??null)&&hash_equals(opsAdminSessionFingerprint($cfg),$session['admin_fingerprint']);
}
function dashboardDays(mixed $days):int{
    if(!is_string($days)||!in_array($days,['7','30'],true))throw new InvalidArgumentException('Invalid period');
    return (int)$days;
}
function dashboardPeriod(int $days,?DateTimeImmutable $now=null):array{
    if(!in_array($days,[7,30],true))throw new InvalidArgumentException('Invalid period');
    $utc=new DateTimeZone('UTC');$end=($now??new DateTimeImmutable('now',$utc))->setTimezone($utc)->setTime(0,0);
    return ['start'=>$end->modify('-'.$days.' days')->format('Y-m-d'),'end'=>$end->format('Y-m-d'),'days'=>$days];
}
function dashboardCounts(PDO $db,string $sql,array $params,array $keys):array{
    $out=array_fill_keys($keys,0);$q=$db->prepare($sql);$q->execute($params);
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){if(array_key_exists($r['status'],$out))$out[$r['status']]=(int)$r['n'];}
    return $out;
}
function dashboardCommunications(PDO $db,array $period):array{
    $channels=['email','whatsapp','sms','calls'];$out=[];
    foreach($channels as $channel)$out[$channel]=['days_reported'=>0,'received'=>null,'answered'=>null,'missed'=>null,'daily'=>[]];
    $q=$db->prepare('SELECT channel,day_utc,received_count,answered_count,missed_count FROM growth_communication_daily WHERE day_utc>=? AND day_utc<? ORDER BY day_utc,channel');$q->execute([$period['start'],$period['end']]);
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
        $c=$r['channel'];if(!isset($out[$c]))continue;$out[$c]['days_reported']++;
        $row=[];foreach(['received','answered','missed'] as $k){$n=(int)$r[$k.'_count'];$row[$k]=$n;$out[$c][$k]=($out[$c][$k]??0)+$n;}
        $out[$c]['daily'][$r['day_utc']]=$row;
    }
    return $out;
}
function dashboardSnapshot(PDO $db,array $cfg,int $days,?DateTimeImmutable $now=null):array{
    if(!dashboardEnabled($cfg))throw new RuntimeException('Disabled');
    $now=$now??new DateTimeImmutable('now',new DateTimeZone('UTC'));$period=dashboardPeriod($days,$now);
    $params=[$period['start'].' 00:00:00',$period['end'].' 00:00:00'];
    $data=['period'=>$period,'captured'=>$now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s').' UTC'];
    $paused=$db->query('SELECT paused FROM growth_control WHERE id=1')->fetchColumn();
    $data['growth']=$paused===false?'unavailable':((int)$paused===1?'paused':'resumed');
    $data['leads']=dashboardCounts($db,'SELECT status,COUNT(*) AS n FROM leads WHERE created_at>=? AND created_at<? GROUP BY status',$params,['new','qualified','responded','converted','closed']);
    $data['tasks']=dashboardCounts($db,'SELECT status,COUNT(*) AS n FROM growth_tasks GROUP BY status',[],['pending_review','regeneration_requested','approved','rejected']);
    $data['reviews']=$db->query("SELECT id,revision,service_id,task_type,status FROM growth_tasks WHERE status IN ('pending_review','regeneration_requested') ORDER BY id LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    $data['opportunities']=null;$data['services']=[];$data['followups']=null;$data['due']=[];
    if(discoveryEnabled($cfg)){
        $data['opportunities']=dashboardCounts($db,'SELECT status,COUNT(*) AS n FROM growth_opportunities GROUP BY status',[],['pending_review','qualified','rejected']);
        $catalog=growthCatalog();
        foreach($db->query('SELECT service_id,status,COUNT(*) AS n FROM growth_opportunities GROUP BY service_id,status ORDER BY service_id,status')->fetchAll(PDO::FETCH_ASSOC) as $r){
            if(!isset($catalog[$r['service_id']]))continue;
            $k=$r['service_id'];if(!isset($data['services'][$k]))$data['services'][$k]=['title'=>$catalog[$k]['title'],'pending_review'=>0,'qualified'=>0,'rejected'=>0];
            $data['services'][$k][$r['status']]=(int)$r['n'];
        }
        if(followEnabled($cfg)){
            $data['followups']=followSummary($db,$now);
            $day=$now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
            $q=$db->prepare("SELECT f.opportunity_id,f.due_on,o.service_id FROM growth_opportunity_followups f JOIN growth_opportunities o ON o.id=f.opportunity_id WHERE f.status='open' AND f.due_on<=? ORDER BY f.due_on,f.opportunity_id LIMIT 20");$q->execute([$day]);$data['due']=$q->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    $data['communications']=communicationsEnabled($cfg)?dashboardCommunications($db,$period):null;
    $data['gmail']=communicationsEnabled($cfg)&&($cfg['communications']['gmail']['enabled']??false)===true?gmailSummary($db,$cfg,$now):null;
    $data['publications']=creativeEnabled($cfg)&&($cfg['social']['enabled']??false)===true?publicationOverview($db,$cfg,'/publications'):null;
    $data['attention']=($data['publications']!==null)?reportPublicationAttention($db):null;
    $data['connections']=($cfg['oauth_lifecycle']['enabled']??false)===true?oauthStatus($db,$cfg,$now->getTimestamp()):null;
    return $data;
}
