<?php
declare(strict_types=1);
function igRequest(array $p,string $kind,string $path,array $data=[],string $bytes=''):array {
    if(PHP_SAPI!=='cli'||!function_exists('curl_init'))throw new RuntimeException('CLI curl required');
    if(!in_array($kind,['get','post','binary'],true)||!preg_match('#^(?:me|[1-9][0-9]{0,30}(?:/media|/media_publish)?)$#D',$path))throw new RuntimeException('Invalid route');
    if($kind==='binary'&&!preg_match('/^[1-9][0-9]{0,30}$/D',$path))throw new RuntimeException('Invalid container');
    $url=$kind==='binary'?'https://rupload.facebook.com/ig-api-upload/'.$p['version'].'/'.$path:'https://graph.facebook.com/'.$p['version'].'/'.$path;
    $headers=['Authorization: '.($kind==='binary'?'OAuth ':'Bearer ').$p['token'],'Accept: application/json'];
    if($kind==='binary')$headers=array_merge($headers,['Content-Type: application/octet-stream','offset: 0','file_size: '.strlen($bytes)]);
    if($kind==='post')$headers[]='Content-Type: application/x-www-form-urlencoded';
    if($kind==='get'&&$data)$url.='?'.http_build_query($data);
    $ch=curl_init($url);$body='';
    curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>180,CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>16384)return 0;$body.=$chunk;return strlen($chunk);}]);
    if($kind!=='get'){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,$kind==='binary'?$bytes:http_build_query($data));}
    $ok=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    return ['http'=>$ok===false?0:$http,'json'=>json_decode($body,true,16,JSON_BIGINT_AS_STRING)];
}
function igIdentity(array $p,callable $request):bool {
    $r=$request($p,'get','me',['fields'=>'id,instagram_business_account{id}']);
    return ($r['http']??0)===200&&!isset($r['json']['error'])&&isset($r['json']['id'],$r['json']['instagram_business_account']['id'])&&(string)$r['json']['id']===$p['page']&&(string)$r['json']['instagram_business_account']['id']===$p['id'];
}
function igResult(string $status,?string $id=null,string $error=''):array{return ['status'=>$status,'id'=>$id,'error'=>$error];}
function igValidId(mixed $id):bool{return is_string($id)&&preg_match('/^[1-9][0-9]{0,30}$/D',$id)===1;}
function igUpload(array $p,array $row,string $bytes,callable $request,?callable $remember=null):array {
    if($p['page']!==$row['page_id']||$p['id']!==$row['target_id']||strlen($bytes)!==(int)$row['byte_count']||!hash_equals($row['video_hash'],hash('sha256',$bytes)))return igResult('failed',null,'asset_mismatch');
    $id=null;
    try{
        if(!igIdentity($p,$request))return igResult('failed',null,'identity_unverified');
        $r=$request($p,'post',$p['id'].'/media',['media_type'=>'REELS','upload_type'=>'resumable','caption'=>$row['caption'],'share_to_feed'=>'false']);
        $candidate=$r['json']['id']??null;
        if(($r['http']??0)!==200||isset($r['json']['error'])||!igValidId($candidate))return igResult('unknown',null,'container_unverified');
        if($remember!==null)$remember($candidate);
        $id=$candidate;
        // Never use a provider-supplied URL; build the fixed upload route from validated ID.
        $r=$request($p,'binary',$id,[],$bytes);
        if(($r['http']??0)===200&&($r['json']['success']??null)===true&&!isset($r['json']['error']))return igResult('processing',$id);
        return igResult('unknown',$id,'upload_unverified');
    }catch(Throwable $e){return igResult('unknown',$id,'transport_error');}
}
function igContainerStatus(array $p,array $row,callable $request):string {
    if($p['page']!==$row['page_id']||$p['id']!==$row['target_id']||!igValidId($row['container_id'])||!igIdentity($p,$request))return 'UNVERIFIED';
    $r=$request($p,'get',$row['container_id'],['fields'=>'id,status_code']);
    if(($r['http']??0)!==200||isset($r['json']['error'])||($r['json']['id']??null)!==$row['container_id'])return 'UNVERIFIED';
    $s=$r['json']['status_code']??null;return in_array($s,['FINISHED','IN_PROGRESS','ERROR','EXPIRED','PUBLISHED'],true)?$s:'UNVERIFIED';
}
function igPublish(array $p,array $row,callable $request):array {
    try{
        if(igContainerStatus($p,$row,$request)!=='FINISHED')return igResult('unknown',null,'container_not_ready');
        $r=$request($p,'post',$p['id'].'/media_publish',['creation_id'=>$row['container_id']]);$id=$r['json']['id']??null;
        return ($r['http']??0)===200&&!isset($r['json']['error'])&&igValidId($id)?igResult('accepted',$id):igResult('unknown',null,'publish_unverified');
    }catch(Throwable $e){return igResult('unknown',null,'transport_error');}
}
