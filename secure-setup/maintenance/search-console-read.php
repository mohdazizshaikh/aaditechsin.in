<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/search-console-core.php';
require_once __DIR__.'/gmail-renew-lib.php';
function scToken(array $p,callable $exchange):string{
    $r=$exchange(['client_id'=>$p['client_id'],'client_secret'=>$p['client_secret'],'refresh_token'=>$p['refresh_token'],'grant_type'=>'refresh_token']);
    $token=$r['access_token']??null;$life=$r['expires_in']??null;
    if(isset($r['error'])||array_key_exists('refresh_token',$r)||($r['token_type']??null)!=='Bearer'||($r['scope']??null)!=='https://www.googleapis.com/auth/webmasters.readonly'||!is_string($token)||!preg_match('/^[A-Za-z0-9._~+\/=-]{20,4096}$/D',$token)||!is_int($life)||$life<=660||$life>86400)throw new RuntimeException('Unverified read-only grant');
    return $token;
}
function scRequest(array $p,string $token,string $resource,?array $body=null):array{
    if(PHP_SAPI!=='cli'||!function_exists('curl_init')||!in_array($resource,['site','query'],true))throw new RuntimeException('Transport unavailable');
    $url='https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode($p['property']).($resource==='query'?'/searchAnalytics/query':'');
    $raw='';$ch=curl_init($url);
    try{
        $opts=[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json','Content-Type: application/json'],CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$raw):int{if(strlen($raw)+strlen($chunk)>32768)return 0;$raw.=$chunk;return strlen($chunk);}];
        if($resource==='query'){$opts[CURLOPT_POST]=true;$opts[CURLOPT_POSTFIELDS]=json_encode($body,JSON_THROW_ON_ERROR);}else $opts[CURLOPT_HTTPGET]=true;
        if(!curl_setopt_array($ch,$opts)||curl_exec($ch)!==true||curl_getinfo($ch,CURLINFO_RESPONSE_CODE)!==200)throw new RuntimeException('Read unavailable');
        $r=strictJsonDecode($raw,true,16);if(!is_array($r))throw new RuntimeException('Invalid response');return $r;
    }finally{curl_close($ch);}
}
function scCollect(array $p,array $window,callable $exchange,callable $read):array{
    $token=scToken($p,$exchange);
    $site=$read($p,$token,'site',null);
    if(($site['siteUrl']??null)!==$p['property']||!in_array($site['permissionLevel']??null,['siteOwner','siteFullUser','siteRestrictedUser'],true))throw new RuntimeException('Property access unverified');
    $r=$read($p,$token,'query',['startDate'=>$window['start'],'endDate'=>$window['end'],'dimensions'=>['date'],'type'=>'web','dataState'=>'final','aggregationType'=>'byProperty','rowLimit'=>7]);
    scParse($r,$window);return $r;
}
