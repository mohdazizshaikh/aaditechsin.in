<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/renewal-orchestrator-lib.php';
require __DIR__.'/network-send.php';
require __DIR__.'/youtube-send.php';
require __DIR__.'/meta-send.php';
require __DIR__.'/instagram-send.php';
try{
    $mode=$argv[1]??'';
    if(!in_array($mode,['--status','--plan','--run'],true)||count($argv)!==($mode==='--run'?3:2))throw new InvalidArgumentException('Explicit mode');
    $cfg=opsConfig();$webroot=dirname(__DIR__,2).'/public_html';
    if($mode==='--status'){echo json_encode(renewalStatus($cfg,time(),$webroot),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";exit;}
    $raw=stream_get_contents(STDIN,4097);if(!is_string($raw))throw new RuntimeException('Scope unavailable');
    $key=oauthRotationKey((string)getenv('AADITECHS_OAUTH_ROTATION_HMAC_KEY_HEX'));
    if($mode==='--plan'){echo json_encode(renewalPlan($cfg,$raw,$key,time(),$webroot),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";exit;}
    $db=opsDb($cfg);$database=$cfg['database']??null;
    $config=static function()use($database):array{$c=opsConfig();if(($c['database']??null)!==$database)throw new RuntimeException('Database changed');return $c;};
    $run=static function(string $p,array $grant,callable $check)use($db,$config):array{
        // Provider runner calls this inside its own transaction; do not nest the batch pause check.
        $bound=static function()use($config,$p,$grant):array{
            $c=$config();if(!renewalEnabled($c)||renewalGrant($c,$p,time())!==$grant)throw new RuntimeException('Reviewed grant changed');return $c;
        };
        $probe=static fn(string $p,array $t):bool=>match($p){
            'facebook','threads'=>metaIdentity($t,'metaRequest'),
            'instagram'=>igIdentity($t,'igRequest'),
            'google-business'=>networkIdentity($t,'networkRequest'),
            'youtube'=>youtubeIdentity($t,'youtubeRequest')
        };
        return match(renewalFamily($p)){
            'google'=>socialRenewRun($db,$bound,$p,'gmailRenewRequest',$probe),
            'meta'=>metaRenewRun($db,$bound,$p,'metaRenewRequest',$probe),
            'network'=>networkRenewRun($db,$bound,$p,'networkRenewRequest',static function($provider,$target,$boundary):bool{
                return networkIdentity($target,static function($p,$method,$path,$data)use($boundary){
                    if($method!=='GET')throw new RuntimeException('Read only');$boundary();$r=networkRequest($p,$method,$path,$data);$boundary();return $r;
                });
            })
        };
    };
    $r=renewalBatch($db,$config,$raw,$key,$argv[2],$webroot,$run);
    echo json_encode($r,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";exit($r['status']==='complete'?0:1);
}catch(Throwable $e){fwrite(STDERR,"BLOCKED: reviewed scope, pause, provider or journal changed. Inspect private status; no automatic retry or activation.\n");exit(1);}
