<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/meta-renew-lib.php';
function mrCheck(bool $ok,string $m):void{if(!$ok)throw new LogicException($m);}
function mrBlocked(callable $f):void{try{$f();}catch(RuntimeException|InvalidArgumentException|JsonException $e){return;}throw new LogicException('Expected refusal');}
$now=time();$date=static fn(int $t)=>gmdate('Y-m-d\TH:i:s\Z',$t);
$tmp=sys_get_temp_dir().'/aaditechs-meta-renew-'.bin2hex(random_bytes(8));mkdir($tmp,0700);
$cfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'media'=>['enabled'=>true],'social'=>['enabled'=>true,'meta'=>['enabled'=>true,'facebook'=>['enabled'=>true,'account_id'=>'123','access_token'=>str_repeat('s',40),'api_version'=>'v27.0'],'threads'=>['enabled'=>true,'account_id'=>'789','access_token'=>str_repeat('s',40),'api_version'=>'v1.0']]],'media_upload'=>['enabled'=>true,'instagram'=>['enabled'=>true,'page_id'=>'123','account_id'=>'456','access_token'=>str_repeat('s',40),'api_version'=>'v27.0']],'oauth_lifecycle'=>['enabled'=>true,'rotation_review_enabled'=>true,'meta_candidate_enabled'=>true,'providers'=>[],'meta_renewal'=>[]]];
foreach(['facebook'=>'123','instagram'=>'123:456','threads'=>'789'] as $p=>$id){
    $cfg['oauth_lifecycle']['providers'][$p]=['generation'=>str_repeat('a',32),'expires_utc'=>$date($now+86400),'revoked'=>false];
    $cfg['oauth_lifecycle']['meta_renewal'][$p]=['enabled'=>true,'authorized'=>true,'generation'=>str_repeat('b',32),'target_id'=>$id,'review_expires_utc'=>$date($now+30*86400),'candidate_directory'=>$tmp,'long_lived_reviewed'=>true,'active_generation'=>str_repeat('a',32),'issued_utc'=>$date($now-10*86400),'provider_expires_utc'=>$date($now+86400),'user_access_token'=>str_repeat('u',40),'user_expires_utc'=>$date($now+15*86400),'user_id'=>'999','app_id'=>'111','app_secret'=>str_repeat('c',32),'reviewed_scopes'=>$p==='instagram'?['instagram_basic','instagram_content_publish','pages_read_engagement','pages_show_list']:['pages_manage_posts','pages_read_engagement','pages_show_list']];
}
function mrReply(array $g,string $op,array $data,int $now):array{
    if($op==='threads-refresh')return ['token_type'=>'bearer','access_token'=>str_repeat('t',40),'expires_in'=>60*86400];
    if($op==='page')return ['id'=>$data['page'],'access_token'=>str_repeat('t',40),'instagram_business_account'=>['id'=>'456']];
    $user=$data['token']===$g['user_token'];
    return ['data'=>['is_valid'=>true,'app_id'=>$g['app_id'],'type'=>$user?'USER':'PAGE','user_id'=>$g['user_id'],'expires_at'=>$user?$now+12*86400:0,'data_access_expires_at'=>$now+10*86400,'scopes'=>$g['scopes']]];
}
try{
    $cases=0;
    foreach(['facebook','instagram','threads'] as $p){
        $g=metaRenewGrant($cfg,$p,$now);$calls=[];
        $req=static function($grant,$op,$data)use(&$calls,$now){$calls[]=$op;return mrReply($grant,$op,$data,$now);};
        $item=metaRenewCandidate($g,$req,static fn()=>null,$now);
        mrCheck($item['provider']===$p&&$item['access_token']===str_repeat('t',40)&&$calls===($p==='threads'?['threads-refresh']:['debug','page','debug']),'Exact route sequence');++$cases;
        foreach([['enabled'=>false],['authorized'=>false],['target_id'=>'other'],['generation'=>str_repeat('0',32)],['review_expires_utc'=>$date($now+660)],['review_expires_utc'=>$date($now+91*86400)]] as $patch){
            $bad=$cfg;$bad['oauth_lifecycle']['meta_renewal'][$p]=array_replace($bad['oauth_lifecycle']['meta_renewal'][$p],$patch);
            mrBlocked(static fn()=>metaRenewGrant($bad,$p,$now));++$cases;
        }
        $bad=$cfg;$bad['oauth_lifecycle']['providers'][$p]['revoked']=true;mrBlocked(static fn()=>metaRenewGrant($bad,$p,$now));++$cases;
        if($p==='threads'){
            foreach([['long_lived_reviewed'=>false],['issued_utc'=>$date($now-3600)],['provider_expires_utc'=>$date($now-1)],['active_generation'=>str_repeat('d',32)]] as $patch){
                $bad=$cfg;$bad['oauth_lifecycle']['meta_renewal'][$p]=array_replace($bad['oauth_lifecycle']['meta_renewal'][$p],$patch);mrBlocked(static fn()=>metaRenewGrant($bad,$p,$now));++$cases;
            }
            foreach([['token_type'=>'wrong'],['expires_in'=>720],['expires_in'=>62*86400],['access_token'=>str_repeat('s',40)],['refresh_token'=>'unexpected']] as $patch){mrBlocked(static fn()=>metaRenewCandidate($g,static fn($a,$b,$c)=>array_replace(mrReply($a,$b,$c,$now),$patch),static fn()=>null,$now));++$cases;}
        }else{
            foreach([['app_id'=>'wrong'],['user_id'=>'wrong'],['is_valid'=>false],['type'=>'PAGE'],['scopes'=>[]],['expires_at'=>$now+1],['data_access_expires_at'=>$now+1]] as $patch){mrBlocked(static fn()=>metaRenewCandidate($g,static function($a,$b,$c)use($now,$patch){$r=mrReply($a,$b,$c,$now);if($b==='debug')$r['data']=array_replace($r['data'],$patch);return $r;},static fn()=>null,$now));++$cases;}
            foreach([['id'=>'999'],['access_token'=>'bad'],['access_token'=>str_repeat('s',40)]] as $patch){mrBlocked(static fn()=>metaRenewCandidate($g,static function($a,$b,$c)use($now,$patch){$r=mrReply($a,$b,$c,$now);return $b==='page'?array_replace($r,$patch):$r;},static fn()=>null,$now));++$cases;}
        }
        $path=$tmp.'/'.$p.'-rotation-candidate.json';socialRenewStore($path,$item);
        mrCheck(oauthRotationPlan($cfg,file_get_contents($path),random_bytes(32),$now)['providers'][0]['provider']===$p,'Compatible rotation');unlink($path);
    }
    mrCheck($cases===53,'Case count');
    echo "Meta renewal: $cases provider grant and response cases PASS\n";
    if(($argv[1]??'')!=='--database')return;
    if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PORT')!=='3306'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
    $db=new PDO('mysql:host=127.0.0.1;port=3306;dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci','ci_only_not_real_password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
    $original=(int)$db->query('SELECT paused FROM growth_control WHERE id=1')->fetchColumn();$db->exec('UPDATE growth_control SET paused=1 WHERE id=1');
    try{
        foreach(['facebook','instagram','threads'] as $p){
            $current=$cfg;$calls=0;$probes=0;$load=static function()use(&$current){return $current;};
            $req=static function($g,$op,$data)use(&$calls,$now,$db){mrCheck(!$db->inTransaction(),'No transaction across provider call');++$calls;return mrReply($g,$op,$data,$now);};
            $probe=static function($provider,$target)use(&$probes,$db){mrCheck(!$db->inTransaction()&&$target['token']===str_repeat('t',40),'Candidate probe');++$probes;return true;};
            $path=$tmp.'/'.$p.'-rotation-candidate.json';
            $db->exec('UPDATE growth_control SET paused=0 WHERE id=1');mrBlocked(static fn()=>metaRenewRun($db,$load,$p,$req,$probe));$db->exec('UPDATE growth_control SET paused=1 WHERE id=1');mrCheck($calls===0,'Pause gate');
            $revoking=static function($g,$op,$data)use(&$current,$p,$now){$current['oauth_lifecycle']['providers'][$p]['revoked']=true;return mrReply($g,$op,$data,$now);};
            mrBlocked(static fn()=>metaRenewRun($db,$load,$p,$revoking,$probe));mrCheck($probes===0&&!file_exists($path),'Revoke gate');$current=$cfg;
            mrBlocked(static fn()=>metaRenewRun($db,$load,$p,$req,static fn()=>false));mrCheck(!file_exists($path),'Identity refusal');
            $r=metaRenewRun($db,$load,$p,$req,$probe);mrCheck($r['status']==='candidate_created'&&!$r['activation_authorized'],'Candidate creation');
            $before=$calls;mrCheck(metaRenewRun($db,$load,$p,$req,$probe)['status']==='candidate_exists'&&$calls===$before,'No repeated call');
            mrCheck($current===$cfg&&!$db->inTransaction(),'Active credentials unchanged');unlink($path);
        }
    }finally{$db->exec('UPDATE growth_control SET paused='.(int)$original.' WHERE id=1');}
    echo "Disposable Meta renewal scheduling, pause, revoke and private candidate checks PASS\n";
}finally{foreach(glob($tmp.'/*')?:[] as $f)unlink($f);rmdir($tmp);}
