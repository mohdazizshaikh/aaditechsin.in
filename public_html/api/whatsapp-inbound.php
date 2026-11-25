<?php
declare(strict_types=1);
require __DIR__.'/operations.php';
require __DIR__.'/whatsapp-inbound-core.php';
opsHeaders();header('Content-Type: text/plain; charset=UTF-8');
try{
    if(!opsSsl())throw new InvalidArgumentException('HTTPS');
    $cfg=opsConfig();$p=waInboundConfig($cfg);if(!$p)throw new InvalidArgumentException('Disabled');
    $method=$_SERVER['REQUEST_METHOD']??'';
    if($method==='GET'){$challenge=waInboundChallenge($p,$_GET);if($challenge===null)throw new InvalidArgumentException('Challenge');echo $challenge;exit;}
    if($method!=='POST'||$_GET||strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')throw new InvalidArgumentException('Method');
    $raw=file_get_contents('php://input',false,null,0,65537);if(!is_string($raw))throw new InvalidArgumentException('Body');
    $sig=$_SERVER['HTTP_X_HUB_SIGNATURE_256']??'';waInboundDecode($p,$raw,$sig); // authenticate before DB connection
    $db=opsDb($cfg);$db->beginTransaction();waInboundRecord($db,$cfg,$raw,$sig);$db->commit();echo 'OK';
}catch(InvalidArgumentException|JsonException $e){if(isset($db)&&$db->inTransaction())$db->rollBack();http_response_code(404);echo 'Unavailable';}
catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();http_response_code(503);echo 'Unavailable';}
