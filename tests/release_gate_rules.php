<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/release-gate-lib.php';
function rgCheck(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$root=dirname(__DIR__);$contract=json_decode(file_get_contents($root.'/secure-setup/maintenance/release-contract.json'),true,32,JSON_THROW_ON_ERROR);
releaseSourceIntegrity($root,$contract);
$bad=$contract;$bad['source_git_blob_sha1']['secure-setup/schema.sql']=str_repeat('0',40);
try{releaseSourceIntegrity($root,$bad);throw new LogicException('Drift accepted');}catch(RuntimeException $e){}
rgCheck(releaseWorkerClear(['a'=>0,'b'=>0])&&!releaseWorkerClear(['a'=>1,'b'=>0])&&!releaseWorkerClear([])&&!releaseWorkerClear(['a'=>1,'b'=>-1])&&!releaseWorkerClear(['a'=>'0']),'Block state');
function rgBlocked(callable $fn):void{try{$fn();}catch(RuntimeException|InvalidArgumentException|JsonException $e){return;}throw new LogicException('Expected refusal');}
$tables=releaseCountTables($contract);$counts=array_fill_keys($tables,'0');
$meta=['version'=>1,'contract_sha256'=>hash('sha256',json_encode($contract,JSON_THROW_ON_ERROR)),'metadata'=>[]];
$backup=str_repeat('a',64);
$manifest=releaseCountManifest($contract,$meta,$backup,$counts);
$countRaw=releaseCountBytes($manifest);
rgCheck(releaseCompareCounts($manifest,$countRaw,hash('sha256',$countRaw))===array_fill_keys($tables,true),'Count round trip');
$countCases=0;
foreach($tables as $table){
    $changed=$manifest;$changed['counts'][$table]='1';$bytes=releaseCountBytes($changed);
    $result=releaseCompareCounts($manifest,$bytes,hash('sha256',$bytes));
    rgCheck($result[$table]===false&&count(array_filter($result))===count($tables)-1,'Count drift '.$table);++$countCases;
    $changed=$manifest;unset($changed['counts'][$table]);$bytes=releaseCountBytes($changed);
    rgBlocked(static fn()=>releaseCompareCounts($manifest,$bytes,hash('sha256',$bytes)));++$countCases;
}
rgCheck($countCases===98,'98 table count cases');
foreach(['backup_sha256','contract_sha256','metadata_sha256'] as $k){
    $changed=$manifest;$changed[$k]=str_repeat('b',64);$bytes=releaseCountBytes($changed);
    rgBlocked(static fn()=>releaseCompareCounts($manifest,$bytes,hash('sha256',$bytes)));
}
foreach([-1,true,1.5,'01','18446744073709551616','1e3',null] as $n)rgBlocked(static fn()=>releaseCountValue($n));
rgCheck(releaseCountValue('18446744073709551615')==='18446744073709551615','Unsigned count boundary');
$duplicate=str_replace('"version":1','"version":1,"version":1',$countRaw);
rgBlocked(static fn()=>releaseCompareCounts($manifest,$duplicate,hash('sha256',$duplicate)));
rgBlocked(static fn()=>releaseCompareCounts($manifest,$countRaw,str_repeat('0',64)));
echo "Restore count manifest: 98 table drift/inventory cases and binding checks PASS\n";
if(($argv[1]??'')!=='--database'){echo "Release source integrity and worker-state rules: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    $current=releaseMetadata($db,$contract);
    $raw=releaseMetadataBytes($current);$digest=hash('sha256',$raw);
    rgCheck(releaseMetadataCompare($current,$raw,$digest)===array_fill_keys(array_keys(releaseMetadataQueries()),true),'Metadata round trip');
    // Exercise drift detection independently for each actual contract table.
    $cases=0;
    foreach(array_keys($contract['tables']) as $table){
        $changed=$current;
        foreach($changed['metadata']['columns'] as &$row)if($row[0]===$table){$row[2].=' synthetic-drift';break;}
        unset($row);
        $candidate=releaseMetadataBytes($changed);
        $r=releaseMetadataCompare($current,$candidate,hash('sha256',$candidate));
        rgCheck($r['columns']===false&&$r['tables']&&$r['indexes']&&$r['foreign_keys'],'Column drift '.$table);++$cases;
    }
    rgCheck($cases===49,'49 table drift cases');
    foreach(['tables','indexes','foreign_keys'] as $kind){
        $changed=$current;array_pop($changed['metadata'][$kind]);
        $candidate=releaseMetadataBytes($changed);
        rgCheck(releaseMetadataCompare($current,$candidate,hash('sha256',$candidate))[$kind]===false,'Metadata drift '.$kind);
    }
    foreach([[$raw,str_repeat('0',64)],[$raw." ",$digest],['{}',hash('sha256','{}')]] as [$input,$hash]){
        try{releaseMetadataCompare($current,$input,$hash);throw new LogicException('Invalid reference accepted');}catch(RuntimeException $e){}
    }
    $changed=$current;$changed['contract_sha256']=str_repeat('0',64);$candidate=releaseMetadataBytes($changed);
    try{releaseMetadataCompare($current,$candidate,hash('sha256',$candidate));throw new LogicException('Wrong contract accepted');}catch(RuntimeException $e){}
    echo "Schema parity and $cases table-level drift checks: PASS\n";
    $db->exec('UPDATE growth_control SET paused=1 WHERE id=1');
    $before=releaseWorkerState($db);
    if(releaseWorkerClear($before)){
        $real=releaseCaptureCounts($db,$contract,$backup);
        rgCheck(count($real['counts'])===49,'Exact table coverage');
        $db->exec("INSERT INTO growth_source_runs(source_id,status) VALUES('ci-count-gate','completed')");
        $later=releaseCaptureCounts($db,$contract,$backup);
        $bytes=releaseCountBytes($real);
        $diff=releaseCompareCounts($later,$bytes,hash('sha256',$bytes));
        rgCheck(!$diff['growth_source_runs']&&count(array_filter($diff))===48,'Actual count drift');
        $db->exec("DELETE FROM growth_source_runs WHERE source_id='ci-count-gate'");
        echo "Disposable database count capture and drift: PASS\n";
    }else{
        rgBlocked(static fn()=>releaseCaptureCounts($db,$contract,$backup));
        echo "Disposable fixture contains unresolved work; count capture correctly refused.\n";
    }
    $db->exec('UPDATE growth_control SET paused=0 WHERE id=1');
    rgBlocked(static fn()=>releaseCaptureCounts($db,$contract,$backup));
    $db->exec('UPDATE growth_control SET paused=1 WHERE id=1');
    $db->exec("INSERT INTO growth_source_runs(source_id,status) VALUES('ci-release-gate','running')");
    $after=releaseWorkerState($db);rgCheck($after['discovery_running']===$before['discovery_running']+1&&!releaseWorkerClear($after),'Running source blocks');
    rgBlocked(static fn()=>releaseCaptureCounts($db,$contract,$backup));
    $db->rollBack();rgCheck(releaseWorkerState($db)===$before,'Read-only and rollback');
    echo "Synthetic release worker-state inspection: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Release gate test failed: '.$e->getMessage()."\n");exit(1);}
