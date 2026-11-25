<?php
declare(strict_types=1);
require_once __DIR__.'/meta-image-core.php';
function storageEnabled(array $cfg):bool {
    return mediaEnabled($cfg)&&($cfg['media_upload']['enabled']??false)===true&&($cfg['media_upload']['inventory_enabled']??false)===true;
}
function storageName(string $name):bool{return preg_match('/^[a-f0-9]{64}\.(?:png|jpg|mp4)$/D',$name)===1;}
// Fixed reference sources shared by retention protection and read-only audit.
function storageReferenceSources():array{
    return [
        'manifest-image'=>['png','growth_media_manifests','image_hash',''],
        'manifest-video'=>['mp4','growth_media_manifests','video_hash',''],
        'telegram-photo'=>['png','growth_telegram_media','asset_hash',"asset_kind='photo'"],
        'telegram-video'=>['mp4','growth_telegram_media','asset_hash',"asset_kind='video'"],
        'facebook-photo'=>['png','growth_facebook_media','asset_hash',"asset_kind='photo'"],
        'facebook-video'=>['mp4','growth_facebook_media','asset_hash',"asset_kind='video'"],
        'network-image'=>['png','growth_network_media','asset_hash',''],
        'carousel-image'=>['jpg','growth_carousel_items','asset_hash',"asset_kind='image'"],
        'carousel-video'=>['mp4','growth_carousel_items','asset_hash',"asset_kind='video'"],
        'meta-image'=>['jpg','growth_meta_images','asset_hash',"asset_kind='image'"],
        'meta-video'=>['mp4','growth_meta_images','asset_hash',"asset_kind='video'"],
        'youtube-video'=>['mp4','growth_media_uploads','video_hash',''],
        'linkedin-video'=>['mp4','growth_linkedin_video','video_hash',''],
        'x-video'=>['mp4','growth_x_video','video_hash',''],
        'instagram-video'=>['mp4','growth_instagram_uploads','video_hash','']
    ];
}
function storageReferences(PDO $db,string $name):int {
    if(!storageName($name))throw new InvalidArgumentException('Invalid asset name');
    [$hash,$ext]=explode('.',$name);$n=0;
    foreach(storageReferenceSources() as [$format,$table,$column,$filter]){
        if($format!==$ext)continue;
        $q=$db->prepare("SELECT COUNT(*) FROM $table WHERE $column=?".($filter!==''?" AND $filter":''));$q->execute([$hash]);$n+=(int)$q->fetchColumn();
    }
    return $n;
}
function storageFile(string $root,string $name):array {
    if(!storageName($name))throw new InvalidArgumentException('Invalid asset name');
    $path=$root.'/'.$name;clearstatcache(true,$path);
    $s=@lstat($path);if(!$s||is_link($path)||!is_file($path)||($s['mode']&0077)!==0||$s['nlink']!==1||$s['size']<24||$s['size']>20*1024*1024)throw new RuntimeException('Unsafe asset');
    return ['path'=>$path,'size'=>(int)$s['size'],'mtime'=>(int)$s['mtime']];
}
function storageBytes(string $root,string $name):string {
    $s=storageFile($root,$name);$f=fopen($s['path'],'rb');if(!$f)throw new RuntimeException('Unavailable');
    try{$bytes=stream_get_contents($f,20*1024*1024+1);}finally{fclose($f);}
    if(!is_string($bytes)||strlen($bytes)!==$s['size']||!hash_equals(substr($name,0,64),hash('sha256',$bytes)))throw new RuntimeException('Hash mismatch');
    $ext=substr($name,65);
    if($ext==='mp4'){if(substr($bytes,4,4)!=='ftyp')throw new RuntimeException('Invalid MP4');}
    else{$info=@getimagesizefromstring($bytes);if(!$info||$info[2]!==($ext==='png'?IMAGETYPE_PNG:IMAGETYPE_JPEG))throw new RuntimeException('Invalid image');}
    return $bytes;
}
function storageInventory(PDO $db,string $root,int $limit=200):array {
    if($limit<1||$limit>500)throw new InvalidArgumentException('Invalid limit');
    $items=[];$skipped=0;$seen=0;$truncated=false;
    foreach(new DirectoryIterator($root) as $entry){
        if($entry->isDot())continue;
        if(++$seen>5000){$truncated=true;break;}
        $name=$entry->getFilename();if($name==='.quarantine')continue;
        if(!storageName($name)){++$skipped;continue;}
        try{$s=storageFile($root,$name);}catch(RuntimeException $e){++$skipped;continue;}
        if(count($items)>=$limit){$truncated=true;break;}
        $refs=storageReferences($db,$name);
        $items[]=['name'=>$name,'size'=>$s['size'],'modified_utc'=>gmdate('Y-m-d H:i:s',$s['mtime']),'references'=>$refs,'candidate'=>$refs===0&&$s['mtime']<=time()-30*86400];
    }
    return ['items'=>$items,'skipped'=>$skipped,'truncated'=>$truncated,'scope'=>'bounded inventory, not total storage'];
}
function storagePreview(PDO $db,array $cfg,string $name):string {
    if(!storageEnabled($cfg)||!storageName($name)||storageReferences($db,$name)<1)throw new RuntimeException('Unavailable');
    return storageBytes(miRoot($cfg),$name);
}
function storageOwner(array $cfg,array $session,int $now):bool {
    if(($cfg['portal']['enabled']??false)!==true||!storageEnabled($cfg)||($session['role']??null)!=='admin'||!is_int($session['last_seen']??null)||$session['last_seen']>$now||$now-$session['last_seen']>1800||!is_string($session['admin_fingerprint']??null))return false;
    return hash_equals(opsAdminSessionFingerprint($cfg),$session['admin_fingerprint']);
}
function storageQuarantineDir(string $root):string {
    $path=$root.'/.quarantine';
    if(!is_dir($path)&&!is_link($path)&&!mkdir($path,0700))throw new RuntimeException('Cannot create quarantine');
    if(is_link($path)||!is_dir($path)||realpath($path)!==$path||(fileperms($path)&0077)!==0)throw new RuntimeException('Unsafe quarantine');
    return $path;
}
function storageMove(PDO $db,array $cfg,string $name,bool $restore=false,?int $now=null):void {
    if(!$db->inTransaction()||!storageEnabled($cfg)||($cfg['media_upload']['retention_enabled']??false)!==true||!storageName($name))throw new RuntimeException('Maintenance disabled');
    // Same row lock as all staged media workers. Require global pause and stopped workers.
    $paused=$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn();
    if($paused===false||(int)$paused!==1)throw new RuntimeException('Pause required');
    $root=miRoot($cfg);$q=storageQuarantineDir($root);
    $from=$restore?$q:$root;$to=$restore?$root:$q;$s=storageFile($from,$name);
    if(!$restore&&(storageReferences($db,$name)!==0||$s['mtime']>($now??time())-30*86400))throw new RuntimeException('Referenced or recent asset protected');
    storageBytes($from,$name);
    if(file_exists($to.'/'.$name)||is_link($to.'/'.$name))throw new RuntimeException('Destination exists');
    // Same-filesystem atomic move, no deletion. DB/filesystem not a single transaction.
    if(!rename($s['path'],$to.'/'.$name))throw new RuntimeException('Move failed');
    $db->prepare('INSERT INTO growth_media_storage_events(asset_name,action,byte_count) VALUES(?,?,?)')->execute([$name,$restore?'restored':'quarantined',$s['size']]);
}

/** No mutation. Missing manifest bytes may legitimately await transfer. */
function storageAuditState(string $root,string $name,int &$remaining):string{
    if(!storageName($name))return 'invalid_reference';
    $path=$root.'/'.$name;clearstatcache(true,$path);
    if(!file_exists($path)&&!is_link($path))return 'missing';
    try{$info=storageFile($root,$name);}catch(RuntimeException $e){return 'unsafe';}
    if($info['size']>$remaining)return 'not_checked_budget';
    $remaining-=$info['size'];
    try{storageBytes($root,$name);return 'verified';}catch(RuntimeException $e){return 'invalid_bytes';}
}
function storageReferenceAudit(PDO $db,string $root,int $perSource=20,int $byteBudget=134217728):array{
    if($perSource<1||$perSource>50||$byteBudget<1||$byteBudget>134217728)throw new InvalidArgumentException('Invalid audit bounds');
    $remaining=$byteBudget;$cache=[];$sources=[];
    foreach(storageReferenceSources() as $label=>[$ext,$table,$column,$filter]){
        $where=$filter!==''?" WHERE $filter":'';
        // Fixed source/column names only; cap returned distinct references.
        $q=$db->query("SELECT DISTINCT $column FROM $table$where ORDER BY $column LIMIT ".($perSource+1));
        $values=$q->fetchAll(PDO::FETCH_COLUMN);
        $truncated=count($values)>$perSource;$values=array_slice($values,0,$perSource);
        $counts=array_fill_keys(['verified','missing','unsafe','invalid_bytes','invalid_reference','not_checked_budget'],0);
        foreach($values as $hash){
            $name=is_string($hash)?$hash.'.'.$ext:'';
            if(!storageName($name)){$counts['invalid_reference']++;continue;}
            if(!isset($cache[$name]))$cache[$name]=storageAuditState($root,$name,$remaining);
            $counts[$cache[$name]]++;
        }
        $sources[$label]=['distinct_sample'=>count($values),'truncated'=>$truncated,'counts'=>$counts];
    }
    return ['scope'=>'bounded distinct-reference sample, not full inventory or publication readiness','per_source_limit'=>$perSource,'unique_valid_names_sampled'=>count($cache),'bytes_budget_consumed'=>$byteBudget-$remaining,'sources'=>$sources];
}
function storagePlanRequest(string $raw):array{
    if(strlen($raw)>8192)throw new InvalidArgumentException('Plan input too large');
    $d=json_decode($raw,true,16,JSON_THROW_ON_ERROR);
    if(!is_array($d)||count($d)!==2||($d['version']??null)!==1||!is_array($d['names']??null)||!array_is_list($d['names'])||count($d['names'])<1||count($d['names'])>50)throw new InvalidArgumentException('Invalid plan');
    $seen=[];foreach($d['names'] as $name){
        if(!is_string($name)||!storageName($name)||isset($seen[$name]))throw new InvalidArgumentException('Invalid or duplicate asset');
        $seen[$name]=true;
    }
    return $d['names'];
}
function storageQuarantinePlan(PDO $db,array $cfg,string $raw,?int $now=null):array{
    if(!$db->inTransaction()||!storageEnabled($cfg)||($cfg['media_upload']['retention_enabled']??false)!==true)throw new RuntimeException('Maintenance disabled');
    $paused=$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn();
    if($paused===false||(int)$paused!==1)throw new RuntimeException('Pause required');
    $root=miRoot($cfg);$q=$root.'/.quarantine';$now=$now??time();
    if((file_exists($q)||is_link($q))&&(is_link($q)||!is_dir($q)||realpath($q)!==$q||(fileperms($q)&0077)!==0))throw new RuntimeException('Unsafe quarantine');
    $rows=[];$remaining=134217728;
    foreach(storagePlanRequest($raw) as $name){
        $s=storageFile($root,$name);
        if($s['mtime']>$now-30*86400||storageReferences($db,$name)!==0||file_exists($q.'/'.$name)||is_link($q.'/'.$name)||$s['size']>$remaining)throw new RuntimeException('Protected asset, destination or budget');
        storageBytes($root,$name);$remaining-=$s['size'];
        $rows[]=['name'=>$name,'bytes'=>$s['size'],'mtime_utc'=>gmdate('Y-m-d H:i:s',$s['mtime'])];
    }
    // Deliberately planning only: existing explicit single-file quarantine still required.
    return ['count'=>count($rows),'bytes'=>134217728-$remaining,'sha256'=>hash('sha256',json_encode(['media-quarantine-plan-v1',$root,$rows],JSON_THROW_ON_ERROR)),'items'=>$rows];
}
