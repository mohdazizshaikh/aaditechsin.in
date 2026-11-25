<?php
declare(strict_types=1);
require_once __DIR__.'/operations.php';
require_once __DIR__.'/twilio-inbound-core.php';
function twInboundEndpoint(string $channel):void{
    opsHeaders();header('Content-Type: text/plain; charset=UTF-8');
    try{
        if(!opsSsl()||($_SERVER['REQUEST_METHOD']??'')!=='POST'||($_SERVER['QUERY_STRING']??'')!==''||strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/x-www-form-urlencoded')throw new InvalidArgumentException('Request');
        $cfg=opsConfig();$p=twInboundConfig($cfg,$channel);if(!$p)throw new InvalidArgumentException('Disabled');
        // Do not reconstruct a signed URL from untrusted forwarded headers.
        if(($_SERVER['REQUEST_URI']??'')!==parse_url($p['url'],PHP_URL_PATH))throw new InvalidArgumentException('Path');
        $raw=file_get_contents('php://input',false,null,0,65537);if(!is_string($raw))throw new InvalidArgumentException('Body');
        $sig=$_SERVER['HTTP_X_TWILIO_SIGNATURE']??'';twInboundDecode($p,$raw,$sig);
        $db=opsDb($cfg);$db->beginTransaction();twInboundRecord($db,$cfg,$channel,$raw,$sig);$db->commit();
        if($channel==='sms'){header('Content-Type: application/xml; charset=UTF-8');echo '<?xml version="1.0" encoding="UTF-8"?><Response/>';}else http_response_code(204);
    }catch(InvalidArgumentException $e){if(isset($db)&&$db->inTransaction())$db->rollBack();http_response_code(404);echo 'Unavailable';}
    catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();http_response_code(503);echo 'Unavailable';}
}
