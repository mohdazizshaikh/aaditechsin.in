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

function gmailCandidateRead(array $cfg,string $dir,?int $now=null):array{
    gmailRenewConfig($cfg);$path=$dir.'/gmail-renewal-candidate.json';
    clearstatcache(true,$path);$st=@lstat($path);
    if(!$st||($st['mode']&0170000)!==0100000||($st['mode']&0777)!==0600||$st['nlink']!==1||$st['size']<2||$st['size']>8192)throw new RuntimeException('Private candidate required');
    $f=@fopen($path,'rb');if(!$f)throw new RuntimeException('Candidate unavailable');
    try{$opened=fstat($f);if(!$opened||$opened['ino']!==$st['ino']||$opened['dev']!==$st['dev'])throw new RuntimeException('Candidate changed');$raw=stream_get_contents($f,8193);$after=fstat($f);}finally{fclose($f);}
    if(!is_string($raw)||strlen($raw)!==$st['size']||!$after||$after['size']!==$st['size']||$after['mtime']!==$st['mtime']||$after['ctime']!==$st['ctime'])throw new RuntimeException('Candidate changed');
    $r=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
    if(!is_array($r)||count($r)!==4||array_diff(['access_token','generation','expires_utc','revoked'],array_keys($r))||($r['revoked']??null)!==false||$r['generation']===($cfg['communications']['gmail']['generation']??null))throw new RuntimeException('Invalid candidate');
    $next=$cfg;$next['communications']['gmail']=array_replace($next['communications']['gmail'],$r);
    $p=gmailConfig($next,$now);if(!$p)throw new RuntimeException('Candidate expired or invalid');
    return ['digest'=>hash('sha256',$raw),'expires_utc'=>$r['expires_utc'],'target'=>$p];
}
function gmailCandidateVerify(array $cfg,string $dir,string $expected,callable $read,?int $now=null):array{
    $r=gmailCandidateRead($cfg,$dir,$now);
    if(!preg_match('/^[a-f0-9]{64}$/D',$expected)||!hash_equals($r['digest'],$expected))throw new RuntimeException('Candidate digest changed');
    gmailCollect($r['target'],$read);
    $after=gmailCandidateRead($cfg,$dir,$now);if(!hash_equals($r['digest'],$after['digest']))throw new RuntimeException('Candidate changed');
    return ['digest'=>$r['digest'],'expires_utc'=>$r['expires_utc']];
}
