<?php
declare(strict_types=1);
// Private transport to fixed official Bot API. No raw response/token logging.
function telegramReceipt(int $http,string $body,string $chat):array {
    try{$d=json_decode($body,true,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){$d=null;}
    if($http===200&&is_array($d)&&($d['ok']??false)===true&&is_int($d['result']['message_id']??null)&&$d['result']['message_id']>0&&
        is_int($d['result']['chat']['id']??null)&&(string)$d['result']['chat']['id']===$chat)
        return ['status'=>'accepted','receipt'=>(string)$d['result']['message_id'],'error'=>''];
    if($http>=400&&$http<500&&is_array($d)&&($d['ok']??null)===false)
        return ['status'=>'failed','receipt'=>null,'error'=>'provider_rejected'];
    return ['status'=>'unknown','receipt'=>null,'error'=>'unconfirmed_response'];
}
function telegramSend(string $token,string $chat,string $text):array {
    if(!preg_match('/^[0-9]{5,16}:[A-Za-z0-9_-]{20,128}$/D',$token)||!preg_match('/^(?:[1-9][0-9]{0,15}|-100[0-9]{6,15})$/D',$chat)||trim($text)===''||strlen($text)>4000)
        throw new InvalidArgumentException('Invalid private bot configuration or text');
    $ch=curl_init('https://api.telegram.org/bot'.$token.'/sendMessage');
    if(!$ch)return ['status'=>'unknown','receipt'=>null,'error'=>'transport_unconfirmed'];
    $body='';
    try{
        if(!curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['chat_id'=>$chat,'text'=>$text,'link_preview_options'=>['is_disabled'=>true]],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,
            CURLOPT_WRITEFUNCTION=>function($ch,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>65536)return 0;$body.=$chunk;return strlen($chunk);}])){
            return ['status'=>'unknown','receipt'=>null,'error'=>'transport_unconfirmed'];
        }
        if(curl_exec($ch)!==true)return ['status'=>'unknown','receipt'=>null,'error'=>'transport_unconfirmed'];
        return telegramReceipt((int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE),$body,$chat);
    }finally{curl_close($ch);}
}
