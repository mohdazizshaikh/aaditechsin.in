<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/network-core.php';
require dirname(__DIR__).'/secure-setup/maintenance/network-send.php';
function netCheck(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
$cfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'social'=>['enabled'=>true,'network'=>['enabled'=>true,'linkedin'=>['enabled'=>true,'account_id'=>'1234','access_token'=>str_repeat('a',40),'api_version'=>'202610'],'x'=>['enabled'=>true,'account_id'=>'5678','access_token'=>str_repeat('b',40)],'google-business'=>['enabled'=>true,'account_id'=>'10:20','access_token'=>str_repeat('c',40)]]]];
netCheck(networkTarget([],'linkedin')===null&&networkTarget($cfg,'youtube')===null,'Default OFF');
foreach(['linkedin','x','google-business'] as $provider){
    $p=networkTarget($cfg,$provider);$row=['provider'=>$provider,'target_id'=>$p['id'],'caption'=>'Plan your IT requirements.'];$calls=[];
    $fake=static function($p,$method,$path,$data)use(&$calls){
        $calls[]=$method;
        if($method==='GET')return match($p['provider']){
            'linkedin'=>['http'=>200,'json'=>['elements'=>[['role'=>'ADMINISTRATOR','state'=>'APPROVED','organizationTarget'=>'urn:li:organization:1234']]]],
            'x'=>['http'=>200,'json'=>['data'=>['id'=>'5678']]],
            'google-business'=>['http'=>200,'json'=>[]]};
        return match($p['provider']){
            'linkedin'=>['http'=>201,'receipt'=>'urn:li:share:99'],
            'x'=>['http'=>201,'json'=>['data'=>['id'=>'99']]],
            'google-business'=>['http'=>200,'json'=>['name'=>'accounts/10/locations/20/localPosts/99','state'=>'PROCESSING']]};
    };
    netCheck(networkSend($p,$row,$fake)['status']==='accepted'&&$calls===['GET','POST'],'Receipt '.$provider);
    $calls=[];$wrong=static function($p,$method,$path,$data)use(&$calls){$calls[]=$method;return ['http'=>403,'json'=>[]];};
    netCheck(networkSend($p,$row,$wrong)['error']==='identity_unverified'&&$calls===['GET'],'Access gate '.$provider);
}
if(($argv[1]??'')!=='--database'){echo "Network adapters mock transport: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);
    $id=creativeCreate($db,'infrastructure','x','education');
    creativeImport($db,['version'=>1,'task_id'=>$id,'revision'=>1,'model'=>'local-test:1b','caption'=>'Plan your infrastructure.','visual_brief'=>'Illustration','video_brief'=>'Planning','review_notes'=>'Review']);
    $when=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d\TH:i\Z');
    try{networkSchedule($db,$cfg,'x',$id,1,'5678',$when);throw new RuntimeException('Unapproved accepted');}catch(InvalidArgumentException $e){}
    growthCommand($db,"/approve $id 1",$cfg);
    $pub=networkSchedule($db,$cfg,'x',$id,1,'5678',$when);
    netCheck(networkClaim($db,$cfg,'x')===null,'Future not due');
    $db->prepare('UPDATE growth_network_publications SET due_at=UTC_TIMESTAMP() WHERE id=?')->execute([$pub]);
    $r=networkClaim($db,$cfg,'x');netCheck((int)$r['id']===$pub,'Claim');
    networkFinish($db,$r,networkResult('unknown',null,'ambiguous_response'));
    netCheck(networkClaim($db,$cfg,'x')===null,'No blind retry');
    netCheck(str_contains(growthCommand($db,'/publications',$cfg),'x unknown: 1'),'Unified ledger');
    $db->rollBack();echo "Network synthetic queue and unified reporting: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Network test failed: '.$e->getMessage()."\n");exit(1);}
