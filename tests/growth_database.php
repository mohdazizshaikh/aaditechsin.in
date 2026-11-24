<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/growth-core.php';
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
function verifyGrowth(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
$db->beginTransaction();
try{
    $id=growthCreate($db,['service_id'=>'it-procurement','task_type'=>'social','title'=>'Synthetic content','draft'=>'Synthetic draft only']);
    verifyGrowth(str_contains(growthCommand($db,'/approvals'),'#'.$id.' rev 1'),'pending visible');
    verifyGrowth(str_contains(growthCommand($db,"/task $id"),'Synthetic draft only'),'review draft');
    verifyGrowth(str_contains(growthCommand($db,"/approve $id 2"),'not pending'),'stale approval');
    verifyGrowth(str_contains(growthCommand($db,"/regenerate $id 1"),'Revision requested'),'regenerate');
    verifyGrowth(!growthReplace($db,$id,1,'stale'),'stale replacement denied');
    verifyGrowth(growthReplace($db,$id,2,'Synthetic revised draft'),'replace');
    verifyGrowth(str_contains(growthCommand($db,"/approve $id 1"),'not pending'),'old approval denied');
    verifyGrowth(str_contains(growthCommand($db,"/approve $id 2"),'approved'),'approved');
    verifyGrowth(str_contains(growthCommand($db,"/reject $id 2"),'not pending'),'terminal action denied');
    verifyGrowth(str_contains(growthCommand($db,'/pause'),'paused'),'pause');
    verifyGrowth(str_contains(growthCommand($db,'/resume'),'resumed'),'resume');
    $q=$db->prepare('SELECT COUNT(*) FROM growth_audit WHERE task_id=?');$q->execute([$id]);verifyGrowth((int)$q->fetchColumn()===4,'audit transitions');
    $q=$db->prepare('INSERT IGNORE INTO growth_telegram_updates(update_id) VALUES(?)');$q->execute([123456789]);verifyGrowth($q->rowCount()===1,'first update');$q->execute([123456789]);verifyGrowth($q->rowCount()===0,'replay blocked');
    $db->rollBack();
    echo "V9.1 synthetic task revisions, audit, pause and replay: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Growth integration failed: '.$e->getMessage()."\n");exit(1);}
