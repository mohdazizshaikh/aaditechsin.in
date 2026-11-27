<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/social-renew-lib.php';
require __DIR__.'/network-send.php';
require __DIR__.'/youtube-send.php';
try{
    if(count($argv)!==3||!in_array($argv[1],['--check','--scheduled-run'],true)||!in_array($argv[2],['google-business','youtube'],true))throw new InvalidArgumentException('Explicit provider and mode');
    $cfg=opsConfig();$provider=$argv[2];$g=socialRenewGrant($cfg,$provider,time());
    socialRenewDirectory($g['directory'],dirname(__DIR__,2).'/public_html');
    if($argv[1]==='--check'){echo "Local grant and directory configuration valid; no provider request or activation.\n";exit;}
    $db=opsDb($cfg);$database=$cfg['database']??null;
    $config=static function()use($database):array{$c=opsConfig();if(($c['database']??null)!==$database)throw new RuntimeException('Database changed');return $c;};
    $probe=static fn(string $provider,array $target):bool=>match($provider){
        'google-business'=>networkIdentity($target,'networkRequest'),
        'youtube'=>youtubeIdentity($target,'youtubeRequest')
    };
    $r=socialRenewRun($db,$config,$provider,'gmailRenewRequest',$probe);
    echo json_encode($r,JSON_THROW_ON_ERROR)."\n";exit($r['status']==='busy'?1:0);
}catch(Throwable $e){fwrite(STDERR,"BLOCKED: grant, pause, identity or private candidate. Active credentials unchanged.\n");exit(1);}
