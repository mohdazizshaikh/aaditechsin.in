<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/linkedin-video-core.php';
require __DIR__.'/upload-assets.php';
require __DIR__.'/linkedin-video-send.php';
try{
    $cfg=opsConfig();$p=lvTarget($cfg);if(!$p)throw new RuntimeException('Disabled');$mode=$argv[1]??'';
    if($mode==='--check-target'&&count($argv)===2){if(!networkIdentity($p,'networkRequest'))throw new RuntimeException('Wrong target');echo "Organization access verified; publish permission not proven.\n";exit;}
    $root=uploadRoot($cfg['media_upload']['private_root']??'',dirname(__DIR__,2).'/public_html');$db=opsDb($cfg);
    if($mode==='--stage'&&count($argv)===6&&preg_match('/^[1-9][0-9]{0,14}$/D',$argv[2])&&preg_match('/^[1-9][0-9]{0,8}$/D',$argv[3])){
        $db->beginTransaction();$r=nmManifest($db,(int)$argv[2],(int)$argv[3],$argv[4],'linkedin');$bytes=uploadRead($argv[5],$r['video_hash']);
        $id=lvRegister($db,$cfg,(int)$argv[2],(int)$argv[3],$argv[4],$r['video_hash'],strlen($bytes));uploadStore($root,$r['video_hash'],$bytes);$db->commit();echo 'Video #'.$id." staged.\n";
    }elseif(count($argv)===3&&in_array($mode,['--upload','--status','--publish'],true)&&preg_match('/^[1-9][0-9]{0,14}$/D',$argv[2])){
        $id=(int)$argv[2];
        if($mode==='--upload'){
            $db->beginTransaction();$row=lvClaim($db,$cfg,$id,'upload');$bytes=uploadRead($root.'/'.$row['video_hash'].'.mp4',$row['video_hash'],(int)$row['byte_count']);$db->commit();
            $remember=static function(string $asset)use($db,$row):void{$db->beginTransaction();try{lvRemember($db,$row,$asset);$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}};
            $r=lvUpload($p,$row,$bytes,'networkRequest','lvPart',$remember);$db->beginTransaction();lvFinish($db,$row,'upload',$r);$db->commit();echo 'Video #'.$id.' '.$r['status']."\n";
        }else{
            $db->beginTransaction();$row=lvRow($db,$cfg,$id,'processing');$db->commit();$ready=lvReady($p,$row,'networkRequest');
            if($mode==='--status'||!$ready){echo ($ready?'Ready':'Not verified ready')." — no publication attempted.\n";exit;}
            $db->beginTransaction();$fresh=lvClaim($db,$cfg,$id,'publish');if($fresh['video_id']!==$row['video_id'])throw new RuntimeException('Changed');$db->commit();
            $r=lvPublish($p,$fresh,'networkRequest');$db->beginTransaction();lvFinish($db,$fresh,'publish',$r);$db->commit();echo 'Video #'.$id.' '.$r['status']."\n";
        }
    }else throw new InvalidArgumentException('Explicit command required');
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: configuration, asset, approval, pause or provider error. Inspect recorded resource; no blind retry.\n");exit(1);}
