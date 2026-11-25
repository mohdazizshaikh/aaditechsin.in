<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/oauth-rotation-lib.php';
require __DIR__.'/meta-send.php';
require __DIR__.'/network-send.php';
require __DIR__.'/instagram-send.php';
require __DIR__.'/youtube-send.php';
try{
    $mode=$argv[1]??'';
    if(!in_array($mode,['--plan','--verify'],true)||count($argv)!==($mode==='--plan'?2:3))throw new InvalidArgumentException('Explicit mode');
    $raw=stream_get_contents(STDIN,32769);if(!is_string($raw)||strlen($raw)>32768)throw new InvalidArgumentException('Scope cap');
    $key=oauthRotationKey((string)getenv('AADITECHS_OAUTH_ROTATION_HMAC_KEY_HEX'));
    if($mode==='--plan'){echo json_encode(oauthRotationPlan(opsConfig(),$raw,$key),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";exit;}
    $db=opsDb(opsConfig());
    $paused=static fn()=> (string)$db->query('SELECT paused FROM growth_control WHERE id=1')->fetchColumn()==='1';
    $probe=static function(string $provider,array $p):bool{
        return match($provider){
            'facebook','threads'=>metaIdentity($p,'metaRequest'),
            'linkedin','x','google-business'=>networkIdentity($p,'networkRequest'),
            'instagram'=>igIdentity($p,'igRequest'),
            'youtube'=>youtubeIdentity($p,'youtubeRequest')
        };
    };
    $r=oauthRotationVerify('opsConfig',$raw,$key,$argv[2],$paused,$probe);
    echo json_encode($r,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";exit($r['complete']?0:1);
}catch(Throwable $e){fwrite(STDERR,"BLOCKED: reviewed rotation, pause, configuration or identity check. Active credentials unchanged.\n");exit(1);}
