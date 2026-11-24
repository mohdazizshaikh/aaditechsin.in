<?php
declare(strict_types=1);
require_once __DIR__.'/oauth-policy.php';
require_once __DIR__.'/meta-core.php';
require_once __DIR__.'/network-core.php';
require_once __DIR__.'/instagram-core.php';
require_once __DIR__.'/upload-core.php';
function oauthTarget(array $cfg,string $provider):?array {
    return match($provider){
        'facebook','threads'=>metaTarget($cfg,$provider),
        'linkedin','x','google-business'=>networkTarget($cfg,$provider),
        'instagram'=>igTarget($cfg),
        'youtube'=>uploadTarget($cfg),
        default=>null
    };
}
function oauthTargetLabel(string $provider,array $p):string {
    return $provider==='instagram'?$p['page'].':'.$p['id']:$p['id'];
}
function oauthRecord(PDO $db,array $cfg,string $provider,array $target,string $state):void {
    if(!$db->inTransaction()||!in_array($state,['identity_verified','not_verified','error'],true))throw new InvalidArgumentException('Invalid health record');
    $policy=oauthPolicy($cfg,$provider);$current=oauthTarget($cfg,$provider);
    if(!in_array($policy['state'],['renew_soon','current'],true)||!$current||oauthTargetLabel($provider,$current)!==oauthTargetLabel($provider,$target))throw new RuntimeException('Unverified configuration');
    $label=oauthTargetLabel($provider,$current);
    $db->prepare('INSERT INTO growth_oauth_health(provider,generation,target_id,state,checked_at) VALUES(?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE generation=VALUES(generation),target_id=VALUES(target_id),state=VALUES(state),checked_at=VALUES(checked_at)')->execute([$provider,$policy['generation'],$label,$state]);
}
function oauthStatus(PDO $db,array $cfg,?int $now=null):array {
    $now=$now??time();$rows=[];
    $q=$db->query('SELECT provider,generation,target_id,state,checked_at FROM growth_oauth_health');
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$rows[$r['provider']]=$r;
    $out=[];
    foreach(oauthProviders() as $provider){
        $policy=oauthPolicy($cfg,$provider,$now);$target=oauthTarget($cfg,$provider);
        $r=$rows[$provider]??null;$health='not_checked';
        if(!$target)$health='disabled_or_blocked';
        elseif($r){
            $ts=strtotime($r['checked_at'].' UTC');
            if($r['generation']!==$policy['generation']||$r['target_id']!==oauthTargetLabel($provider,$target)||$ts===false||$ts>$now||$now-$ts>86400)$health='stale';
            else $health=$r['state'];
        }
        $out[]=['provider'=>$provider,'policy'=>$policy['state'],'expires'=>$policy['expires'],'health'=>$health];
    }return $out;
}
function oauthCommand(PDO $db,array $cfg,string $cmd):?string {
    if($cmd!=='/connections')return null;
    if(($cfg['growth']['enabled']??false)!==true||($cfg['oauth_lifecycle']['enabled']??false)!==true)return 'OAuth lifecycle checks disabled.';
    $lines=['Provider identity health (not publish permission):'];
    foreach(oauthStatus($db,$cfg) as $r)$lines[]=$r['provider'].': '.$r['policy'].' / '.$r['health'].($r['expires']?' / '.$r['expires']:'');
    $lines[]='Rotate privately; no tokens or refresh secrets in Telegram.';
    return implode("\n",$lines);
}
