<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/facebook-media-core.php';
require __DIR__.'/upload-assets.php';
require __DIR__.'/facebook-media-send.php';
try{
    $cfg=opsConfig();$p=fbMediaTarget($cfg);if(!$p)throw new RuntimeException('Disabled');
    $mode=$argv[1]??'';
    if($mode==='--check-target'&&count($argv)===2){if(!metaIdentity($p,'metaRequest'))throw new RuntimeException('Wrong Page');echo "Page verified; publish permission not proven.\n";exit;}
    $root=uploadRoot($cfg['media_upload']['private_root']??'',dirname(__DIR__,2).'/public_html');$db=opsDb($cfg);
    if($mode==='--stage'&&count($argv)===7&&preg_match('/^[1-9][0-9]{0,14}$/D',$argv[2])&&preg_match('/^[1-9][0-9]{0,8}$/D',$argv[3])&&in_array($argv[5],['photo','video'],true)){
        $db->beginTransaction();$r=fbMediaManifest($db,(int)$argv[2],(int)$argv[3],$argv[4],$argv[5]);$hash=$r['asset_hash'];
        $bytes=$argv[5]==='photo'?uploadPhotoRead($argv[6],$hash,(int)$r['width'],(int)$r['height']):uploadRead($argv[6],$hash);
        $id=fbMediaRegister($db,$cfg,(int)$argv[2],(int)$argv[3],$argv[4],$argv[5],$hash,strlen($bytes));
        if($argv[5]==='photo')uploadPhotoStore($root,$hash,$bytes,(int)$r['width'],(int)$r['height']);else uploadStore($root,$hash,$bytes);
        $db->commit();echo 'Media #'.$id." staged, pending approval.\n";
    }elseif($mode==='--run'&&count($argv)===3&&preg_match('/^[1-9][0-9]{0,14}$/D',$argv[2])){
        $db->beginTransaction();$row=fbMediaClaim($db,$cfg,(int)$argv[2]);$path=$root.'/'.$row['asset_hash'];
        $bytes=$row['asset_kind']==='photo'?uploadPhotoRead($path.'.png',$row['asset_hash'],(int)$row['width'],(int)$row['height'],(int)$row['byte_count']):uploadRead($path.'.mp4',$row['asset_hash'],(int)$row['byte_count']);
        $db->commit();$r=fbMediaSend($p,$row,$bytes,'metaRequest','fbMediaRequest');$db->beginTransaction();fbMediaFinish($db,$row,$r);$db->commit();echo 'Media #'.$row['id'].' '.$r['status']."\n";
    }else throw new InvalidArgumentException('Explicit command required');
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: configuration, asset, approval, pause or provider error. Reconcile uncertain sends manually.\n");exit(1);}
