<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/oauth-health-core.php';
function ohCheck(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
$now=time();$g=str_repeat('a',32);
$cfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'social'=>['enabled'=>true,'meta'=>['enabled'=>true,'facebook'=>['enabled'=>true,'account_id'=>'123','access_token'=>str_repeat('s',40),'api_version'=>'v27.0']]],'oauth_lifecycle'=>['enabled'=>true,'providers'=>['facebook'=>['generation'=>$g,'expires_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now+86400),'revoked'=>false]]]];
ohCheck(oauthPolicy($cfg,'facebook',$now)['state']==='renew_soon'&&oauthAllowed($cfg,'facebook',$now)&&oauthTarget($cfg,'facebook')!==null,'Current metadata');
foreach(['2020-01-01T00:00:00Z','2026-02-30T00:00:00Z','not a date',gmdate('Y-m-d\TH:i:s\Z',$now+300)] as $expiry){
    $bad=$cfg;$bad['oauth_lifecycle']['providers']['facebook']['expires_utc']=$expiry;
    ohCheck(!oauthAllowed($bad,'facebook',$now)&&oauthTarget($bad,'facebook')===null,'Expiry gate');
}
$bad=$cfg;$bad['oauth_lifecycle']['providers']['facebook']['revoked']=true;ohCheck(oauthTarget($bad,'facebook')===null,'Revoked gate');
$bad=$cfg;unset($bad['oauth_lifecycle']['providers']['facebook']);ohCheck(oauthTarget($bad,'facebook')===null,'Missing metadata gate');
if(($argv[1]??'')!=='--database'){echo "OAuth expiry and revocation gates: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    $p=oauthTarget($cfg,'facebook');oauthRecord($db,$cfg,'facebook',$p,'identity_verified');
    $s=oauthStatus($db,$cfg);ohCheck($s[0]['health']==='identity_verified','Sanitized health');
    ohCheck(!str_contains(json_encode($s),$p['token']),'No secret in report');
    $rotated=$cfg;$rotated['oauth_lifecycle']['providers']['facebook']['generation']=str_repeat('b',32);
    ohCheck(oauthStatus($db,$rotated)[0]['health']==='stale','Rotation invalidates evidence');
    ohCheck(oauthStatus($db,$cfg,time()+90000)[0]['health']==='stale'||oauthStatus($db,$cfg,time()+90000)[0]['policy']==='expired','Age gate');
    ohCheck(str_contains(oauthCommand($db,$cfg,'/connections'),'identity_verified'),'Owner report');
    $db->rollBack();echo "Synthetic OAuth health evidence and stale rotation: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'OAuth test failed: '.$e->getMessage()."\n");exit(1);}
