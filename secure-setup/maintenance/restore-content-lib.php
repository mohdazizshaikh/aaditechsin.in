<?php
declare(strict_types=1);
require_once __DIR__.'/release-gate-lib.php';
function restoreContentKey(string $hex):string{
    if(!preg_match('/^[a-f0-9]{64}$/D',$hex)||$hex===str_repeat('0',64))throw new RuntimeException('Private random key required');
    return hex2bin($hex);
}
function restoreContentIdentifier(string $v):string{
    if(!preg_match('/^[a-z0-9_]+$/D',$v))throw new RuntimeException('Invalid identifier');
    return chr(96).$v.chr(96);
}
function restoreContentLayout(array $contract,array $metadata):array{
    $layout=[];
    foreach(releaseCountTables($contract) as $table){
        $columns=$contract['tables'][$table];
        if(!is_array($columns)||!array_is_list($columns)||!$columns||count(array_unique($columns))!==count($columns))throw new RuntimeException('Invalid columns');
        sort($columns,SORT_STRING);
        foreach($columns as $column)restoreContentIdentifier($column);
        $pk=[];
        foreach($metadata['metadata']['indexes'] as $r){
            if($r[0]!==$table||$r[1]!=='PRIMARY')continue;
            if($r[2]!=='0'||$r[5]!==null||!in_array($r[4],$columns,true)||$r[3]!== (string)(count($pk)+1))throw new RuntimeException('Unsupported primary key');
            $pk[]=$r[4];
        }
        if(!$pk||count($pk)!==count(array_unique($pk)))throw new RuntimeException('Primary key required');
        $layout[$table]=['columns'=>$columns,'primary'=>$pk];
    }
    return $layout;
}
/** A typed, length-delimited stream prevents concatenation and NULL collisions. */
function restoreContentFrame(mixed $v):string{
    if($v===null)return 'N';
    if(!is_string($v))throw new RuntimeException('Expected binary projection');
    if(strlen($v)>4194304)throw new RuntimeException('Field cap');
    return 'S'.pack('N',strlen($v)).$v;
}
function restoreContentStart(string $key,string $backup,string $metadata,string $table,array $layout):HashContext{
    if(strlen($key)!==32||!releaseCountDigest($backup)||!releaseCountDigest($metadata))throw new RuntimeException('Invalid fingerprint binding');
    $h=hash_init('sha256',HASH_HMAC,$key);
    hash_update($h,"aaditechs-restore-content-v1\0");
    foreach([$backup,$metadata,$table,json_encode($layout,JSON_THROW_ON_ERROR)] as $v)hash_update($h,restoreContentFrame($v));
    return $h;
}
function restoreContentRow(HashContext $h,array $row,int $width,int &$bytes):void{
    if(!array_is_list($row)||count($row)!==$width)throw new RuntimeException('Projection width');
    hash_update($h,'R');
    foreach($row as $value){
        $frame=restoreContentFrame($value);
        $bytes+=strlen($frame);
        if($bytes>536870912)throw new RuntimeException('Fingerprint byte cap');
        hash_update($h,$frame);
    }
}
function restoreContentEnd(HashContext $h,int $rows):string{
    hash_update($h,'E'.restoreContentFrame((string)$rows));
    return hash_final($h);
}
function restoreContentCapture(PDO $db,array $contract,string $backup,string $key):array{
    if(!$db->inTransaction()||!releaseCountDigest($backup)||strlen($key)!==32)throw new RuntimeException('Snapshot and key required');
    $paused=$db->query('SELECT paused FROM growth_control WHERE id=1')->fetchColumn();
    if($paused===false||(string)$paused!=='1'||!releaseWorkerClear(releaseWorkerState($db)))throw new RuntimeException('Release state not clear');
    $meta=releaseMetadata($db,$contract);$metadata=hash('sha256',releaseMetadataBytes($meta));
    $layout=restoreContentLayout($contract,$meta);$entries=[];$bytes=0;$total=0;
    $previous=$db->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
    $db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
    try{
        foreach($layout as $table=>$spec){
            $cols=array_map(static fn($v)=>'CAST('.restoreContentIdentifier($v).' AS BINARY)',$spec['columns']);
            $pk=array_map('restoreContentIdentifier',$spec['primary']);
            $q=$db->query('SELECT '.implode(',',$cols).' FROM '.restoreContentIdentifier($table).' ORDER BY '.implode(',',$pk));
            $h=restoreContentStart($key,$backup,$metadata,$table,$spec);$rows=0;
            try{
                while(($r=$q->fetch(PDO::FETCH_NUM))!==false){
                    if(++$total>1000000)throw new RuntimeException('Fingerprint row cap');
                    ++$rows;restoreContentRow($h,$r,count($cols),$bytes);
                }
            }finally{$q->closeCursor();}
            $entries[$table]=['rows'=>(string)$rows,'hmac_sha256'=>restoreContentEnd($h,$rows)];
        }
    }finally{$db->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,$previous);}
    return ['version'=>1,'scope'=>'restore-content-fingerprints','contract_sha256'=>$meta['contract_sha256'],'metadata_sha256'=>$metadata,'backup_sha256'=>$backup,'tables'=>$entries];
}
function restoreContentBytes(array $manifest):string{
    $raw=json_encode($manifest,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
    if(strlen($raw)>65536)throw new RuntimeException('Reference cap');
    return $raw;
}
function restoreContentCompare(array $current,string $raw,string $digest):array{
    if(strlen($raw)>65536||!releaseCountDigest($digest)||!hash_equals($digest,hash('sha256',$raw)))throw new RuntimeException('Reference integrity');
    $ref=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
    if(!is_array($ref)||array_keys($ref)!==['version','scope','contract_sha256','metadata_sha256','backup_sha256','tables']||$ref['version']!==1||$ref['scope']!=='restore-content-fingerprints'||!is_array($ref['tables'])||restoreContentBytes($ref)!==$raw)throw new RuntimeException('Reference format');
    foreach(['contract_sha256','metadata_sha256','backup_sha256'] as $k)
        if(!is_string($ref[$k])||!releaseCountDigest($ref[$k])||!hash_equals($current[$k],$ref[$k]))throw new RuntimeException('Reference binding');
    if(array_keys($ref['tables'])!==array_keys($current['tables']))throw new RuntimeException('Table inventory');
    $out=[];
    foreach($ref['tables'] as $table=>$r){
        if(!is_array($r)||array_keys($r)!==['rows','hmac_sha256']||!is_string($r['rows'])||releaseCountValue($r['rows'])!==$r['rows']||!is_string($r['hmac_sha256'])||!releaseCountDigest($r['hmac_sha256']))throw new RuntimeException('Table reference');
        $out[$table]=hash_equals($current['tables'][$table]['rows'],$r['rows'])&&hash_equals($current['tables'][$table]['hmac_sha256'],$r['hmac_sha256']);
    }
    return $out;
}
