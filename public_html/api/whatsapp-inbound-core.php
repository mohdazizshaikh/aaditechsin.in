<?php
declare(strict_types=1);
require_once __DIR__.'/communications-core.php';
function waInboundConfig(array $cfg):?array{
    $p=$cfg['communications']['whatsapp_inbound']??[];
    if(!communicationsEnabled($cfg)||!is_array($p)||($p['enabled']??false)!==true||($p['authorized']??false)!==true||($p['log_redaction_verified']??false)!==true)return null;
    foreach(['waba','phone_id'] as $k)if(!is_string($p[$k]??null)||!preg_match('/^[1-9][0-9]{0,30}$/D',$p[$k]))return null;
    if(!is_string($p['app_secret']??null)||!preg_match('/^[a-fA-F0-9]{32}$/D',$p['app_secret'])||!is_string($p['verify_token']??null)||!preg_match('/^[a-f0-9]{64}$/D',$p['verify_token'])||!is_string($p['dedupe_key_hex']??null)||!preg_match('/^[a-f0-9]{64}$/D',$p['dedupe_key_hex'])||$p['verify_token']===$p['dedupe_key_hex'])return null;
    return $p;
}
function waInboundChallenge(array $p,array $query):?string{
    if(count($query)!==3||($query['hub_mode']??null)!=='subscribe'||!is_string($query['hub_verify_token']??null)||!hash_equals($p['verify_token'],$query['hub_verify_token'])||!is_string($query['hub_challenge']??null)||!preg_match('/^[0-9]{1,32}$/D',$query['hub_challenge']))return null;
    return $query['hub_challenge'];
}
function waInboundAccount(array $p):string{return hash('sha256',$p['waba'].':'.$p['phone_id']);}
function waInboundDecode(array $p,string $raw,string $sig,?int $now=null):array{
    $now=$now??time();
    if(strlen($raw)>65536||!preg_match('/^sha256=[a-f0-9]{64}$/D',$sig)||!hash_equals('sha256='.hash_hmac('sha256',$raw,$p['app_secret']),$sig))throw new InvalidArgumentException('Signature');
    $d=json_decode($raw,true,24,JSON_THROW_ON_ERROR);
    if(!is_array($d)||($d['object']??null)!=='whatsapp_business_account'||!is_array($d['entry']??null)||!array_is_list($d['entry'])||count($d['entry'])<1||count($d['entry'])>16)throw new InvalidArgumentException('Envelope');
    $rows=[];$n=0;
    foreach($d['entry'] as $entry){
        if(($entry['id']??null)!==$p['waba']||!is_array($entry['changes']??null)||!array_is_list($entry['changes'])||count($entry['changes'])>32)throw new InvalidArgumentException('Account');
        foreach($entry['changes'] as $c){
            if(($c['field']??null)!=='messages')continue;
            $v=$c['value']??null;
            if(!is_array($v)||($v['messaging_product']??null)!=='whatsapp'||($v['metadata']['phone_number_id']??null)!==$p['phone_id'])throw new InvalidArgumentException('Phone');
            if(!array_key_exists('messages',$v))continue; // delivery statuses are not inbound messages
            if(!is_array($v['messages'])||!array_is_list($v['messages']))throw new InvalidArgumentException('Messages');
            foreach($v['messages'] as $m){
                if(++$n>64||!is_array($m)||!is_string($m['id']??null)||!preg_match('/^wamid\.[A-Za-z0-9_+=\/-]{1,500}$/D',$m['id'])||!is_string($m['timestamp']??null)||!preg_match('/^[0-9]{10}$/D',$m['timestamp'])||!is_string($m['type']??null))throw new InvalidArgumentException('Message');
                $ts=(int)$m['timestamp'];if($ts>$now+300)throw new InvalidArgumentException('Future');
                if($ts<$now-7*86400||!in_array($m['type'],['text','image','video','audio','document','sticker','location','contacts','interactive','button','order'],true))continue;
                $h=hash_hmac('sha256',"aaditechs-wa-v1\n".$p['waba'].':'.$p['phone_id'].':'.$m['id'],hex2bin($p['dedupe_key_hex']));
                $day=gmdate('Y-m-d',$ts);if(isset($rows[$h])&&$rows[$h]!==$day)throw new InvalidArgumentException('Conflicting event');$rows[$h]=$day;
            }
        }
    }
    return $rows;
}
function waInboundRecord(PDO $db,array $cfg,string $raw,string $sig,?int $now=null):int{
    $p=waInboundConfig($cfg);if(!$p||!$db->inTransaction())throw new RuntimeException('Disabled');$rows=waInboundDecode($p,$raw,$sig,$now);creativeActive($db);
    $account=waInboundAccount($p);
    $n=(int)$db->query('SELECT COUNT(*) FROM growth_whatsapp_inbound')->fetchColumn();$added=0;
    $q=$db->prepare('SELECT account_hash,day_utc FROM growth_whatsapp_inbound WHERE event_hash=?');
    $put=$db->prepare('INSERT INTO growth_whatsapp_inbound(event_hash,account_hash,day_utc) VALUES(?,?,?)');
    foreach($rows as $h=>$day){$q->execute([$h]);$old=$q->fetch(PDO::FETCH_ASSOC);if($old){if($old['account_hash']!==$account||$old['day_utc']!==$day)throw new RuntimeException('Conflicting event');continue;}if($n+$added>=50000)throw new RuntimeException('Retention cap');$put->execute([$h,$account,$day]);++$added;}
    return $added;
}
function waInboundSummary(PDO $db,array $cfg,?DateTimeImmutable $now=null):string{
    $p=waInboundConfig($cfg);if(!$p)return 'WhatsApp inbound counting disabled.';
    $day=($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'))->modify('-1 day')->format('Y-m-d');
    $q=$db->prepare('SELECT COUNT(*) FROM growth_whatsapp_inbound WHERE account_hash=? AND day_utc=?');$q->execute([waInboundAccount($p),$day]);$n=(int)$q->fetchColumn();
    return 'WhatsApp inbound webhook events '.$day.' UTC: '.($n===0?'no recorded events (coverage unknown)':$n.' distinct recorded messages').'. Not complete inbox or unanswered count.';
}
