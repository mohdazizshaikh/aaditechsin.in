<?php
declare(strict_types=1);
require_once __DIR__.'/discovery-feed-lib.php';
require_once dirname(__DIR__,2).'/public_html/api/communications-core.php';
function commBridgeConfig(array $cfg,string $channel):array{
    if(!communicationsEnabled($cfg)||($cfg['communications']['bridge_enabled']??false)!==true||!in_array($channel,['whatsapp','sms','calls'],true))throw new RuntimeException('Disabled');
    $s=$cfg['communications']['bridges'][$channel]??[];
    if(!is_array($s)||($s['enabled']??false)!==true||($s['authorized']??false)!==true)throw new RuntimeException('Source not authorized');
    $src=feedDefinition($channel,['authorized'=>true,'url'=>$s['url']??null,'host'=>$s['host']??null,'source_type'=>'manual_research']);
    $key=$s['hmac_key_hex']??null;$bearer=$s['bearer_token']??null;$generation=$s['generation']??null;
    if(!is_string($key)||!preg_match('/^[a-f0-9]{64}$/D',$key)||!is_string($bearer)||!preg_match('/^[A-Za-z0-9._~+\/=-]{32,256}$/D',$bearer)||str_starts_with($bearer,'REPLACE_')||!is_string($generation)||!preg_match('/^[a-f0-9]{32}$/D',$generation))throw new RuntimeException('Invalid private credential');
    return $src+['channel'=>$channel,'key'=>hex2bin($key),'bearer'=>$bearer,'generation'=>$generation];
}
function commBridgeFetch(array $s):string{
    if(PHP_SAPI!=='cli'||!function_exists('curl_init'))throw new RuntimeException('Transport unavailable');
    $ips=gethostbynamel($s['host']);if(!$ips)throw new RuntimeException('DNS unavailable');
    foreach($ips as $ip)if(!feedPublicIp($ip))throw new RuntimeException('Unsafe destination');
    $ch=curl_init($s['url']);$body='';
    try{
        if(!curl_setopt_array($ch,[CURLOPT_HTTPGET=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,CURLOPT_RESOLVE=>[$s['host'].':443:'.$ips[0]],CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Accept: application/json','Authorization: Bearer '.$s['bearer'],'Cache-Control: no-store'],CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>8192)return 0;$body.=$chunk;return strlen($chunk);}]))throw new RuntimeException('Transport unavailable');
        if(curl_exec($ch)!==true||curl_getinfo($ch,CURLINFO_RESPONSE_CODE)!==200||strtolower(trim(explode(';',(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE))[0]))!=='application/json')throw new RuntimeException('Response unavailable');
        return $body;
    }finally{curl_close($ch);}
}
function commBridgeDecode(array $src,string $raw,?DateTimeImmutable $now=null):array{
    if(strlen($raw)>8192)throw new RuntimeException('Envelope size');
    $env=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
    if(!is_array($env)||count($env)!==2||!is_string($env['payload_b64']??null)||!is_string($env['signature']??null)||!preg_match('/^[a-f0-9]{64}$/D',$env['signature']))throw new RuntimeException('Envelope invalid');
    $payload=base64_decode($env['payload_b64'],true);
    if($payload===false||strlen($payload)>2048||base64_encode($payload)!==$env['payload_b64']||!hash_equals(hash_hmac('sha256',"aaditechs-communications-v1\n".$payload,$src['key']),$env['signature']))throw new RuntimeException('Signature invalid');
    $r=json_decode($payload,true,8,JSON_THROW_ON_ERROR);
    $keys=['version','generation','generated_utc','channel','day_utc','received','answered','missed'];
    if(!is_array($r)||count($r)!==count($keys)||array_diff($keys,array_keys($r))||$r['version']!==1||$r['generation']!==$src['generation']||$r['channel']!==$src['channel']||!is_string($r['generated_utc']))throw new RuntimeException('Source binding invalid');
    $utc=new DateTimeZone('UTC');$now=($now??new DateTimeImmutable('now',$utc))->setTimezone($utc);
    $at=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$r['generated_utc'],$utc);
    if(!$at||$at->format('Y-m-d\TH:i:s\Z')!==$r['generated_utc']||$at<$now->modify('-24 hours')||$at>$now->modify('+5 minutes')||$r['day_utc']!==$now->modify('-1 day')->format('Y-m-d')||$at<$now->setTime(0,0))throw new RuntimeException('Stale or incomplete day');
    return communicationsValidate(array_intersect_key($r,array_flip(['channel','day_utc','received','answered','missed'])),$now);
}
function commBridgeImport(PDO $db,array $cfg,string $channel,string $raw,?DateTimeImmutable $now=null):bool{
    $src=commBridgeConfig($cfg,$channel);$r=commBridgeDecode($src,$raw,$now);
    $new=communicationsImport($db,$cfg,$r,$now);
    if($new)growthAudit($db,null,null,'comm_bridge_'.$channel,'private_cli');
    return $new;
}
