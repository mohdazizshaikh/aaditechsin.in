<?php
declare(strict_types=1);
// CLI-only helpers. Transport never retries or follows redirects.
function metaRequest(array $p,string $method,string $path,array $data=[]):array {
    if(PHP_SAPI!=='cli'||!function_exists('curl_init'))throw new RuntimeException('CLI curl required');
    $host=match($p['provider']){'facebook'=>'graph.facebook.com','threads'=>'graph.threads.net'};
    $url='https://'.$host.'/'.$p['version'].'/'.$path;
    if($method==='GET'&&$data)$url.='?'.http_build_query($data);
    $ch=curl_init($url);$body='';
    curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$p['token'],'Accept: application/json','Content-Type: application/x-www-form-urlencoded'],CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>30,CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>16384)return 0;$body.=$chunk;return strlen($chunk);}]);
    if($method==='POST'){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($data));}
    $ok=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    return ['http'=>$ok===false?0:$code,'json'=>json_decode($body,true,16,JSON_BIGINT_AS_STRING)];
}
function metaIdentity(array $p,callable $request):bool {
    $r=$request($p,'GET','me',['fields'=>'id']);
    return ($r['http']??0)===200&&is_array($r['json']??null)&&!isset($r['json']['error'])&&isset($r['json']['id'])&&(is_string($r['json']['id'])||is_int($r['json']['id']))&&(string)$r['json']['id']===$p['id'];
}
function metaResult(string $status,?string $receipt=null,string $error=''):array{return ['status'=>$status,'receipt'=>$receipt,'error'=>$error];}
function metaResponse(array $r,string $pattern):array {
    $http=$r['http']??0;$j=$r['json']??null;$id=is_array($j)?($j['id']??null):null;
    if($http>=200&&$http<300&&is_array($j)&&!isset($j['error'])&&(is_string($id)||is_int($id))&&preg_match($pattern,(string)$id))return metaResult('accepted',(string)$id);
    if(in_array($http,[400,401,403,404,429],true)&&is_array($j)&&is_array($j['error']??null))return metaResult('failed',null,'provider_rejected');
    return metaResult('unknown',null,'ambiguous_response');
}
function metaSend(array $p,array $row,callable $request):array {
    if($row['provider']!==$p['provider']||$row['target_id']!==$p['id'])return metaResult('failed',null,'target_mismatch');
    $caption=$row['caption'];$limit=$p['provider']==='threads'?500:900;
    if(!is_string($caption)||trim($caption)===''||strlen($caption)>$limit)return metaResult('failed',null,'invalid_caption');
    try{
        if(!metaIdentity($p,$request))return metaResult('failed',null,'identity_unverified');
        if($p['provider']==='facebook'){
            return metaResponse($request($p,'POST',$p['id'].'/feed',['message'=>$caption]),'/^'.preg_quote($p['id'],'/').'_[0-9]{1,40}$/D');
        }
        // Container creation does not auto-publish. Never repeat either POST.
        $container=metaResponse($request($p,'POST',$p['id'].'/threads',['media_type'=>'TEXT','text'=>$caption,'auto_publish_text'=>'false']),'/^[0-9]{1,40}$/D');
        if($container['status']!=='accepted')return $container;
        return metaResponse($request($p,'POST',$p['id'].'/threads_publish',['creation_id'=>$container['receipt']]),'/^[0-9]{1,40}$/D');
    }catch(Throwable $e){return metaResult('unknown',null,'transport_error');}
}
