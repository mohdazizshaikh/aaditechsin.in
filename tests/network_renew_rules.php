<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/network-renew-lib.php';
function nrnCheck(bool $v,string $m):void{if(!$v)throw new LogicException($m);}
function nrnBlocked(callable $f):void{try{$f();}catch(RuntimeException|InvalidArgumentException|JsonException $e){return;}throw new LogicException('Expected refusal');}
$now=time();$date=static fn(int $t)=>gmdate('Y-m-d\TH:i:s\Z',$t);
$tmp=sys_get_temp_dir().'/aaditechs-network-renew-'.bin2hex(random_bytes(8));mkdir($tmp,0700);
$cfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'social'=>['enabled'=>true,'network'=>['enabled'=>true,'linkedin'=>['enabled'=>true,'account_id'=>'123','access_token'=>str_repeat('s',40),'api_version'=>'202611'],'x'=>['enabled'=>true,'account_id'=>'456','access_token'=>str_repeat('s',40)]]],'oauth_lifecycle'=>['enabled'=>true,'rotation_review_enabled'=>true,'network_candidate_enabled'=>true,'providers'=>[],'network_renewal'=>[]]];
foreach(['linkedin'=>'123','x'=>'456'] as $p=>$id){
    $cfg['oauth_lifecycle']['providers'][$p]=['generation'=>str_repeat('a',32),'expires_utc'=>$date($now-3600),'revoked'=>false];
    $cfg['oauth_lifecycle']['network_renewal'][$p]=['enabled'=>true,'authorized'=>true,'confidential_client_reviewed'=>true,'programmatic_refresh_reviewed'=>true,'generation'=>str_repeat('b',32),'target_id'=>$id,'review_expires_utc'=>$date($now+30*86400),'refresh_expires_utc'=>$date($now+20*86400),'client_id'=>str_repeat('i',20),'client_secret'=>str_repeat('c',20),'refresh_token'=>str_repeat('r',40),'reviewed_scopes'=>$p==='linkedin'?['rw_organization_admin','w_organization_social']:['offline.access','tweet.read','tweet.write','users.read','media.write'],'candidate_directory'=>$tmp];
}
function nrnReply(array $g):array{
    $r=['access_token'=>str_repeat('t',40),'refresh_token'=>str_repeat('n',40),'expires_in'=>3600,'scope'=>implode(' ',$g['scopes'])];
    if($g['provider']==='linkedin'){$r['refresh_token_expires_in']=10*86400;$r['scope']=urlencode($r['scope']);}
    else $r['token_type']='bearer';
    return $r;
}
try{
    $cases=0;
    foreach(['linkedin','x'] as $p){
        $g=networkRenewGrant($cfg,$p,$now);$r=nrnReply($g);
        $out=networkRenewResponse($g,$r,$now);nrnCheck($out['item']['expires_utc']===$date($now+3540),'Expiry');++$cases;
        foreach([['enabled'=>false],['authorized'=>false],['confidential_client_reviewed'=>false],['target_id'=>'wrong'],['generation'=>str_repeat('0',32)],['refresh_expires_utc'=>$date($now+660)],['review_expires_utc'=>$date($now+91*86400)],['refresh_token'=>'bad'],['reviewed_scopes'=>[]],['reviewed_scopes'=>array_merge($g['scopes'],['unknown'])],['reviewed_scopes'=>array_merge($g['scopes'],[$g['scopes'][0]])]] as $patch){
            $bad=$cfg;$bad['oauth_lifecycle']['network_renewal'][$p]=array_replace($bad['oauth_lifecycle']['network_renewal'][$p],$patch);nrnBlocked(static fn()=>networkRenewGrant($bad,$p,$now));++$cases;
        }
        $bad=$cfg;$bad['oauth_lifecycle']['providers'][$p]['revoked']=true;nrnBlocked(static fn()=>networkRenewGrant($bad,$p,$now));++$cases;
        foreach([['access_token'=>'bad'],['access_token'=>str_repeat('s',40)],['refresh_token'=>null],['expires_in'=>720],['expires_in'=>'3600'],['scope'=>''],['scope'=>$r['scope'].' extra'],['token_type'=>'wrong'],['error'=>null]] as $patch){nrnBlocked(static fn()=>networkRenewResponse($g,array_replace($r,$patch),$now));++$cases;}
        if($p==='x'){nrnBlocked(static fn()=>networkRenewResponse($g,array_replace($r,['refresh_token'=>$g['refresh_token']]),$now));++$cases;}
        else{nrnBlocked(static fn()=>networkRenewResponse($g,array_replace($r,['refresh_token_expires_in'=>660]),$now));++$cases;}
        $path=$tmp.'/'.$p.'-rotation-candidate.json';socialRenewStore($path,$out['item']);
        nrnCheck(oauthRotationPlan($cfg,file_get_contents($path),random_bytes(32),$now)['providers'][0]['provider']===$p,'Rotation compatibility');unlink($path);
    }
    nrnCheck($cases===46,'Case count');
    echo "Network renewal: $cases grant/response cases and rotation compatibility PASS\n";
    if(($argv[1]??'')!=='--database')return;
    if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PORT')!=='3306'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
    $db=new PDO('mysql:host=127.0.0.1;port=3306;dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci','ci_only_not_real_password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $original=(int)$db->query('SELECT paused FROM growth_control WHERE id=1')->fetchColumn();$db->exec('UPDATE growth_control SET paused=1 WHERE id=1');
    try{
        foreach(['linkedin','x'] as $p){
            $calls=0;$probes=0;$current=$cfg;$load=static function()use(&$current){return $current;};
            $marker=$tmp.'/'.$p.'-exchange-attempt.json';$receipt=$tmp.'/'.$p.'-unverified-exchange.json';$candidate=$tmp.'/'.$p.'-rotation-candidate.json';
            $clean=static function()use($marker,$receipt,$candidate){foreach([$marker,$receipt,$candidate] as $f)if(file_exists($f))unlink($f);};
            $exchange=static function($g)use(&$calls,$db,$marker){nrnCheck(!$db->inTransaction()&&file_exists($marker),'Marker before network');++$calls;return nrnReply($g);};
            $probe=static function($provider,$target,$boundary)use(&$probes,$db){nrnCheck(!$db->inTransaction()&&$target['token']===str_repeat('t',40),'Probe');$boundary();++$probes;return true;};
            $db->exec('UPDATE growth_control SET paused=0 WHERE id=1');nrnBlocked(static fn()=>networkRenewRun($db,$load,$p,$exchange,$probe));$db->exec('UPDATE growth_control SET paused=1 WHERE id=1');nrnCheck($calls===0&&!file_exists($marker),'Pause before exchange');
            nrnBlocked(static fn()=>networkRenewRun($db,$load,$p,static fn()=>throw new RuntimeException('Lost response'),$probe));
            nrnCheck(file_exists($marker)&&!file_exists($receipt),'Uncertain exchange journal');nrnCheck(networkRenewRun($db,$load,$p,$exchange,$probe)['status']==='reconciliation_required'&&$calls===0,'No retry');$clean();
            $revoking=static function($g)use(&$current,$p){$current['oauth_lifecycle']['providers'][$p]['revoked']=true;return nrnReply($g);};
            nrnBlocked(static fn()=>networkRenewRun($db,$load,$p,$revoking,$probe));nrnCheck(file_exists($receipt)&&!file_exists($candidate)&&$probes===0,'Preserve rotated response after revocation');$current=$cfg;$clean();
            nrnBlocked(static fn()=>networkRenewRun($db,$load,$p,$exchange,static fn()=>false));nrnCheck(file_exists($receipt)&&!file_exists($candidate),'Wrong identity retained only as unverified');$clean();
            $result=networkRenewRun($db,$load,$p,$exchange,$probe);
            nrnCheck($result['status']==='candidate_created'&&!$result['activation_authorized']&&file_exists($candidate),'Candidate');
            foreach([$marker,$receipt,$candidate] as $f)nrnCheck((fileperms($f)&0777)===0600&&lstat($f)['nlink']===1,'Private single link');
            nrnCheck(json_decode(file_get_contents($receipt),true)['response']['refresh_token']===str_repeat('n',40),'Rotated token retained privately');
            $before=$calls;nrnCheck(networkRenewRun($db,$load,$p,$exchange,$probe)['status']==='reconciliation_required'&&$calls===$before,'Repeat blocked');
            nrnCheck($current===$cfg&&!$db->inTransaction(),'No active update');$clean();
        }
    }finally{$db->exec('UPDATE growth_control SET paused='.(int)$original.' WHERE id=1');}
    echo "Disposable network renewal: pause, uncertain response, revoked grant, private reconciliation and no retry PASS\n";
}finally{foreach(glob($tmp.'/*')?:[] as $f)unlink($f);rmdir($tmp);}
