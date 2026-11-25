<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require dirname(__DIR__,2).'/public_html/api/oauth-health-core.php';
require __DIR__.'/meta-send.php';
require __DIR__.'/network-send.php';
require __DIR__.'/instagram-send.php';
require __DIR__.'/youtube-send.php';
try{
    $cfg=opsConfig();if(($cfg['oauth_lifecycle']['enabled']??false)!==true)throw new RuntimeException('Disabled');
    $db=opsDb($cfg);$mode=$argv[1]??'';
    if($mode==='--status'&&count($argv)===2){echo json_encode(oauthStatus($db,$cfg),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";exit;}
    $probe=static function(string $provider,array $p):bool{
        return match($provider){
            'facebook','threads'=>metaIdentity($p,'metaRequest'),
            'linkedin','x','google-business'=>networkIdentity($p,'networkRequest'),
            'instagram'=>igIdentity($p,'igRequest'),
            'youtube'=>youtubeIdentity($p,'youtubeRequest')
        };
    };
    if(in_array($mode,['--batch-plan','--batch-check'],true)){
        if(count($argv)!==($mode==='--batch-plan'?2:3))throw new InvalidArgumentException('Explicit mode required');
        $raw=stream_get_contents(STDIN,4097);if(!is_string($raw))throw new RuntimeException('Scope unavailable');
        if($mode==='--batch-plan'){echo json_encode(oauthBatchPlan($cfg,$raw),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";exit;}
        $result=oauthBatchCheck($db,$cfg,$raw,$argv[2],$probe);
        echo json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";exit($result['complete']?0:1);
    }
    if($mode!=='--check'||count($argv)!==3||!in_array($argv[2],oauthProviders(),true))throw new InvalidArgumentException('Explicit command required');
    $provider=$argv[2];$p=oauthTarget($cfg,$provider);if(!$p)throw new RuntimeException('Disabled or expired');
    try{
        $state=$probe($provider,$p)?'identity_verified':'not_verified';
    }catch(Throwable $e){$state='error';}
    $db->beginTransaction();oauthRecord($db,$cfg,$provider,$p,$state);$db->commit();echo $provider.': '.$state." (read-only, no publish permission proven)\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: disabled, expired, unverified configuration or database unavailable.\n");exit(1);}
