<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/oauth-health-core.php';
function oauthRotationKey(string $raw):string{
    if(!preg_match('/^[a-f0-9]{64}$/D',$raw)||$raw===str_repeat('0',64))throw new InvalidArgumentException('Private random key required');
    return hex2bin($raw);
}
function oauthRotationPath(string $provider):array{
    return match($provider){
        'facebook','threads'=>['social','meta',$provider],
        'linkedin','x','google-business'=>['social','network',$provider],
        'instagram','youtube'=>['media_upload',$provider],
        default=>throw new InvalidArgumentException('Unknown provider')
    };
}
function oauthRotationGet(array $cfg,array $path):array{
    $v=$cfg;foreach($path as $part){if(!is_array($v)||!array_key_exists($part,$v))throw new RuntimeException('Missing provider');$v=$v[$part];}
    if(!is_array($v))throw new RuntimeException('Missing provider');return $v;
}
function oauthRotationSet(array &$cfg,array $path,array $value):void{
    $ref=&$cfg;foreach($path as $part)$ref=&$ref[$part];$ref=$value;
}
function oauthRotationPrepare(array $cfg,string $raw,string $key,?int $now=null):array{
    $now=$now??time();
    if(($cfg['growth']['enabled']??false)!==true||($cfg['oauth_lifecycle']['enabled']??false)!==true||($cfg['oauth_lifecycle']['rotation_review_enabled']??false)!==true||strlen($key)!==32)throw new RuntimeException('Rotation review disabled');
    if(strlen($raw)>32768)throw new InvalidArgumentException('Scope cap');
    $doc=strictJsonDecode($raw,false,8);
    if(!$doc instanceof stdClass)throw new InvalidArgumentException('Object required');
    $d=get_object_vars($doc);
    if(count($d)!==2||($d['version']??null)!==1||!is_array($d['items']??null)||count($d['items'])<1||count($d['items'])>7)throw new InvalidArgumentException('Scope');
    $seen=[];$generations=[];$rows=[];$private=[];$bindings=[];
    foreach($d['items'] as $o){
        if(!$o instanceof stdClass)throw new InvalidArgumentException('Candidate object required');
        $v=get_object_vars($o);$keys=array_keys($v);sort($keys);
        if($keys!==['access_token','expires_utc','generation','provider']||!is_string($v['provider'])||!in_array($v['provider'],oauthProviders(),true)||isset($seen[$v['provider']]))throw new InvalidArgumentException('Provider scope');
        $provider=$v['provider'];$seen[$provider]=true;
        $old=oauthPolicy($cfg,$provider,$now);
        if(!in_array($old['state'],['expired','too_close','renew_soon','current'],true)||!oauthWatched($cfg,$provider))throw new RuntimeException('Existing provider not eligible; revoked requires separate reauthorization');
        $gen=$v['generation'];$token=$v['access_token'];$expiry=$v['expires_utc'];
        if(!is_string($gen)||!preg_match('/^[a-f0-9]{32}$/D',$gen)||$gen===str_repeat('0',32)||isset($generations[$gen])||!is_string($token)||!is_string($expiry))throw new InvalidArgumentException('Candidate fields');
        foreach(oauthProviders() as $other)if($gen===($cfg['oauth_lifecycle']['providers'][$other]['generation']??null))throw new InvalidArgumentException('Generation reused');
        $generations[$gen]=true;
        $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$expiry,new DateTimeZone('UTC'));
        if(!$date||$date->format('Y-m-d\TH:i:s\Z')!==$expiry||$date->getTimestamp()<=$now+600||$date->getTimestamp()>$now+90*86400)throw new InvalidArgumentException('Candidate review deadline');
        $path=oauthRotationPath($provider);$oldTarget=oauthRotationGet($cfg,$path);
        if(!is_string($oldTarget['access_token']??null)||hash_equals($oldTarget['access_token'],$token))throw new InvalidArgumentException('Distinct replacement credential required');
        $next=$cfg;$next['oauth_lifecycle']['providers'][$provider]=['generation'=>$gen,'expires_utc'=>$expiry,'revoked'=>false];
        // Verify old destination shape using the new review window without probing old credentials.
        $original=oauthTarget($next,$provider);
        oauthRotationSet($next,$path,array_replace($oldTarget,['access_token'=>$token]));
        $candidate=oauthTarget($next,$provider);
        if(!$original||!$candidate)throw new RuntimeException('Invalid target configuration');
        $a=$original;$b=$candidate;unset($a['token'],$b['token']);
        if($a!==$b)throw new RuntimeException('Destination changed');
        $rows[]=['provider'=>$provider,'previous_policy'=>$old['state'],'candidate_expires_utc'=>$expiry];
        $private[]=['provider'=>$provider,'target'=>$candidate];
        $bindings[]=[$provider,$cfg['oauth_lifecycle']['providers'][$provider],$original,$gen,$expiry,$candidate];
    }
    $digest=hash_hmac('sha256',json_encode(['oauth-rotation-review-v1',$bindings],JSON_THROW_ON_ERROR),$key);
    return ['plan'=>['providers'=>$rows,'sha256'=>$digest,'activation_authorized'=>false],'private'=>$private];
}
function oauthRotationPlan(array $cfg,string $raw,string $key,?int $now=null):array{
    return oauthRotationPrepare($cfg,$raw,$key,$now)['plan'];
}
function oauthRotationVerify(callable $config,string $raw,string $key,string $expected,callable $paused,callable $probe):array{
    if(!preg_match('/^[a-f0-9]{64}$/D',$expected))throw new InvalidArgumentException('Reviewed digest required');
    $fresh=static function()use($config,$raw,$key,$expected,$paused):array{
        if($paused()!==true)throw new RuntimeException('Pause required');
        $p=oauthRotationPrepare($config(),$raw,$key);
        if(!hash_equals($expected,$p['plan']['sha256']))throw new RuntimeException('Reviewed rotation changed');
        return $p;
    };
    $initial=$fresh();$out=[];
    foreach($initial['private'] as $i=>$unused){
        $p=$fresh()['private'][$i];$provider=$p['provider'];
        try{$state=$probe($provider,$p['target'])===true?'identity_verified':'not_verified';}catch(Throwable $e){$state='error';}
        $fresh(); // Refuse evidence if pause/config changed during the call.
        $out[]=['provider'=>$provider,'state'=>$state];
        if($state!=='identity_verified')break;
    }
    return ['planned'=>count($initial['private']),'checked'=>count($out),'results'=>$out,'complete'=>count($out)===count($initial['private'])&&end($out)['state']==='identity_verified','activation_authorized'=>false];
}
