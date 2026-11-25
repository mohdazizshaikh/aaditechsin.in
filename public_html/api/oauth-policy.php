<?php
declare(strict_types=1);
function oauthProviders():array{return ['facebook','threads','linkedin','x','google-business','instagram','youtube'];}
function oauthPolicy(array $cfg,string $provider,?int $now=null):array {
    $now=$now??time();$policy=$cfg['oauth_lifecycle']??[];
    if(($policy['enabled']??false)!==true)return ['state'=>'not_enforced','generation'=>null,'expires'=>null];
    if(!in_array($provider,oauthProviders(),true))return ['state'=>'invalid','generation'=>null,'expires'=>null];
    $p=$policy['providers'][$provider]??[];
    if(($p['revoked']??false)===true)return ['state'=>'revoked','generation'=>null,'expires'=>null];
    $generation=$p['generation']??null;$expires=$p['expires_utc']??null;
    if(!is_string($generation)||!preg_match('/^[a-f0-9]{32}$/D',$generation)||!is_string($expires))return ['state'=>'unverified','generation'=>null,'expires'=>null];
    $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$expires,new DateTimeZone('UTC'));
    if(!$date||$date->format('Y-m-d\TH:i:s\Z')!==$expires)return ['state'=>'unverified','generation'=>null,'expires'=>null];
    $ts=$date->getTimestamp();
    // Conservative local review deadline also required for tokens without a provider expiry.
    $state=$ts<=$now?'expired':($ts<=$now+600?'too_close':($ts<=$now+7*86400?'renew_soon':'current'));
    return ['state'=>$state,'generation'=>$generation,'expires'=>$expires];
}
function oauthAllowed(array $cfg,string $provider,?int $now=null):bool {
    return in_array(oauthPolicy($cfg,$provider,$now)['state'],['not_enforced','renew_soon','current'],true);
}
