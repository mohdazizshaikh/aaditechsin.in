<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/google-capture-lib.php';
try{
    if(count($argv)!==3||!in_array($argv[1],['--check','--scheduled-run'],true)||!in_array($argv[2],['gmail','search-console'],true))throw new InvalidArgumentException('Explicit provider/mode');
    $cfg=opsConfig();$provider=$argv[2];googleCaptureGrant($cfg,$provider,time());
    if($argv[1]==='--check'){echo "Local reviewed grant configuration valid; no network or database access.\n";exit;}
    $r=googleCaptureRun(opsDb($cfg),'opsConfig',$provider,'gmailRenewRequest',$provider==='gmail'?'gmailRead':'scRequest');
    echo json_encode($r,JSON_THROW_ON_ERROR)."\n";exit($r['status']==='busy'?1:0);
}catch(Throwable $e){fwrite(STDERR,"BLOCKED: reviewed grant, pause, scope, identity or provider capture. No credential or response logged.\n");exit(1);}
