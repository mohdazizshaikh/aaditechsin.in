<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/release-gate-lib.php';
function rgCheck(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$root=dirname(__DIR__);$contract=json_decode(file_get_contents($root.'/secure-setup/maintenance/release-contract.json'),true,32,JSON_THROW_ON_ERROR);
releaseSourceIntegrity($root,$contract);
$bad=$contract;$bad['source_git_blob_sha1']['secure-setup/schema.sql']=str_repeat('0',40);
try{releaseSourceIntegrity($root,$bad);throw new LogicException('Drift accepted');}catch(RuntimeException $e){}
rgCheck(releaseWorkerClear(['a'=>0,'b'=>0])&&!releaseWorkerClear(['a'=>1,'b'=>0])&&!releaseWorkerClear([])&&!releaseWorkerClear(['a'=>1,'b'=>-1])&&!releaseWorkerClear(['a'=>'0']),'Block state');
if(($argv[1]??'')!=='--database'){echo "Release source integrity and worker-state rules: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    $before=releaseWorkerState($db);
    $db->exec("INSERT INTO growth_source_runs(source_id,status) VALUES('ci-release-gate','running')");
    $after=releaseWorkerState($db);rgCheck($after['discovery_running']===$before['discovery_running']+1&&!releaseWorkerClear($after),'Running source blocks');
    $db->rollBack();rgCheck(releaseWorkerState($db)===$before,'Read-only and rollback');
    echo "Synthetic release worker-state inspection: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Release gate test failed: '.$e->getMessage()."\n");exit(1);}
