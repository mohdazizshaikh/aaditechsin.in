<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require __DIR__.'/operations.php';
require __DIR__.'/creative-transport-core.php';
header('X-Robots-Tag: noindex, nofollow');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')jsonResponse(405,['error'=>'Method not allowed']);
if(!opsSsl())jsonResponse(403,['error'=>'HTTPS required']);
try{
    $cfg=opsConfig();
    if(!creativeTransportEnabled($cfg))jsonResponse(503,['error'=>'Unavailable']);
    if(!creativeTransportAuthorized($cfg,(string)($_SERVER['HTTP_X_AADITECHS_WORKER_TOKEN']??'')))jsonResponse(401,['error'=>'Unauthorized']);
    $raw=file_get_contents('php://input',false,null,0,16385);
    if(!is_string($raw)||strlen($raw)>16384)jsonResponse(413,['error'=>'Invalid request']);
    $v=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    if(!is_array($v))throw new InvalidArgumentException('Invalid request');
    $db=opsDb($cfg);$db->beginTransaction();
    $op=$v['op']??null;
    if($op==='claim'&&count($v)===1){
        $reply=creativeLeaseClaim($db);
    }elseif(in_array($op,['complete','fail'],true)){
        $keys=$op==='complete'?['op','task_id','revision','lease','result']:['op','task_id','revision','lease'];
        if(count($v)!==count($keys)||array_diff(array_keys($v),$keys)||!is_int($v['task_id']??null)||!is_int($v['revision']??null)||!is_string($v['lease']??null)||($op==='complete'&&!is_array($v['result']??null)))throw new InvalidArgumentException('Invalid request');
        $reply=creativeLeaseFinish($db,$v['task_id'],$v['revision'],$v['lease'],$op==='complete'?$v['result']:null);
    }else throw new InvalidArgumentException('Invalid request');
    $db->commit();
    jsonResponse($reply['status']==='conflict'?409:200,$reply);
}catch(InvalidArgumentException|JsonException $e){
    if(isset($db)&&$db->inTransaction())$db->rollBack();
    jsonResponse(400,['error'=>'Invalid request']);
}catch(Throwable $e){
    if(isset($db)&&$db->inTransaction())$db->rollBack();
    error_log('Aaditechs creative transport unavailable');
    jsonResponse(503,['error'=>'Unavailable']);
}
