<?php
declare(strict_types=1);
require_once __DIR__.'/telegram-media-send.php';
require_once dirname(__DIR__,2).'/public_html/api/telegram-album-core.php';
function taSend(array $p,array $r,string $png,string $mp4,callable $request):array{
    $unknown=['status'=>'unknown','receipt'=>null,'messages'=>null];$failed=['status'=>'failed','receipt'=>null,'messages'=>null];
    try{
        if($p['id']!==$r['target_id']||!taVerify($r,$png,$mp4)||trim($r['caption'])===''||strlen($r['caption'])>900||!tgMediaIdentity($p,$request))return $failed;
        $media=[['type'=>'photo','media'=>'attach://photo1','caption'=>$r['caption']],['type'=>'video','media'=>'attach://video2']];
        $res=tgMediaRead($p,'sendMediaGroup',['chat_id'=>$p['id'],'media'=>json_encode($media,JSON_THROW_ON_ERROR),'photo1'=>new CURLStringFile($png,'creative.png','image/png'),'video2'=>new CURLStringFile($mp4,'creative.mp4','video/mp4')],$request);
        if(!is_array($res)||!array_is_list($res)||count($res)!==2)return $unknown;
        $group=null;$ids=[];
        foreach($res as $i=>$m){
            $g=$m['media_group_id']??null;$id=$m['message_id']??null;
            if(!is_string($g)||!preg_match('/^[0-9]{1,32}$/D',$g)||($group!==null&&$group!==$g)||!is_int($id)||$id<=0||in_array($id,$ids,true)||!is_int($m['chat']['id']??null)||(string)$m['chat']['id']!==$p['id']||!isset($m[$i===0?'photo':'video']))return $unknown;
            $group=$g;$ids[]=$id;
        }
        return ['status'=>'accepted','receipt'=>$group,'messages'=>implode(',',$ids)];
    }catch(Throwable $e){return $unknown;}
}
