<?php
declare(strict_types=1);
require_once __DIR__.'/network-send.php';
function xvUpload(array $p,array $row,string $bytes,callable $request,callable $remember):array{
    $id=null;$size=strlen($bytes);
    if($p['provider']!=='x'||$p['id']!==$row['target_id']||$size!==(int)$row['byte_count']||$size<24||$size>20*1024*1024||!hash_equals($row['video_hash'],hash('sha256',$bytes)))return networkResult('failed',null,'asset_mismatch');
    try{
        if(!networkIdentity($p,$request))return networkResult('failed',null,'identity_unverified');
        $r=$request($p,'POST','2/media/upload/initialize',['media_category'=>'tweet_video','media_type'=>'video/mp4','total_bytes'=>$size,'shared'=>false]);
        $v=$r['json']['data']??[];$candidate=$v['id']??null;$expiry=$v['expires_after_secs']??null;
        if(!in_array($r['http']??0,[200,201],true)||!empty($r['json']['errors'])||!xvId($candidate))return networkResult('unknown',null,'session_unverified');
        $remember($candidate);$id=$candidate;
        if(!is_int($expiry)||$expiry<60||$expiry>86400)return networkResult('unknown',$id,'session_unverified');
        $deadline=time()+$expiry;
        for($offset=0,$index=0;$offset<$size;$offset+=4194304,$index++){
            if(time()+30>=$deadline)return networkResult('unknown',$id,'session_expired');
            $r=$request($p,'POST','2/media/upload/'.$id.'/append',['media'=>base64_encode(substr($bytes,$offset,4194304)),'segment_index'=>$index]);
            if(!in_array($r['http']??0,[200,201,204],true)||!empty($r['json']['errors']))return networkResult('unknown',$id,'part_unverified');
        }
        if(time()+30>=$deadline||!networkIdentity($p,$request))return networkResult('unknown',$id,'identity_or_expiry');
        $r=$request($p,'POST','2/media/upload/'.$id.'/finalize',[]);$v=$r['json']['data']??[];
        return in_array($r['http']??0,[200,201],true)&&empty($r['json']['errors'])&&($v['id']??null)===$id&&in_array($v['processing_info']['state']??'succeeded',['pending','in_progress','succeeded'],true)?networkResult('processing',$id):networkResult('unknown',$id,'finalize_unverified');
    }catch(Throwable $e){return networkResult('unknown',$id,'transport_error');}
}
function xvReady(array $p,array $row,callable $request):bool{
    if($p['provider']!=='x'||$p['id']!==$row['target_id']||!xvId($row['video_id'])||!networkIdentity($p,$request))return false;
    $r=$request($p,'GET','2/media/upload',['media_id'=>$row['video_id'],'command'=>'STATUS']);$v=$r['json']['data']??[];
    return ($r['http']??0)===200&&empty($r['json']['errors'])&&($v['id']??null)===$row['video_id']&&($v['processing_info']['state']??null)==='succeeded'&&is_int($v['expires_after_secs']??null)&&$v['expires_after_secs']>30;
}
function xvPublish(array $p,array $row,callable $request):array{
    try{
        if(!xvReady($p,$row,$request))return networkResult('unknown',null,'asset_not_ready');
        $r=$request($p,'POST','2/tweets',['text'=>$row['caption'],'media'=>['media_ids'=>[$row['video_id']]]]);$id=$r['json']['data']['id']??null;
        return in_array($r['http']??0,[200,201],true)&&empty($r['json']['errors'])&&xvId($id)?networkResult('accepted',$id):networkResult('unknown',null,'publish_unverified');
    }catch(Throwable $e){return networkResult('unknown',null,'transport_error');}
}
