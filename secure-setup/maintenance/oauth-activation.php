<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/oauth-activation-lib.php';
require __DIR__.'/network-send.php';
require __DIR__.'/meta-send.php';
require __DIR__.'/instagram-send.php';
require __DIR__.'/youtube-send.php';
try{
    $mode=$argv[1]??'';
    if(!in_array($mode,['--plan','--apply'],true)||count($argv)!==($mode==='--plan'?4:5))throw new InvalidArgumentException('Explicit staging origin and backup directory required');
    $origin=$argv[2];$backup=$argv[3];$root=dirname(__DIR__,2);$path=$root.'/secure/aaditechs-config.php';$web=$root.'/public_html';
    $raw=stream_get_contents(STDIN,4097);if(!is_string($raw))throw new RuntimeException('Scope unavailable');
    $key=oauthRotationKey((string)getenv('AADITECHS_OAUTH_ROTATION_HMAC_KEY_HEX'));
    $loaded=activationConfig($path,$web);activationStage($loaded['config'],$origin);
    if($mode==='--plan'){echo json_encode(activationPrepare($loaded,$raw,$key,$origin,$web,$backup,time())['plan'],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";exit;}
    $db=opsDb($loaded['config']);
    $probe=static function(string $p,array $target,callable $boundary):bool{
        return match($p){
            'linkedin','x','google-business'=>networkIdentity($target,static function($t,$method,$path,$data)use($boundary){if($method!=='GET')throw new RuntimeException('Read only');$boundary();$r=networkRequest($t,$method,$path,$data);$boundary();return $r;}),
            'facebook','threads'=>metaIdentity($target,static function($t,$method,$path,$data)use($boundary){if($method!=='GET')throw new RuntimeException('Read only');$boundary();$r=metaRequest($t,$method,$path,$data);$boundary();return $r;}),
            'instagram'=>igIdentity($target,static function($t,$method,$path,$data)use($boundary){if($method!=='get')throw new RuntimeException('Read only');$boundary();$r=igRequest($t,$method,$path,$data);$boundary();return $r;}),
            'youtube'=>youtubeIdentity($target,static function($t,$method,$url)use($boundary){if($method!=='GET')throw new RuntimeException('Read only');$boundary();$r=youtubeRequest($t,$method,$url);$boundary();return $r;})
        };
    };
    if(activationConfig($path,$web)['bytes']!==$loaded['bytes'])throw new RuntimeException('Config changed before execution');
    $result=activationApply($db,$path,$raw,$key,$argv[4],$origin,$web,$backup,$probe,null,$loaded['bytes']);
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";
}catch(Throwable $e){fwrite(STDERR,"BLOCKED: review configuration, pause, identity, backup and journals privately. No automatic rollback or retry; inspect active file before further action.\n");exit(1);}
