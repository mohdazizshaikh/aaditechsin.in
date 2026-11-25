<?php
declare(strict_types=1);
require_once __DIR__.'/oauth-rotation-lib.php';
require_once __DIR__.'/gmail-renew-lib.php';
function socialRenewScopes(string $provider):array{
    return match($provider){
        'google-business'=>['https://www.googleapis.com/auth/business.manage'],
        'youtube'=>['https://www.googleapis.com/auth/youtube.readonly','https://www.googleapis.com/auth/youtube.upload'],
        default=>throw new InvalidArgumentException('Unsupported renewal provider')
    };
}
function socialRenewGrant(array $cfg,string $provider,int $now):array{
    $scopes=socialRenewScopes($provider);
    $l=$cfg['oauth_lifecycle']??[];$r=$l['renewal'][$provider]??[];
    if(($l['enabled']??false)!==true||($l['rotation_review_enabled']??false)!==true||($l['automatic_candidate_enabled']??false)!==true||($r['enabled']??false)!==true||($r['authorized']??false)!==true||!oauthWatched($cfg,$provider))throw new RuntimeException('Renewal disabled');
    $policy=oauthPolicy($cfg,$provider,$now);
    if(!in_array($policy['state'],['expired','too_close','renew_soon','current'],true))throw new RuntimeException('Revoked or unverified policy');
    $deadline=$r['review_expires_utc']??null;
    $date=is_string($deadline)?DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$deadline,new DateTimeZone('UTC')):false;
    if(!$date||$date->format('Y-m-d\TH:i:s\Z')!==$deadline||$date->getTimestamp()<=$now+660||$date->getTimestamp()>$now+90*86400)throw new RuntimeException('Reviewed grant deadline');
    $gen=$r['generation']??null;
    if(!is_string($gen)||!preg_match('/^[a-f0-9]{32}$/D',$gen)||$gen===str_repeat('0',32))throw new RuntimeException('Grant generation');
    foreach(['client_id','client_secret','refresh_token'] as $k)if(!is_string($r[$k]??null)||!preg_match('/^[A-Za-z0-9._~+\/=-]{8,4096}$/D',$r[$k])||str_starts_with($r[$k],'REPLACE_'))throw new RuntimeException('Invalid credential');
    $targetCfg=oauthRotationGet($cfg,oauthRotationPath($provider));
    $next=$cfg;$next['oauth_lifecycle']['providers'][$provider]['expires_utc']=$deadline;
    $target=oauthTarget($next,$provider);
    if(!$target||($r['target_id']??null)!==$target['id'])throw new RuntimeException('Exact reviewed destination required');
    $dir=$r['candidate_directory']??null;
    if(!is_string($dir)||$dir==='')throw new RuntimeException('Private directory required');
    return ['provider'=>$provider,'grant_generation'=>$gen,'review_expires_utc'=>$deadline,'client_id'=>$r['client_id'],'client_secret'=>$r['client_secret'],'refresh_token'=>$r['refresh_token'],'target'=>$target,'policy'=>$l['providers'][$provider],'target_config'=>$targetCfg,'scopes'=>$scopes,'directory'=>$dir];
}
function socialRenewToken(array $g,array $r,int $start):array{
    $scope=$r['scope']??null;$token=$r['access_token']??null;$life=$r['expires_in']??null;
    if(array_key_exists('error',$r)||array_key_exists('refresh_token',$r)||($r['token_type']??null)!=='Bearer'||!is_string($scope)||strlen($scope)>1024||!is_string($token)||!preg_match('/^[A-Za-z0-9._~+\/=-]{20,4096}$/D',$token)||str_starts_with($token,'REPLACE_')||hash_equals($g['target']['token'],$token)||!is_int($life)||$life<=720||$life>86400)throw new RuntimeException('Unverified replacement');
    $actual=explode(' ',$scope);sort($actual,SORT_STRING);$expected=$g['scopes'];sort($expected,SORT_STRING);
    if($actual!==$expected)throw new RuntimeException('Scope mismatch');
    $expiry=min($start+$life-60,strtotime($g['review_expires_utc']));
    if($expiry<=$start+660)throw new RuntimeException('Insufficient token window');
    return ['provider'=>$g['provider'],'access_token'=>$token,'generation'=>bin2hex(random_bytes(16)),'expires_utc'=>gmdate('Y-m-d\TH:i:s\Z',$expiry)];
}
function socialRenewDirectory(string $dir,string $webroot):string{
    $real=realpath($dir);$web=realpath($webroot);
    if($real===false||$web===false||$real!==$dir||is_link($dir)||!is_dir($dir)||($real===$web)||str_starts_with($real,$web.'/')||str_starts_with($web,$real.'/')||(fileperms($dir)&0777)!==0700)throw new RuntimeException('Private directory required');
    return $real;
}
function socialRenewPath(string $dir,string $provider):string{
    socialRenewScopes($provider);return $dir.'/'.$provider.'-rotation-candidate.json';
}
function socialRenewStore(string $path,array $item):void{
    $tmp=dirname($path).'/.renew-'.bin2hex(random_bytes(16));$old=umask(0077);
    try{$f=@fopen($tmp,'xb');}finally{umask($old);}
    if(!$f)throw new RuntimeException('Private candidate unavailable');
    try{
        $raw=json_encode(['version'=>1,'items'=>[$item]],JSON_THROW_ON_ERROR)."\n";
        if(!chmod($tmp,0600)||fwrite($f,$raw)!==strlen($raw)||!fflush($f))throw new RuntimeException('Candidate write failed');
        fclose($f);$f=null;
        // Non-overwriting final name; same filesystem. Never overwrite old candidates.
        if(!@link($tmp,$path))throw new RuntimeException('Existing candidate; inspect privately');
    }finally{if(is_resource($f))fclose($f);if(is_file($tmp))unlink($tmp);}
}
function socialRenewRun(PDO $db,callable $config,string $provider,callable $exchange,callable $probe,?callable $clock=null):array{
    if($db->inTransaction())throw new LogicException('Independent transaction required');
    $clock=$clock??static fn()=>time();$start=$clock();$cfg=$config();$g=socialRenewGrant($cfg,$provider,$start);$database=$cfg['database']??null;
    $dir=socialRenewDirectory($g['directory'],dirname(__DIR__,2).'/public_html');$path=socialRenewPath($dir,$provider);
    $lock='aaditechs-social-renew-'.$provider;
    $q=$db->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);if((string)$q->fetchColumn()!=='1')return ['status'=>'busy'];
    try{
        $last=$start;
        $fresh=static function()use($config,$clock,$g,$provider,$database,$dir,$db,&$last):array{
            $now=$clock();if(!is_int($now)||$now<$last)throw new RuntimeException('Clock moved backward');$last=$now;
            $c=$config();
            if(($c['database']??null)!==$database||socialRenewGrant($c,$provider,$now)!==$g||socialRenewDirectory($g['directory'],dirname(__DIR__,2).'/public_html')!==$dir)throw new RuntimeException('Grant or database changed');
            $db->beginTransaction();
            try{oauthBatchPaused($db);$db->commit();}catch(Throwable $e){$db->rollBack();throw $e;}
            return $c;
        };
        $fresh();clearstatcache(true,$path);
        if(file_exists($path)||is_link($path))return ['status'=>'candidate_exists','activation_authorized'=>false];
        if(strtotime($g['policy']['expires_utc'])>$start+900)return ['status'=>'not_due','activation_authorized'=>false];
        $r=$exchange(['client_id'=>$g['client_id'],'client_secret'=>$g['client_secret'],'refresh_token'=>$g['refresh_token'],'grant_type'=>'refresh_token']);
        if(!is_array($r))throw new RuntimeException('Invalid exchange');
        $item=socialRenewToken($g,$r,$start);
        $next=$fresh();
        if(strtotime($item['expires_utc'])<=$last+600)throw new RuntimeException('Candidate window elapsed');
        $plan=oauthRotationPrepare($next,json_encode(['version'=>1,'items'=>[$item]],JSON_THROW_ON_ERROR),random_bytes(32),$last);
        $target=$plan['private'][0]['target'];
        if($probe($provider,$target)!==true)throw new RuntimeException('Identity unverified');
        $next=$fresh();
        if(strtotime($item['expires_utc'])<=$last+600)throw new RuntimeException('Candidate window elapsed');
        // Hold the pause row through the local file creation; no HTTP under a transaction.
        $db->beginTransaction();oauthBatchPaused($db);
        $finalTime=$clock();$final=$config();
        if(!is_int($finalTime)||$finalTime<$last||strtotime($item['expires_utc'])<=$finalTime+600||($final['database']??null)!==$database||socialRenewGrant($final,$provider,$finalTime)!==$g||socialRenewDirectory($g['directory'],dirname(__DIR__,2).'/public_html')!==$dir)throw new RuntimeException('Grant changed');
        socialRenewStore($path,$item);$db->commit();
        return ['status'=>'candidate_created','provider'=>$provider,'expires_utc'=>$item['expires_utc'],'activation_authorized'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    finally{$q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);$q->fetchColumn();}
}
