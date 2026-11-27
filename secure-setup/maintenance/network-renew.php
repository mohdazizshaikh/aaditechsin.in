<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/network-renew-lib.php';
require __DIR__.'/network-send.php';
try{
    if(count($argv)!==3||!in_array($argv[1],['--check','--scheduled-run'],true)||!in_array($argv[2],['linkedin','x'],true))throw new InvalidArgumentException('Explicit mode and provider');
    $cfg=opsConfig();$provider=$argv[2];$g=networkRenewGrant($cfg,$provider,time());
    socialRenewDirectory($g['directory'],dirname(__DIR__,2).'/public_html');
    if($argv[1]==='--check'){echo "Local reviewed grant valid; no provider request.\n";exit;}
    $db=opsDb($cfg);$database=$cfg['database']??null;
    $config=static function()use($database):array{$c=opsConfig();if(($c['database']??null)!==$database)throw new RuntimeException('Database changed');return $c;};
    $probe=static function(string $provider,array $target,callable $boundary):bool{
        return networkIdentity($target,static function($p,$method,$path,$data)use($boundary){
            if($method!=='GET')throw new RuntimeException('Read only');
            $boundary();$r=networkRequest($p,$method,$path,$data);$boundary();return $r;
        });
    };
    $r=networkRenewRun($db,$config,$provider,'networkRenewRequest',$probe);
    echo json_encode($r,JSON_THROW_ON_ERROR)."\n";exit(in_array($r['status'],['busy','reconciliation_required'],true)?1:0);
}catch(Throwable $e){fwrite(STDERR,"BLOCKED: reviewed grant, exchange, identity or private journal. Inspect privately; no automatic retry or activation.\n");exit(1);}
