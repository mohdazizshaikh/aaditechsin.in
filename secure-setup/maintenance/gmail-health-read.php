<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/gmail-health-core.php';
function gmailRead(array $p,string $resource):array{
    if(PHP_SAPI!=='cli'||!function_exists('curl_init')||!in_array($resource,['profile','labels/INBOX'],true)||!preg_match('/^[A-Za-z0-9._~+\/=-]{20,4096}$/D',$p['token']??''))throw new RuntimeException('Invalid request');
    $fields=$resource==='profile'?'emailAddress':'id,type,messagesTotal,messagesUnread';
    $ch=curl_init('https://gmail.googleapis.com/gmail/v1/users/me/'.$resource.'?fields='.rawurlencode($fields));$body='';
    try{
        if(!curl_setopt_array($ch,[CURLOPT_HTTPGET=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$p['token'],'Accept: application/json'],CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>16384)return 0;$body.=$chunk;return strlen($chunk);}]))throw new RuntimeException('Transport unavailable');
        if(curl_exec($ch)!==true||curl_getinfo($ch,CURLINFO_RESPONSE_CODE)!==200)throw new RuntimeException('Read unverified');
        $r=json_decode($body,true,16,JSON_THROW_ON_ERROR);if(!is_array($r))throw new RuntimeException('Invalid response');return $r;
    }finally{curl_close($ch);}
}
function gmailCollect(array $p,callable $read):array{
    $r=$read($p,'profile');if(!is_string($r['emailAddress']??null)||!hash_equals($p['mailbox'],$r['emailAddress']))throw new RuntimeException('Wrong mailbox');
    return gmailCounts($read($p,'labels/INBOX'));
}
