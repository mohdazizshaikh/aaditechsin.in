<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/release-gate-lib.php';
try{
    $mode=$argv[1]??'';
    $modes=['--source-only'=>2,'--check-db'=>2,'--capture-schema'=>2,'--compare-schema'=>3,'--capture-counts'=>3,'--compare-counts'=>4];
    if(!isset($modes[$mode])||count($argv)!==$modes[$mode])throw new RuntimeException('Explicit mode required');
    $contract=json_decode((string)file_get_contents(__DIR__.'/release-contract.json'),true,32,JSON_THROW_ON_ERROR);
    if(($contract['version']??null)!==1||!is_array($contract['tables']??null)||!is_array($contract['migrations']??null))throw new RuntimeException('Contract invalid');
    $root=dirname(__DIR__,2);
    if($argv[1]==='--source-only'){
        $dir=$root.'/secure-setup/migrations';
        $actual=array_map('basename',glob($dir.'/*.sql')?:[]);$expected=$contract['migrations'];sort($actual);sort($expected);
        if($actual!==$expected||count($expected)!==count(array_unique($expected)))throw new RuntimeException('Migration inventory mismatch');
        releaseSourceIntegrity($root,$contract);
        echo 'PASS: 38 schema/fixture/migration file Git blob hashes match. No signature authenticity asserted.' ."\n";
        echo 'PASS: '.count($expected)." ordered migration files accounted for.\n";
        echo 'SOURCE ONLY: no database or hosting acceptance.'."\n";exit;
    }
    require $root.'/public_html/api/operations.php';
    $cfg=opsConfig();$db=opsDb($cfg);
    if(in_array($mode,['--capture-counts','--compare-counts'],true)){
        releaseSourceIntegrity($root,$contract);
        if(!releaseCountDigest($argv[2]))throw new RuntimeException('Backup digest required');
        $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
        $db->beginTransaction();
        $manifest=releaseCaptureCounts($db,$contract,$argv[2]);
        $db->rollBack();
        if($mode==='--capture-counts'){echo releaseCountBytes($manifest);exit;}
        $raw=stream_get_contents(STDIN,65537);
        if(!is_string($raw))throw new RuntimeException('Reference unavailable');
        $result=releaseCompareCounts($manifest,$raw,$argv[3]);
        foreach($result as $table=>$ok)echo $table.': '.($ok?'MATCH':'DRIFT')."\n";
        if(in_array(false,$result,true))exit(1);
        echo "READ ONLY: row counts match. Row contents, backup provenance, grants, worker-stop and production acceptance NOT verified.\n";exit;
    }
    if(in_array($mode,['--capture-schema','--compare-schema'],true)){
        // Run only with workers stopped and growth paused; no data or metadata writes.
        $pause=$db->query('SELECT paused FROM growth_control WHERE id=1')->fetchColumn();
        if($pause===false||(int)$pause!==1||!releaseWorkerClear(releaseWorkerState($db)))throw new RuntimeException('Release state not clear');
        $metadata=releaseMetadata($db,$contract);
        if($mode==='--capture-schema'){
            // Redirect only into a private operator-controlled file on a trusted reference instance.
            echo releaseMetadataBytes($metadata);exit;
        }
        $raw=stream_get_contents(STDIN,1048577);
        if(!is_string($raw))throw new RuntimeException('Reference unavailable');
        $result=releaseMetadataCompare($metadata,$raw,$argv[2]);
        foreach($result as $kind=>$ok)echo $kind.': '.($ok?'MATCH':'DRIFT')."\n";
        if(in_array(false,$result,true))exit(1);
        echo "READ ONLY: metadata parity observed; no restore completeness, grants, worker-stop or production acceptance asserted.\n";exit;
    }
    $q=$db->query('SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()');
    $actual=[];foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$actual[$r['TABLE_NAME']][$r['COLUMN_NAME']]=true;
    $missing=0;
    foreach($contract['tables'] as $table=>$columns){
        if(!preg_match('/^[a-z0-9_]+$/D',$table)||!is_array($columns))throw new RuntimeException('Contract invalid');
        if(!isset($actual[$table])){echo 'BLOCKED: missing table '.$table."\n";++$missing;continue;}
        foreach($columns as $column){if(!preg_match('/^[a-z0-9_]+$/D',$column))throw new RuntimeException('Contract invalid');if(!isset($actual[$table][$column])){echo 'BLOCKED: missing column '.$table.'.'.$column."\n";++$missing;}}
    }
    if($missing)exit(1);
    $pause=$db->query('SELECT paused FROM growth_control WHERE id=1')->fetchColumn();
    if($pause===false||(int)$pause!==1){echo "BLOCKED: growth is not paused; stop workers and pause before release rehearsal.\n";exit(1);}
    echo 'PASS: required columns present across '.count($contract['tables'])." tables; growth paused.\n";
    $review=recUnknownCounts($db);echo 'Owner-reviewed unknown rows (original status unchanged): '.$review['reviewed']."\n";
    $state=releaseWorkerState($db);foreach($state as $k=>$n)echo $k.': '.$n."\n";
    if(!releaseWorkerClear($state)){echo "BLOCKED: unresolved worker, media or provider evidence; reconcile manually. Do not blindly retry.\n";exit(1);}
    echo "READ ONLY: no unresolved listed rows observed; no migration execution, type/index/FK validation, backup/restore, worker-stop proof or provider acceptance asserted.\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: release contract, database configuration or schema check unavailable. No credentials or raw exception printed.\n");exit(1);}
