<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/social-renew-lib.php';
function srCheck(bool $v,string $m):void{if(!$v)throw new LogicException($m);}
function srBlocked(callable $f):void{try{$f();}catch(RuntimeException|InvalidArgumentException|JsonException $e){return;}throw new LogicException('Expected refusal');}
$now=time();$tmp=sys_get_temp_dir().'/aaditechs-social-renew-'.bin2hex(random_bytes(8));mkdir($tmp,0700);
$cfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'media'=>['enabled'=>true],'social'=>['enabled'=>true,'network'=>['enabled'=>true,'google-business'=>['enabled'=>true,'account_id'=>'123:456','access_token'=>str_repeat('s',40)]]],'media_upload'=>['enabled'=>true,'youtube'=>['enabled'=>true,'channel_id'=>'UC'.str_repeat('a',22),'access_token'=>str_repeat('s',40)]],'oauth_lifecycle'=>['enabled'=>true,'rotation_review_enabled'=>true,'automatic_candidate_enabled'=>true,'providers'=>[],'renewal'=>[]]];
foreach(['google-business'=>'123:456','youtube'=>'UC'.str_repeat('a',22)] as $provider=>$id){
    $cfg['oauth_lifecycle']['providers'][$provider]=['generation'=>str_repeat('a',32),'expires_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now-3600),'revoked'=>false];
    $cfg['oauth_lifecycle']['renewal'][$provider]=['enabled'=>true,'authorized'=>true,'generation'=>str_repeat('b',32),'target_id'=>$id,'review_expires_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now+86400),'client_id'=>str_repeat('i',20),'client_secret'=>str_repeat('c',20),'refresh_token'=>str_repeat('r',40),'candidate_directory'=>$tmp];
}
$reply=static fn($p)=>['access_token'=>str_repeat('t',40),'token_type'=>'Bearer','scope'=>implode(' ',socialRenewScopes($p)),'expires_in'=>3600];
try{
    $cases=0;
    foreach(['google-business','youtube'] as $p){
        $g=socialRenewGrant($cfg,$p,$now);$r=$reply($p);
        srCheck(socialRenewToken($g,$r,$now)['expires_utc']===gmdate('Y-m-d\TH:i:s\Z',$now+3540),'Token expiry');++$cases;
        srCheck(socialRenewToken($g,array_replace($r,['scope'=>implode(' ',array_reverse($g['scopes']))]),$now)['provider']===$p,'Scope order');++$cases;
        foreach([['scope'=>''],['scope'=>$r['scope'].' extra'],['scope'=>$r['scope'].' '.$g['scopes'][0]],['scope'=>null],['token_type'=>'bearer'],['expires_in'=>720],['expires_in'=>86401],['expires_in'=>'3600'],['access_token'=>'bad'],['access_token'=>str_repeat('s',40)],['refresh_token'=>'new'],['error'=>null]] as $patch){srBlocked(static fn()=>socialRenewToken($g,array_replace($r,$patch),$now));++$cases;}
        foreach([['authorized'=>false],['enabled'=>false],['generation'=>str_repeat('0',32)],['target_id'=>'wrong'],['review_expires_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now+660)],['client_secret'=>'REPLACE_SECRET']] as $patch){$bad=$cfg;$bad['oauth_lifecycle']['renewal'][$p]=array_replace($bad['oauth_lifecycle']['renewal'][$p],$patch);srBlocked(static fn()=>socialRenewGrant($bad,$p,$now));++$cases;}
        $bad=$cfg;$bad['oauth_lifecycle']['providers'][$p]['revoked']=true;srBlocked(static fn()=>socialRenewGrant($bad,$p,$now));++$cases;
        $path=socialRenewPath($tmp,$p);$item=socialRenewToken($g,$r,$now);socialRenewStore($path,$item);
        $raw=file_get_contents($path);srCheck((fileperms($path)&0777)===0600&&lstat($path)['nlink']===1,'Private candidate');
        srCheck(oauthRotationPlan($cfg,$raw,random_bytes(32),$now)['providers'][0]['provider']===$p,'Rotation interoperability');
        srBlocked(static fn()=>socialRenewStore($path,$item));srCheck(file_get_contents($path)===$raw,'No overwrite');unlink($path);
    }
    srCheck($cases===42,'42 provider cases');
    srBlocked(static fn()=>socialRenewGrant($cfg,'x',$now));
    $bad=$cfg;$bad['oauth_lifecycle']['automatic_candidate_enabled']=false;srBlocked(static fn()=>socialRenewGrant($bad,'youtube',$now));
    echo "Social renewal: 42 provider grant/token cases and private rotation interoperability PASS\n";
    if(($argv[1]??'')!=='--database')return;
    if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PORT')!=='3306'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
    $db=new PDO('mysql:host=127.0.0.1;port=3306;dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci','ci_only_not_real_password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $original=(int)$db->query('SELECT paused FROM growth_control WHERE id=1')->fetchColumn();$db->exec('UPDATE growth_control SET paused=1 WHERE id=1');
    try{
        foreach(['google-business','youtube'] as $p){
            $current=$cfg;$calls=0;$probes=0;
            $load=static function()use(&$current){return $current;};
            $exchange=static function($data)use(&$calls,$reply,$p,$db){srCheck(!$db->inTransaction()&&$data['grant_type']==='refresh_token','No transaction across exchange');++$calls;return $reply($p);};
            $probe=static function($provider,$target)use(&$probes,$db){srCheck(!$db->inTransaction()&&$target['token']===str_repeat('t',40),'Ephemeral target');++$probes;return true;};
            $path=socialRenewPath($tmp,$p);
            $db->exec('UPDATE growth_control SET paused=0 WHERE id=1');srBlocked(static fn()=>socialRenewRun($db,$load,$p,$exchange,$probe));$db->exec('UPDATE growth_control SET paused=1 WHERE id=1');srCheck($calls===0,'No exchange while resumed');
            $current['oauth_lifecycle']['providers'][$p]['expires_utc']=gmdate('Y-m-d\TH:i:s\Z',time()+7200);
            srCheck(socialRenewRun($db,$load,$p,$exchange,$probe)['status']==='not_due'&&$calls===0,'Skip not due');$current=$cfg;
            srBlocked(static fn()=>socialRenewRun($db,$load,$p,static function($data)use(&$current,$p,$reply){$current['oauth_lifecycle']['providers'][$p]['revoked']=true;return $reply($p);},$probe));
            srCheck($probes===0&&!file_exists($path),'Revocation stops probe');$current=$cfg;
            srBlocked(static fn()=>socialRenewRun($db,$load,$p,$exchange,static fn()=>false));srCheck(!file_exists($path),'Identity refusal');
            $result=socialRenewRun($db,$load,$p,$exchange,$probe);srCheck($result['status']==='candidate_created'&&!$result['activation_authorized']&&file_exists($path),'Candidate created');
            $before=$calls;srCheck(socialRenewRun($db,$load,$p,$exchange,$probe)['status']==='candidate_exists'&&$calls===$before,'Existing candidate no network');
            srCheck(!$db->inTransaction()&&$current===$cfg,'Active config unchanged');unlink($path);
        }
    }finally{$db->exec('UPDATE growth_control SET paused='.(int)$original.' WHERE id=1');}
    echo "Disposable scheduled renewal, pause, due, revoke, identity and no-overwrite checks PASS\n";
}finally{foreach(glob($tmp.'/*')?:[] as $f)unlink($f);rmdir($tmp);}
