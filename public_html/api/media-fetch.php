<?php
declare(strict_types=1);
require __DIR__.'/operations.php';
require __DIR__.'/meta-image-core.php';
opsHeaders();
try{
    $method=$_SERVER['REQUEST_METHOD']??'';
    if(!opsSsl()||!in_array($method,['GET','HEAD'],true)||count($_GET)!==1||!is_string($_GET['t']??null)||isset($_SERVER['HTTP_RANGE']))throw new RuntimeException('Unavailable');
    $cfg=opsConfig();$db=opsDb($cfg);$db->beginTransaction();
    $r=miFetch($db,$cfg,$_GET['t'],$method==='HEAD');$db->commit();
    header('Content-Type: image/jpeg');header('Content-Length: '.$r['size']);header('Content-Disposition: inline; filename="creative.jpg"');
    echo $r['bytes'];
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();http_response_code(404);}
