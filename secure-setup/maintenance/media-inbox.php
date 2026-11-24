<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/meta-image-core.php';
require __DIR__.'/media-inbox-core.php';
try{
    $cfg=opsConfig();if(!mediaEnabled($cfg)||($cfg['media_upload']['enabled']??false)!==true||($cfg['media_upload']['inbox_import_enabled']??false)!==true)throw new RuntimeException('Disabled');
    if(count($argv)!==5||$argv[1]!=='--import'||!preg_match('/^[1-9][0-9]{0,14}$/D',$argv[2])||!preg_match('/^[1-9][0-9]{0,8}$/D',$argv[3])||!preg_match('/^[a-f0-9]{64}$/D',$argv[4]))throw new RuntimeException('Explicit manifest required');
    $root=miRoot($cfg);$inbox=uploadRoot($cfg['media_upload']['private_inbox']??'',dirname(__DIR__,2).'/public_html');
    $db=opsDb($cfg);$db->beginTransaction();creativeActive($db);
    $q=$db->prepare("SELECT m.image_hash,m.video_hash,m.width,m.height FROM growth_media_manifests m JOIN growth_tasks t ON t.id=m.task_id AND t.revision=m.revision AND t.status='approved' WHERE m.task_id=? AND m.revision=? AND m.manifest_hash=? AND m.status='approved' FOR UPDATE");
    $q->execute([(int)$argv[2],(int)$argv[3],$argv[4]]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new RuntimeException('Approved manifest required');
    inboxImport($inbox,$root,$r);growthAudit($db,(int)$argv[2],(int)$argv[3],'media_inbox_import','private_cli');$db->commit();echo "Verified PNG and MP4 imported. No publication staged.\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: verify approved manifest, private inbox and exact assets. Source files retained; inspect destination before retry.\n");exit(1);}
