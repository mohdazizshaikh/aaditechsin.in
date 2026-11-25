<?php
declare(strict_types=1);
require_once __DIR__.'/search-console-read.php';
function googleCaptureGrant(array $cfg,string $provider,int $now):array{
    if($provider==='gmail')$p=gmailAutoGrant($cfg,$now);
    elseif($provider==='search-console'&&($cfg['seo']['search_console']['scheduled_capture_enabled']??false)===true)$p=scConfig($cfg,$now);
    else $p=null;
    if(!$p)throw new RuntimeException('Scheduled grant unavailable');
    return $p;
}
function googleEphemeralToken(array $p,string $scope,callable $exchange):array{
    $r=$exchange(['client_id'=>$p['client_id'],'client_secret'=>$p['client_secret'],'refresh_token'=>$p['refresh_token'],'grant_type'=>'refresh_token']);
    if(!is_array($r)||array_key_exists('error',$r)||array_key_exists('refresh_token',$r)||($r['token_type']??null)!=='Bearer'||($r['scope']??null)!==$scope||!is_string($r['access_token']??null)||!preg_match('/^[A-Za-z0-9._~+\/=-]{20,4096}$/D',$r['access_token'])||!is_int($r['expires_in']??null)||$r['expires_in']<=660||$r['expires_in']>86400)throw new RuntimeException('Unverified grant');
    return ['token'=>$r['access_token'],'lifetime'=>$r['expires_in']];
}
// At most one scheduled capture per provider per DB at a time; no token persistence.
function googleCaptureRun(PDO $db,callable $config,string $provider,callable $exchange,callable $read,?callable $clock=null):array{
    if($db->inTransaction()||!in_array($provider,['gmail','search-console'],true))throw new InvalidArgumentException('Explicit provider required');
    $clock=$clock??static fn()=>new DateTimeImmutable('now');
    $lock='aaditechs-google-capture-'.$provider;
    $q=$db->prepare('SELECT GET_LOCK(?,0)');$q->execute([$lock]);if((string)$q->fetchColumn()!=='1')return ['status'=>'busy'];
    try{
        $start=$clock();$cfg=$config();$p=googleCaptureGrant($cfg,$provider,$start->getTimestamp());
        $fresh=static function()use($config,$clock,$p,$provider):array{
            $cfg=$config();if(googleCaptureGrant($cfg,$provider,$clock()->getTimestamp())!==$p)throw new RuntimeException('Grant changed');return $cfg;
        };
        $db->beginTransaction();creativeActive($db);$fresh();
        $table=$provider==='gmail'?'growth_gmail_snapshot':'growth_search_console';
        $old=$db->query('SELECT generation,captured_at'.($provider==='search-console'?',property_hash,start_day,end_day':'').' FROM '.$table.' WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        $window=$provider==='search-console'?scWindow($start):null;
        $ts=$old?strtotime($old['captured_at'].' UTC'):false;
        $same=$old&&$old['generation']===$p['generation']&&($provider==='gmail'||($old['property_hash']===hash('sha256',$p['property'])&&$old['start_day']===$window['start']&&$old['end_day']===$window['end']));
        if($same&&$ts!==false&&$ts<=$start->getTimestamp()&&$start->getTimestamp()-$ts<14400){$db->rollBack();return ['status'=>'not_due'];}
        $db->commit();
        $scope=$provider==='gmail'?'https://www.googleapis.com/auth/gmail.metadata':'https://www.googleapis.com/auth/webmasters.readonly';
        $token=googleEphemeralToken($p,$scope,$exchange);
        // Recheck between refresh and provider data access, and before committing.
        $fresh();$db->beginTransaction();creativeActive($db);$db->commit();
        if($provider==='gmail'){
            $response=gmailCollect(['mailbox'=>$p['mailbox'],'token'=>$token['token']],$read);
        }else{
            $site=$read($p,$token['token'],'site',null);
            if(($site['siteUrl']??null)!==$p['property']||!in_array($site['permissionLevel']??null,['siteOwner','siteFullUser','siteRestrictedUser'],true))throw new RuntimeException('Property unverified');
            $fresh();$db->beginTransaction();creativeActive($db);$db->commit();
            $response=$read($p,$token['token'],'query',['startDate'=>$window['start'],'endDate'=>$window['end'],'dimensions'=>['date'],'type'=>'web','dataState'=>'final','aggregationType'=>'byProperty','rowLimit'=>7]);
            scParse($response,$window);
        }
        $end=$clock();
        if($end<$start||$end->getTimestamp()>=$start->getTimestamp()+$token['lifetime']-60)throw new RuntimeException('Token window elapsed');
        $db->beginTransaction();creativeActive($db);$cfg=$fresh();
        if($provider==='gmail')gmailCapture($db,$cfg,$response,$end,true);
        else scCapture($db,$cfg,$window,$response,$end);
        $db->commit();return ['status'=>'captured','provider'=>$provider,'token_persisted'=>false];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    finally{$q=$db->prepare('SELECT RELEASE_LOCK(?)');$q->execute([$lock]);$q->fetchColumn();}
}
