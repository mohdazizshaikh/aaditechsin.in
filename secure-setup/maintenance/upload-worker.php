<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/upload-core.php';
require __DIR__.'/upload-assets.php';
require __DIR__.'/youtube-send.php';
try{
    $cfg=opsConfig();$p=uploadTarget($cfg);if(!$p)throw new RuntimeException('Disabled');
    $mode=$argv[1]??'';
    if($mode==='--check-target'&&count($argv)===2){if(!youtubeIdentity($p,'youtubeRequest'))throw new RuntimeException('Wrong channel');echo "Channel identity verified; upload permission not proven.\n";exit;}
    $root=uploadRoot($cfg['media_upload']['private_root']??'',dirname(__DIR__,2).'/public_html');
    $db=opsDb($cfg);
    if($mode==='--stage'&&count($argv)===6&&preg_match('/^[1-9][0-9]{0,14}$/D',$argv[2])&&preg_match('/^[1-9][0-9]{0,8}$/D',$argv[3])){
        $db->beginTransaction();$r=uploadManifest($db,(int)$argv[2],(int)$argv[3],$argv[4]);
        $bytes=uploadRead($argv[5],$r['video_hash']);$raw=stream_get_contents(STDIN,8193);if(strlen($raw)>8192)throw new RuntimeException('Metadata too large');
        $m=json_decode($raw,true,16,JSON_THROW_ON_ERROR);if(!is_array($m))throw new RuntimeException('Invalid metadata');
        $id=uploadRegister($db,$cfg,(int)$argv[2],(int)$argv[3],$argv[4],$r['video_hash'],strlen($bytes),$m);
        uploadStore($root,$r['video_hash'],$bytes);$db->commit();echo "Staged upload #".$id." — pending owner approval.\n";
    }elseif($mode==='--run'&&count($argv)===3&&preg_match('/^[1-9][0-9]{0,14}$/D',$argv[2])){
        $db->beginTransaction();$row=uploadClaim($db,$cfg,(int)$argv[2]);
        $bytes=uploadRead($root.'/'.$row['video_hash'].'.mp4',$row['video_hash'],(int)$row['byte_count']);$db->commit();
        $result=youtubeSend($p,$row,$bytes,'youtubeRequest');$db->beginTransaction();uploadFinish($db,$row,$result);$db->commit();echo 'Upload #'.$row['id'].' '.$result['status']."\n";
    }else throw new InvalidArgumentException('Explicit command required');
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: configuration, asset, approval, pause or provider error. Never blindly retry sending/unknown.\n");exit(1);}
