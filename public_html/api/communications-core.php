<?php
declare(strict_types=1);
require_once __DIR__.'/growth-core.php';
require_once __DIR__.'/creative-core.php';
function communicationsEnabled(array $cfg):bool{return ($cfg['growth']['enabled']??false)===true&&($cfg['communications']['enabled']??false)===true;}
function communicationsValidate(array $row,?DateTimeImmutable $now=null):array{
    $keys=['channel','day_utc','received','answered','missed'];
    if(count($row)!==count($keys)||array_diff($keys,array_keys($row))||!in_array($row['channel'],['email','whatsapp','sms','calls'],true)||!is_string($row['day_utc']))throw new InvalidArgumentException('Exact aggregate fields required');
    $utc=new DateTimeZone('UTC');$now=($now??new DateTimeImmutable('now',$utc))->setTimezone($utc);
    $day=DateTimeImmutable::createFromFormat('!Y-m-d',$row['day_utc'],$utc);
    if(!$day||$day->format('Y-m-d')!==$row['day_utc']||$day>=$now->setTime(0,0)||$day<$now->setTime(0,0)->modify('-90 days'))throw new InvalidArgumentException('Completed UTC day within 90 days required');
    foreach(['received','answered','missed'] as $k)if(!is_int($row[$k])||$row[$k]<0||$row[$k]>1000000)throw new InvalidArgumentException('Invalid count');
    if($row['answered']+$row['missed']>$row['received'])throw new InvalidArgumentException('Inconsistent totals');
    return $row;
}
function communicationsImport(PDO $db,array $cfg,array $row,?DateTimeImmutable $now=null):bool{
    if(!communicationsEnabled($cfg)||!$db->inTransaction())throw new RuntimeException('Disabled or no transaction');
    creativeActive($db);$r=communicationsValidate($row,$now);
    $q=$db->prepare('SELECT received_count,answered_count,missed_count FROM growth_communication_daily WHERE channel=? AND day_utc=? FOR UPDATE');$q->execute([$r['channel'],$r['day_utc']]);$old=$q->fetch(PDO::FETCH_ASSOC);
    if($old){if([(int)$old['received_count'],(int)$old['answered_count'],(int)$old['missed_count']]!==[$r['received'],$r['answered'],$r['missed']])throw new RuntimeException('Conflicting existing summary; no overwrite');return false;}
    $db->prepare('INSERT INTO growth_communication_daily(channel,day_utc,received_count,answered_count,missed_count) VALUES(?,?,?,?,?)')->execute([$r['channel'],$r['day_utc'],$r['received'],$r['answered'],$r['missed']]);
    growthAudit($db,null,null,'communications_import','private_cli');return true;
}
function communicationsSummary(PDO $db,?DateTimeImmutable $now=null):string{
    $utc=new DateTimeZone('UTC');$day=($now??new DateTimeImmutable('now',$utc))->setTimezone($utc)->modify('-1 day')->format('Y-m-d');
    $q=$db->prepare('SELECT channel,received_count,answered_count,missed_count FROM growth_communication_daily WHERE day_utc=? ORDER BY channel');$q->execute([$day]);$rows=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$rows[$r['channel']]=$r;
    $lines=['Imported communications aggregates, completed UTC day '.$day.' (not live monitoring):'];
    foreach(['email','whatsapp','sms','calls'] as $c){$r=$rows[$c]??null;$lines[]=$c.': '.($r?'received '.$r['received_count'].' / answered '.$r['answered_count'].' / missed '.$r['missed_count']:'no data');}
    return implode("\n",$lines);
}
function communicationsCommand(PDO $db,array $cfg,string $cmd):?string{
    if($cmd!=='/communications')return null;
    return communicationsEnabled($cfg)?communicationsSummary($db):'Communications summary disabled.';
}

function communicationsBatch(PDO $db,array $cfg,array $doc,?DateTimeImmutable $now=null):array{
    if(!communicationsEnabled($cfg)||($cfg['communications']['batch_import_enabled']??false)!==true||!$db->inTransaction())throw new RuntimeException('Batch disabled');
    if(count($doc)!==2||($doc['version']??null)!==1||!is_array($doc['items']??null)||!array_is_list($doc['items'])||count($doc['items'])<1||count($doc['items'])>120)throw new InvalidArgumentException('Exact batch contract');
    $now=$now??new DateTimeImmutable('now',new DateTimeZone('UTC'));$seen=[];$rows=[];
    foreach($doc['items'] as $r){
        if(!is_array($r))throw new InvalidArgumentException('Invalid row');$r=communicationsValidate($r,$now);$key=$r['channel'].':'.$r['day_utc'];
        if(isset($seen[$key]))throw new InvalidArgumentException('Duplicate day/channel within batch');$seen[$key]=true;$rows[]=$r;
    }
    creativeActive($db);$db->exec('SAVEPOINT communications_batch');$n=0;$dupes=0;
    try{foreach($rows as $r){if(communicationsImport($db,$cfg,$r,$now))++$n;else ++$dupes;}$db->exec('RELEASE SAVEPOINT communications_batch');}
    catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT communications_batch');$db->exec('RELEASE SAVEPOINT communications_batch');throw $e;}
    return ['imported'=>$n,'duplicates'=>$dupes];
}
