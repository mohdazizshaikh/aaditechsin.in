<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/media-core.php';
try{
    $cfg=opsConfig();if(!mediaEnabled($cfg))throw new RuntimeException('Disabled');
    $db=opsDb($cfg);$db->beginTransaction();
    $mode=$argv[1]??'';
    if($mode==='--export'&&count($argv)===4&&preg_match('/^[1-9][0-9]{0,14}$/D',$argv[2])&&preg_match('/^[1-9][0-9]{0,8}$/D',$argv[3])){
        $reply=mediaExport($db,(int)$argv[2],(int)$argv[3]);
    }elseif($mode==='--register'&&count($argv)===2){
        $reply=mediaRegister($db,stream_get_contents(STDIN,4097));
    }else throw new InvalidArgumentException('Invalid command');
    $db->commit();echo json_encode($reply,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)."\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: media configuration, revision, pause or manifest invalid.\n");exit(1);}
