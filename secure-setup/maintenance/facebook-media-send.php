<?php
declare(strict_types=1);
require_once __DIR__.'/meta-send.php';
function fbMediaRequest(array $p,string $kind,array $data,string $bytes):array {
    if(PHP_SAPI!=='cli'||!function_exists('curl_init')||!class_exists('CURLStringFile')||!in_array($kind,['photo','video'],true))throw new RuntimeException('CLI curl required');
    $photo=$kind==='photo';$host=$photo?'graph.facebook.com':'graph-video.facebook.com';
    $url='https://'.$host.'/'.$p['version'].'/'.$p['id'].($photo?'/photos':'/videos');
    // Send the exact previously verified in-memory bytes, not a mutable path.
    $data['source']=new CURLStringFile($bytes,$photo?'creative.png':'creative.mp4',$photo?'image/png':'video/mp4');
    $ch=curl_init($url);$body='';
    curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$p['token'],'Accept: application/json'],CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$data,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>180,CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>16384)return 0;$body.=$chunk;return strlen($chunk);}]);
    $ok=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    return ['http'=>$ok===false?0:$http,'json'=>json_decode($body,true,16,JSON_BIGINT_AS_STRING)];
}
function fbMediaSend(array $p,array $row,string $bytes,callable $identityRequest,callable $request):array {
    if($p['provider']!=='facebook'||$p['id']!==$row['target_id']||!in_array($row['asset_kind'],['photo','video'],true)||strlen($bytes)!==(int)$row['byte_count']||!hash_equals($row['asset_hash'],hash('sha256',$bytes)))return metaResult('failed',null,'asset_mismatch');
    try{
        if(!metaIdentity($p,$identityRequest))return metaResult('failed',null,'identity_unverified');
        $data=['published'=>'true',($row['asset_kind']==='photo'?'message':'description')=>$row['caption']];
        $r=$request($p,$row['asset_kind'],$data,$bytes);
        return metaResponse($r,'/^[1-9][0-9]{0,30}$/D');
    }catch(Throwable $e){return metaResult('unknown',null,'transport_error');}
}
