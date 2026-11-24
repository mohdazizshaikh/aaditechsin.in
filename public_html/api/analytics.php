<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require __DIR__.'/operations.php';
require __DIR__.'/analytics-core.php';
header('X-Robots-Tag: noindex, nofollow');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')jsonResponse(405,['error'=>'Method not allowed']);
if(!opsSsl())jsonResponse(403,['error'=>'HTTPS required']);
if(strtolower(trim(explode(';',(string)($_SERVER['CONTENT_TYPE']??''))[0]))!=='application/json')jsonResponse(415,['error'=>'JSON required']);
if((int)($_SERVER['CONTENT_LENGTH']??0)>2048)jsonResponse(413,['error'=>'Request too large']);
if(($_SERVER['HTTP_SEC_GPC']??'')==='1'||($_SERVER['HTTP_DNT']??'')==='1')jsonResponse(204,[]);
try{
    $cfg=opsConfig();if(!analyticsEnabled($cfg))jsonResponse(503,['error'=>'Analytics unavailable']);
    if(!hash_equals(opsPublicBaseUrl($cfg),(string)($_SERVER['HTTP_ORIGIN']??''))||($_SERVER['HTTP_SEC_FETCH_SITE']??'')==='cross-site')jsonResponse(403,['error'=>'Origin not allowed']);
    $raw=file_get_contents('php://input',false,null,0,2049);
    if(!is_string($raw)||strlen($raw)>2048)jsonResponse(413,['error'=>'Request too large']);
    $data=json_decode($raw,true);
    if(!is_array($data)||!analyticsValidate($data))jsonResponse(422,['error'=>'Invalid event']);
    $db=opsDb($cfg);$db->beginTransaction();
    $result=analyticsRecord($db,$cfg,$data,(string)($_SERVER['HTTP_USER_AGENT']??''));
    $db->commit();
    jsonResponse($result==='limited'?429:200,['status'=>$result]);
}catch(Throwable $e){
    if(isset($db)&&$db->inTransaction())$db->rollBack();
    error_log('Aaditechs analytics event unavailable');
    jsonResponse(503,['error'=>'Analytics unavailable']);
}
