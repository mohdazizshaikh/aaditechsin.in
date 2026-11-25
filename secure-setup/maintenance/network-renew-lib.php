<?php
declare(strict_types=1);
require_once __DIR__.'/meta-renew-lib.php';
function networkRenewSecret(mixed $v):bool{return is_string($v)&&preg_match('/^[A-Za-z0-9._~+\/=-]{8,4096}$/D',$v)===1&&!str_starts_with($v,'REPLACE_');}
function networkRenewGrant(array $cfg,string $provider,int $now):array{
    if(!in_array($provider,['linkedin','x'],true))throw new InvalidArgumentException('Provider');
    $l=$cfg['oauth_lifecycle']??[];$r=$l['network_renewal'][$provider]??[];
    if(($l['enabled']??false)!==true||($l['rotation_review_enabled']??false)!==true||($l['network_candidate_enabled']??false)!==true||($r['enabled']??false)!==true||($r['authorized']??false)!==true||!oauthWatched($cfg,$provider))throw new RuntimeException('Disabled');
    $policy=oauthPolicy($cfg,$provider,$now);
    if(!in_array($policy['state'],['expired','too_close','renew_soon','current'],true))throw new RuntimeException('Revoked or unverified');
    $deadline=metaRenewDate($r['review_expires_utc']??null);$refreshExpiry=metaRenewDate($r['refresh_expires_utc']??null);
    if($deadline<=$now+660||$deadline>$now+90*86400||$refreshExpiry<=$now+660||!is_string($r['generation']??null)||!preg_match('/^[a-f0-9]{32}$/D',$r['generation'])||$r['generation']===str_repeat('0',32))throw new RuntimeException('Grant deadline');
    foreach(['client_id','client_secret','refresh_token'] as $k)if(!networkRenewSecret($r[$k]??null))throw new RuntimeException('Credential');
    if(($r['confidential_client_reviewed']??false)!==true||($provider==='linkedin'&&($r['programmatic_refresh_reviewed']??false)!==true))throw new RuntimeException('Client eligibility');
    $next=$cfg;$next['oauth_lifecycle']['providers'][$provider]['expires_utc']=$r['review_expires_utc'];$target=oauthTarget($next,$provider);
    if(!$target||($r['target_id']??null)!==$target['id']||!is_string($r['candidate_directory']??null))throw new RuntimeException('Exact destination');
    $required=$provider==='linkedin'?['rw_organization_admin','w_organization_social']:['offline.access','tweet.read','tweet.write','users.read'];
    $allowed=$provider==='linkedin'?$required:array_merge($required,['media.write']);
    $scopes=$r['reviewed_scopes']??null;
    if(!is_array($scopes)||!array_is_list($scopes)||count($scopes)>count($allowed))throw new RuntimeException('Scope list');
    foreach($scopes as $s)if(!is_string($s))throw new RuntimeException('Scope type');
    if(count(array_unique($scopes))!==count($scopes)||array_diff($required,$scopes)||array_diff($scopes,$allowed))throw new RuntimeException('Scope mismatch');
    sort($scopes,SORT_STRING);
    return ['provider'=>$provider,'target'=>$target,'policy'=>$l['providers'][$provider],'generation'=>$r['generation'],'deadline'=>$deadline,'refresh_expiry'=>$refreshExpiry,'client_id'=>$r['client_id'],'client_secret'=>$r['client_secret'],'refresh_token'=>$r['refresh_token'],'scopes'=>$scopes,'directory'=>$r['candidate_directory']];
}
function networkRenewResponse(array $g,array $r,int $start):array{
    $token=$r['access_token']??null;$refresh=$r['refresh_token']??null;$life=$r['expires_in']??null;$scope=$r['scope']??null;
    if(array_key_exists('error',$r)||!networkRenewSecret($token)||strlen($token)<20||hash_equals($g['target']['token'],$token)||!networkRenewSecret($refresh)||!is_int($life)||$life<=720||$life>61*86400||!is_string($scope)||strlen($scope)>1024)throw new RuntimeException('Token response');
    if($g['provider']==='x'){
        if(!is_string($r['token_type']??null)||strcasecmp($r['token_type'],'bearer')!==0||hash_equals($g['refresh_token'],$refresh)||$life>86400)throw new RuntimeException('X rotated grant required');
    }else{
        if(isset($r['token_type'])&&(!is_string($r['token_type'])||strcasecmp($r['token_type'],'bearer')!==0))throw new RuntimeException('Token type');
        $scope=urldecode($scope);
        if(!is_int($r['refresh_token_expires_in']??null)||$r['refresh_token_expires_in']<=660||$r['refresh_token_expires_in']>366*86400)throw new RuntimeException('Refresh expiry');
    }
    $actual=explode(' ',$scope);sort($actual,SORT_STRING);if($actual!==$g['scopes'])throw new RuntimeException('Scope mismatch');
    $refreshDeadline=$g['refresh_expiry'];
    if($g['provider']==='linkedin')$refreshDeadline=min($refreshDeadline,$start+$r['refresh_token_expires_in']-60);
    // X supplies no independently verified new refresh expiry: never extend the reviewed deadline.
    $expiry=min($start+$life-60,$g['deadline'],$refreshDeadline);
    if($expiry<=$start+660)throw new RuntimeException('Candidate window');
    return ['item'=>['provider'=>$g['provider'],'access_token'=>$token,'generation'=>bin2hex(random_bytes(16)),'expires_utc'=>gmdate('Y-m-d\TH:i:s\Z',$expiry)],'refresh_expires_utc'=>gmdate('Y-m-d\TH:i:s\Z',$refreshDeadline)];
}
function networkRenewRequest(array $g):array{
    if(PHP_SAPI!=='cli'||!function_exists('curl_init'))throw new RuntimeException('CLI transport');
    $data=['grant_type'=>'refresh_token','refresh_token'=>$g['refresh_token']];
    $headers=['Content-Type: application/x-www-form-urlencoded','Accept: application/json'];
    if($g['provider']==='linkedin'){
        $url='https://www.linkedin.com/oauth/v2/accessToken';$data['client_id']=$g['client_id'];$data['client_secret']=$g['client_secret'];
    }elseif($g['provider']==='x'){
        $url='https://api.x.com/2/oauth2/token';$headers[]='Authorization: Basic '.base64_encode($g['client_id'].':'.$g['client_secret']);
    }else throw new RuntimeException('Provider');
    $ch=curl_init($url);$body='';
    try{
        if(!curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($data,'','&',PHP_QUERY_RFC3986),CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>32768)return 0;$body.=$chunk;return strlen($chunk);}])||curl_exec($ch)!==true||curl_getinfo($ch,CURLINFO_RESPONSE_CODE)!==200)throw new RuntimeException('Exchange uncertain');
        $r=strictJsonDecode($body,true,16);if(!is_array($r))throw new RuntimeException('Response');return $r;
    }finally{curl_close($ch);}
}
function networkRenewWrite(string $path,array $data):void{
    $raw=json_encode($data,JSON_THROW_ON_ERROR)."\n";
    if(strlen($raw)>65536)throw new RuntimeException('Journal cap');
    $old=umask(0077);try{$f=@fopen($path,'xb');}finally{umask($old);}
    if(!$f)throw new RuntimeException('Existing journal');
    try{if(!chmod($path,0600)||fwrite($f,$raw)!==strlen($raw)||!fflush($f)||(function_exists('fsync')&&!fsync($f)))throw new RuntimeException('Journal write');}finally{fclose($f);}
}
function networkRenewRun(PDO $db,callable $config,string $provider,callable $exchange,callable $probe,?callable $clock=null):array{
    if($db->inTransaction())throw new LogicException('Independent transaction required');
    $clock=$clock??static fn()=>time();$start=$clock();$cfg=$config();$g=networkRenewGrant($cfg,$provider,$start);$database=$cfg['database']??null;
    $dir=socialRenewDirectory($g['directory'],dirname(__DIR__,2).'/public_html');
    $prefix=$dir.'/'.$provider;$marker=$prefix.'-exchange-attempt.json';$receipt=$prefix.'-unverified-exchange.json';$candidate=$prefix.'-rotation-candidate.json';
    $lock='aaditechs-social-renew-'.$provider;$q=$db->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);if((string)$q->fetchColumn()!=='1')return ['status'=>'busy'];
    try{
        $last=$start;
        $fresh=static function()use($config,$clock,$g,$provider,$database,$dir,&$last):array{
            $now=$clock();if(!is_int($now)||$now<$last)throw new RuntimeException('Clock moved backward');$last=$now;$c=$config();
            if(($c['database']??null)!==$database||networkRenewGrant($c,$provider,$now)!==$g||socialRenewDirectory($g['directory'],dirname(__DIR__,2).'/public_html')!==$dir)throw new RuntimeException('Grant changed');
            return $c;
        };
        $boundary=static function()use($db,$fresh):array{$db->beginTransaction();try{oauthBatchPaused($db);$c=$fresh();$db->commit();return $c;}catch(Throwable $e){$db->rollBack();throw $e;}};
        $boundary();
        foreach([$marker,$receipt,$candidate] as $file){clearstatcache(true,$file);if(file_exists($file)||is_link($file))return ['status'=>'reconciliation_required','activation_authorized'=>false];}
        if(metaRenewDate($g['policy']['expires_utc'])>$start+900)return ['status'=>'not_due','activation_authorized'=>false];
        // Write a durable fail-stop marker BEFORE a potentially consuming exchange.
        networkRenewWrite($marker,['version'=>1,'provider'=>$provider,'generation'=>$g['generation'],'attempt_utc'=>gmdate('Y-m-d\TH:i:s\Z',$start),'state'=>'attempt_started']);
        $boundary();$response=$exchange($g);
        if(!is_array($response))throw new RuntimeException('Exchange uncertain');
        // Preserve returned refresh token even if scope, pause, identity or config checks subsequently fail.
        // This file is unverified evidence, never an active grant or a rotation candidate.
        networkRenewWrite($receipt,['version'=>1,'provider'=>$provider,'generation'=>$g['generation'],'state'=>'unverified','response'=>$response]);
        $next=$boundary();$parsed=networkRenewResponse($g,$response,$start);$item=$parsed['item'];
        if(metaRenewDate($item['expires_utc'])<=$last+600)throw new RuntimeException('Candidate expired');
        $prepared=oauthRotationPrepare($next,json_encode(['version'=>1,'items'=>[$item]],JSON_THROW_ON_ERROR),random_bytes(32),$last);
        $boundary();if($probe($provider,$prepared['private'][0]['target'],$boundary)!==true)throw new RuntimeException('Identity refused');
        $db->beginTransaction();oauthBatchPaused($db);$fresh();
        if(metaRenewDate($item['expires_utc'])<=$last+600)throw new RuntimeException('Candidate expired');
        socialRenewStore($candidate,$item);$db->commit();
        return ['status'=>'candidate_created','provider'=>$provider,'expires_utc'=>$item['expires_utc'],'refresh_review_deadline_utc'=>$parsed['refresh_expires_utc'],'activation_authorized'=>false,'reconciliation_required'=>true];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    finally{$q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);$q->fetchColumn();}
}
