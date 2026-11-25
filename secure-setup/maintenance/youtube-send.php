<?php
declare(strict_types=1);
function youtubeRequest(array $p,string $method,string $url,string $body='',array $extra=[]):array {
    if(PHP_SAPI!=='cli'||!function_exists('curl_init'))throw new RuntimeException('CLI curl required');
    $u=parse_url($url);
    if(($u['scheme']??'')!=='https'||($u['host']??'')!=='www.googleapis.com'||(isset($u['user'])||isset($u['pass']))||isset($u['port'])||isset($u['fragment'])||!in_array($u['path']??'',['/youtube/v3/channels','/upload/youtube/v3/videos'],true))throw new RuntimeException('Invalid upload URL');
    $ch=curl_init($url);$response='';$location=null;
    curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>array_merge(['Authorization: Bearer '.$p['token'],'Accept: application/json'],$extra),CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>180,
      CURLOPT_HEADERFUNCTION=>static function($ch,string $line)use(&$location):int{if(preg_match('/^HTTP\//i',$line))$location=null;if(stripos($line,'location:')===0)$location=trim(substr($line,9));return strlen($line);},
      CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$response):int{if(strlen($response)+strlen($chunk)>65536)return 0;$response.=$chunk;return strlen($chunk);}]);
    if($method!=='GET')curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
    $ok=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    return ['http'=>$ok===false?0:$http,'json'=>json_decode($response,true,32),'location'=>$location];
}
function youtubeIdentity(array $p,callable $request):bool {
    $r=$request($p,'GET','https://www.googleapis.com/youtube/v3/channels?part=id&mine=true&maxResults=2');
    return ($r['http']??0)===200&&is_array($r['json']['items']??null)&&count($r['json']['items'])===1&&($r['json']['items'][0]['id']??null)===$p['id']&&!isset($r['json']['nextPageToken']);
}
function youtubeResult(string $s,?string $r=null,string $e=''):array{return ['status'=>$s,'receipt'=>$r,'error'=>$e];}
function youtubeSend(array $p,array $row,string $bytes,callable $request):array {
    if($p['id']!==$row['target_id']||strlen($bytes)!==(int)$row['byte_count']||!hash_equals($row['video_hash'],hash('sha256',$bytes)))return youtubeResult('failed',null,'asset_mismatch');
    try{
        if(!youtubeIdentity($p,$request))return youtubeResult('failed',null,'identity_unverified');
        $data=['snippet'=>['title'=>$row['title'],'description'=>$row['description'],'categoryId'=>$row['category_id']],'status'=>['privacyStatus'=>$row['visibility'],'selfDeclaredMadeForKids'=>(bool)$row['made_for_kids'],'containsSyntheticMedia'=>(bool)$row['synthetic_content']]];
        $r=$request($p,'POST','https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status&notifySubscribers=false',json_encode($data,JSON_THROW_ON_ERROR),['Content-Type: application/json','X-Upload-Content-Type: video/mp4','X-Upload-Content-Length: '.strlen($bytes)]);
        $url=$r['location']??null;
        if(($r['http']??0)!==200||!is_string($url)||!preg_match('#^https://www\.googleapis\.com/upload/youtube/v3/videos\?[A-Za-z0-9_=&.%,-]{1,2048}$#D',$url)||!str_contains($url,'upload_id='))return youtubeResult('unknown',null,'session_unverified');
        $r=$request($p,'PUT',$url,$bytes,['Content-Type: video/mp4','Content-Length: '.strlen($bytes)]);
        $id=$r['json']['id']??null;
        if(in_array($r['http']??0,[200,201],true)&&is_string($id)&&preg_match('/^[A-Za-z0-9_-]{11}$/D',$id)&&($r['json']['snippet']['channelId']??null)===$p['id']&&($r['json']['status']['privacyStatus']??null)===$row['visibility'])return youtubeResult('accepted',$id);
        return youtubeResult('unknown',null,'ambiguous_response');
    }catch(Throwable $e){return youtubeResult('unknown',null,'transport_error');}
}
