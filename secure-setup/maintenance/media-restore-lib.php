<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/media-storage-core.php';
require_once __DIR__.'/restore-content-lib.php';
function mediaRestoreNames(string $dir,bool $active):array{
    if(is_link($dir)||realpath($dir)!==$dir||!is_dir($dir)||(fileperms($dir)&0077)!==0)throw new RuntimeException('Unsafe directory');
    $names=[];
    foreach(new DirectoryIterator($dir) as $entry){
        if($entry->isDot())continue;
        $name=$entry->getFilename();
        if($active&&$name==='.quarantine')continue;
        if(!storageName($name)||count($names)>=1000)throw new RuntimeException('Unexpected entry or inventory cap');
        $names[]=$name;
    }
    sort($names,SORT_STRING);return $names;
}
function mediaRestoreZone(string $dir,array $names,int &$budget):array{
    $out=[];
    foreach($names as $name){
        $s=storageFile($dir,$name);
        if($s['size']>$budget)throw new RuntimeException('Byte cap');
        $before=lstat($s['path']);
        $data=storageBytes($dir,$name);
        clearstatcache(true,$s['path']);$after=lstat($s['path']);
        if(!$before||!$after||$before['ino']!==$after['ino']||$before['dev']!==$after['dev']||$before['size']!==$after['size']||$before['mtime']!==$after['mtime']||$before['ctime']!==$after['ctime']||strlen($data)!==$s['size'])throw new RuntimeException('Changed asset');
        storageFile($dir,$name);
        $budget-=$s['size'];$out[$name]=$s['size'];
    }
    return $out;
}
function mediaRestoreDigest(string $key,string $backup,string $label,array $data):string{
    if(strlen($key)!==32||!releaseCountDigest($backup))throw new RuntimeException('Binding');
    return hash_hmac('sha256',json_encode(['media-restore-v1',$backup,$label,$data],JSON_THROW_ON_ERROR),$key);
}
function mediaRestoreEntry(string $key,string $backup,string $label,array $data):array{
    return ['count'=>count($data),'hmac_sha256'=>mediaRestoreDigest($key,$backup,$label,$data)];
}
function mediaRestoreReferences(PDO $db,array $active,array $quarantine,string $key,string $backup):array{
    $out=[];$total=0;
    foreach(storageReferenceSources() as $label=>[$ext,$table,$column,$filter]){
        $where=$filter!==''?" WHERE $filter":'';
        $values=$db->query("SELECT DISTINCT $column FROM $table$where ORDER BY $column LIMIT 1001")->fetchAll(PDO::FETCH_COLUMN);
        if(count($values)>1000||($total+=count($values))>10000)throw new RuntimeException('Reference cap');
        $states=[];$missing=0;$quarantined=0;$invalid=0;
        foreach($values as $hash){
            $name=is_string($hash)?$hash.'.'.$ext:'';
            if(!storageName($name)){++$invalid;continue;}
            if(isset($active[$name]))$state='active';
            elseif(isset($quarantine[$name])){$state='quarantine';++$quarantined;}
            else{$state='missing';++$missing;}
            $states[$name]=$state;
        }
        if($invalid)throw new RuntimeException('Invalid reference');
        ksort($states,SORT_STRING);
        $out[$label]=mediaRestoreEntry($key,$backup,'reference:'.$label,$states)+['missing'=>$missing,'quarantined'=>$quarantined];
    }
    return $out;
}
function mediaRestoreCapture(PDO $db,array $cfg,string $backup,string $key):array{
    if(!$db->inTransaction()||!storageEnabled($cfg)||!releaseCountDigest($backup)||strlen($key)!==32)throw new RuntimeException('Disabled or invalid snapshot');
    $paused=$db->query('SELECT paused FROM growth_control WHERE id=1')->fetchColumn();
    if($paused===false||(string)$paused!=='1'||!releaseWorkerClear(releaseWorkerState($db)))throw new RuntimeException('Release state not clear');
    $root=miRoot($cfg);$q=$root.'/.quarantine';$exists=file_exists($q)||is_link($q);
    $aNames=mediaRestoreNames($root,true);$qNames=$exists?mediaRestoreNames($q,false):[];
    if(count($aNames)+count($qNames)>1000||array_intersect($aNames,$qNames))throw new RuntimeException('Inventory cap or duplicate location');
    $budget=268435456;
    $active=mediaRestoreZone($root,$aNames,$budget);
    $quarantine=$exists?mediaRestoreZone($q,$qNames,$budget):[];
    $refs=mediaRestoreReferences($db,$active,$quarantine,$key,$backup);
    if(mediaRestoreNames($root,true)!==$aNames||(file_exists($q)||is_link($q))!==$exists||($exists&&mediaRestoreNames($q,false)!==$qNames))throw new RuntimeException('Inventory changed');
    return ['version'=>1,'scope'=>'private-media-restore','backup_sha256'=>$backup,'zones'=>[
        'active'=>mediaRestoreEntry($key,$backup,'active',$active),
        'quarantine'=>mediaRestoreEntry($key,$backup,'quarantine',$quarantine)
    ],'references'=>$refs];
}
function mediaRestoreBytes(array $m):string{
    $raw=json_encode($m,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
    if(strlen($raw)>65536)throw new RuntimeException('Manifest cap');
    return $raw;
}
function mediaRestoreCompare(array $current,string $raw,string $digest):array{
    if(strlen($raw)>65536||!releaseCountDigest($digest)||!hash_equals($digest,hash('sha256',$raw)))throw new RuntimeException('Manifest integrity');
    $r=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
    if(!is_array($r)||array_keys($r)!==['version','scope','backup_sha256','zones','references']||$r['version']!==1||$r['scope']!=='private-media-restore'||$r['backup_sha256']!==$current['backup_sha256']||mediaRestoreBytes($r)!==$raw)throw new RuntimeException('Manifest binding');
    $out=[];
    foreach(['zones','references'] as $group){
        if(!is_array($r[$group])||array_keys($r[$group])!==array_keys($current[$group]))throw new RuntimeException('Inventory mismatch');
        foreach($r[$group] as $label=>$entry){
            $expected=$current[$group][$label];
            if(!is_array($entry)||array_keys($entry)!==array_keys($expected)||!is_int($entry['count'])||$entry['count']<0||$entry['count']>1000||!is_string($entry['hmac_sha256'])||!releaseCountDigest($entry['hmac_sha256']))throw new RuntimeException('Entry shape');
            if($group==='references'&&(!is_int($entry['missing'])||!is_int($entry['quarantined'])||$entry['missing']<0||$entry['quarantined']<0||$entry['missing']+$entry['quarantined']>$entry['count']))throw new RuntimeException('Reference count');
            $out[$group.':'.$label]=$entry===$expected;
        }
    }
    return $out;
}
function mediaRestoreReady(array $manifest):bool{
    foreach($manifest['references'] as $r)if($r['missing']!==0||$r['quarantined']!==0)return false;
    return true;
}
