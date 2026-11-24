<?php
declare(strict_types=1);
require_once __DIR__.'/operations.php';
require_once __DIR__.'/intelligence-core.php';
require_once __DIR__.'/publication-overview.php';
require_once __DIR__.'/analytics-core.php';
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
    $data['web_analytics']=analyticsEnabled($cfg)?dashboardWebAnalytics($db,$days,$now):null;
    return $data;
}

function dashboardWebAnalytics(PDO $db,int $days,DateTimeImmutable $now):array{
    if(!in_array($days,[7,30],true))throw new InvalidArgumentException('Period');
    $end=$now->setTimezone(new DateTimeZone('Asia/Kolkata'))->setTime(0,0);
    $start=$end->modify('-'.$days.' days');$rows=[];
    for($d=$start;$d<$end;$d=$d->modify('+1 day'))$rows[$d->format('Y-m-d')]=null;
    $q=$db->prepare("SELECT event_day,COUNT(*) AS events,SUM(event_type='page_view') AS views,COUNT(DISTINCT CASE WHEN event_type='page_view' THEN session_hash END) AS tab_days,SUM(event_type='page_view' AND automation_signal=1) AS signals,SUM(event_type='service_interest') AS interests,SUM(event_type='call_click') AS call_clicks,SUM(event_type='email_click') AS email_clicks,SUM(event_type='chat_open') AS chat_opens FROM growth_analytics_events WHERE event_day>=? AND event_day<? GROUP BY event_day ORDER BY event_day");
    $q->execute([$start->format('Y-m-d'),$end->format('Y-m-d')]);
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){$day=$r['event_day'];unset($r['event_day']);$rows[$day]=array_map('intval',$r);}
    return ['start'=>$start->format('Y-m-d'),'end'=>$end->format('Y-m-d'),'daily'=>$rows];
}
function dashboardCsvCell(string|int|null $v):string{
    if($v===null)return '';
    $s=(string)$v;
    return preg_match('/^[\s\x00-\x1f]*[=+@\x2d]/u',$s)||preg_match('/^[\t\r\n]/',$s)?"'".$s:$s;
}
function dashboardCsv(array $data):string{
    $f=fopen('php://temp','w+');if(!$f)throw new RuntimeException('Export unavailable');
    $write=static function(array $row)use($f):void{
        if(count($row)!==7||fputcsv($f,array_map('dashboardCsvCell',$row),',','"','')===false)throw new RuntimeException('Export unavailable');
    };
    try{
        $write(['section','window_start','window_end_exclusive','day','category','metric','value']);
        $p=$data['period'];$start=$p['start'];$end=$p['end'];
        foreach($data['leads'] as $k=>$n)$write(['inquiry_current_status_utc',$start,$end,'',$k,'count',$n]);
        foreach($data['tasks'] as $k=>$n)$write(['task_current_status_all_time','','','',$k,'count',$n]);
        foreach($data['opportunities']??[] as $k=>$n)$write(['opportunity_current_status_all_time','','','',$k,'count',$n]);
        foreach($data['services'] as $id=>$r)foreach(['pending_review','qualified','rejected'] as $k)$write(['opportunity_service_all_time','','','',$id,$k,$r[$k]]);
        foreach($data['communications']??[] as $c=>$r){
            $write(['communications_utc',$start,$end,'',$c,'days_reported',$r['days_reported']]);
            foreach(['received','answered','missed'] as $k)$write(['communications_utc',$start,$end,'',$c,$k,$r[$k]]);
            foreach($r['daily'] as $day=>$daily)foreach($daily as $k=>$n)$write(['communications_utc_daily',$start,$end,$day,$c,$k,$n]);
        }
        if($data['web_analytics']!==null){
            $a=$data['web_analytics'];
            foreach($a['daily'] as $day=>$r){
                $write(['consented_web_ist',$a['start'],$a['end'],$day,'website','recorded_data',$r===null?0:1]);
                if($r!==null)foreach($r as $k=>$n)$write(['consented_web_ist',$a['start'],$a['end'],$day,'website',$k,$n]);
            }
        }
        foreach($data['attention']??[] as $k=>$n)$write(['publication_all_time','','','','all_ledgers',$k,$n]);
        rewind($f);$raw=stream_get_contents($f,262145);
        if(!is_string($raw)||strlen($raw)>262144)throw new RuntimeException('Export too large');return $raw;
    }finally{fclose($f);}
}
