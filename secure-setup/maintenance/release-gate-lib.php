<?php
declare(strict_types=1);
require_once __DIR__.'/reconciliation-lib.php';
function releaseBlobHash(string $raw):string{return sha1('blob '.strlen($raw)."\0".$raw);}
function releaseSourceIntegrity(string $root,array $contract):void{
    $expected=$contract['source_git_blob_sha1']??null;
    if(!is_array($expected))throw new RuntimeException('Missing file inventory');
    $paths=array_map(static fn($name)=>'secure-setup/migrations/'.$name,$contract['migrations']);$paths[]='secure-setup/schema.sql';$paths[]='tests/fixtures/schema-v96.sql';
    sort($paths);$actual=array_keys($expected);sort($actual);
    if($actual!==$paths)throw new RuntimeException('File inventory mismatch');
    foreach($expected as $path=>$hash){
        if(!preg_match('#^(?:secure-setup/migrations/[0-9a-z.-]+\.sql|secure-setup/schema\.sql|tests/fixtures/schema-v96\.sql)$#D',$path)||!is_string($hash)||!preg_match('/^[a-f0-9]{40}$/D',$hash)||is_link($root.'/'.$path)||!is_file($root.'/'.$path))throw new RuntimeException('File inventory invalid');
        $raw=file_get_contents($root.'/'.$path);if($raw===false||!hash_equals($hash,releaseBlobHash($raw)))throw new RuntimeException('Source file drift');
    }
}
function releaseWorkerState(PDO $db):array{
    $queries=[
        'smtp_sending'=>"SELECT COUNT(*) FROM notification_outbox WHERE status='sending'",
        'discovery_running'=>"SELECT COUNT(*) FROM growth_source_runs WHERE status='running'",
        'creative_leased'=>"SELECT COUNT(*) FROM growth_creative_leases WHERE status='leased'",
        'owner_report_sending'=>"SELECT COUNT(*) FROM growth_report_runs WHERE status='sending'",
        'single_media_bearer'=>"SELECT COUNT(*) FROM growth_meta_images WHERE token_hash IS NOT NULL AND expires_at>UTC_TIMESTAMP()",
        'carousel_bearer'=>"SELECT COUNT(*) FROM growth_carousel_items WHERE token_hash IS NOT NULL AND expires_at>UTC_TIMESTAMP()"
    ];
    $out=[];foreach($queries as $key=>$sql)$out[$key]=(int)$db->query($sql)->fetchColumn();
    $out['publication_inflight']=0;$out['publication_processing']=0;$out['publication_unknown']=0;
    foreach(recLedgers() as $table){
        foreach($db->query("SELECT status,COUNT(*) AS n FROM $table GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) as $r){
            if(in_array($r['status'],['sending','uploading','fetching','assembling','publishing'],true))$out['publication_inflight']+=(int)$r['n'];
            elseif(in_array($r['status'],['processing','parent_ready'],true))$out['publication_processing']+=(int)$r['n'];
            elseif($r['status']==='unknown')$out['publication_unknown']+=(int)$r['n'];
        }
    }
    $out['publication_unknown']=recUnknownCounts($db)['unreviewed'];
    return $out;
}
function releaseWorkerClear(array $state):bool{if(!$state)return false;foreach($state as $n)if(!is_int($n)||$n!==0)return false;return true;}

/** Metadata only, fixed queries; no row data, schema names, connection strings or credentials. */
function releaseMetadataQueries():array{
    return [
        'tables'=>"SELECT TABLE_NAME,ENGINE,TABLE_COLLATION,TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME",
        'columns'=>"SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,CHARACTER_SET_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,COLUMN_NAME",
        'indexes'=>"SELECT TABLE_NAME,INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME,SUB_PART,INDEX_TYPE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX",
        'foreign_keys'=>"SELECT k.TABLE_NAME,k.CONSTRAINT_NAME,k.ORDINAL_POSITION,k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.UPDATE_RULE,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.CONSTRAINT_SCHEMA=DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.TABLE_NAME,k.CONSTRAINT_NAME,k.ORDINAL_POSITION"
    ];
}
function releaseMetadata(PDO $db,array $contract):array{
    $rows=[];$actual=[];
    foreach(releaseMetadataQueries() as $kind=>$sql){
        $q=$db->query($sql);$rows[$kind]=[];
        while(($r=$q->fetch(PDO::FETCH_NUM))!==false){
            if(count($rows[$kind])>=10000||!isset($contract['tables'][$r[0]]))throw new RuntimeException('Unexpected metadata inventory');
            // Canonical string-or-null representation; preserve order within index/FK definitions.
            $rows[$kind][]=array_map(static fn($v)=>$v===null?null:(string)$v,$r);
            if($kind==='columns')$actual[$r[0]][]=$r[1];
            if($kind==='tables'&&($r[3]!=='BASE TABLE'||$r[1]!=='InnoDB'))throw new RuntimeException('Unexpected table type or engine');
        }
    }
    $expected=$contract['tables'];ksort($expected);ksort($actual);
    foreach($expected as &$v)sort($v,SORT_STRING);unset($v);
    foreach($actual as &$v)sort($v,SORT_STRING);unset($v);
    if($actual!==$expected||count($rows['tables'])!==count($expected))throw new RuntimeException('Required metadata inventory mismatch');
    // Refuse foreign keys referencing another database without exposing its name.
    $cross=(int)$db->query("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND REFERENCED_TABLE_SCHEMA IS NOT NULL AND REFERENCED_TABLE_SCHEMA<>DATABASE()")->fetchColumn();
    if($cross!==0)throw new RuntimeException('Cross-schema reference');
    // Bind to exact release contract (including migration/source hashes).
    return ['version'=>1,'contract_sha256'=>hash('sha256',json_encode($contract,JSON_THROW_ON_ERROR)),'metadata'=>$rows];
}
function releaseMetadataBytes(array $reference):string{
    $raw=json_encode($reference,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
    if(strlen($raw)>1048576)throw new RuntimeException('Reference too large');
    return $raw;
}
function releaseMetadataCompare(array $current,string $raw,string $digest):array{
    if(strlen($raw)>1048576||!preg_match('/^[a-f0-9]{64}$/D',$digest)||!hash_equals($digest,hash('sha256',$raw)))throw new RuntimeException('Reference integrity mismatch');
    $ref=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    if(!is_array($ref)||array_keys($ref)!==['version','contract_sha256','metadata']||$ref['version']!==1||$ref['contract_sha256']!==$current['contract_sha256']||!is_array($ref['metadata'])||array_keys($ref['metadata'])!==array_keys(releaseMetadataQueries()))throw new RuntimeException('Reference contract mismatch');
    $out=[];
    foreach(releaseMetadataQueries() as $kind=>$unused){
        $r=$ref['metadata'][$kind];
        if(!is_array($r)||!array_is_list($r)||count($r)>10000)throw new RuntimeException('Reference rows invalid');
        $out[$kind]=$r===$current['metadata'][$kind];
    }
    return $out;
}

/** Count metadata only. Caller must use an isolated, quiescent read-only snapshot. */
function releaseCountDigest(string $value):bool{return preg_match('/^[a-f0-9]{64}$/D',$value)===1;}
function releaseCountValue(mixed $value):string{
    if(!is_string($value)&&!is_int($value))throw new RuntimeException('Invalid count');
    $n=(string)$value;
    if(!preg_match('/^(?:0|[1-9][0-9]{0,19})$/D',$n)||(strlen($n)===20&&strcmp($n,'18446744073709551615')>0))throw new RuntimeException('Invalid count');
    return $n;
}
function releaseCountTables(array $contract):array{
    $tables=array_keys($contract['tables']??[]);
    if(!$tables||count($tables)>1000)throw new RuntimeException('Invalid table inventory');
    foreach($tables as $table)if(!is_string($table)||!preg_match('/^[a-z0-9_]+$/D',$table))throw new RuntimeException('Invalid table');
    sort($tables,SORT_STRING);return $tables;
}
function releaseCountManifest(array $contract,array $metadata,string $backup,array $counts):array{
    if(!releaseCountDigest($backup))throw new RuntimeException('Backup digest required');
    $tables=releaseCountTables($contract);ksort($counts,SORT_STRING);
    if(array_keys($counts)!==$tables)throw new RuntimeException('Count inventory mismatch');
    foreach($counts as &$n)$n=releaseCountValue($n);unset($n);
    $contractHash=hash('sha256',json_encode($contract,JSON_THROW_ON_ERROR));
    if(($metadata['contract_sha256']??null)!==$contractHash)throw new RuntimeException('Metadata contract mismatch');
    return ['version'=>1,'scope'=>'restore-row-counts','contract_sha256'=>$contractHash,'metadata_sha256'=>hash('sha256',releaseMetadataBytes($metadata)),'backup_sha256'=>$backup,'counts'=>$counts];
}
function releaseCaptureCounts(PDO $db,array $contract,string $backup):array{
    if(!$db->inTransaction()||!releaseCountDigest($backup))throw new RuntimeException('Snapshot required');
    $paused=$db->query('SELECT paused FROM growth_control WHERE id=1')->fetchColumn();
    if($paused===false||(string)$paused!=='1'||!releaseWorkerClear(releaseWorkerState($db)))throw new RuntimeException('Release state not clear');
    $metadata=releaseMetadata($db,$contract);$counts=[];
    foreach(releaseCountTables($contract) as $table){
        $counts[$table]=releaseCountValue($db->query('SELECT COUNT(*) FROM '.chr(96).$table.chr(96))->fetchColumn());
    }
    return releaseCountManifest($contract,$metadata,$backup,$counts);
}
function releaseCountBytes(array $manifest):string{
    $raw=json_encode($manifest,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
    if(strlen($raw)>65536)throw new RuntimeException('Count reference too large');
    return $raw;
}
function releaseCompareCounts(array $current,string $raw,string $digest):array{
    if(strlen($raw)>65536||!releaseCountDigest($digest)||!hash_equals($digest,hash('sha256',$raw)))throw new RuntimeException('Count reference integrity');
    $r=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
    if(!is_array($r)||array_keys($r)!==['version','scope','contract_sha256','metadata_sha256','backup_sha256','counts']||$r['version']!==1||$r['scope']!=='restore-row-counts'||!is_array($r['counts']))throw new RuntimeException('Count reference shape');
    if(releaseCountBytes($r)!==$raw)throw new RuntimeException('Noncanonical count reference');
    foreach(['contract_sha256','metadata_sha256','backup_sha256'] as $k)
        if(!is_string($r[$k])||!releaseCountDigest($r[$k])||!hash_equals($current[$k],$r[$k]))throw new RuntimeException('Count reference binding');
    if(array_keys($r['counts'])!==array_keys($current['counts']))throw new RuntimeException('Count inventory mismatch');
    $out=[];
    foreach($r['counts'] as $table=>$n){
        if(!is_string($n)||releaseCountValue($n)!==$n)throw new RuntimeException('Invalid count');
        $out[$table]=hash_equals($current['counts'][$table],$n);
    }
    return $out;
}
