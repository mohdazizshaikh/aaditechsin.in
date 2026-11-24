<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/network-media-core.php';
require __DIR__.'/upload-assets.php';
require __DIR__.'/network-media-send.php';
try{
    $cfg=opsConfig();$provider=$argv[2]??'';$p=nmTarget($cfg,$provider);if(!$p)throw new RuntimeException('Disabled');
    $mode=$argv[1]??'';
    if($mode==='--check-target'&&count($argv)===3){if(!networkIdentity($p,'networkRequest'))throw new RuntimeException('Wrong target');echo "Account verified; publish permission not proven.\n";exit;}
    $root=uploadRoot($cfg['media_upload']['private_root']??'',dirname(__DIR__,2).'/public_html');$db=opsDb($cfg);
    if($mode==='--stage'&&count($argv)===7&&preg_match('/^[1-9][0-9]{0,14}$/D',$argv[3])&&preg_match('/^[1-9][0-9]{0,8}$/D',$argv[4])){
        $db->beginTransaction();$r=nmManifest($db,(int)$argv[3],(int)$argv[4],$argv[5],$provider);$hash=$r['asset_hash'];
        $bytes=uploadPhotoRead($argv[6],$hash,(int)$r['width'],(int)$r['height']);
        $id=nmRegister($db,$cfg,(int)$argv[3],(int)$argv[4],$argv[5],$provider,$hash,strlen($bytes));
        uploadPhotoStore($root,$hash,$bytes,(int)$r['width'],(int)$r['height']);
        $db->commit();echo 'Media #'.$id." staged, pending approval.\n";
    }elseif($mode==='--run'&&count($argv)===4&&preg_match('/^[1-9][0-9]{0,14}$/D',$argv[3])){
        $db->beginTransaction();$row=nmClaim($db,$cfg,$provider,(int)$argv[3]);$path=$root.'/'.$row['asset_hash'];
        $bytes=uploadPhotoRead($path.'.png',$row['asset_hash'],(int)$row['width'],(int)$row['height'],(int)$row['byte_count']);
        $db->commit();$r=nmSend($p,$row,$bytes,'metaRequest','nmRequest');$db->beginTransaction();nmFinish($db,$row,$r);$db->commit();echo 'Media #'.$row['id'].' '.$r['status']."\n";
    }else throw new InvalidArgumentException('Explicit command required');
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: configuration, asset, approval, pause or provider error. Reconcile uncertain sends manually.\n");exit(1);}
