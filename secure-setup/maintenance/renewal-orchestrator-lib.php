<?php
declare(strict_types=1);
require_once __DIR__.'/network-renew-lib.php';
function renewalFamily(string $provider):string{
    return match($provider){
        'google-business','youtube'=>'google',
        'facebook','instagram','threads'=>'meta',
        'linkedin','x'=>'network',
        default=>throw new InvalidArgumentException('Provider')
    };
}
function renewalGrant(array $cfg,string $p,int $now):array{
    return match(renewalFamily($p)){
        'google'=>socialRenewGrant($cfg,$p,$now),
        'meta'=>metaRenewGrant($cfg,$p,$now),
        'network'=>networkRenewGrant($cfg,$p,$now)
    };
}
function renewalEnabled(array $cfg):bool{
    return ($cfg['growth']['enabled']??false)===true&&($cfg['oauth_lifecycle']['enabled']??false)===true&&($cfg['oauth_lifecycle']['rotation_review_enabled']??false)===true&&($cfg['oauth_lifecycle']['orchestrator_enabled']??false)===true;
}
function renewalFiles(string $dir,string $provider):array{
    $family=renewalFamily($provider);$prefix=$dir.'/'.$provider;
    $paths=['candidate'=>$prefix.'-rotation-candidate.json'];
    if($family==='network')$paths+=['attempt'=>$prefix.'-exchange-attempt.json','response'=>$prefix.'-unverified-exchange.json'];
    $out=[];
    foreach($paths as $k=>$path){
        clearstatcache(true,$path);$st=@lstat($path);
        if($st===false){$out[$k]='absent';continue;}
        $out[$k]=(($st['mode']&0170000)===0100000&&($st['mode']&0777)===0600&&$st['nlink']===1&&$st['size']>0&&$st['size']<=65536)?'present':'unsafe';
    }
    return $out;
}
function renewalRow(array $cfg,string $provider,int $now,string $webroot):array{
    $policy=oauthPolicy($cfg,$provider,$now);$family=renewalFamily($provider);
    $row=['provider'=>$provider,'family'=>$family,'policy'=>$policy['state'],'active_deadline_utc'=>$policy['expires'],'state'=>'blocked','files'=>[]];
    $group=match($family){'google'=>'renewal','meta'=>'meta_renewal','network'=>'network_renewal'};
    $r=$cfg['oauth_lifecycle'][$group][$provider]??[];
    // Surface existing journals even if the grant expired or was revoked.
    if(is_string($r['candidate_directory']??null)){
        try{$dir=socialRenewDirectory($r['candidate_directory'],$webroot);$row['files']=renewalFiles($dir,$provider);}
        catch(Throwable $e){$row['state']='private_directory_unverified';return $row;}
        if(in_array('unsafe',$row['files'],true)){$row['state']='unsafe_private_file';return $row;}
        if($family==='network'&&in_array('present',$row['files'],true)){$row['state']='reconciliation_required';return $row;}
        if(($row['files']['candidate']??null)==='present'){$row['state']='candidate_present_unverified';return $row;}
    }
    if(!renewalEnabled($cfg)){$row['state']='orchestrator_disabled';return $row;}
    try{
        $g=renewalGrant($cfg,$provider,$now);
        socialRenewDirectory($g['directory'],$webroot);
        $due=metaRenewDate($g['policy']['expires_utc']);
        if($provider==='threads')$due=min($due,$g['provider_expiry']);
        $row['state']=$due<=$now+($family==='meta'?7*86400:900)?'due':'not_due';
    }catch(Throwable $e){$row['state']='grant_unverified';}
    return $row;
}
function renewalStatus(array $cfg,int $now,string $webroot):array{
    $rows=[];foreach(oauthProviders() as $p)$rows[]=renewalRow($cfg,$p,$now,$webroot);
    return ['version'=>1,'providers'=>$rows,'activation_authorized'=>false,'network_called'=>false];
}
function renewalPlan(array $cfg,string $raw,string $key,int $now,string $webroot):array{
    if(!renewalEnabled($cfg)||strlen($key)!==32)throw new RuntimeException('Orchestrator disabled');
    $providers=oauthBatchScope($raw);$rows=[];$bindings=[];
    foreach($providers as $p){
        $row=renewalRow($cfg,$p,$now,$webroot);
        if(!in_array($row['state'],['due','not_due'],true))throw new RuntimeException('Blocked provider');
        $g=renewalGrant($cfg,$p,$now);$rows[]=$row;
        // HMAC binds exact private grant, target, database and scheduling decision.
        $bindings[]=[$p,$g,$row];
    }
    $digest=hash_hmac('sha256',json_encode(['renewal-batch-v1',$cfg['database']??null,$bindings],JSON_THROW_ON_ERROR),$key);
    return ['version'=>1,'providers'=>$rows,'sha256'=>$digest,'activation_authorized'=>false];
}
function renewalBatch(PDO $db,callable $config,string $raw,string $key,string $expected,string $webroot,callable $run,?callable $clock=null):array{
    if($db->inTransaction()||!preg_match('/^[a-f0-9]{64}$/D',$expected))throw new InvalidArgumentException('Reviewed plan required');
    $clock=$clock??static fn()=>time();$start=$clock();$cfg=$config();$database=$cfg['database']??null;
    $lock='aaditechs-renewal-orchestrator';$q=$db->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);if((string)$q->fetchColumn()!=='1')return ['status'=>'busy','activation_authorized'=>false];
    try{
        $plan=renewalPlan($cfg,$raw,$key,$start,$webroot);
        if(!hash_equals($expected,$plan['sha256']))throw new RuntimeException('Reviewed plan changed');
        $last=$start;
        $check=static function()use($db,$config,$database,$clock,&$last):array{
            $now=$clock();if(!is_int($now)||$now<$last)throw new RuntimeException('Clock moved backward');$last=$now;
            $db->beginTransaction();
            try{oauthBatchPaused($db);$c=$config();if(!renewalEnabled($c)||($c['database']??null)!==$database)throw new RuntimeException('Configuration changed');$db->commit();return $c;}
            catch(Throwable $e){$db->rollBack();throw $e;}
        };
        $initial=[];foreach($plan['providers'] as $row){$p=$row['provider'];$initial[$p]=renewalGrant($cfg,$p,$start);}
        $out=[];
        foreach($plan['providers'] as $row){
            $p=$row['provider'];$current=$check();
            // Compare every not-yet-processed grant, not just the next provider.
            foreach($plan['providers'] as $future){
                $id=$future['provider'];if(isset($out[$id]))continue;
                if(renewalGrant($current,$id,$last)!==$initial[$id]||renewalRow($current,$id,$last,$webroot)!==$future)throw new RuntimeException('Remaining reviewed scope changed');
            }
            if($row['state']==='not_due'){$out[$p]=['provider'=>$p,'status'=>'not_due'];continue;}
            $result=$run($p,$initial[$p],$check);
            $after=$check();
            if(renewalGrant($after,$p,$last)!==$initial[$p])throw new RuntimeException('Grant changed after renewal');
            $state=$result['status']??null;
            if($state!=='candidate_created'){$out[$p]=['provider'=>$p,'status'=>'blocked'];break;}
            $observed=renewalRow($after,$p,$last,$webroot);
            if(!in_array($observed['state'],['candidate_present_unverified','reconciliation_required'],true)||($observed['files']['candidate']??null)!=='present')throw new RuntimeException('Candidate not observed');
            $out[$p]=['provider'=>$p,'status'=>'candidate_created'];
        }
        return ['status'=>count($out)===count($plan['providers'])&&!in_array('blocked',array_column($out,'status'),true)?'complete':'stopped','results'=>array_values($out),'activation_authorized'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    finally{$q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);$q->fetchColumn();}
}
