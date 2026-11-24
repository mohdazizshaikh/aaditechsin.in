<?php
declare(strict_types=1);
require_once __DIR__.'/meta-image-send.php';
function caBound(array $p,array $r):bool{return $p['provider']===$r['provider']&&$p['id']===$r['target_id']&&$p['page']===$r['page_id'];}
function caChildren(array $p,array $r,callable $request,callable $remember):bool{
    try{
        if(!caBound($p,$r)||!miIdentity($p,$request))return false;
        foreach($r['items'] as $item){
            if(!preg_match('/^[a-f0-9]{64}$/D',$item['fetch_token']))return false;
            $kind=$item['asset_kind']??'image';if(!in_array($kind,['image','video'],true))return false;
            $data=[($kind==='video'?'video_url':'image_url')=>$p['origin'].'/api/media-fetch.php?c='.$item['fetch_token'],'is_carousel_item'=>'true'];
            if($kind==='video'||$p['provider']==='threads')$data['media_type']=$kind==='video'?'VIDEO':'IMAGE';
            $v=$request($p,'POST',$p['id'].($p['provider']==='threads'?'/threads':'/media'),$data);$id=$v['json']['id']??null;
            if(($v['http']??0)!==200||isset($v['json']['error'])||!caId($id))return false;
            $remember((int)$item['position_no'],$id);
        }return true;
    }catch(Throwable $e){return false;}
}
function caChildrenReady(array $p,array $r,callable $request):bool{
    if(!caBound($p,$r)||!miIdentity($p,$request))return false;
    foreach($r['items'] as $item){$child=$r;$child['container_id']=$item['container_id'];if(!miReady($p,$child,$request))return false;}return true;
}
function caParent(array $p,array $r,callable $request):?string{
    try{
        if(!caChildrenReady($p,$r,$request))return null;
        $data=['media_type'=>'CAROUSEL','children'=>implode(',',array_column($r['items'],'container_id')),$p['provider']==='threads'?'text':'caption'=>$r['caption']];
        $v=$request($p,'POST',$p['id'].($p['provider']==='threads'?'/threads':'/media'),$data);$id=$v['json']['id']??null;
        return ($v['http']??0)===200&&!isset($v['json']['error'])&&caId($id)?$id:null;
    }catch(Throwable $e){return null;}
}
