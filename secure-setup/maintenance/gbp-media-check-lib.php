<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/network-core.php';
require_once __DIR__.'/network-send.php';
function gbpMediaReadTarget(array $cfg):?array{
    if(($cfg['media_upload']['gbp_media_check_enabled']??false)!==true)return null;
    return networkTarget($cfg,'google-business');
}
function gbpMediaReadCheck(array $p,callable $request):bool{
    if(($p['provider']??null)!=='google-business'||!is_string($p['id']??null)||!preg_match('/^[1-9][0-9]{0,30}:[1-9][0-9]{0,30}$/D',$p['id']))return false;
    [$account,$location]=explode(':',$p['id']);
    $prefix='accounts/'.$account.'/locations/'.$location.'/media';
    $r=$request($p,'GET','v4/'.$prefix,['pageSize'=>1]);
    if(($r['http']??null)!==200||!is_array($r['json']??null)||isset($r['json']['error']))return false;
    $items=$r['json']['mediaItems']??[];
    if(!is_array($items)||!array_is_list($items)||count($items)>1)return false;
    foreach($items as $item){
        if(!is_array($item)||!is_string($item['name']??null)||!preg_match('#^'.preg_quote($prefix,'#').'/[A-Za-z0-9_-]{1,160}$#D',$item['name']))return false;
    }
    return true; // scoped read evidence only; not upload permission or verified ownership
}
