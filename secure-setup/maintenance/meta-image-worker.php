<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/meta-image-core.php';
require __DIR__.'/upload-assets.php';
require __DIR__.'/meta-image-send.php';
try{
    $cfg=opsConfig();$provider=$argv[2]??'';$p=miTarget($cfg,$provider);if(!$p)throw new RuntimeException('Disabled');$mode=$argv[1]??'';
    if($mode==='--check-target'&&count($argv)===3){if(!miIdentity($p,'miRequest'))throw new RuntimeException('Wrong target');echo "Identity verified; publishing scope not proven.\n";exit;}
    $root=miRoot($cfg);$db=opsDb($cfg);
    if($mode==='--stage'&&count($argv)===8&&preg_match('/^[1-9][0-9]{0,14}$/D',$argv[3])&&preg_match('/^[1-9][0-9]{0,8}$/D',$argv[4])){
        $db->beginTransaction();$r=miManifest($db,(int)$argv[3],(int)$argv[4],$argv[5],$provider);
        uploadPhotoRead($argv[6],$r['image_hash'],(int)$r['width'],(int)$r['height']);
        if(is_link($argv[7])||!is_file($argv[7])||filesize($argv[7])>8000000)throw new RuntimeException('Invalid JPEG');$hash=hash_file('sha256',$argv[7]);if(!is_string($hash))throw new RuntimeException('Invalid JPEG');
        [$w,$h]=miImageDimensions($provider,(int)$r['width'],(int)$r['height']);$bytes=miRead($argv[7],$hash,$w,$h);
        $id=miRegister($db,$cfg,$provider,(int)$argv[3],(int)$argv[4],$argv[5],$hash,strlen($bytes));miStore($root,$hash,$bytes,$w,$h);$db->commit();echo 'Image #'.$id." staged. Inspect exact JPEG separately before approval.\n";
    }elseif(count($argv)===4&&in_array($mode,['--create','--status','--publish'],true)&&preg_match('/^[1-9][0-9]{0,14}$/D',$argv[3])){
        $id=(int)$argv[3];
        if($mode==='--create'){
            $db->beginTransaction();$r=miClaimFetch($db,$cfg,$provider,$id);[$w,$h]=miImageDimensions($provider,(int)$r['width'],(int)$r['height']);miRead($root.'/'.$r['asset_hash'].'.jpg',$r['asset_hash'],$w,$h,(int)$r['byte_count']);$db->commit();
            $container=miCreate($p,$r,'miRequest');$db->beginTransaction();miFinishFetch($db,$r,$container);$db->commit();echo $container?"Processing; check status.\n":"Unknown; reconcile manually.\n";
        }else{
            $db->beginTransaction();$r=miRow($db,$cfg,$provider,$id,'processing');$db->commit();$ready=miReady($p,$r,'miRequest');
            if($mode==='--status'||!$ready){echo ($ready?'Ready':'Not verified ready')." — no publication attempted.\n";exit;}
            $db->beginTransaction();$fresh=miClaimPublish($db,$cfg,$provider,$id);if($fresh['container_id']!==$r['container_id'])throw new RuntimeException('Changed');$db->commit();
            $receipt=miPublish($p,$fresh,'miRequest');$db->beginTransaction();miFinishPublish($db,$fresh,$receipt);$db->commit();echo $receipt?"Accepted; visibility not verified.\n":"Unknown; reconcile manually.\n";
        }
    }else throw new InvalidArgumentException('Explicit command required');
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: configuration, asset, approval, pause or provider error. Reconcile manually.\n");exit(1);}
