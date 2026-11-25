<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/strict-json.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/creative-core.php';
try{
    $cfg=opsConfig();if(!creativeEnabled($cfg))throw new RuntimeException('Disabled');
    $db=opsDb($cfg);$db->beginTransaction();
    $mode=$argv[1]??'--pending';
    if($mode==='--pending'&&count($argv)<=2){
        $reply=$db->query("SELECT t.id,t.revision,t.service_id,c.channel,c.objective FROM growth_tasks t JOIN growth_creative c ON c.task_id=t.id WHERE t.status='regeneration_requested' ORDER BY t.id LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    }elseif($mode==='--export'&&count($argv)===3&&preg_match('/^[1-9][0-9]{0,14}$/D',$argv[2])){
        $reply=creativeExport($db,(int)$argv[2]);
    }elseif($mode==='--import'&&count($argv)===2){
        $raw=stream_get_contents(STDIN,8193);if(strlen($raw)>8192)throw new InvalidArgumentException('Too large');
        $v=strictJsonDecode($raw,true,32);if(!is_array($v))throw new InvalidArgumentException('Invalid result');
        if(!creativeImport($db,$v))throw new RuntimeException('Stale result');
        $reply=['status'=>'pending_review','task_id'=>$v['task_id'],'revision'=>$v['revision']];
    }else throw new InvalidArgumentException('Invalid command');
    $db->commit();echo json_encode($reply,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)."\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: creative request, configuration, pause or result invalid.\n");exit(1);}
