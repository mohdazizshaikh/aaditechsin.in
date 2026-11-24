<?php
declare(strict_types=1);
require_once __DIR__.'/telegram-send.php';
function tgMediaRequest(array $p,string $method,array $data):array {
    if(PHP_SAPI!=='cli'||!function_exists('curl_init')||!preg_match('/^[0-9]{5,16}:[A-Za-z0-9_-]{20,128}$/D',$p['token']??'')||!in_array($method,['getMe','getChat','getChatMember','sendPhoto','sendVideo'],true))throw new RuntimeException('Invalid request');
    $ch=curl_init('https://api.telegram.org/bot'.$p['token'].'/'.$method);$body='';
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$data,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>120,CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>65536)return 0;$body.=$chunk;return strlen($chunk);}]);
    try{$ok=curl_exec($ch);return ['http'=>$ok===false?0:(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE),'body'=>$body];}finally{curl_close($ch);}
}
function tgMediaRead(array $p,string $method,array $data,callable $request):?array{
    $r=$request($p,$method,$data);$j=json_decode($r['body']??'',true,32);
    return ($r['http']??0)===200&&($j['ok']??false)===true&&is_array($j['result']??null)?$j['result']:null;
}
function tgMediaIdentity(array $p,callable $request):bool{
    $me=tgMediaRead($p,'getMe',[],$request);$id=$me['id']??null;
    if(!is_int($id)||$id<=0||($me['is_bot']??false)!==true)return false;
    $chat=tgMediaRead($p,'getChat',['chat_id'=>$p['id']],$request);
    if(!is_int($chat['id']??null)||(string)$chat['id']!==$p['id']||($chat['type']??null)!=='channel')return false;
    $member=tgMediaRead($p,'getChatMember',['chat_id'=>$p['id'],'user_id'=>(string)$id],$request);
    return ($member['user']['id']??null)===$id&&($member['status']??null)==='administrator'&&($member['can_post_messages']??false)===true;
}
function tgMediaSend(array $p,array $row,string $bytes,callable $request):array{
    $unknown=['status'=>'unknown','receipt'=>null,'error'=>'transport_unconfirmed'];
    if($p['id']!==$row['target_id']||!in_array($row['asset_kind'],['photo','video'],true)||strlen($bytes)!==(int)$row['byte_count']||strlen($bytes)>($row['asset_kind']==='photo'?10:20)*1024*1024||!hash_equals($row['asset_hash'],hash('sha256',$bytes))||trim($row['caption'])===''||strlen($row['caption'])>900)return ['status'=>'failed','receipt'=>null,'error'=>'asset_mismatch'];
    try{
        if(!tgMediaIdentity($p,$request))return ['status'=>'failed','receipt'=>null,'error'=>'identity_unverified'];
        $photo=$row['asset_kind']==='photo';
        $file=new CURLStringFile($bytes,$photo?'creative.png':'creative.mp4',$photo?'image/png':'video/mp4');
        $r=$request($p,$photo?'sendPhoto':'sendVideo',['chat_id'=>$p['id'],'caption'=>$row['caption'],$photo?'photo':'video'=>$file]);
        return telegramReceipt($r['http'],$r['body'],$p['id']);
    }catch(Throwable $e){return $unknown;}
}
