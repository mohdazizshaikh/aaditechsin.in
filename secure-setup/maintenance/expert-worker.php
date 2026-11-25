<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/strict-json.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/expert-core.php';
try{
    $cfg=opsConfig();if(!expertEnabled($cfg))throw new RuntimeException('Disabled');
    $db=opsDb($cfg);$db->beginTransaction();$mode=$argv[1]??'';
    if($mode==='--export'&&count($argv)===3&&preg_match('/^[1-9][0-9]{0,14}$/D',$argv[2]))$reply=expertExport($db,(int)$argv[2]);
    elseif($mode==='--import'&&count($argv)===2){
        $raw=stream_get_contents(STDIN,8193);if(strlen($raw)>8192)throw new InvalidArgumentException('Too large');
        $v=strictJsonDecode($raw,true,32);
        if(!is_array($v)||!expertImport($db,$v))throw new RuntimeException('Stale result');
        $reply=['status'=>'pending_review','task_id'=>$v['task_id'],'revision'=>$v['revision']];
    }else throw new InvalidArgumentException('Invalid command');
    $db->commit();echo json_encode($reply,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)."\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: expert configuration, pause, request or result invalid.\n");exit(1);}
