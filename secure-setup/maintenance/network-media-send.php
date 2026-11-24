<?php
declare(strict_types=1);
require_once __DIR__.'/network-send.php';
function nmLinkedInUrl(string $url,string $id):bool {
    if(strlen($url)>4096||!preg_match('/^urn:li:image:[A-Za-z0-9_-]{1,100}$/D',$id))return false;
    $u=parse_url($url);$asset=substr($id,13);
    return ($u['scheme']??'')==='https'&&($u['host']??'')==='www.linkedin.com'&&!isset($u['user'])&&!isset($u['pass'])&&!isset($u['port'])&&!isset($u['fragment'])&&($u['path']??'')==='/dms-uploads/'.$asset.'/uploaded-image/0'&&isset($u['query'])&&preg_match('/^[A-Za-z0-9_=&.%~+,-]+$/D',$u['query'])===1;
}
function nmBinary(array $p,string $url,string $id,string $bytes):array {
    if(PHP_SAPI!=='cli'||!function_exists('curl_init')||!nmLinkedInUrl($url,$id))throw new RuntimeException('Invalid upload URL');
    $ch=curl_init($url);$body='';
    curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$p['token'],'Content-Type: application/octet-stream'],CURLOPT_CUSTOMREQUEST=>'PUT',CURLOPT_POSTFIELDS=>$bytes,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>120,CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>16384)return 0;$body.=$chunk;return strlen($chunk);}]);
    $ok=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);return ['http'=>$ok===false?0:$http];
}
function nmSend(array $p,array $row,string $bytes,callable $request,callable $binary,callable $delay,?callable $remember=null):array {
    if($p['provider']!==$row['provider']||$p['id']!==$row['target_id']||strlen($bytes)!==(int)$row['byte_count']||!hash_equals($row['asset_hash'],hash('sha256',$bytes)))return networkResult('failed',null,'asset_mismatch');
    try{
        if(!networkIdentity($p,$request))return networkResult('failed',null,'identity_unverified');
        if($p['provider']==='linkedin'){
            $r=$request($p,'POST','rest/images?action=initializeUpload',['initializeUploadRequest'=>['owner'=>'urn:li:organization:'.$p['id']]]);
            $v=$r['json']['value']??[];$id=$v['image']??null;$url=$v['uploadUrl']??null;
            if(($r['http']??0)!==200||!is_string($id)||!is_string($url)||!nmLinkedInUrl($url,$id))return networkResult('unknown',null,'session_unverified');
            if($remember!==null)$remember($id,'initialized');
            $r=$binary($p,$url,$id,$bytes);if(!in_array($r['http']??0,[200,201,204],true))return networkResult('unknown',null,'upload_unverified');
            if($remember!==null)$remember($id,'uploaded');
            $ready=false;
            for($i=0;$i<5;$i++){
                $r=$request($p,'GET','rest/images/'.rawurlencode($id),[]);
                if(($r['http']??0)!==200||($r['json']['id']??null)!==$id||($r['json']['owner']??null)!=='urn:li:organization:'.$p['id'])break;
                if(($r['json']['status']??null)==='AVAILABLE'){$ready=true;break;}
                if(!in_array($r['json']['status']??null,['PROCESSING','WAITING_UPLOAD'],true))break;
                if($i<4)$delay();
            }
            if(!$ready)return networkResult('unknown',null,'asset_not_ready');
            if(!networkIdentity($p,$request))return networkResult('unknown',null,'identity_unverified');
            $r=$request($p,'POST','rest/posts',['author'=>'urn:li:organization:'.$p['id'],'commentary'=>$row['caption'],'visibility'=>'PUBLIC','distribution'=>['feedDistribution'=>'MAIN_FEED','targetEntities'=>[],'thirdPartyDistributionChannels'=>[]],'content'=>['media'=>['id'=>$id]],'lifecycleState'=>'PUBLISHED','isReshareDisabledByAuthor'=>false]);
            $id=$r['receipt']??null;$valid=is_string($id)&&preg_match('/^urn:li:(?:share|ugcPost):[0-9]{1,30}$/D',$id);
        }else{
            $r=$request($p,'POST','2/media/upload',['media'=>base64_encode($bytes),'media_category'=>'tweet_image','media_type'=>'image/png']);
            $v=$r['json']['data']??[];$id=$v['id']??null;$expiry=$v['expires_after_secs']??null;
            if(!in_array($r['http']??0,[200,201],true)||!is_string($id)||!preg_match('/^[1-9][0-9]{0,30}$/D',$id)||!is_int($expiry)||$expiry<60||isset($v['processing_info'])||isset($r['json']['errors']))return networkResult('unknown',null,'upload_unverified');
            if($remember!==null)$remember($id,'uploaded');
            $until=time()+$expiry;
            if(!networkIdentity($p,$request)||time()+30>=$until)return networkResult('unknown',null,'asset_not_ready');
            $r=$request($p,'POST','2/tweets',['text'=>$row['caption'],'media'=>['media_ids'=>[$id]]]);$id=$r['json']['data']['id']??null;$valid=is_string($id)&&preg_match('/^[1-9][0-9]{0,30}$/D',$id);
        }
        return in_array($r['http']??0,[200,201],true)&&$valid?networkResult('accepted',$id):networkResult('unknown',null,'publish_unverified');
    }catch(Throwable $e){return networkResult('unknown',null,'transport_error');}
}
