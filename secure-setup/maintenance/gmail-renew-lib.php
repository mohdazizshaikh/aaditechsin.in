<?php
declare(strict_types=1);
require_once __DIR__.'/gmail-health-read.php';
function gmailRenewConfig(array $cfg):array{
    $p=$cfg['communications']['gmail']??[];$r=$p['renewal']??[];
    if(!communicationsEnabled($cfg)||($p['enabled']??false)!==true||($r['enabled']??false)!==true||($p['revoked']??false)!==false||!is_string($p['mailbox']??null)||!filter_var($p['mailbox'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Renewal disabled');
    foreach(['client_id','client_secret','refresh_token'] as $k)if(!is_string($r[$k]??null)||!preg_match('/^[A-Za-z0-9._~+\/=-]{8,4096}$/D',$r[$k])||str_starts_with($r[$k],'REPLACE_'))throw new RuntimeException('Invalid credential');
    return ['mailbox'=>$p['mailbox'],'client_id'=>$r['client_id'],'client_secret'=>$r['client_secret'],'refresh_token'=>$r['refresh_token']];
}
function gmailRenewRequest(array $data):array{
    if(PHP_SAPI!=='cli'||!function_exists('curl_init'))throw new RuntimeException('Transport unavailable');
    $ch=curl_init('https://oauth2.googleapis.com/token');$body='';
    try{
        if(!curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($data,'','&',PHP_QUERY_RFC3986),CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded','Accept: application/json'],CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>16384)return 0;$body.=$chunk;return strlen($chunk);}]))throw new RuntimeException('Transport unavailable');
        if(curl_exec($ch)!==true||curl_getinfo($ch,CURLINFO_RESPONSE_CODE)!==200)throw new RuntimeException('Renewal unverified');
        $r=json_decode($body,true,16,JSON_THROW_ON_ERROR);if(!is_array($r))throw new RuntimeException('Invalid response');return $r;
    }finally{curl_close($ch);}
}
function gmailRenewCandidate(array $cfg,callable $exchange,callable $read,?int $now=null):array{
    $p=gmailRenewConfig($cfg);$now=$now??time();
    $r=$exchange(['client_id'=>$p['client_id'],'client_secret'=>$p['client_secret'],'refresh_token'=>$p['refresh_token'],'grant_type'=>'refresh_token']);
    $token=$r['access_token']??null;$life=$r['expires_in']??null;
    // No assumed scope or acceptance of a broader grant. Unexpected refresh-token rotation needs operator review.
    if(isset($r['error'])||array_key_exists('refresh_token',$r)||($r['token_type']??null)!=='Bearer'||($r['scope']??null)!=='https://www.googleapis.com/auth/gmail.metadata'||!is_string($token)||!preg_match('/^[A-Za-z0-9._~+\/=-]{20,4096}$/D',$token)||!is_int($life)||$life<=660||$life>86400)throw new RuntimeException('Unverified grant');
    gmailCollect(['mailbox'=>$p['mailbox'],'token'=>$token],$read);
    return ['access_token'=>$token,'generation'=>bin2hex(random_bytes(16)),'expires_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now+$life-60),'revoked'=>false];
}
function gmailRenewDirectory(array $cfg,string $webroot):string{
    $path=$cfg['communications']['gmail']['renewal']['candidate_directory']??'';
    if(!is_string($path)||$path===''||is_link($path))throw new RuntimeException('Private directory required');
    $dir=realpath($path);$web=realpath($webroot);
    if($dir===false||$web===false||!is_dir($dir)||$dir===$web||str_starts_with($dir,$web.'/')||str_starts_with($web,$dir.'/')||(fileperms($dir)&0777)!==0700)throw new RuntimeException('Private directory required');
    return $dir;
}
function gmailRenewStore(string $dir,array $candidate):void{
    $path=$dir.'/gmail-renewal-candidate.json';$old=umask(0077);
    try{$f=@fopen($path,'xb');}finally{umask($old);}
    if(!$f)throw new RuntimeException('Existing candidate; inspect privately');
    try{if(!chmod($path,0600))throw new RuntimeException('Permissions');$raw=json_encode($candidate,JSON_THROW_ON_ERROR);if(fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('Incomplete candidate');}finally{fclose($f);}
}
