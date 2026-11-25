<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/meta-image-core.php';
require __DIR__.'/media-inbox-core.php';
try{
    $cfg=opsConfig();if(!mediaEnabled($cfg)||($cfg['media_upload']['enabled']??false)!==true||($cfg['media_upload']['inbox_import_enabled']??false)!==true)throw new RuntimeException('Disabled');
    $mode=$argv[1]??'';
    if(!in_array($mode,['--import','--batch-plan','--batch-apply'],true))throw new RuntimeException('Explicit mode required');
    if($mode==='--import'){
        if(count($argv)!==5||!preg_match('/^[1-9][0-9]{0,14}$/D',$argv[2])||!preg_match('/^[1-9][0-9]{0,8}$/D',$argv[3])||!preg_match('/^[a-f0-9]{64}$/D',$argv[4]))throw new RuntimeException('Explicit manifest required');
    }elseif(!inboxBatchEnabled($cfg)||count($argv)!==($mode==='--batch-plan'?2:3))throw new RuntimeException('Explicit enabled batch mode required');
    $root=miRoot($cfg);$inbox=uploadRoot($cfg['media_upload']['private_inbox']??'',dirname(__DIR__,2).'/public_html');
    $db=opsDb($cfg);$db->beginTransaction();creativeActive($db);
    if($mode!=='--import'){
        $raw=stream_get_contents(STDIN,16385);if(!is_string($raw))throw new RuntimeException('Input unavailable');
        if($mode==='--batch-plan'){
            $p=inboxBatchPlan($db,$cfg,$inbox,$root,$raw);$db->rollBack();
            echo json_encode(['count'=>$p['count'],'sha256'=>$p['sha256']],JSON_THROW_ON_ERROR)."\n";exit;
        }
        $count=inboxBatchApply($db,$cfg,$inbox,$root,$raw,$argv[2]);$db->commit();
        echo "Verified $count approved bundles imported. No publication staged.\n";exit;
    }
    $r=inboxApproved($db,(int)$argv[2],(int)$argv[3],$argv[4]);
    inboxImport($inbox,$root,$r);growthAudit($db,(int)$argv[2],(int)$argv[3],'media_inbox_import','private_cli');$db->commit();echo "Verified PNG and MP4 imported. No publication staged.\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: verify approved manifest, private inbox and exact assets. Source files retained; inspect destination before retry.\n");exit(1);}
