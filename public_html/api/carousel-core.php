<?php
declare(strict_types=1);
require_once __DIR__.'/meta-image-core.php';
function caTarget(array $cfg,string $provider):?array{
    if(($cfg['media_upload'][$provider.'_carousel_enabled']??false)!==true)return null;
    return miTarget($cfg,$provider);
}
function caDigest(array $items):string{
    if(!array_is_list($items)||count($items)<2||count($items)>5)throw new InvalidArgumentException('Require 2 to 5 images');
    $seen=[];$canonical=[];
    foreach($items as $item){
        $h=$item['asset_hash']??null;$n=$item['byte_count']??null;
        if(!is_string($h)||!preg_match('/^[a-f0-9]{64}$/D',$h)||isset($seen[$h])||!is_int($n)||$n<24||$n>8000000)throw new InvalidArgumentException('Invalid or duplicate image');
        $seen[$h]=true;$canonical[]=['asset_hash'=>$h,'byte_count'=>$n];
    }
    return hash('sha256',json_encode($canonical,JSON_THROW_ON_ERROR));
}
function caItems(PDO $db,int $id):array{
    $q=$db->prepare('SELECT * FROM growth_carousel_items WHERE carousel_id=? ORDER BY position_no');$q->execute([$id]);return $q->fetchAll(PDO::FETCH_ASSOC);
}
function caVerify(array $row,array $items):void{
    $i=0;$canonical=[];
    foreach($items as $item){if((int)$item['position_no']!==++$i)throw new RuntimeException('Invalid order');$canonical[]=['asset_hash'=>$item['asset_hash'],'byte_count'=>(int)$item['byte_count']];}
    if(!hash_equals($row['bundle_hash'],caDigest($canonical)))throw new RuntimeException('Bundle changed');
}
function caRegister(PDO $db,array $cfg,string $provider,int $task,int $rev,string $manifest,array $items):int{
    $p=caTarget($cfg,$provider);if(!$p)throw new RuntimeException('Disabled');$r=miManifest($db,$task,$rev,$manifest,$provider);$digest=caDigest($items);
    if(strlen($r['caption'])>($provider==='threads'?500:900))throw new InvalidArgumentException('Caption too long');
    foreach(['growth_carousels','growth_meta_images',$provider==='threads'?'growth_meta_publications':'growth_instagram_uploads'] as $table){
        $q=$db->prepare("SELECT id FROM $table WHERE task_id=? AND revision=?");$q->execute([$task,$rev]);if($q->fetchColumn()!==false)throw new InvalidArgumentException('Revision reserved');
    }
    $db->prepare('INSERT INTO growth_carousels(task_id,revision,manifest_hash,bundle_hash,provider,target_id,page_id,caption) VALUES(?,?,?,?,?,?,?,?)')->execute([$task,$rev,$manifest,$digest,$provider,$p['id'],$p['page'],$r['caption']]);$id=(int)$db->lastInsertId();
    foreach($items as $i=>$item)$db->prepare('INSERT INTO growth_carousel_items(carousel_id,position_no,asset_hash,byte_count) VALUES(?,?,?,?)')->execute([$id,$i+1,$item['asset_hash'],$item['byte_count']]);
    growthAudit($db,$task,$rev,'carousel_staged','private_cli');return $id;
}
function caRow(PDO $db,array $cfg,string $provider,int $id,string $state):array{
    creativeActive($db);$p=caTarget($cfg,$provider);if(!$p)throw new RuntimeException('Disabled');
    $q=$db->prepare("SELECT u.*,m.width,m.height FROM growth_carousels u JOIN growth_tasks t ON t.id=u.task_id AND t.revision=u.revision AND t.status='approved' JOIN growth_media_manifests m ON m.task_id=u.task_id AND m.revision=u.revision AND m.manifest_hash=u.manifest_hash AND m.status='approved' WHERE u.id=? AND u.provider=? AND u.target_id=? AND u.page_id=? AND u.status=? FOR UPDATE");
    $q->execute([$id,$provider,$p['id'],$p['page'],$state]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new RuntimeException('No current approved carousel');caVerify($r,caItems($db,$id));return $r;
}
function caCommand(PDO $db,array $cfg,string $cmd):?string{
    if(!preg_match('/^\/(?:carousels|carousel|approvecarousel|cancelcarousel)(?: |$)/D',$cmd))return null;
    if(!caTarget($cfg,'threads')&&!caTarget($cfg,'instagram'))return 'Carousels disabled.';
    if($cmd==='/carousels'){
        $lines=['Carousel ledger (not visibility):'];foreach($db->query('SELECT id,provider,status,receipt FROM growth_carousels ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC) as $r)$lines[]='#'.$r['id'].' '.$r['provider'].' '.$r['status'].' '.($r['receipt']??'');return implode("\n",$lines);
    }
    if(preg_match('/^\/carousel ([1-9][0-9]{0,14})$/D',$cmd,$m)){
        $q=$db->prepare('SELECT * FROM growth_carousels WHERE id=?');$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return 'Not found.';
        $lines=['#'.$r['id'].' '.$r['status'].' public '.$r['provider'].' '.$r['target_id'],$r['caption'],'Bundle '.$r['bundle_hash'],'Review all JPEGs in this order; temporary bearer access authorized on approval.'];
        foreach(caItems($db,(int)$r['id']) as $item)$lines[]=$item['position_no'].': '.$item['asset_hash'].' '.($item['container_id']??'');return implode("\n",$lines);
    }
    if(preg_match('/^\/approvecarousel ([1-9][0-9]{0,14}) ([a-f0-9]{64}) (threads|instagram) ([1-9][0-9]{0,30})$/D',$cmd,$m)){
        try{$r=caRow($db,$cfg,$m[3],(int)$m[1],'pending_review');}catch(RuntimeException $e){return 'Approval blocked.';}
        if(!hash_equals($r['bundle_hash'],$m[2])||$r['target_id']!==$m[4])return 'Bundle or target mismatch.';
        $db->prepare("UPDATE growth_carousels SET status='approved' WHERE id=?")->execute([$r['id']]);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'carousel_approved','owner');return 'Exact ordered bundle approved. No network request sent.';
    }
    if(preg_match('/^\/cancelcarousel ([1-9][0-9]{0,14})$/D',$cmd,$m)){
        $q=$db->prepare("SELECT * FROM growth_carousels WHERE id=? AND status IN ('pending_review','approved','processing','parent_ready') FOR UPDATE");$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)return 'In-flight or terminal state cannot cancel.';
        $db->prepare("UPDATE growth_carousels SET status='cancelled' WHERE id=?")->execute([$r['id']]);$db->prepare('UPDATE growth_carousel_items SET token_hash=NULL WHERE carousel_id=?')->execute([$r['id']]);growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'carousel_cancelled','owner');return 'Cancelled locally; provider copies not deleted.';
    }
    return 'Use /carousels /carousel ID /approvecarousel ID BUNDLE_SHA256 PROVIDER ACCOUNT_ID /cancelcarousel ID.';
}
function caClaim(PDO $db,array $cfg,string $provider,int $id,string $stage):array{
    [$from,$to]=match($stage){'children'=>['approved','fetching'],'parent'=>['processing','assembling'],'publish'=>['parent_ready','publishing'],default=>throw new InvalidArgumentException('Invalid stage')};
    $r=caRow($db,$cfg,$provider,$id,$from);$items=caItems($db,$id);
    if($stage==='children'){
        [$w,$h]=miImageDimensions($provider,(int)$r['width'],(int)$r['height']);
        foreach($items as &$item){miRead(miRoot($cfg).'/'.$item['asset_hash'].'.jpg',$item['asset_hash'],$w,$h,(int)$item['byte_count']);$token=bin2hex(random_bytes(32));$db->prepare('UPDATE growth_carousel_items SET token_hash=?,expires_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE),fetch_count=0 WHERE carousel_id=? AND position_no=?')->execute([hash('sha256',$token),$id,$item['position_no']]);$item['fetch_token']=$token;}unset($item);
    }else foreach($items as $item)if(!caId($item['container_id']))throw new RuntimeException('Missing child receipt');
    if($stage==='publish'&&!caId($r['container_id']))throw new RuntimeException('Missing parent receipt');
    $db->prepare('UPDATE growth_carousels SET status=? WHERE id=?')->execute([$to,$id]);
    if($stage!=='children')$db->prepare('UPDATE growth_carousel_items SET token_hash=NULL WHERE carousel_id=?')->execute([$id]);
    growthAudit($db,(int)$r['task_id'],(int)$r['revision'],'carousel_'.$to,'private_cli');return $r+['items'=>$items];
}
function caId(mixed $id):bool{return is_string($id)&&preg_match('/^[1-9][0-9]{0,30}$/D',$id)===1;}
function caRemember(PDO $db,array $row,int $position,string $id):void{
    if(!$db->inTransaction()||!caId($id))throw new InvalidArgumentException('Invalid receipt');
    $q=$db->prepare("UPDATE growth_carousel_items i JOIN growth_carousels c ON c.id=i.carousel_id SET i.container_id=? WHERE c.id=? AND c.status='fetching' AND i.position_no=? AND i.container_id IS NULL");
    $q->execute([$id,$row['id'],$position]);if($q->rowCount()!==1)throw new RuntimeException('Invalid child transition');
    growthAudit($db,(int)$row['task_id'],(int)$row['revision'],'carousel_child','private_cli');
}
function caFinish(PDO $db,array $row,string $stage,bool $ok,?string $receipt=null):void{
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    [$from,$to]=match($stage){'children'=>['fetching','processing'],'parent'=>['assembling','parent_ready'],'publish'=>['publishing','accepted'],default=>throw new InvalidArgumentException('Stage')};
    if($ok&&$stage==='children'){foreach(caItems($db,(int)$row['id']) as $item)if(!caId($item['container_id']))throw new RuntimeException('Incomplete children');}
    if($ok&&$stage!=='children'&&!caId($receipt))throw new InvalidArgumentException('Invalid receipt');
    $state=$ok?$to:'unknown';$field=$stage==='parent'?'container_id':'receipt';
    $q=$db->prepare("UPDATE growth_carousels SET status=?,$field=COALESCE(?,$field) WHERE id=? AND status=?");$q->execute([$state,$ok?$receipt:null,$row['id'],$from]);if($q->rowCount()!==1)throw new RuntimeException('State changed');
    if(!$ok)$db->prepare('UPDATE growth_carousel_items SET token_hash=NULL WHERE carousel_id=?')->execute([$row['id']]);
    growthAudit($db,(int)$row['task_id'],(int)$row['revision'],'carousel_'.$state,'private_cli');
}
function caFetch(PDO $db,array $cfg,string $token,bool $head):array{
    creativeActive($db);if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new RuntimeException('Unavailable');
    $q=$db->prepare("SELECT i.*,c.provider,c.target_id,c.page_id,c.bundle_hash,m.width,m.height FROM growth_carousel_items i JOIN growth_carousels c ON c.id=i.carousel_id JOIN growth_tasks t ON t.id=c.task_id AND t.revision=c.revision AND t.status='approved' JOIN growth_media_manifests m ON m.task_id=c.task_id AND m.revision=c.revision AND m.manifest_hash=c.manifest_hash AND m.status='approved' WHERE i.token_hash=? AND i.expires_at>UTC_TIMESTAMP() AND i.fetch_count<20 AND c.status IN ('fetching','processing') FOR UPDATE");
    $q->execute([hash('sha256',$token)]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new RuntimeException('Unavailable');$p=caTarget($cfg,$r['provider']);if(!$p||$p['id']!==$r['target_id']||$p['page']!==$r['page_id'])throw new RuntimeException('Unavailable');caVerify($r,caItems($db,(int)$r['carousel_id']));
    [$w,$h]=miImageDimensions($r['provider'],(int)$r['width'],(int)$r['height']);$bytes=miRead(miRoot($cfg).'/'.$r['asset_hash'].'.jpg',$r['asset_hash'],$w,$h,(int)$r['byte_count']);
    $db->prepare('UPDATE growth_carousel_items SET fetch_count=fetch_count+1 WHERE carousel_id=? AND position_no=?')->execute([$r['carousel_id'],$r['position_no']]);return ['bytes'=>$head?'':$bytes,'size'=>strlen($bytes),'kind'=>'image'];
}
