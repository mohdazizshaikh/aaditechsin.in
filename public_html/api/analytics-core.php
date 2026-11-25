<?php
declare(strict_types=1);
require_once __DIR__.'/growth-core.php';
function analyticsPages(): array {
    return ['home'=>'','data-recovery'=>'data-recovery','it-amc'=>'it-amc',
        'video-conferencing-solutions'=>'video-conferencing','auditorium-av-solutions'=>'auditorium-av',
        'professional-sound-systems'=>'professional-sound','av-project-planner'=>'av-maintenance',
        'it-infrastructure'=>'infrastructure','software-web-development'=>'software-web','cloud-modern-it'=>'cloud','security-compliance'=>'security','data-management-backup'=>'data-backup','it-asset-procurement'=>'it-procurement','smart-office-solutions'=>'smart-office','it-special-projects'=>'special-projects','av-installation-maintenance'=>'av-maintenance'];
}
function analyticsDay(): string {return (new DateTimeImmutable('now',new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');}
function analyticsEnabled(array $cfg): bool {
    $salt=$cfg['analytics']['salt']??null;
    return ($cfg['analytics']['enabled']??false)===true&&is_string($salt)&&strlen($salt)>=32&&!str_starts_with($salt,'REPLACE_');
}
function analyticsValidate(array $data): bool {
    $keys=['event_id','session','page','event','service','channel','automation','consent'];
    if(count($data)!==count($keys)||array_diff(array_keys($data),$keys))return false;
    foreach(['event_id','session','page','event','service','channel'] as $key)if(!is_string($data[$key]??null))return false;
    if(!preg_match('/^[a-f0-9]{32}$/D',$data['event_id'])||!preg_match('/^[a-f0-9]{32}$/D',$data['session']))return false;
    if($data['consent']!==true||!is_bool($data['automation']))return false;
    if(!array_key_exists($data['page'],analyticsPages())||!in_array($data['event'],['page_view','service_interest','call_click','email_click','chat_open'],true))return false;
    if($data['service']!==''&&!isset(growthCatalog()[$data['service']]))return false;
    if($data['event']==='service_interest'&&$data['service']==='')return false;
    return in_array($data['channel'],['direct','search','social','email','referral','other'],true);
}
function analyticsSignal(array $data,string $agent): bool {
    // Heuristic only. A missing or ordinary UA is not evidence of a human.
    return $data['automation']===true || (bool)preg_match('/bot|crawler|spider|headless|curl\/|python-requests/i',substr($agent,0,512));
}
function analyticsRecord(PDO $db,array $cfg,array $data,string $agent): string {
    if(!analyticsEnabled($cfg)||!analyticsValidate($data)||!$db->inTransaction())throw new InvalidArgumentException('Analytics unavailable or invalid');
    $day=analyticsDay();
    $hash=hash_hmac('sha256',$day.':'.$data['session'],$cfg['analytics']['salt']);
    // One daily lock serializes quota, duplicates and insertion; bounded daily storage.
    $db->prepare('INSERT IGNORE INTO growth_analytics_budget(event_day) VALUES(?)')->execute([$day]);
    $q=$db->prepare('SELECT accepted_count FROM growth_analytics_budget WHERE event_day=? FOR UPDATE');$q->execute([$day]);$count=(int)$q->fetchColumn();
    $q=$db->prepare('SELECT 1 FROM growth_analytics_events WHERE event_day=? AND event_id=?');$q->execute([$day,$data['event_id']]);
    if($q->fetchColumn()!==false)return 'duplicate';
    if($count>=10000)return 'limited';
    $q=$db->prepare('SELECT COUNT(*) FROM growth_analytics_events WHERE event_day=? AND session_hash=?');$q->execute([$day,$hash]);
    if((int)$q->fetchColumn()>=100)return 'limited';
    $q=$db->prepare('INSERT INTO growth_analytics_events(event_day,event_id,session_hash,page_id,event_type,service_id,channel,automation_signal) VALUES(?,?,?,?,?,?,?,?)');
    $q->execute([$day,$data['event_id'],$hash,$data['page'],$data['event'],$data['service'],$data['channel'],analyticsSignal($data,$agent)?1:0]);
    $db->prepare('UPDATE growth_analytics_budget SET accepted_count=accepted_count+1 WHERE event_day=?')->execute([$day]);
    return 'accepted';
}
function analyticsReport(PDO $db,array $cfg,string $command): string {
    if(!analyticsEnabled($cfg))return 'Optional website analytics is disabled or unconfigured. Metrics unavailable.';
    $day=analyticsDay();
    $q=$db->prepare("SELECT COUNT(*) total, COUNT(DISTINCT session_hash) sessions, COALESCE(SUM(automation_signal),0) signals FROM growth_analytics_events WHERE event_day=? AND event_type='page_view'");
    $q->execute([$day]);$r=$q->fetch(PDO::FETCH_ASSOC);
    $lines=['Website analytics — '.$day.' IST','Consented page views: '.$r['total'],'Estimated tab sessions (not people): '.$r['sessions'],'Page views with automation signal: '.$r['signals']];
    $groups=match($command){'/pages'=>['page_id','page_view'],'/services'=>['service_id','service_interest'],'/sources'=>['channel','page_view'],default=>null};
    if($groups){
        // SQL identifiers are chosen only by this fixed mapping.
        [$column,$event]=$groups;
        $q=$db->prepare("SELECT $column label,COUNT(*) total FROM growth_analytics_events WHERE event_day=? AND event_type=? GROUP BY $column ORDER BY total DESC LIMIT 20");
        $q->execute([$day,$event]);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$lines[]=$row['label'].': '.$row['total'];
    }
    $q=$db->prepare("SELECT event_type,COUNT(*) total FROM growth_analytics_events WHERE event_day=? AND event_type<>'page_view' GROUP BY event_type");
    $q->execute([$day]);$counts=array_column($q->fetchAll(PDO::FETCH_ASSOC),'total','event_type');
    foreach(['service_interest'=>'Service interactions','call_click'=>'Phone link clicks','email_click'=>'Email link clicks','chat_open'=>'Chat opens'] as $key=>$label)$lines[]=$label.': '.($counts[$key]??0);
    $lines[]='Clicks are not confirmed calls or sent emails. No cross-source lead attribution or full bot detection. Non-consenting visitors and non-JS requests are excluded.';
    return implode("\n",$lines);
}
