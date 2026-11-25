<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/renewal-orchestrator-lib.php';
function roCheck(bool $v,string $m):void{if(!$v)throw new LogicException($m);}
function roBlocked(callable $f):void{try{$f();}catch(RuntimeException|InvalidArgumentException|JsonException $e){return;}throw new LogicException('Expected refusal');}
$now=time();$date=static fn(int $t)=>gmdate('Y-m-d\TH:i:s\Z',$t);$webroot=dirname(__DIR__).'/public_html';
$tmp=sys_get_temp_dir().'/aaditechs-renewal-orchestrator-'.bin2hex(random_bytes(8));mkdir($tmp,0700);$key=random_bytes(32);
$cfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'media'=>['enabled'=>true],'social'=>['enabled'=>true,'network'=>['enabled'=>true],'meta'=>['enabled'=>true]],'media_upload'=>['enabled'=>true],'oauth_lifecycle'=>['enabled'=>true,'rotation_review_enabled'=>true,'orchestrator_enabled'=>true,'automatic_candidate_enabled'=>true,'meta_candidate_enabled'=>true,'network_candidate_enabled'=>true,'providers'=>[],'renewal'=>[],'meta_renewal'=>[],'network_renewal'=>[]]];
$providers=oauthProviders();
foreach($providers as $i=>$p){
    $id=(string)(100+$i);$target=['enabled'=>true,'access_token'=>str_repeat('s',40),'account_id'=>$id];
    if($p==='google-business'){$id='123:456';$target['account_id']=$id;}
    if($p==='youtube'){$id='UC'.str_repeat('a',22);$target['channel_id']=$id;}
    if($p==='instagram'){$target['page_id']='999';$id='999:'.$id;}
    if(in_array($p,['facebook','instagram','threads'],true))$target['api_version']='v27.0';
    if($p==='linkedin')$target['api_version']='202611';
    oauthRotationSet($cfg,oauthRotationPath($p),$target);
    $gen=str_pad(dechex($i+1),32,'a',STR_PAD_LEFT);
    $cfg['oauth_lifecycle']['providers'][$p]=['generation'=>$gen,'expires_utc'=>$date($now-3600),'revoked'=>false];
    $r=['enabled'=>true,'authorized'=>true,'generation'=>str_pad(dechex($i+1),32,'b',STR_PAD_LEFT),'target_id'=>$id,'review_expires_utc'=>$date($now+30*86400),'candidate_directory'=>$tmp,'client_id'=>str_repeat('i',20),'client_secret'=>str_repeat('c',20),'refresh_token'=>str_repeat('r',40)];
    if(renewalFamily($p)==='google')$cfg['oauth_lifecycle']['renewal'][$p]=$r;
    elseif(renewalFamily($p)==='network')$cfg['oauth_lifecycle']['network_renewal'][$p]=$r+['confidential_client_reviewed'=>true,'programmatic_refresh_reviewed'=>true,'refresh_expires_utc'=>$date($now+10*86400),'reviewed_scopes'=>$p==='linkedin'?['rw_organization_admin','w_organization_social']:['offline.access','tweet.read','tweet.write','users.read']];
    else $cfg['oauth_lifecycle']['meta_renewal'][$p]=$r+['long_lived_reviewed'=>true,'active_generation'=>$gen,'issued_utc'=>$date($now-10*86400),'provider_expires_utc'=>$date($now+86400),'user_access_token'=>str_repeat('u',40),'user_expires_utc'=>$date($now+15*86400),'user_id'=>'999','app_id'=>'111','app_secret'=>str_repeat('c',32),'reviewed_scopes'=>$p==='instagram'?['instagram_basic','instagram_content_publish','pages_read_engagement','pages_show_list']:['pages_manage_posts','pages_read_engagement','pages_show_list']];
}
$scope=static fn(array $p)=>json_encode(['version'=>1,'providers'=>$p],JSON_THROW_ON_ERROR);
try{
    $status=renewalStatus($cfg,$now,$webroot);roCheck(count($status['providers'])===7&&array_unique(array_column($status['providers'],'state'))===['due'],'All due');
    $cases=0;$digests=[];
    for($mask=1;$mask<128;$mask++){
        $selected=[];foreach($providers as $i=>$p)if($mask&(1<<$i))$selected[]=$p;
        $plan=renewalPlan($cfg,$scope($selected),$key,$now,$webroot);
        roCheck(array_column($plan['providers'],'provider')===$selected&&!$plan['activation_authorized'],'Exact scope');
        $digests[]=$plan['sha256'];++$cases;
    }
    roCheck(count(array_unique($digests))===127,'Unique reviewed subset digests');
    foreach($providers as $p){
        $one=$scope([$p]);$plan=renewalPlan($cfg,$one,$key,$now,$webroot);
        $changed=$cfg;$changed['oauth_lifecycle']['providers'][$p]['expires_utc']=$date($now+10*86400);
        if($p!=='threads')roCheck(renewalRow($changed,$p,$now,$webroot)['state']==='not_due','Not due');
        roCheck(renewalPlan($changed,$one,$key,$now,$webroot)['sha256']!==$plan['sha256'],'Deadline drift');
        $candidate=$tmp.'/'.$p.'-rotation-candidate.json';file_put_contents($candidate,'{}');chmod($candidate,0600);
        $state=renewalRow($cfg,$p,$now,$webroot)['state'];
        roCheck($state===(renewalFamily($p)==='network'?'reconciliation_required':'candidate_present_unverified'),'Existing candidate');
        roBlocked(static fn()=>renewalPlan($cfg,$one,$key,$now,$webroot));
        chmod($candidate,0644);roCheck(renewalRow($cfg,$p,$now,$webroot)['state']==='unsafe_private_file','Unsafe candidate');unlink($candidate);
        symlink($tmp.'/missing',$candidate);roCheck(renewalRow($cfg,$p,$now,$webroot)['state']==='unsafe_private_file','Dangling symlink');unlink($candidate);
        roCheck(!str_contains(json_encode($plan),str_repeat('s',40))&&!str_contains(json_encode($plan),$tmp),'Redacted output');
    }
    $marker=$tmp.'/x-exchange-attempt.json';file_put_contents($marker,'{}');chmod($marker,0600);
    $revoked=$cfg;$revoked['oauth_lifecycle']['providers']['x']['revoked']=true;
    roCheck(renewalRow($revoked,'x',$now,$webroot)['state']==='reconciliation_required','Revoked journal surfaced');unlink($marker);
    roBlocked(static fn()=>renewalPlan($cfg,$scope(['x','x']),$key,$now,$webroot));
    echo "Renewal orchestrator: $cases reviewed provider subsets, private inventory and redaction PASS\n";
    if(($argv[1]??'')!=='--database')return;
    if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PORT')!=='3306'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
    $db=new PDO('mysql:host=127.0.0.1;port=3306;dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci','ci_only_not_real_password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $original=(int)$db->query('SELECT paused FROM growth_control WHERE id=1')->fetchColumn();$db->exec('UPDATE growth_control SET paused=1 WHERE id=1');
    try{
        $current=$cfg;$load=static function()use(&$current){return $current;};$raw=$scope($providers);$plan=renewalPlan($cfg,$raw,$key,$now,$webroot);$clock=static fn()=>$now;$calls=[];
        $run=static function($p,$g,$check)use(&$calls,$tmp,$date,$now,$db){roCheck(!$db->inTransaction(),'No transaction across dispatch');$check();$calls[]=$p;socialRenewStore($tmp.'/'.$p.'-rotation-candidate.json',['provider'=>$p,'access_token'=>str_repeat('t',40),'generation'=>bin2hex(random_bytes(16)),'expires_utc'=>$date($now+3600)]);return ['status'=>'candidate_created'];};
        roBlocked(static fn()=>renewalBatch($db,$load,$raw,$key,str_repeat('0',64),$webroot,$run,$clock));roCheck(!$calls,'Digest gate');
        $db->exec('UPDATE growth_control SET paused=0 WHERE id=1');roBlocked(static fn()=>renewalBatch($db,$load,$raw,$key,$plan['sha256'],$webroot,$run,$clock));$db->exec('UPDATE growth_control SET paused=1 WHERE id=1');roCheck(!$calls,'Pause gate');
        $result=renewalBatch($db,$load,$raw,$key,$plan['sha256'],$webroot,$run,$clock);
        roCheck($result['status']==='complete'&&$calls===$providers&&!$result['activation_authorized'],'Full batch');
        foreach(glob($tmp.'/*')?:[] as $file)unlink($file);
        $calls=[];$stop=static function($p,$g,$check)use(&$calls){$calls[]=$p;return ['status'=>'busy'];};
        roCheck(renewalBatch($db,$load,$raw,$key,$plan['sha256'],$webroot,$stop,$clock)['status']==='stopped'&&count($calls)===1,'Stop on busy');
        $calls=[];$drift=static function($p,$g,$check)use(&$current,$run){$r=$run($p,$g,$check);$current['oauth_lifecycle']['providers']['x']['revoked']=true;return $r;};
        roBlocked(static fn()=>renewalBatch($db,$load,$raw,$key,$plan['sha256'],$webroot,$drift,$clock));roCheck(count($calls)===1,'Stop before another provider after scope drift');
        roCheck(!$db->inTransaction(),'Transaction closed');
    }finally{$db->exec('UPDATE growth_control SET paused='.(int)$original.' WHERE id=1');}
    echo "Disposable reviewed batch: seven providers, digest, pause, busy and drift gates PASS\n";
}finally{foreach(glob($tmp.'/*')?:[] as $f)unlink($f);rmdir($tmp);}
