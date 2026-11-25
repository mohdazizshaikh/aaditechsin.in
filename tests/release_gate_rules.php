<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/restore-content-lib.php';
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
$key=restoreContentKey(str_repeat('a',64));$otherKey=restoreContentKey(str_repeat('b',64));
$fake=['version'=>1,'scope'=>'restore-content-fingerprints','contract_sha256'=>$manifest['contract_sha256'],'metadata_sha256'=>$manifest['metadata_sha256'],'backup_sha256'=>$backup,'tables'=>[]];
foreach($tables as $table)$fake['tables'][$table]=['rows'=>'1','hmac_sha256'=>str_repeat('a',64)];
$fpRaw=restoreContentBytes($fake);
rgCheck(restoreContentCompare($fake,$fpRaw,hash('sha256',$fpRaw))===array_fill_keys($tables,true),'Fingerprint round trip');
$fpCases=0;
foreach($tables as $table){
    $changed=$fake;$changed['tables'][$table]['hmac_sha256']=str_repeat('b',64);$bytes=restoreContentBytes($changed);
    $diff=restoreContentCompare($fake,$bytes,hash('sha256',$bytes));
    rgCheck(!$diff[$table]&&count(array_filter($diff))===48,'Same-count content drift');++$fpCases;
    $changed=$fake;unset($changed['tables'][$table]);$bytes=restoreContentBytes($changed);
    rgBlocked(static fn()=>restoreContentCompare($fake,$bytes,hash('sha256',$bytes)));++$fpCases;
}
rgCheck($fpCases===98,'98 fingerprint cases');
foreach(['',str_repeat('0',64),'bad'] as $bad)rgBlocked(static fn()=>restoreContentKey($bad));
foreach(['backup_sha256','contract_sha256','metadata_sha256'] as $k){
    $changed=$fake;$changed[$k]=str_repeat('f',64);$bytes=restoreContentBytes($changed);
    rgBlocked(static fn()=>restoreContentCompare($fake,$bytes,hash('sha256',$bytes)));
}
$spec=['columns'=>['a','b'],'primary'=>['a']];
$fingerprint=static function(array $rows,string $k,string $table='synthetic')use($backup,$fake,$spec):string{
    $h=restoreContentStart($k,$backup,$fake['metadata_sha256'],$table,$spec);$n=0;
    foreach($rows as $row)restoreContentRow($h,$row,2,$n);
    return restoreContentEnd($h,count($rows));
};
$base=$fingerprint([['ab','c']],$key);
foreach([[['a','bc']],[['ab',null]],[['ab','']],[['ab',"c\0"]],[]] as $rows)rgCheck($fingerprint($rows,$key)!==$base,'Framing');
rgCheck($fingerprint([['ab','c']],$otherKey)!==$base&&$fingerprint([['ab','c']],$key,'other')!==$base,'Key and table binding');
rgCheck($fingerprint([[null,'']],$key)!==$fingerprint([['',null]],$key),'Null vs empty');
rgCheck($fingerprint([['a','b'],['c','d']],$key)!==$fingerprint([['c','d'],['a','b']],$key),'Row order');
rgCheck($fingerprint([['ab','c']],$key)===$base,'Determinism');
rgBlocked(static fn()=>restoreContentFrame(str_repeat('x',4194305)));
rgBlocked(static fn()=>restoreContentIdentifier('table;DROP'));
echo "Restore content fingerprint: 98 table checks, framing and key binding PASS\n";
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
        $db->exec("INSERT INTO growth_source_runs(source_id,status) VALUES('ci-content-gate','completed')");
        $content=restoreContentCapture($db,$contract,$backup,$key);
        rgCheck(count($content['tables'])===49,'All table fingerprints');
        $db->exec("UPDATE growth_source_runs SET source_id='ci-content-drift' WHERE source_id='ci-content-gate'");
        $changed=restoreContentCapture($db,$contract,$backup,$key);
        $ref=restoreContentBytes($content);
        $diff=restoreContentCompare($changed,$ref,hash('sha256',$ref));
        rgCheck(!$diff['growth_source_runs']&&count(array_filter($diff))===48&&$content['tables']['growth_source_runs']['rows']===$changed['tables']['growth_source_runs']['rows'],'Same-count actual row drift');
        $db->exec("UPDATE growth_source_runs SET source_id='ci-content-gate' WHERE source_id='ci-content-drift'");
        $reverted=restoreContentCapture($db,$contract,$backup,$key);
        rgCheck($reverted===$content,'Reverted content matches');
        $db->exec("DELETE FROM growth_source_runs WHERE source_id='ci-content-gate'");
        echo "Disposable database keyed content capture and same-count drift: PASS\n";
    }else{
        rgBlocked(static fn()=>releaseCaptureCounts($db,$contract,$backup));
        echo "Disposable fixture contains unresolved work; count capture correctly refused.\n";
    }
    $db->exec('UPDATE growth_control SET paused=0 WHERE id=1');
    rgBlocked(static fn()=>releaseCaptureCounts($db,$contract,$backup));
    rgBlocked(static fn()=>restoreContentCapture($db,$contract,$backup,$key));
    $db->exec('UPDATE growth_control SET paused=1 WHERE id=1');
    $db->exec("INSERT INTO growth_source_runs(source_id,status) VALUES('ci-release-gate','running')");
    $after=releaseWorkerState($db);rgCheck($after['discovery_running']===$before['discovery_running']+1&&!releaseWorkerClear($after),'Running source blocks');
    rgBlocked(static fn()=>releaseCaptureCounts($db,$contract,$backup));
    rgBlocked(static fn()=>restoreContentCapture($db,$contract,$backup,$key));
    $db->rollBack();rgCheck(releaseWorkerState($db)===$before,'Read-only and rollback');
    echo "Synthetic release worker-state inspection: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Release gate test failed: '.$e->getMessage()."\n");exit(1);}
