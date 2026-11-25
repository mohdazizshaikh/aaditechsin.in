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
// Exercise all seven provider policy boundaries and malformed metadata.
$cases=0;
foreach(oauthProviders() as $provider){
    $p=['generation'=>$g,'expires_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now+86400),'revoked'=>false];
    foreach([[0,'expired'],[600,'too_close'],[601,'renew_soon'],[7*86400,'renew_soon'],[7*86400+1,'current']] as [$delta,$state]){
        $c=['oauth_lifecycle'=>['enabled'=>true,'providers'=>[$provider=>array_replace($p,['expires_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now+$delta)])]]];
        ohCheck(oauthPolicy($c,$provider,$now)['state']===$state,'Boundary');++$cases;
    }
    foreach([['revoked'=>true],['revoked'=>'false'],['revoked'=>null],['generation'=>'INVALID'],['expires_utc'=>'2026-02-30T00:00:00Z'],['expires_utc'=>'not-a-date']] as $patch){
        $c=['oauth_lifecycle'=>['enabled'=>true,'providers'=>[$provider=>array_replace($p,$patch)]]];
        ohCheck(!oauthAllowed($c,$provider,$now),'Malformed or revoked');++$cases;
    }
}
ohCheck($cases===77&&!oauthAllowed([],'unknown')&&!oauthAllowed(['oauth_lifecycle'=>['enabled'=>'true']],'facebook'),'Strict policy');
$providers=oauthProviders();
for($i=1;$i<=7;$i++)ohCheck(count(oauthBatchScope(json_encode(['version'=>1,'providers'=>array_slice($providers,0,$i)],JSON_THROW_ON_ERROR)))===$i,'Scope sizes');
foreach([[],['facebook','facebook'],['unknown'],[true],array_merge($providers,['extra'])] as $bad){
    try{oauthBatchScope(json_encode(['version'=>1,'providers'=>$bad],JSON_THROW_ON_ERROR));throw new LogicException('Invalid scope accepted');}catch(InvalidArgumentException $e){}
}
$raw=json_encode(['version'=>1,'providers'=>['facebook']],JSON_THROW_ON_ERROR);
try{oauthBatchPlan($cfg,$raw);throw new LogicException('Disabled batch accepted');}catch(RuntimeException $e){}
$cfg['oauth_lifecycle']['batch_health_enabled']=true;
$plan=oauthBatchPlan($cfg,$raw);
ohCheck(count($plan['providers'])===1&&!str_contains(json_encode($plan),str_repeat('s',40)),'Sanitized plan');
$changed=$cfg;$changed['social']['meta']['facebook']['access_token']=str_repeat('t',40);
ohCheck(oauthBatchPlan($changed,$raw)['sha256']!==$plan['sha256'],'Credential change binds digest');
echo "OAuth policy matrix: $cases cases PASS\n";
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
    $db->rollBack();
    // Independent transaction path: preserve original pause, roll back evidence by restoring prior row.
    $pause=(int)$db->query('SELECT paused FROM growth_control WHERE id=1')->fetchColumn();
    $prior=$db->query("SELECT * FROM growth_oauth_health WHERE provider='facebook'")->fetch(PDO::FETCH_ASSOC);
    try{
        $db->exec('UPDATE growth_control SET paused=1 WHERE id=1');
        $calls=0;$probe=static function($provider,$target)use(&$calls){++$calls;return true;};
        try{oauthBatchCheck($db,$cfg,$raw,str_repeat('0',64),$probe);throw new LogicException('Wrong digest accepted');}catch(RuntimeException $e){}
        ohCheck($calls===0,'No probe on mismatch');
        $r=oauthBatchCheck($db,$cfg,$raw,$plan['sha256'],$probe);
        ohCheck($r['complete']&&$r['checked']===1&&$calls===1,'Batch health');
        $r=oauthBatchCheck($db,$cfg,$raw,$plan['sha256'],static fn()=>false);
        ohCheck(!$r['complete']&&$r['results'][0]['state']==='not_verified','Fail closed result');
    }finally{
        $db->exec("DELETE FROM growth_oauth_health WHERE provider='facebook'");
        if($prior)$db->prepare('INSERT INTO growth_oauth_health(provider,generation,target_id,state,checked_at) VALUES(?,?,?,?,?)')->execute([$prior['provider'],$prior['generation'],$prior['target_id'],$prior['state'],$prior['checked_at']]);
        $db->prepare('UPDATE growth_control SET paused=? WHERE id=1')->execute([$pause]);
    }
    echo "Synthetic OAuth health evidence, stale rotation and reviewed batch: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'OAuth test failed: '.$e->getMessage()."\n");exit(1);}
