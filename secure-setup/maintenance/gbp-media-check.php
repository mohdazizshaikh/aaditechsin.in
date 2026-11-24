<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/gbp-media-check-lib.php';
try{
    if(count($argv)!==2||!in_array($argv[1],['--check-config','--check-read'],true))throw new RuntimeException('Mode');
    $cfg=opsConfig();$p=gbpMediaReadTarget($cfg);if(!$p)throw new RuntimeException('Disabled');
    if($argv[1]==='--check-config'){echo "Local configuration valid; no network access.\n";exit;}
    if(!gbpMediaReadCheck($p,'networkRequest'))throw new RuntimeException('Read not verified');
    echo "Configured location media list readable. Upload permission, ownership and publication NOT verified.\n";
}catch(Throwable $e){fwrite(STDERR,"BLOCKED: media read configuration or scoped read unavailable. No provider response logged.\n");exit(1);}
