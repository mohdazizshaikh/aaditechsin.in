<?php
declare(strict_types=1);
require_once __DIR__.'/meta-send.php';
require_once __DIR__.'/instagram-send.php';
function miRequest(array $p,string $method,string $path,array $data):array {
    return $p['provider']==='threads'?metaRequest($p,$method,$path,$data):igRequest($p,strtolower($method),$path,$data);
}
function miIdentity(array $p,callable $request):bool {
    $r=$request($p,'GET','me',['fields'=>$p['provider']==='threads'?'id':'id,instagram_business_account{id}']);
    if(($r['http']??0)!==200||isset($r['json']['error']))return false;
    if($p['provider']==='threads')return (string)($r['json']['id']??'')===$p['id'];
    return (string)($r['json']['id']??'')===$p['page']&&(string)($r['json']['instagram_business_account']['id']??'')===$p['id'];
}
function miId(mixed $id):?string{return is_string($id)&&preg_match('/^[1-9][0-9]{0,30}$/D',$id)?$id:null;}
function miCreate(array $p,array $r,callable $request):?string {
    try{
        if($p['id']!==$r['target_id']||$p['provider']!==$r['provider']||$p['page']!==$r['page_id']||!miIdentity($p,$request)||!preg_match('/^[a-f0-9]{64}$/D',$r['fetch_token']))return null;
        $url=$p['origin'].'/api/media-fetch.php?t='.$r['fetch_token'];
        $data=$p['provider']==='threads'?['media_type'=>'IMAGE','image_url'=>$url,'text'=>$r['caption']]:['image_url'=>$url,'caption'=>$r['caption']];
        $result=$request($p,'POST',$p['id'].($p['provider']==='threads'?'/threads':'/media'),$data);
        return ($result['http']??0)===200&&!isset($result['json']['error'])?miId($result['json']['id']??null):null;
    }catch(Throwable $e){return null;}
}
function miReady(array $p,array $r,callable $request):bool {
    if($p['id']!==$r['target_id']||$p['provider']!==$r['provider']||$p['page']!==$r['page_id']||!miId($r['container_id'])||!miIdentity($p,$request))return false;
    $field=$p['provider']==='threads'?'status':'status_code';
    $v=$request($p,'GET',$r['container_id'],['fields'=>'id,'.$field]);
    return ($v['http']??0)===200&&!isset($v['json']['error'])&&($v['json']['id']??null)===$r['container_id']&&($v['json'][$field]??null)==='FINISHED';
}
function miPublish(array $p,array $r,callable $request):?string {
    try{
        if(!miReady($p,$r,$request))return null;
        $v=$request($p,'POST',$p['id'].($p['provider']==='threads'?'/threads_publish':'/media_publish'),['creation_id'=>$r['container_id']]);
        return ($v['http']??0)===200&&!isset($v['json']['error'])?miId($v['json']['id']??null):null;
    }catch(Throwable $e){return null;}
}
