<?php
declare(strict_types=1);
require_once __DIR__.'/seo-core.php';
require_once __DIR__.'/creative-core.php';
function scConfig(array $cfg,?int $now=null):?array{
    $p=$cfg['seo']['search_console']??[];$now=$now??time();
    if(!seoEnabled($cfg)||!is_array($p)||($p['enabled']??false)!==true||($p['authorized']??false)!==true||($p['revoked']??false)!==false||!in_array($p['property']??null,['sc-domain:aaditechs.in','https://aaditechs.in/','https://www.aaditechs.in/','sc-domain:aaditechsin.in','https://aaditechsin.in/','https://www.aaditechsin.in/'],true)||!is_string($p['generation']??null)||!preg_match('/^[a-f0-9]{32}$/D',$p['generation'])||!is_string($p['review_expires_utc']??null))return null;
    $d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$p['review_expires_utc'],new DateTimeZone('UTC'));
    if(!$d||$d->format('Y-m-d\TH:i:s\Z')!==$p['review_expires_utc']||$d->getTimestamp()<=$now+600)return null;
    foreach(['client_id','client_secret','refresh_token'] as $k)if(!is_string($p[$k]??null)||!preg_match('/^[A-Za-z0-9._~+\/=-]{8,4096}$/D',$p[$k])||str_starts_with($p[$k],'REPLACE_'))return null;
    return $p;
}
function scWindow(?DateTimeImmutable $now=null):array{
    $end=($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('America/Los_Angeles'))->setTime(0,0)->modify('-3 days');
    return ['start'=>$end->modify('-6 days')->format('Y-m-d'),'end'=>$end->format('Y-m-d')];
}
function scCount(mixed $v):int{
    if(!is_int($v)&&!(is_float($v)&&is_finite($v)&&floor($v)===$v))throw new RuntimeException('Invalid count');
    if($v<0||$v>1000000000)throw new RuntimeException('Count outside bounds');return (int)$v;
}
function scParse(array $r,array $window):array{
    if(isset($r['error'])||($r['responseAggregationType']??null)!=='byProperty')throw new RuntimeException('Unverified aggregation');
    $rows=array_key_exists('rows',$r)?$r['rows']:[];if(!is_array($rows)||!array_is_list($rows)||count($rows)>7)throw new RuntimeException('Invalid rows');
    $seen=[];$clicks=0;$impressions=0;$weighted=0.0;
    foreach($rows as $row){
        if(!is_array($row))throw new RuntimeException('Invalid row');
        $keys=$row['keys']??null;$day=is_array($keys)?($keys[0]??null):null;
        if(!is_array($keys)||!array_is_list($keys)||count($keys)!==1||!is_string($day)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$day)||$day<$window['start']||$day>$window['end']||isset($seen[$day]))throw new RuntimeException('Invalid date');
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$day,new DateTimeZone('UTC'));if(!$d||$d->format('Y-m-d')!==$day)throw new RuntimeException('Invalid date');
        $seen[$day]=true;$c=scCount($row['clicks']??null);$i=scCount($row['impressions']??null);$p=$row['position']??null;
        if($c>$i||(!is_int($p)&&!is_float($p))||!is_finite((float)$p)||$p<0||$p>1000000||($i>0&&$p<1))throw new RuntimeException('Invalid metrics');
        $clicks+=$c;$impressions+=$i;$weighted+=$p*$i;
    }
    return ['days'=>count($seen),'clicks'=>$clicks,'impressions'=>$impressions,'position'=>$impressions?round($weighted/$impressions,4):null];
}
function scCapture(PDO $db,array $cfg,array $window,array $response,?DateTimeImmutable $now=null):void{
    $now=($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'));$p=scConfig($cfg,$now->getTimestamp());
    if(!$p||!$db->inTransaction()||$window!==scWindow($now))throw new RuntimeException('Disabled or stale window');creativeActive($db);
    $r=scParse($response,$window);
    $db->exec('SAVEPOINT search_console_capture');
    try{
    $db->prepare('INSERT INTO growth_search_console(id,generation,property_hash,start_day,end_day,days_reported,clicks,impressions,average_position,captured_at) VALUES(1,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE generation=VALUES(generation),property_hash=VALUES(property_hash),start_day=VALUES(start_day),end_day=VALUES(end_day),days_reported=VALUES(days_reported),clicks=VALUES(clicks),impressions=VALUES(impressions),average_position=VALUES(average_position),captured_at=VALUES(captured_at)')->execute([$p['generation'],hash('sha256',$p['property']),$window['start'],$window['end'],$r['days'],$r['clicks'],$r['impressions'],$r['position'],$now->format('Y-m-d H:i:s')]);
    if(($cfg['seo']['search_console']['history_enabled']??false)===true)scHistoryCapture($db,$p,$response,$window,$now);
    growthAudit($db,null,null,'search_console_capture','private_cli');
    $db->exec('RELEASE SAVEPOINT search_console_capture');
    }catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT search_console_capture');$db->exec('RELEASE SAVEPOINT search_console_capture');throw $e;}
}
function scSummary(PDO $db,array $cfg,?DateTimeImmutable $now=null):string{
    $now=$now??new DateTimeImmutable('now');$p=scConfig($cfg,$now->getTimestamp());if(!$p)return 'Search Console disabled or authorization review expired.';
    $w=scWindow($now);$r=$db->query('SELECT * FROM growth_search_console WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    if(!$r||$r['generation']!==$p['generation']||!hash_equals($r['property_hash'],hash('sha256',$p['property']))||$r['start_day']!==$w['start']||$r['end_day']!==$w['end'])return 'Search Console: no current evidence.';
    $ts=strtotime($r['captured_at'].' UTC');if($ts===false||$ts>$now->getTimestamp()||$now->getTimestamp()-$ts>86400)return 'Search Console: stale; refresh privately.';
    $label='Search Console web search '.$w['start'].' to '.$w['end'].' PT: '.$r['days_reported'].'/7 days returned.';
    if((int)$r['days_reported']===0)return $label.' No recorded data; not zero traffic.';
    return $label.' Clicks '.$r['clicks'].', impressions '.$r['impressions'].', average position '.($r['average_position']??'unavailable').'. Returned finalized rows only; not site visits, leads or keyword rankings.';
}

function scHistoryCapture(PDO $db,array $p,array $response,array $window,DateTimeImmutable $now):void{
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    creativeActive($db);scParse($response,$window);$hash=hash('sha256',$p['property']);$gen=$p['generation'];
    $total=(int)$db->query('SELECT COUNT(*) FROM growth_search_console_daily')->fetchColumn();
    $q=$db->prepare('SELECT COUNT(*) FROM growth_search_console_daily WHERE property_hash=? AND generation=? AND day_pt=?');
    $put=$db->prepare('INSERT INTO growth_search_console_daily(property_hash,generation,day_pt,clicks,impressions,average_position,captured_at) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE clicks=VALUES(clicks),impressions=VALUES(impressions),average_position=VALUES(average_position),captured_at=VALUES(captured_at)');
    foreach($response['rows']??[] as $row){
        $q->execute([$hash,$gen,$row['keys'][0]]);
        if((int)$q->fetchColumn()===0&&++$total>5000)throw new RuntimeException('History cap; review retention');
    }
    foreach($response['rows']??[] as $row){
        $day=$row['keys'][0];$i=scCount($row['impressions']);$put->execute([$hash,$gen,$day,scCount($row['clicks']),$i,$i?round((float)$row['position'],4):null,$now->format('Y-m-d H:i:s')]);
    }
}
function scHistory(PDO $db,array $cfg,int $days,?DateTimeImmutable $now=null):?array{
    $now=$now??new DateTimeImmutable('now');$p=scConfig($cfg,$now->getTimestamp());
    if(!$p||($p['history_enabled']??false)!==true)return null;
    if(!in_array($days,[7,30],true))throw new InvalidArgumentException('Period');
    $end=new DateTimeImmutable(scWindow($now)['end'],new DateTimeZone('America/Los_Angeles'));$start=$end->modify('-'.($days-1).' days');$rows=[];
    for($d=$start;$d<=$end;$d=$d->modify('+1 day'))$rows[$d->format('Y-m-d')]=null;
    $q=$db->prepare('SELECT day_pt,clicks,impressions,average_position,captured_at FROM growth_search_console_daily WHERE property_hash=? AND generation=? AND day_pt BETWEEN ? AND ? ORDER BY day_pt');
    $q->execute([hash('sha256',$p['property']),$p['generation'],$start->format('Y-m-d'),$end->format('Y-m-d')]);
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){$ts=strtotime($r['captured_at'].' UTC');if($ts===false||$ts>$now->getTimestamp())continue;$day=$r['day_pt'];unset($r['day_pt']);$rows[$day]=$r;}
    return ['start'=>$start->format('Y-m-d'),'end'=>$end->format('Y-m-d'),'daily'=>$rows];
}
function scHistorySummary(PDO $db,array $cfg,?DateTimeImmutable $now=null):string{
    $r=scHistory($db,$cfg,30,$now);if($r===null)return 'Search history disabled or authorization expired.';
    $n=0;$c=0;$i=0;foreach($r['daily'] as $row)if($row!==null){++$n;$c+=(int)$row['clicks'];$i+=(int)$row['impressions'];}
    return 'Search history '.$r['start'].' to '.$r['end'].' PT: '.$n.'/30 previously observed days. '.($n?'Clicks '.$c.', impressions '.$i.'. Missing dates not zero.':'No observations.').' Previously captured finalized rows, not live traffic.';
}
