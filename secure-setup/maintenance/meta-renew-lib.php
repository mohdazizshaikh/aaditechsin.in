<?php
declare(strict_types=1);
require_once __DIR__.'/social-renew-lib.php';
function metaRenewDate(mixed $v):int{
    $d=is_string($v)?DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$v,new DateTimeZone('UTC')):false;
    if(!$d||$d->format('Y-m-d\TH:i:s\Z')!==$v)throw new InvalidArgumentException('UTC date required');
    return $d->getTimestamp();
}
function metaRenewCredential(mixed $v):bool{return is_string($v)&&preg_match('/^[A-Za-z0-9._~-]{20,4096}$/D',$v)===1&&!str_starts_with($v,'REPLACE_');}
function metaRenewGrant(array $cfg,string $provider,int $now):array{
    if(!in_array($provider,['facebook','instagram','threads'],true))throw new InvalidArgumentException('Provider');
    $l=$cfg['oauth_lifecycle']??[];$r=$l['meta_renewal'][$provider]??[];
    if(($l['enabled']??false)!==true||($l['rotation_review_enabled']??false)!==true||($l['meta_candidate_enabled']??false)!==true||($r['enabled']??false)!==true||($r['authorized']??false)!==true||!oauthWatched($cfg,$provider))throw new RuntimeException('Disabled');
    $policy=oauthPolicy($cfg,$provider,$now);
    if(!in_array($policy['state'],['expired','too_close','renew_soon','current'],true))throw new RuntimeException('Revoked or unverified');
    $deadline=metaRenewDate($r['review_expires_utc']??null);
    if($deadline<=$now+660||$deadline>$now+90*86400||!is_string($r['generation']??null)||!preg_match('/^[a-f0-9]{32}$/D',$r['generation'])||$r['generation']===str_repeat('0',32))throw new RuntimeException('Grant review');
    $next=$cfg;$next['oauth_lifecycle']['providers'][$provider]['expires_utc']=$r['review_expires_utc'];
    $target=oauthTarget($next,$provider);
    if(!$target||($r['target_id']??null)!==oauthTargetLabel($provider,$target)||!is_string($r['candidate_directory']??null))throw new RuntimeException('Exact destination');
    $g=['provider'=>$provider,'target'=>$target,'policy'=>$l['providers'][$provider],'review_deadline'=>$deadline,'generation'=>$r['generation'],'directory'=>$r['candidate_directory']];
    if($provider==='threads'){
        $issued=metaRenewDate($r['issued_utc']??null);$expiry=metaRenewDate($r['provider_expires_utc']??null);
        if($issued>$now-86400||$expiry<=$now+660||$expiry>$issued+61*86400||($r['long_lived_reviewed']??false)!==true||($r['active_generation']??null)!==$policy['generation'])throw new RuntimeException('Eligible unexpired long-lived Threads token required');
        return $g+['issued'=>$issued,'provider_expiry'=>$expiry];
    }
    if(!metaRenewCredential($r['user_access_token']??null)||!is_string($r['app_id']??null)||!preg_match('/^[1-9][0-9]{0,30}$/D',$r['app_id'])||!is_string($r['app_secret']??null)||!preg_match('/^[a-f0-9]{32}$/D',$r['app_secret']))throw new RuntimeException('Reviewed user grant required');
    $userExpiry=metaRenewDate($r['user_expires_utc']??null);
    if($userExpiry<=$now+660||!is_string($r['user_id']??null)||!preg_match('/^[1-9][0-9]{0,30}$/D',$r['user_id']))throw new RuntimeException('User grant expiry');
    $required=$provider==='facebook'?['pages_manage_posts','pages_read_engagement','pages_show_list']:['instagram_basic','instagram_content_publish','pages_read_engagement','pages_show_list'];
    $scopes=$r['reviewed_scopes']??null;
    if(!is_array($scopes)||!array_is_list($scopes)||count($scopes)>count($required)+1||count(array_unique($scopes,SORT_REGULAR))!==count($scopes)||array_diff($required,$scopes)||array_diff($scopes,array_merge($required,['public_profile'])))throw new RuntimeException('Exact reviewed scope set');
    sort($scopes,SORT_STRING);
    return $g+['user_token'=>$r['user_access_token'],'user_expiry'=>$userExpiry,'user_id'=>$r['user_id'],'app_id'=>$r['app_id'],'app_secret'=>$r['app_secret'],'scopes'=>$scopes];
}
function metaRenewDebug(array $g,array $response,string $type,int $now):int{
    $d=$response['data']??null;
    if(!is_array($d)||array_key_exists('error',$response)||($d['is_valid']??null)!==true||($d['app_id']??null)!==$g['app_id']||($d['type']??null)!==$type||!is_int($d['expires_at']??null)||!is_int($d['data_access_expires_at']??null))throw new RuntimeException('Token debug refused');
    $scopes=$d['scopes']??null;
    if(!is_array($scopes)||!array_is_list($scopes))throw new RuntimeException('Scopes missing');
    foreach($scopes as $s)if(!is_string($s))throw new RuntimeException('Scope type');
    sort($scopes,SORT_STRING);if($scopes!==$g['scopes'])throw new RuntimeException('Scope mismatch');
    if($type==='USER'&&($d['user_id']??null)!==$g['user_id'])throw new RuntimeException('User mismatch');
    $expiry=$g['review_deadline'];
    foreach(['expires_at','data_access_expires_at'] as $k){
        if($d[$k]===0&&$type==='PAGE')continue;
        if($d[$k]<=$now+660)throw new RuntimeException('Token expiry');
        $expiry=min($expiry,$d[$k]);
    }
    return $expiry;
}
function metaRenewCandidate(array $g,callable $request,callable $boundary,int $start):array{
    $provider=$g['provider'];$target=$g['target'];
    $read=static function(string $op,array $data)use($request,$boundary,$g):array{
        $boundary();$r=$request($g,$op,$data);$boundary();
        if(!is_array($r)||array_key_exists('error',$r))throw new RuntimeException('Provider refused');
        return $r;
    };
    if($provider==='threads'){
        $r=$read('threads-refresh',[]);
        if(($r['token_type']??null)!=='bearer'||!is_int($r['expires_in']??null)||$r['expires_in']<=720||$r['expires_in']>61*86400||array_key_exists('refresh_token',$r))throw new RuntimeException('Refresh response');
        $token=$r['access_token']??null;$expiry=min($g['review_deadline'],$start+$r['expires_in']-60);
    }else{
        $expiry=min($g['user_expiry'],metaRenewDebug($g,$read('debug',['token'=>$g['user_token']]),'USER',$start));
        $page=$provider==='instagram'?$target['page']:$target['id'];
        $r=$read('page',['page'=>$page]);
        if(($r['id']??null)!==$page||($provider==='instagram'&&($r['instagram_business_account']['id']??null)!==$target['id']))throw new RuntimeException('Page mismatch');
        $token=$r['access_token']??null;
        if(!metaRenewCredential($token))throw new RuntimeException('Page token');
        $expiry=min($expiry,metaRenewDebug($g,$read('debug',['token'=>$token]),'PAGE',$start));
    }
    if(!metaRenewCredential($token)||hash_equals($target['token'],$token)||$expiry<=$start+660)throw new RuntimeException('Distinct viable candidate required');
    return ['provider'=>$provider,'access_token'=>$token,'generation'=>bin2hex(random_bytes(16)),'expires_utc'=>gmdate('Y-m-d\TH:i:s\Z',$expiry)];
}
function metaRenewRequest(array $g,string $op,array $data):array{
    if(PHP_SAPI!=='cli'||!function_exists('curl_init'))throw new RuntimeException('CLI transport');
    $target=$g['target'];$query=[];$bearer='';
    if($op==='threads-refresh'&&$g['provider']==='threads'){
        $url='https://graph.threads.net/refresh_access_token';$query=['grant_type'=>'th_refresh_token','access_token'=>$target['token']];
    }elseif($op==='debug'&&$g['provider']!=='threads'){
        $url='https://graph.facebook.com/'.$target['version'].'/debug_token';$query=['input_token'=>$data['token']];$bearer=$g['app_id'].'|'.$g['app_secret'];
    }elseif($op==='page'&&$g['provider']!=='threads'){
        $page=$g['provider']==='instagram'?$target['page']:$target['id'];
        if($data['page']!==$page)throw new RuntimeException('Page route');
        $url='https://graph.facebook.com/'.$target['version'].'/'.$page;
        $query=['fields'=>$g['provider']==='instagram'?'id,access_token,instagram_business_account{id}':'id,access_token'];$bearer=$g['user_token'];
    }else throw new RuntimeException('Route');
    $ch=curl_init($url.'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986));$body='';
    try{
        $headers=['Accept: application/json'];if($bearer!=='')$headers[]='Authorization: Bearer '.$bearer;
        if(!curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>32768)return 0;$body.=$chunk;return strlen($chunk);}])||curl_exec($ch)!==true||curl_getinfo($ch,CURLINFO_RESPONSE_CODE)!==200)throw new RuntimeException('Provider request failed');
        $r=strictJsonDecode($body,true,16);if(!is_array($r))throw new RuntimeException('Response');return $r;
    }finally{curl_close($ch);}
}
function metaRenewRun(PDO $db,callable $config,string $provider,callable $request,callable $probe,?callable $clock=null):array{
    if($db->inTransaction())throw new LogicException('Independent transaction required');
    $clock=$clock??static fn()=>time();$start=$clock();$cfg=$config();$g=metaRenewGrant($cfg,$provider,$start);$database=$cfg['database']??null;
    $dir=socialRenewDirectory($g['directory'],dirname(__DIR__,2).'/public_html');$path=$dir.'/'.$provider.'-rotation-candidate.json';
    $lock='aaditechs-social-renew-'.$provider;$q=$db->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);if((string)$q->fetchColumn()!=='1')return ['status'=>'busy'];
    try{
        $last=$start;
        $fresh=static function()use($db,$config,$clock,$provider,$g,$database,$dir,&$last):array{
            $now=$clock();if(!is_int($now)||$now<$last)throw new RuntimeException('Clock changed');$last=$now;
            $c=$config();
            if(($c['database']??null)!==$database||metaRenewGrant($c,$provider,$now)!==$g||socialRenewDirectory($g['directory'],dirname(__DIR__,2).'/public_html')!==$dir)throw new RuntimeException('Grant changed');
            return $c;
        };
        $boundary=static function()use($db,$fresh):array{
            $db->beginTransaction();try{oauthBatchPaused($db);$c=$fresh();$db->commit();return $c;}catch(Throwable $e){$db->rollBack();throw $e;}
        };
        $boundary();clearstatcache(true,$path);
        if(file_exists($path)||is_link($path))return ['status'=>'candidate_exists','activation_authorized'=>false];
        $due=metaRenewDate($g['policy']['expires_utc']);
        if($provider==='threads')$due=min($due,$g['provider_expiry']);
        if($due>$start+7*86400)return ['status'=>'not_due','activation_authorized'=>false];
        $item=metaRenewCandidate($g,$request,$boundary,$start);$next=$boundary();
        if(metaRenewDate($item['expires_utc'])<=$last+600)throw new RuntimeException('Candidate window');
        $prepared=oauthRotationPrepare($next,json_encode(['version'=>1,'items'=>[$item]],JSON_THROW_ON_ERROR),random_bytes(32),$last);
        $boundary();if($probe($provider,$prepared['private'][0]['target'])!==true)throw new RuntimeException('Identity refused');
        $db->beginTransaction();oauthBatchPaused($db);$fresh();
        if(metaRenewDate($item['expires_utc'])<=$last+600)throw new RuntimeException('Candidate expired');
        socialRenewStore($path,$item);$db->commit();
        return ['status'=>'candidate_created','provider'=>$provider,'expires_utc'=>$item['expires_utc'],'activation_authorized'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    finally{$q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);$q->fetchColumn();}
}
