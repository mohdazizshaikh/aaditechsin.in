<?php
declare(strict_types=1);
// Test-only helper. Never deploy under webroot or run with a real DB.
if(PHP_SAPI!=='cli'||getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PORT')!=='3306'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password'||count($argv)!==4)exit(2);
require dirname(__DIR__).'/secure-setup/maintenance/media-restore-lib.php';
try{
    $root=realpath($argv[1]);
    if($root===false||$root!==$argv[1]||!preg_match('#^/tmp/aaditechs-ci-restore-[a-z0-9_]+/(source|restored)$#D',$root)||!is_dir($root)||is_link($root))throw new RuntimeException('Disposable media path required');
    $contract=json_decode(file_get_contents(dirname(__DIR__).'/secure-setup/maintenance/release-contract.json'),true,32,JSON_THROW_ON_ERROR);
    releaseSourceIntegrity(dirname(__DIR__),$contract);
    $key=restoreContentKey((string)getenv('AADITECHS_RESTORE_HMAC_KEY_HEX'));
    $cfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'media'=>['enabled'=>true],'media_upload'=>['enabled'=>true,'inventory_enabled'=>true,'private_root'=>$root]];
    $db=new PDO('mysql:host=127.0.0.1;port=3306;dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci','ci_only_not_real_password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $db->exec("SET time_zone='+00:00'");
    $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
    $db->beginTransaction();
    $m=['schema'=>releaseMetadata($db,$contract),'counts'=>releaseCaptureCounts($db,$contract,$argv[2]),'content'=>restoreContentCapture($db,$contract,$argv[2],$key),'media'=>mediaRestoreCapture($db,$cfg,$argv[3],$key)];
    $db->rollBack();
    echo json_encode($m,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"Synthetic capture blocked: ".$e->getMessage()."\n");exit(1);}
