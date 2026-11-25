<?php
declare(strict_types=1);
require_once __DIR__.'/renewal-orchestrator-lib.php';
require_once __DIR__.'/preflight-rules.php';
function activationBytes(string $path,int $cap):string{
    clearstatcache(true,$path);$st=@lstat($path);
    if(!$st||($st['mode']&0170000)!==0100000||($st['mode']&0777)!==0600||$st['nlink']!==1||$st['size']<1||$st['size']>$cap)throw new RuntimeException('Private file required');
    $f=@fopen($path,'rb');if(!$f)throw new RuntimeException('Private file unavailable');
    try{$a=fstat($f);if(!$a||$a['ino']!==$st['ino']||$a['dev']!==$st['dev'])throw new RuntimeException('File changed');$raw=stream_get_contents($f,$cap+1);$b=fstat($f);}
    finally{fclose($f);}
    clearstatcache(true,$path);$c=@lstat($path);
    if(!is_string($raw)||strlen($raw)!==$st['size']||!$b||!$c)throw new RuntimeException('File changed');
    foreach(['ino','dev','size','mtime','ctime','mode','nlink'] as $k)if($st[$k]!==$b[$k]||$st[$k]!==$c[$k])throw new RuntimeException('File changed');
    return $raw;
}
function activationValue(mixed $v,int $depth=0):void{
    if($depth>32)throw new RuntimeException('Configuration depth');
    if(is_array($v)){foreach($v as $child)activationValue($child,$depth+1);return;}
    if(!is_null($v)&&!is_bool($v)&&!is_int($v)&&!is_string($v)&&!(is_float($v)&&is_finite($v)))throw new RuntimeException('Static configuration only');
}
function activationConfigBytes(array $cfg):string{
    activationValue($cfg);return "<?php\ndeclare(strict_types=1);\nreturn ".var_export($cfg,true).";\n";
}
function activationConfig(string $path,string $webroot):array{
    if(!preflightPrivateConfig($webroot,dirname($path),$path)||(fileperms(dirname($path))&0777)!==0700)throw new RuntimeException('Canonical private configuration required');
    $raw=activationBytes($path,524288);
    // Owner-maintained trusted configuration only; never require user input or candidate files.
    if(function_exists('opcache_invalidate'))@opcache_invalidate($path,true);
    $cfg=require $path;
    if(!is_array($cfg)||activationConfigBytes($cfg)!==$raw||activationBytes($path,524288)!==$raw)throw new RuntimeException('Reviewed static-array format required');
    return ['config'=>$cfg,'bytes'=>$raw];
}
function activationStage(array $cfg,string $origin):void{
    if(!renewalEnabled($cfg)||($cfg['oauth_lifecycle']['activation_enabled']??false)!==true)throw new RuntimeException('Activation disabled');
    $host=parse_url($origin,PHP_URL_HOST);
    if(!is_string($host)||!preg_match('~^https://[a-z0-9](?:[a-z0-9.-]*[a-z0-9])$~D',$origin)||!str_contains($host,'.')||!filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)||filter_var($host,FILTER_VALIDATE_IP)||in_array($host,['aaditechs.in','www.aaditechs.in','aaditechsin.in','www.aaditechsin.in'],true)||($cfg['public_base_url']??null)!==$origin)throw new RuntimeException('Exact isolated staging origin required');
}
function activationJson(string $raw):array{
    $v=strictJsonDecode($raw,true,16);if(!is_array($v))throw new RuntimeException('Private JSON object required');return $v;
}
function activationNetwork(array $g,array $item,string $dir,int $now):array{
    $p=$g['provider'];$marker=activationBytes($dir.'/'.$p.'-exchange-attempt.json',65536);$receipt=activationBytes($dir.'/'.$p.'-unverified-exchange.json',65536);
    $m=activationJson($marker);$r=activationJson($receipt);
    if(count($m)!==5||($m['version']??null)!==1||($m['provider']??null)!==$p||($m['generation']??null)!==$g['generation']||($m['state']??null)!=='attempt_started'||count($r)!==5||($r['version']??null)!==1||($r['provider']??null)!==$p||($r['generation']??null)!==$g['generation']||($r['state']??null)!=='unverified'||!is_array($r['response']??null))throw new RuntimeException('Journal binding');
    $start=metaRenewDate($m['attempt_utc']??null);if($start>$now||$start<$now-61*86400)throw new RuntimeException('Attempt time');
    $parsed=networkRenewResponse($g,$r['response'],$start);
    if(!hash_equals($parsed['item']['access_token'],$item['access_token'])||$parsed['item']['expires_utc']!==$item['expires_utc'])throw new RuntimeException('Candidate response mismatch');
    return ['refresh_token'=>$r['response']['refresh_token'],'refresh_expires_utc'=>$parsed['refresh_expires_utc'],'bindings'=>[$marker,$receipt]];
}
function activationPrepare(array $loaded,string $scope,string $key,string $origin,string $webroot,string $backupDir,int $now):array{
    $cfg=$loaded['config'];activationStage($cfg,$origin);
    if(strlen($key)!==32||activationConfigBytes($cfg)!==$loaded['bytes'])throw new RuntimeException('Exact config required');
    socialRenewDirectory($backupDir,$webroot);
    $providers=oauthBatchScope($scope);$next=$cfg;$rows=[];$bindings=[];$targets=[];$seen=[];
    foreach($providers as $p){
        $g=renewalGrant($cfg,$p,$now);$dir=socialRenewDirectory($g['directory'],$webroot);
        if($dir===$backupDir||str_starts_with($dir,$backupDir.'/')||str_starts_with($backupDir,$dir.'/'))throw new RuntimeException('Separate backup directory required');
        $raw=activationBytes($dir.'/'.$p.'-rotation-candidate.json',32768);
        $doc=activationJson($raw);
        if(count($doc)!==2||($doc['version']??null)!==1||!is_array($doc['items']??null)||!array_is_list($doc['items'])||count($doc['items'])!==1||($doc['items'][0]['provider']??null)!==$p)throw new RuntimeException('Exact provider candidate');
        $item=$doc['items'][0];$prepared=oauthRotationPrepare($cfg,$raw,$key,$now);
        if(isset($seen[$item['generation']]))throw new RuntimeException('Duplicate generation');$seen[$item['generation']]=true;
        $path=oauthRotationPath($p);$old=oauthRotationGet($next,$path);
        oauthRotationSet($next,$path,array_replace($old,['access_token'=>$item['access_token']]));
        $next['oauth_lifecycle']['providers'][$p]=['generation'=>$item['generation'],'expires_utc'=>$item['expires_utc'],'revoked'=>false];
        $family=renewalFamily($p);$extra=[];
        if($family==='network'){
            $n=activationNetwork($g,$item,$dir,$now);$extra=$n['bindings'];
            $next['oauth_lifecycle']['network_renewal'][$p]['refresh_token']=$n['refresh_token'];
            $next['oauth_lifecycle']['network_renewal'][$p]['refresh_expires_utc']=$n['refresh_expires_utc'];
            $next['oauth_lifecycle']['network_renewal'][$p]['generation']=substr(hash_hmac('sha256','renewal-grant:'.$item['generation'],$key),0,32);
        }
        // Do not infer Threads issue time or provider expiry; suspend this renewal grant until reviewed.
        if($p==='threads'){$next['oauth_lifecycle']['meta_renewal'][$p]['enabled']=false;$next['oauth_lifecycle']['meta_renewal'][$p]['authorized']=false;}
        $rows[]=['provider'=>$p,'candidate_expires_utc'=>$item['expires_utc'],'refresh_reconciled'=>$family==='network','renewal_suspended'=>$p==='threads'];
        $bindings[]=[$p,$g,$raw,$extra];$targets[$p]=$prepared['private'][0]['target'];
    }
    $bytes=activationConfigBytes($next);
    $digest=hash_hmac('sha256',json_encode(['activation-v1',$origin,$backupDir,$loaded['bytes'],$bytes,$bindings],JSON_THROW_ON_ERROR),$key);
    return ['plan'=>['providers'=>$rows,'sha256'=>$digest,'activation_authorized'=>false],'bytes'=>$bytes,'targets'=>$targets,'config'=>$next];
}
function activationWrite(string $path,string $raw):void{
    $old=umask(0077);try{$f=@fopen($path,'xb');}finally{umask($old);}
    if(!$f)throw new RuntimeException('Existing private file');
    try{if(!chmod($path,0600)||fwrite($f,$raw)!==strlen($raw)||!fflush($f)||(function_exists('fsync')&&!fsync($f)))throw new RuntimeException('Private write failed');}finally{fclose($f);}
}
function activationApply(PDO $db,string $path,string $scope,string $key,string $expected,string $origin,string $webroot,string $backupDir,callable $probe,?callable $clock=null):array{
    if($db->inTransaction()||!preg_match('/^[a-f0-9]{64}$/D',$expected))throw new InvalidArgumentException('Explicit reviewed plan required');
    $clock=$clock??static fn()=>time();$last=$clock();$loaded=activationConfig($path,$webroot);$database=$loaded['config']['database']??null;
    // Single configuration writer; provider locks exclude individual acquisition and batch dispatch.
    $locks=[];$tmp=null;$backup=null;
    try{
        foreach(array_merge(['aaditechs-renewal-orchestrator','aaditechs-oauth-activation'],array_map(static fn($p)=>'aaditechs-social-renew-'.$p,oauthProviders())) as $lock){
            $q=$db->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);if((string)$q->fetchColumn()!=='1')throw new RuntimeException('Worker busy');$locks[]=$lock;
        }
        $fresh=static function()use($path,$scope,$key,$expected,$origin,$webroot,$backupDir,$clock,$database,&$last):array{
            $now=$clock();if(!is_int($now)||$now<$last)throw new RuntimeException('Clock moved backward');$last=$now;
            $current=activationConfig($path,$webroot);
            if(($current['config']['database']??null)!==$database)throw new RuntimeException('Database changed');
            $p=activationPrepare($current,$scope,$key,$origin,$webroot,$backupDir,$now);
            if(!hash_equals($expected,$p['plan']['sha256']))throw new RuntimeException('Reviewed activation changed');return $p;
        };
        $boundary=static function()use($db,$fresh):array{$db->beginTransaction();try{oauthBatchPaused($db);$p=$fresh();$db->commit();return $p;}catch(Throwable $e){$db->rollBack();throw $e;}};
        $p=$boundary();
        foreach($p['targets'] as $provider=>$target){
            $boundary();if($probe($provider,$target,$boundary)!==true)throw new RuntimeException('Identity not verified');$boundary();
        }
        $db->beginTransaction();oauthBatchPaused($db);$p=$fresh();
        $backup=$backupDir.'/oauth-backup-'.bin2hex(random_bytes(16)).'.php';
        activationWrite($backup,$loaded['bytes']);if(activationBytes($backup,524288)!==$loaded['bytes'])throw new RuntimeException('Backup verification');
        $tmp=dirname($path).'/.oauth-next-'.bin2hex(random_bytes(16)).'.php';
        activationWrite($tmp,$p['bytes']);if(activationBytes($tmp,524288)!==$p['bytes'])throw new RuntimeException('Replacement verification');
        $final=$fresh();if($final['bytes']!==$p['bytes']||activationBytes($path,524288)!==$loaded['bytes'])throw new RuntimeException('Config drift');
        if(!rename($tmp,$path))throw new RuntimeException('Config replacement failed');$tmp=null;
        if(function_exists('opcache_invalidate'))@opcache_invalidate($path,true);
        if(activationBytes($path,524288)!==$p['bytes'])throw new RuntimeException('Replacement reconciliation required');
        $db->commit();
        return ['status'=>'activated_on_staging','providers'=>array_keys($p['targets']),'backup_created'=>true,'publication_authorized'=>false,'journals_retained'=>true];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    finally{if($tmp!==null&&is_file($tmp))unlink($tmp);foreach(array_reverse($locks) as $lock){$q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);$q->fetchColumn();}}
}
