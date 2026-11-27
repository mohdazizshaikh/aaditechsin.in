<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/strict-json.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/seo-core.php';
try{
    $cfg=opsConfig();if(!seoEnabled($cfg)||($argv[1]??'')!=='--register'||count($argv)!==2)throw new RuntimeException('Disabled or invalid');
    $raw=stream_get_contents(STDIN,65537);if(strlen($raw)>65536)throw new InvalidArgumentException('Too large');
    $r=strictJsonDecode($raw,true,32);if(!is_array($r))throw new InvalidArgumentException('Invalid report');
    $db=opsDb($cfg);$db->beginTransaction();$reply=seoRegister($db,$r);$db->commit();
    echo json_encode($reply,JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: SEO configuration, pause or report invalid.\n");exit(1);}
