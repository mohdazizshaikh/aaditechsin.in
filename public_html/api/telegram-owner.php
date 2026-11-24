<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require __DIR__.'/operations.php';
require __DIR__.'/growth-core.php';
header('X-Robots-Tag: noindex, nofollow');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')jsonResponse(405,['error'=>'Method not allowed']);
if(!opsSsl())jsonResponse(403,['error'=>'HTTPS required']);
try{
    $cfg=opsConfig();
    if(($cfg['growth']['enabled']??false)!==true||($cfg['growth']['telegram']['enabled']??false)!==true)jsonResponse(503,['error'=>'Unavailable']);
    $raw=file_get_contents('php://input',false,null,0,16385);
    if(!is_string($raw)||strlen($raw)>16384)jsonResponse(413,['error'=>'Invalid request']);
    $u=json_decode($raw,true);
    if(!is_array($u)||!growthOwnerUpdate($u,$cfg,(string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']??'')))jsonResponse(200,['ok'=>true]);
    $db=opsDb($cfg);$db->beginTransaction();
    $q=$db->prepare('INSERT IGNORE INTO growth_telegram_updates(update_id) VALUES(?)');$q->execute([$u['update_id']]);
    if($q->rowCount()!==1){$db->commit();jsonResponse(200,['ok'=>true]);}
    $reply=growthCommand($db,$u['message']['text'],$cfg);
    $db->commit();
    // Direct webhook response. Telegram delivery is NOT confirmed.
    jsonResponse(200,['method'=>'sendMessage','chat_id'=>$cfg['growth']['telegram']['owner_chat_id'],'text'=>$reply]);
}catch(Throwable $e){
    if(isset($db)&&$db->inTransaction())$db->rollBack();
    error_log('Aaditechs growth webhook unavailable');
    jsonResponse(503,['error'=>'Unavailable']);
}
