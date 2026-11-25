<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/telegram-album-send.php';
require __DIR__.'/upload-assets.php';
try{
    $cfg=opsConfig();$p=taTarget($cfg);if(!$p)throw new RuntimeException('Disabled');
    $root=uploadRoot($cfg['media_upload']['private_root']??'',dirname(__DIR__,2).'/public_html');$db=opsDb($cfg);
    if(count($argv)===7&&$argv[1]==='--stage'&&preg_match('/^[1-9][0-9]{0,14}$/D',$argv[2])&&preg_match('/^[1-9][0-9]{0,8}$/D',$argv[3])){
        $db->beginTransaction();$r=tgMediaManifest($db,(int)$argv[2],(int)$argv[3],$argv[4],'photo');
        $png=uploadPhotoRead($argv[5],$r['image_hash'],(int)$r['width'],(int)$r['height']);$mp4=uploadRead($argv[6],$r['video_hash']);
        $id=taRegister($db,$cfg,(int)$argv[2],(int)$argv[3],$argv[4],strlen($png),strlen($mp4));
        uploadPhotoStore($root,$r['image_hash'],$png,(int)$r['width'],(int)$r['height']);uploadStore($root,$r['video_hash'],$mp4);$db->commit();echo "Album #$id staged. Separate approval required.\n";
    }elseif(count($argv)===3&&$argv[1]==='--run'&&preg_match('/^[1-9][0-9]{0,14}$/D',$argv[2])){
        $db->beginTransaction();$r=taClaim($db,$cfg,(int)$argv[2]);$png=uploadPhotoRead($root.'/'.$r['image_hash'].'.png',$r['image_hash'],(int)$r['width'],(int)$r['height']);$mp4=uploadRead($root.'/'.$r['video_hash'].'.mp4',$r['video_hash']);if(!taVerify($r,$png,$mp4))throw new RuntimeException('Bundle mismatch');$db->commit();
        $res=taSend($p,$r,$png,$mp4,'tgMediaRequest');$db->beginTransaction();taFinish($db,$r,$res);$db->commit();echo 'Album '.$res['status']."\n";
    }else throw new RuntimeException('Explicit command required');
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: album, approval, pause or provider error. No blind retry.\n");exit(1);}
