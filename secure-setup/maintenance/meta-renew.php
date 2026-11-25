<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/meta-renew-lib.php';
require __DIR__.'/meta-send.php';
require __DIR__.'/instagram-send.php';
try{
    if(count($argv)!==3||!in_array($argv[1],['--check','--scheduled-run'],true)||!in_array($argv[2],['facebook','instagram','threads'],true))throw new InvalidArgumentException('Explicit mode and provider');
    $cfg=opsConfig();$provider=$argv[2];$g=metaRenewGrant($cfg,$provider,time());
    socialRenewDirectory($g['directory'],dirname(__DIR__,2).'/public_html');
    if($argv[1]==='--check'){echo "Reviewed local configuration valid; no provider request.\n";exit;}
    $db=opsDb($cfg);$database=$cfg['database']??null;
    $config=static function()use($database):array{$c=opsConfig();if(($c['database']??null)!==$database)throw new RuntimeException('Database changed');return $c;};
    $probe=static fn(string $provider,array $target):bool=>$provider==='instagram'?igIdentity($target,'igRequest'):metaIdentity($target,'metaRequest');
    $r=metaRenewRun($db,$config,$provider,'metaRenewRequest',$probe);
    echo json_encode($r,JSON_THROW_ON_ERROR)."\n";exit($r['status']==='busy'?1:0);
}catch(Throwable $e){fwrite(STDERR,"BLOCKED: reviewed Meta grant, pause, identity or candidate. No active credential update.\n");exit(1);}
