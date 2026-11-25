<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/oauth-rotation-lib.php';
function orCheck(bool $ok,string $msg):void{if(!$ok)throw new LogicException($msg);}
function orBlocked(callable $f):void{try{$f();}catch(RuntimeException|InvalidArgumentException|JsonException $e){return;}throw new LogicException('Expected refusal');}
$now=time();$key=oauthRotationKey(str_repeat('a',64));$cfg=[
'growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'media'=>['enabled'=>true],
'social'=>['enabled'=>true,'meta'=>['enabled'=>true],'network'=>['enabled'=>true]],
'media_upload'=>['enabled'=>true],
'oauth_lifecycle'=>['enabled'=>true,'rotation_review_enabled'=>true,'providers'=>[]]];
$items=[];$i=0;
foreach(oauthProviders() as $provider){
    $i++;$target=['enabled'=>true,'access_token'=>str_repeat('s',40)];
    if($provider==='youtube')$target['channel_id']='UC'.str_repeat('a',22);
    else $target['account_id']=$provider==='google-business'?'123:456':'123';
    if($provider==='instagram')$target['page_id']='456';
    if(in_array($provider,['facebook','threads','instagram'],true))$target['api_version']='v27.0';
    if($provider==='linkedin')$target['api_version']='202611';
    oauthRotationSet($cfg,oauthRotationPath($provider),$target);
    $cfg['oauth_lifecycle']['providers'][$provider]=['generation'=>str_pad(dechex($i),32,'a',STR_PAD_LEFT),'expires_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now-3600),'revoked'=>false];
    $items[]=['provider'=>$provider,'access_token'=>str_repeat('t',40),'generation'=>str_pad(dechex($i),32,'b',STR_PAD_LEFT),'expires_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now+86400)];
}
$encode=static fn($items)=>json_encode(['version'=>1,'items'=>$items],JSON_THROW_ON_ERROR);
$raw=$encode($items);$plan=oauthRotationPlan($cfg,$raw,$key,$now);
orCheck(count($plan['providers'])===7&&!$plan['activation_authorized']&&!str_contains(json_encode($plan),str_repeat('t',40)),'Full private plan');
$cases=0;
foreach($items as $item){
    $p=$item['provider'];$one=$encode([$item]);
    orCheck(count(oauthRotationPlan($cfg,$one,$key,$now)['providers'])===1,'Provider valid');++$cases;
    foreach([
        ['provider'=>'unknown'],['access_token'=>str_repeat('s',40)],['access_token'=>'bad'],
        ['generation'=>$cfg['oauth_lifecycle']['providers'][$p]['generation']],['generation'=>str_repeat('0',32)],
        ['expires_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now+600)],['expires_utc'=>gmdate('Y-m-d\TH:i:s\Z',$now+91*86400)],
        ['expires_utc'=>'2026-02-30T00:00:00Z'],['extra'=>true]
    ] as $patch){orBlocked(static fn()=>oauthRotationPlan($cfg,$encode([array_replace($item,$patch)]),$key,$now));++$cases;}
    $revoked=$cfg;$revoked['oauth_lifecycle']['providers'][$p]['revoked']=true;
    orBlocked(static fn()=>oauthRotationPlan($revoked,$one,$key,$now));++$cases;
    $changed=$cfg;$target=oauthRotationGet($changed,oauthRotationPath($p));$target['access_token']=str_repeat('u',40);oauthRotationSet($changed,oauthRotationPath($p),$target);
    orCheck(oauthRotationPlan($changed,$one,$key,$now)['sha256']!==oauthRotationPlan($cfg,$one,$key,$now)['sha256'],'Old credential binding');++$cases;
}
orCheck($cases===84,'84 provider-specific cases');
orBlocked(static fn()=>oauthRotationPlan($cfg,$encode([$items[0],$items[0]]),$key,$now));
orBlocked(static fn()=>oauthRotationPlan($cfg,str_replace('"version":1','"version":0,"version":1',$raw),$key,$now));
$disabled=$cfg;$disabled['oauth_lifecycle']['rotation_review_enabled']=false;
orBlocked(static fn()=>oauthRotationPlan($disabled,$raw,$key,$now));
$calls=0;$probe=static function($p,$target)use(&$calls){++$calls;return $target['token']===str_repeat('t',40);};
orBlocked(static fn()=>oauthRotationVerify(static fn()=>$cfg,$raw,$key,str_repeat('0',64),static fn()=>true,$probe));
orBlocked(static fn()=>oauthRotationVerify(static fn()=>$cfg,$raw,$key,$plan['sha256'],static fn()=>false,$probe));
orCheck($calls===0,'No probe before gate');
$r=oauthRotationVerify(static fn()=>$cfg,$raw,$key,$plan['sha256'],static fn()=>true,$probe);
orCheck($r['complete']&&$r['checked']===7&&$calls===7&&!$r['activation_authorized'],'All identities synthetic');
$r=oauthRotationVerify(static fn()=>$cfg,$raw,$key,$plan['sha256'],static fn()=>true,static fn()=>false);
orCheck(!$r['complete']&&$r['checked']===1,'Stop on refusal');
$pause=true;
orBlocked(static function()use($cfg,$raw,$key,$plan,&$pause){oauthRotationVerify(static fn()=>$cfg,$raw,$key,$plan['sha256'],static function()use(&$pause){return $pause;},static function()use(&$pause){$pause=false;return true;});});
echo "OAuth rotation: 84 provider-specific cases and reviewed mock identity checks PASS\n";
