<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/instagram-core.php';
require __DIR__.'/upload-assets.php';
require __DIR__.'/instagram-send.php';
try{
    $cfg=opsConfig();$p=igTarget($cfg);if(!$p)throw new RuntimeException('Disabled');
    $mode=$argv[1]??'';
    if($mode==='--check-target'&&count($argv)===2){if(!igIdentity($p,'igRequest'))throw new RuntimeException('Wrong target');echo "Page and linked Instagram account verified; publish permission not proven.\n";exit;}
    $root=uploadRoot($cfg['media_upload']['private_root']??'',dirname(__DIR__,2).'/public_html');$db=opsDb($cfg);
    if($mode==='--stage'&&count($argv)===6&&preg_match('/^[1-9][0-9]{0,14}$/D',$argv[2])&&preg_match('/^[1-9][0-9]{0,8}$/D',$argv[3])){
        $db->beginTransaction();$r=igManifest($db,(int)$argv[2],(int)$argv[3],$argv[4]);$bytes=uploadRead($argv[5],$r['video_hash']);
        $id=igRegister($db,$cfg,(int)$argv[2],(int)$argv[3],$argv[4],$r['video_hash'],strlen($bytes));uploadStore($root,$r['video_hash'],$bytes);$db->commit();echo 'Reel #'.$id." staged, pending approval.\n";
    }elseif(count($argv)===3&&in_array($mode,['--upload','--status','--publish'],true)&&preg_match('/^[1-9][0-9]{0,14}$/D',$argv[2])){
        $id=(int)$argv[2];
        if($mode==='--upload'){
            $db->beginTransaction();$row=igClaim($db,$cfg,$id,'upload');$bytes=uploadRead($root.'/'.$row['video_hash'].'.mp4',$row['video_hash'],(int)$row['byte_count']);$db->commit();
            $result=igUpload($p,$row,$bytes,'igRequest');$db->beginTransaction();igFinish($db,$row,'upload',$result);$db->commit();echo 'Reel #'.$id.' '.$result['status']."\n";
        }else{
            $db->beginTransaction();$row=igRow($db,$cfg,$id,'processing');$db->commit();
            $status=igContainerStatus($p,$row,'igRequest');
            if($mode==='--status'||$status!=='FINISHED'){echo 'Container status: '.$status." — no publish attempted.\n";exit;}
            $db->beginTransaction();$fresh=igClaim($db,$cfg,$id,'publish');
            if($fresh['container_id']!==$row['container_id'])throw new RuntimeException('Container changed');
            $db->commit();$result=igPublish($p,$fresh,'igRequest');$db->beginTransaction();igFinish($db,$fresh,'publish',$result);$db->commit();echo 'Reel #'.$id.' '.$result['status']."\n";
        }
    }else throw new InvalidArgumentException('Explicit command required');
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: configuration, asset, approval, pause or provider error. Reconcile uncertain stages manually.\n");exit(1);}
