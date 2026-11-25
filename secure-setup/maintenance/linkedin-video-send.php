<?php
declare(strict_types=1);
require_once __DIR__.'/network-send.php';
function lvUrl(string $url,string $id):bool{
    if(!lvId($id)||strlen($url)>4096)return false;$u=parse_url($url);
    return ($u['scheme']??'')==='https'&&($u['host']??'')==='www.linkedin.com'&&!isset($u['user'])&&!isset($u['pass'])&&!isset($u['port'])&&!isset($u['fragment'])&&($u['path']??'')==='/dms-uploads/'.substr($id,13).'/uploadedVideo'&&isset($u['query'])&&preg_match('/^[A-Za-z0-9_=&.%~+,-]+$/D',$u['query'])===1;
}
function lvParts(array $ins,int $size,string $id):array{
    if(!$ins||count($ins)>8||!array_is_list($ins)||$size<1||$size>20*1024*1024)throw new RuntimeException('Invalid ranges');
    $cursor=0;$out=[];
    foreach($ins as $r){
        if(!is_array($r)||!is_int($r['firstByte']??null)||!is_int($r['lastByte']??null)||$r['firstByte']!==$cursor||$r['lastByte']<$cursor||$r['lastByte']-$cursor>=4194304||$cursor>=$size||!is_string($r['uploadUrl']??null)||!lvUrl($r['uploadUrl'],$id))throw new RuntimeException('Invalid part');
        $end=min($r['lastByte'],$size-1);$out[]=['offset'=>$cursor,'length'=>$end-$cursor+1,'url'=>$r['uploadUrl']];$cursor=$end+1;
    }if($cursor!==$size)throw new RuntimeException('Incomplete ranges');return $out;
}
function lvPart(string $url,string $id,string $bytes):array{
    if(PHP_SAPI!=='cli'||!function_exists('curl_init')||!lvUrl($url,$id))throw new RuntimeException('Invalid signed URL');
    $ch=curl_init($url);$tag=null;$body='';
    curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>['Content-Type: application/octet-stream'],CURLOPT_CUSTOMREQUEST=>'PUT',CURLOPT_POSTFIELDS=>$bytes,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>120,CURLOPT_HEADERFUNCTION=>static function($ch,string $line)use(&$tag):int{if(preg_match('/^HTTP\//i',$line))$tag=null;if(stripos($line,'etag:')===0)$tag=trim(trim(substr($line,5)),'"');return strlen($line);},CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>16384)return 0;$body.=$chunk;return strlen($chunk);}]);
    $ok=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);return ['http'=>$ok===false?0:$http,'etag'=>$tag];
}
function lvUpload(array $p,array $row,string $bytes,callable $request,callable $binary,callable $remember):array{
    $id=null;
    if($p['provider']!=='linkedin'||$p['id']!==$row['target_id']||strlen($bytes)!==(int)$row['byte_count']||!hash_equals($row['video_hash'],hash('sha256',$bytes)))return networkResult('failed',null,'asset_mismatch');
    try{
        if(!networkIdentity($p,$request))return networkResult('failed',null,'identity_unverified');
        $r=$request($p,'POST','rest/videos?action=initializeUpload',['initializeUploadRequest'=>['owner'=>'urn:li:organization:'.$p['id'],'fileSizeBytes'=>strlen($bytes),'uploadCaptions'=>false,'uploadThumbnail'=>false]]);
        $v=$r['json']['value']??[];$candidate=$v['video']??null;
        if(($r['http']??0)!==200||!lvId($candidate))return networkResult('unknown',null,'session_unverified');
        $remember($candidate);$id=$candidate;$expiry=$v['uploadUrlsExpireAt']??null;$token=$v['uploadToken']??null;
        if(!is_int($expiry)||$expiry<=time()*1000+30000||!is_string($token)||strlen($token)>4096||!is_array($v['uploadInstructions']??null))return networkResult('unknown',$id,'session_unverified');
        $parts=lvParts($v['uploadInstructions'],strlen($bytes),$id);$tags=[];
        foreach($parts as $part){
            if(time()*1000+30000>=$expiry)return networkResult('unknown',$id,'session_expired');
            $r=$binary($part['url'],$id,substr($bytes,$part['offset'],$part['length']));$tag=$r['etag']??null;
            if(!in_array($r['http']??0,[200,201,204],true)||!is_string($tag)||!preg_match('#^[A-Za-z0-9_./-]{1,2048}$#D',$tag))return networkResult('unknown',$id,'part_unverified');
            $tags[]=$tag;
        }
        if(!networkIdentity($p,$request))return networkResult('unknown',$id,'identity_unverified');
        $r=$request($p,'POST','rest/videos?action=finalizeUpload',['finalizeUploadRequest'=>['video'=>$id,'uploadToken'=>$token,'uploadedPartIds'=>$tags]]);
        return in_array($r['http']??0,[200,204],true)&&!isset($r['json']['error'])?networkResult('processing',$id):networkResult('unknown',$id,'finalize_unverified');
    }catch(Throwable $e){return networkResult('unknown',$id,'transport_error');}
}
function lvReady(array $p,array $row,callable $request):bool{
    if($p['provider']!=='linkedin'||$p['id']!==$row['target_id']||!lvId($row['video_id'])||!networkIdentity($p,$request))return false;
    $r=$request($p,'GET','rest/videos/'.rawurlencode($row['video_id']),[]);
    return ($r['http']??0)===200&&($r['json']['id']??null)===$row['video_id']&&($r['json']['owner']??null)==='urn:li:organization:'.$p['id']&&($r['json']['status']??null)==='AVAILABLE';
}
function lvPublish(array $p,array $row,callable $request):array{
    try{
        if(!lvReady($p,$row,$request))return networkResult('unknown',null,'asset_not_ready');
        $r=$request($p,'POST','rest/posts',['author'=>'urn:li:organization:'.$p['id'],'commentary'=>$row['caption'],'visibility'=>'PUBLIC','distribution'=>['feedDistribution'=>'MAIN_FEED','targetEntities'=>[],'thirdPartyDistributionChannels'=>[]],'content'=>['media'=>['id'=>$row['video_id']]],'lifecycleState'=>'PUBLISHED','isReshareDisabledByAuthor'=>false]);
        $id=$r['receipt']??null;return ($r['http']??0)===201&&is_string($id)&&preg_match('/^urn:li:(?:share|ugcPost):[0-9]{1,30}$/D',$id)?networkResult('accepted',$id):networkResult('unknown',null,'publish_unverified');
    }catch(Throwable $e){return networkResult('unknown',null,'transport_error');}
}
