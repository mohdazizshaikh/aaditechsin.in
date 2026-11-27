<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/oauth-activation-lib.php';
function acCheck(bool $v,string $m):void{if(!$v)throw new LogicException($m);}
function acBlocked(callable $f):void{try{$f();}catch(RuntimeException|InvalidArgumentException|JsonException $e){return;}throw new LogicException('Expected refusal');}
$now=time();$date=static fn(int $t)=>gmdate('Y-m-d\TH:i:s\Z',$t);$webroot=dirname(__DIR__).'/public_html';
$tmp=sys_get_temp_dir().'/aaditechs-activation-'.bin2hex(random_bytes(8));mkdir($tmp,0700);$key=random_bytes(32);
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
$secure=$tmp.'/config';$backup=$tmp.'/backup';$candidates=$tmp.'/candidates';
foreach([$secure,$backup,$candidates] as $dir)mkdir($dir,0700);
$origin='https://staging.example.invalid';$cfg['public_base_url']=$origin;$cfg['oauth_lifecycle']['activation_enabled']=true;
foreach(['renewal','meta_renewal','network_renewal'] as $group)foreach($cfg['oauth_lifecycle'][$group] as &$grant)$grant['candidate_directory']=$candidates;
unset($grant);
$path=$secure.'/aaditechs-config.php';$original=activationConfigBytes($cfg);activationWrite($path,$original);
try{
    $items=[];
    foreach($providers as $i=>$p){
        $g=renewalGrant($cfg,$p,$now);
        $item=['provider'=>$p,'access_token'=>str_repeat('t',40),'generation'=>str_pad(dechex($i+1),32,'d',STR_PAD_LEFT),'expires_utc'=>$date($now+3540)];
        if(renewalFamily($p)==='network'){
            $r=['access_token'=>$item['access_token'],'refresh_token'=>str_repeat('n',40),'expires_in'=>3600,'scope'=>implode(' ',$g['scopes'])];
            if($p==='linkedin')$r['refresh_token_expires_in']=86400;else $r['token_type']='bearer';
            networkRenewWrite($candidates.'/'.$p.'-exchange-attempt.json',['version'=>1,'provider'=>$p,'generation'=>$g['generation'],'attempt_utc'=>$date($now),'state'=>'attempt_started']);
            networkRenewWrite($candidates.'/'.$p.'-unverified-exchange.json',['version'=>1,'provider'=>$p,'generation'=>$g['generation'],'state'=>'unverified','response'=>$r]);
        }
        socialRenewStore($candidates.'/'.$p.'-rotation-candidate.json',$item);$items[$p]=$item;
    }
    $loaded=activationConfig($path,$webroot);$raw=$scope($providers);$prepared=activationPrepare($loaded,$raw,$key,$origin,$webroot,$backup,$now);
    acCheck(count($prepared['targets'])===7&&$prepared['config']['oauth_lifecycle']['network_renewal']['x']['refresh_token']===str_repeat('n',40)&&!$prepared['config']['oauth_lifecycle']['meta_renewal']['threads']['enabled'],'Exact refresh reconciliation');
    acCheck(!str_contains(json_encode($prepared['plan']),str_repeat('t',40))&&!str_contains(json_encode($prepared['plan']),$tmp),'Redacted plan');
    $count=0;
    for($mask=1;$mask<128;$mask++){
        $chosen=[];foreach($providers as $i=>$p)if($mask&(1<<$i))$chosen[]=$p;
        $p=activationPrepare($loaded,$scope($chosen),$key,$origin,$webroot,$backup,$now);
        acCheck(array_keys($p['targets'])===$chosen,'Exact subset');
        foreach($providers as $provider){
            $t=oauthRotationGet($p['config'],oauthRotationPath($provider));
            acCheck($t['access_token']===str_repeat(in_array($provider,$chosen,true)?'t':'s',40),'Unselected credential unchanged');
        }
        ++$count;
    }
    acBlocked(static fn()=>activationPrepare($loaded,$raw,$key,'https://aaditechs.in',$webroot,$backup,$now));
    acBlocked(static fn()=>activationPrepare($loaded,$raw,$key,$origin,$webroot,$candidates,$now));
    $file=$candidates.'/x-unverified-exchange.json';$saved=file_get_contents($file);$bad=activationJson($saved);$bad['response']['access_token']=str_repeat('q',40);file_put_contents($file,json_encode($bad));
    acBlocked(static fn()=>activationPrepare($loaded,$raw,$key,$origin,$webroot,$backup,$now));file_put_contents($file,$saved);
    chmod($file,0644);acBlocked(static fn()=>activationPrepare($loaded,$raw,$key,$origin,$webroot,$backup,$now));chmod($file,0600);
    file_put_contents($path,$original."// dynamic-format comment\n");acBlocked(static fn()=>activationConfig($path,$webroot));file_put_contents($path,$original);
    acCheck(file_get_contents($path)===$original&&count(glob($backup.'/*'))===0,'Offline plan never writes');
    echo "OAuth activation: $count provider subsets, exact refresh reconciliation and private static config gates PASS\n";
    if(($argv[1]??'')!=='--database')return;
    if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PORT')!=='3306'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
    $db=new PDO('mysql:host=127.0.0.1;port=3306;dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci','ci_only_not_real_password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $paused=(int)$db->query('SELECT paused FROM growth_control WHERE id=1')->fetchColumn();$db->exec('UPDATE growth_control SET paused=1 WHERE id=1');
    try{
        $calls=[];$probe=static function($p,$t,$boundary)use(&$calls,$db){acCheck(!$db->inTransaction()&&$t['token']===str_repeat('t',40),'Identity outside transaction');$boundary();$calls[]=$p;return true;};
        $clock=static fn()=>$now;$digest=$prepared['plan']['sha256'];
        acBlocked(static fn()=>activationApply($db,$path,$raw,$key,str_repeat('0',64),$origin,$webroot,$backup,$probe,$clock));
        $db->exec('UPDATE growth_control SET paused=0 WHERE id=1');acBlocked(static fn()=>activationApply($db,$path,$raw,$key,$digest,$origin,$webroot,$backup,$probe,$clock));$db->exec('UPDATE growth_control SET paused=1 WHERE id=1');
        acCheck(!$calls&&file_get_contents($path)===$original,'Pre-network gates');
        acBlocked(static fn()=>activationApply($db,$path,$raw,$key,$digest,$origin,$webroot,$backup,static fn()=>false,$clock));
        acCheck(file_get_contents($path)===$original&&count(glob($backup.'/*'))===0,'Identity failure leaves active file');
        $r=activationApply($db,$path,$raw,$key,$digest,$origin,$webroot,$backup,$probe,$clock);
        acCheck($r['status']==='activated_on_staging'&&$calls===$providers&&!$r['publication_authorized'],'All seven activated in fixture');
        acCheck(activationConfig($path,$webroot)['bytes']===$prepared['bytes'],'Exact active replacement');
        $backups=glob($backup.'/*');acCheck(count($backups)===1&&activationBytes($backups[0],524288)===$original,'Exact private backup');
        acCheck(count(glob($candidates.'/*'))===11,'All candidate and journal files retained');
        acBlocked(static fn()=>activationApply($db,$path,$raw,$key,$digest,$origin,$webroot,$backup,$probe,$clock));
        acCheck(!$db->inTransaction(),'Transaction closed');
    }finally{$db->exec('UPDATE growth_control SET paused='.(int)$paused.' WHERE id=1');}
    echo "Disposable activation: pause, identity, exact replacement, backup and stale replay PASS\n";
}finally{foreach([$secure,$backup,$candidates] as $dir){foreach(glob($dir.'/*')?:[] as $f)unlink($f);rmdir($dir);}rmdir($tmp);}
