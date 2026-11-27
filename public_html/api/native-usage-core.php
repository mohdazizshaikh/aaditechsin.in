<?php
declare(strict_types=1);
require_once __DIR__.'/communications-core.php';
function nativeUsageConfig(array $cfg,string $source,?int $now=null):?array{
    if(!communicationsEnabled($cfg)||($cfg['communications']['native_usage_enabled']??false)!==true||!in_array($source,['whatsapp','twilio-sms','twilio-calls'],true))return null;
    $p=$cfg['communications']['native_usage'][$source]??[];$now=$now??time();
    if(($p['enabled']??false)!==true||($p['authorized']??false)!==true||($p['revoked']??false)!==false||!is_string($p['generation']??null)||!preg_match('/^[a-f0-9]{32}$/D',$p['generation'])||!is_string($p['expires_utc']??null))return null;
    $d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$p['expires_utc'],new DateTimeZone('UTC'));
    if(!$d||$d->format('Y-m-d\TH:i:s\Z')!==$p['expires_utc']||$d->getTimestamp()<=$now+600)return null;
    if($source==='whatsapp'){
        if(!is_string($p['account']??null)||!preg_match('/^[1-9][0-9]{0,30}$/D',$p['account'])||!is_string($p['api_version']??null)||!preg_match('/^v[0-9]{1,2}\.0$/D',$p['api_version'])||!is_string($p['token']??null)||!preg_match('/^[A-Za-z0-9._~-]{20,4096}$/D',$p['token'])||str_starts_with($p['token'],'REPLACE_'))return null;
    }else{
        foreach(['account'=>'AC','key'=>'SK'] as $k=>$prefix)if(!is_string($p[$k]??null)||!preg_match('/^'.$prefix.'[a-fA-F0-9]{32}$/D',$p[$k]))return null;
        if(!is_string($p['secret']??null)||!preg_match('/^[A-Za-z0-9]{32,128}$/D',$p['secret']))return null;
    }
    return $p+['source'=>$source];
}
function nativeUsageDay(?DateTimeImmutable $now=null):string{return ($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'))->modify('-1 day')->format('Y-m-d');}
function nativeUsageCount(mixed $v):int{
    if(is_string($v)&&preg_match('/^(?:0|[1-9][0-9]{0,6})$/D',$v))$v=(int)$v;
    if(!is_int($v)||$v<0||$v>1000000)throw new RuntimeException('Invalid metric');
    return $v;
}
function nativeUsageCapture(PDO $db,array $cfg,string $source,string $day,array $counts,?DateTimeImmutable $now=null):void{
    if(!$db->inTransaction())throw new LogicException('Transaction required');creativeActive($db);
    $now=($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'));$p=nativeUsageConfig($cfg,$source,$now->getTimestamp());
    if(!$p||$day!==nativeUsageDay($now))throw new RuntimeException('Disabled or stale date');
    $n=nativeUsageCount($counts['count']??null);$d=$source==='whatsapp'?nativeUsageCount($counts['delivered']??null):null;
    $db->prepare('INSERT INTO growth_native_usage(source,generation,account_hash,day_utc,metric_count,delivered_count,captured_at) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE generation=VALUES(generation),account_hash=VALUES(account_hash),day_utc=VALUES(day_utc),metric_count=VALUES(metric_count),delivered_count=VALUES(delivered_count),captured_at=VALUES(captured_at)')->execute([$source,$p['generation'],hash('sha256',$p['account']),$day,$n,$d,$now->format('Y-m-d H:i:s')]);
    growthAudit($db,null,null,'native_usage_capture','private_cli');
}
function nativeUsageSummary(PDO $db,array $cfg,?DateTimeImmutable $now=null):string{
    $now=$now??new DateTimeImmutable('now');$day=nativeUsageDay($now);$lines=['Native provider usage, '.$day.' UTC (provisional, not response metrics):'];
    foreach(['whatsapp','twilio-sms','twilio-calls'] as $source){
        $p=nativeUsageConfig($cfg,$source,$now->getTimestamp());if(!$p){$lines[]=$source.': disabled or credential unavailable';continue;}
        $q=$db->prepare('SELECT * FROM growth_native_usage WHERE source=?');$q->execute([$source]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!$r||$r['day_utc']!==$day||$r['generation']!==$p['generation']||!hash_equals($r['account_hash'],hash('sha256',$p['account']))){$lines[]=$source.': no current data';continue;}
        $lines[]=$source.': '.($source==='whatsapp'?'sent '.$r['metric_count'].', delivered '.$r['delivered_count']:'inbound usage count '.$r['metric_count']);
    }
    return implode("\n",$lines);
}
