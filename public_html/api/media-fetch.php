<?php
declare(strict_types=1);
require __DIR__.'/operations.php';
require __DIR__.'/carousel-core.php';
require __DIR__.'/media-range.php';
opsHeaders();
try{
    $method=$_SERVER['REQUEST_METHOD']??'';
    if(!opsSsl()||!in_array($method,['GET','HEAD'],true)||count($_GET)!==1||(!is_string($_GET['t']??null)&&!is_string($_GET['c']??null)))throw new RuntimeException('Unavailable');
    $cfg=opsConfig();$db=opsDb($cfg);$db->beginTransaction();
    $r=isset($_GET['c'])?caFetch($db,$cfg,$_GET['c'],$method==='HEAD'):miFetch($db,$cfg,$_GET['t'],$method==='HEAD');$db->commit();
    $range=mediaRange($r['kind'],$r['size'],$_SERVER['HTTP_RANGE']??null,$method);
    if($range===false){http_response_code(416);header('Content-Range: bytes */'.$r['size']);exit;}
    if($range!==null){http_response_code(206);header('Content-Range: bytes '.$range[0].'-'.$range[1].'/'.$r['size']);$r['bytes']=substr($r['bytes'],$range[0],$range[1]-$range[0]+1);$r['size']=$range[1]-$range[0]+1;}
    if($r['kind']==='video')header('Accept-Ranges: bytes');
    header('Content-Type: '.($r['kind']==='video'?'video/mp4':'image/jpeg'));header('Content-Length: '.$r['size']);header('Content-Disposition: inline; filename="creative.'.($r['kind']==='video'?'mp4':'jpg').'"');
    echo $r['bytes'];
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();http_response_code(404);}
