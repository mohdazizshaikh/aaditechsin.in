<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/carousel-core.php';
require __DIR__.'/upload-assets.php';
require __DIR__.'/carousel-send.php';
try{
    $cfg=opsConfig();$provider=$argv[2]??'';$p=caTarget($cfg,$provider);if(!$p)throw new RuntimeException('Disabled');$db=opsDb($cfg);$mode=$argv[1]??'';
    if($mode==='--stage'&&count($argv)>=9&&count($argv)<=12&&preg_match('/^[1-9][0-9]{0,14}$/D',$argv[3])&&preg_match('/^[1-9][0-9]{0,8}$/D',$argv[4])){
        $db->beginTransaction();$m=miManifest($db,(int)$argv[3],(int)$argv[4],$argv[5],$provider);uploadPhotoRead($argv[6],$m['image_hash'],(int)$m['width'],(int)$m['height']);
        [$w,$h]=miImageDimensions($provider,(int)$m['width'],(int)$m['height']);$items=[];
        foreach(array_slice($argv,7) as $path){
            if(is_link($path)||!is_file($path)||filesize($path)>8000000)throw new RuntimeException('Invalid JPEG');$hash=hash_file('sha256',$path);$bytes=miRead($path,$hash,$w,$h);miStore(miRoot($cfg),$hash,$bytes,$w,$h);$items[]=['asset_hash'=>$hash,'byte_count'=>strlen($bytes)];
        }
        $id=caRegister($db,$cfg,$provider,(int)$argv[3],(int)$argv[4],$argv[5],$items);$db->commit();echo 'Carousel #'.$id." staged. Review exact order and hashes.\n";
    }elseif(count($argv)===4&&in_array($mode,['--children','--parent','--publish','--status'],true)&&preg_match('/^[1-9][0-9]{0,14}$/D',$argv[3])){
        $id=(int)$argv[3];$stage=substr($mode,2);
        if($stage==='status'){$q=$db->prepare('SELECT status FROM growth_carousels WHERE id=? AND provider=? AND target_id=? AND page_id=?');$q->execute([$id,$provider,$p['id'],$p['page']]);echo ($q->fetchColumn()?:'Unavailable')."\n";exit;}
        if($stage!=='children'){
            $db->beginTransaction();$row=caRow($db,$cfg,$provider,$id,$stage==='parent'?'processing':'parent_ready');$row['items']=caItems($db,$id);$db->commit();
            if(!($stage==='parent'?caChildrenReady($p,$row,'miRequest'):miReady($p,$row,'miRequest'))){echo "Not verified ready; no write attempted.\n";exit;}
        }
        $db->beginTransaction();$row=caClaim($db,$cfg,$provider,$id,$stage);$db->commit();
        $receipt=null;
        if($stage==='children'){
            $remember=static function(int $position,string $asset)use($db,$row):void{$db->beginTransaction();try{caRemember($db,$row,$position,$asset);$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}};
            $ok=caChildren($p,$row,'miRequest',$remember);
        }else{$receipt=$stage==='parent'?caParent($p,$row,'miRequest'):miPublish($p,$row,'miRequest');$ok=$receipt!==null;}
        $db->beginTransaction();caFinish($db,$row,$stage,$ok,$receipt);$db->commit();echo $ok?"Stage recorded; inspect readiness before next action.\n":"Unknown; reconcile manually, no retry.\n";
    }else throw new InvalidArgumentException('Explicit command required');
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: check private assets, approval, pause and provider evidence. No blind retry.\n");exit(1);}
