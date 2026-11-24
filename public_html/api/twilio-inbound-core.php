<?php
declare(strict_types=1);
require_once __DIR__.'/communications-core.php';
function twInboundConfig(array $cfg,string $channel):?array{
    if(!communicationsEnabled($cfg)||!in_array($channel,['sms','calls'],true))return null;
    $p=$cfg['communications']['twilio_inbound'][$channel]??[];
    if(!is_array($p)||($p['enabled']??false)!==true||($p['authorized']??false)!==true||($p['log_redaction_verified']??false)!==true)return null;
    if(!is_string($p['account']??null)||!preg_match('/^AC[a-fA-F0-9]{32}$/D',$p['account'])||!is_string($p['to']??null)||!preg_match('/^\+[1-9][0-9]{7,14}$/D',$p['to'])||!is_string($p['auth_token']??null)||!preg_match('/^[a-fA-F0-9]{32}$/D',$p['auth_token'])||!is_string($p['dedupe_key_hex']??null)||!preg_match('/^[a-f0-9]{64}$/D',$p['dedupe_key_hex']))return null;
    $url=$p['url']??null;
    if(!is_string($url)||!preg_match('#^https://[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?/api/twilio-'.($channel==='sms'?'sms':'voice').'-inbound\.php$#D',$url))return null;
    return $p+['channel'=>$channel];
}
function twInboundForm(string $raw):array{
    if($raw===''||strlen($raw)>65536)throw new InvalidArgumentException('Body');
    $pairs=explode('&',$raw);if(count($pairs)>100)throw new InvalidArgumentException('Fields');
    $out=[];
    foreach($pairs as $pair){
        if(!str_contains($pair,'=')||preg_match('/%(?![a-fA-F0-9]{2})/',$pair))throw new InvalidArgumentException('Encoding');
        [$k,$v]=array_map('urldecode',explode('=',$pair,2));
        if(!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/D',$k)||isset($out[$k])||strlen($v)>16384||str_contains($v,"\0"))throw new InvalidArgumentException('Field');
        $out[$k]=$v;
    }return $out;
}
function twInboundAccount(array $p):string{return hash('sha256',$p['account'].':'.$p['to'].':'.$p['channel']);}
function twInboundDecode(array $p,string $raw,string $sig):string{
    $fields=twInboundForm($raw);ksort($fields,SORT_STRING);$signed=$p['url'];
    foreach($fields as $k=>$v)$signed.=$k.$v;
    if(!preg_match('#^[A-Za-z0-9+/]{27}=$#D',$sig)||!hash_equals(base64_encode(hash_hmac('sha1',$signed,$p['auth_token'],true)),$sig))throw new InvalidArgumentException('Signature');
    if(($fields['AccountSid']??null)!==$p['account']||($fields['To']??null)!==$p['to'])throw new InvalidArgumentException('Account');
    if($p['channel']==='sms'){
        $id=$fields['MessageSid']??null;
        if(!is_string($id)||!preg_match('/^SM[a-fA-F0-9]{32}$/D',$id)||($fields['SmsStatus']??null)!=='received'||!preg_match('/^\+[1-9][0-9]{7,14}$/D',$fields['From']??'')||($fields['NumMedia']??null)!=='0')throw new InvalidArgumentException('SMS only');
    }else{
        $id=$fields['CallSid']??null;
        if(!is_string($id)||!preg_match('/^CA[a-fA-F0-9]{32}$/D',$id)||($fields['Direction']??null)!=='inbound'||!in_array($fields['CallStatus']??null,['completed','busy','failed','no-answer','canceled'],true))throw new InvalidArgumentException('Terminal inbound status only');
    }
    return hash_hmac('sha256',"aaditechs-twilio-inbound-v1\n".twInboundAccount($p).':'.$id,hex2bin($p['dedupe_key_hex']));
}
function twInboundRecord(PDO $db,array $cfg,string $channel,string $raw,string $sig,?DateTimeImmutable $now=null):bool{
    $p=twInboundConfig($cfg,$channel);if(!$p||!$db->inTransaction())throw new RuntimeException('Disabled');
    $h=twInboundDecode($p,$raw,$sig);creativeActive($db);
    $q=$db->prepare('SELECT account_hash,channel FROM growth_twilio_inbound WHERE event_hash=?');$q->execute([$h]);$old=$q->fetch(PDO::FETCH_ASSOC);
    $account=twInboundAccount($p);if($old){if($old['account_hash']!==$account||$old['channel']!==$channel)throw new RuntimeException('Conflict');return false;}
    if((int)$db->query('SELECT COUNT(*) FROM growth_twilio_inbound')->fetchColumn()>=50000)throw new RuntimeException('Retention cap');
    $day=($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
    $db->prepare('INSERT INTO growth_twilio_inbound(event_hash,account_hash,channel,day_utc) VALUES(?,?,?,?)')->execute([$h,$account,$channel,$day]);return true;
}
function twInboundSummary(PDO $db,array $cfg,?DateTimeImmutable $now=null):string{
    $day=($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'))->modify('-1 day')->format('Y-m-d');$lines=['Twilio callback observations, '.$day.' UTC receipt day (coverage unknown):'];
    foreach(['sms','calls'] as $channel){
        $p=twInboundConfig($cfg,$channel);if(!$p){$lines[]=$channel.': disabled';continue;}
        $q=$db->prepare('SELECT COUNT(*) FROM growth_twilio_inbound WHERE account_hash=? AND channel=? AND day_utc=?');$q->execute([twInboundAccount($p),$channel,$day]);$n=(int)$q->fetchColumn();
        $lines[]=$channel.': '.($n===0?'no recorded events':$n.' distinct recorded callbacks');
    }return implode("\n",$lines)."\nNot complete inbox, answered or missed-call counts.";
}
