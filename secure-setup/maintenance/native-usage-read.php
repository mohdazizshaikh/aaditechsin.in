<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/native-usage-core.php';
function nativeUsageRequest(array $p,string $day):array{
    if(PHP_SAPI!=='cli'||!function_exists('curl_init'))throw new RuntimeException('Transport unavailable');
    $start=strtotime($day.' UTC');$wa=$p['source']==='whatsapp';
    if($wa){
        $field='id,analytics.start('.$start.').end('.($start+86400).').granularity(DAY){granularity,data_points{start,end,sent,delivered}}';
        $url='https://graph.facebook.com/'.$p['api_version'].'/'.$p['account'].'?fields='.rawurlencode($field);
    }else{
        $category=$p['source']==='twilio-sms'?'sms-inbound':'calls-inbound';
        $url='https://api.twilio.com/2010-04-01/Accounts/'.$p['account'].'/Usage/Records/Daily.json?'.http_build_query(['Category'=>$category,'StartDate'=>$day,'EndDate'=>$day,'PageSize'=>2],'','&',PHP_QUERY_RFC3986);
    }
    $body='';$ch=curl_init($url);
    try{
        $opts=[CURLOPT_HTTPGET=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_PROXY=>'',CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>$wa?['Accept: application/json','Authorization: Bearer '.$p['token']]:['Accept: application/json'],CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>32768)return 0;$body.=$chunk;return strlen($chunk);}];
        if(!$wa){$opts[CURLOPT_HTTPAUTH]=CURLAUTH_BASIC;$opts[CURLOPT_USERPWD]=$p['key'].':'.$p['secret'];}
        if(!curl_setopt_array($ch,$opts)||curl_exec($ch)!==true||curl_getinfo($ch,CURLINFO_RESPONSE_CODE)!==200)throw new RuntimeException('Read unavailable');
        $r=json_decode($body,true,24,JSON_THROW_ON_ERROR);if(!is_array($r))throw new RuntimeException('Invalid response');return $r;
    }finally{curl_close($ch);}
}
function nativeUsageParse(array $p,string $day,array $r):array{
    if(isset($r['error'])||isset($r['paging']['next']))throw new RuntimeException('Incomplete response');
    if($p['source']==='whatsapp'){
        $a=$r['analytics']??null;$items=$a['data_points']??null;$start=strtotime($day.' UTC');
        if(($r['id']??null)!==$p['account']||!is_array($a)||strtoupper((string)($a['granularity']??''))!=='DAY'||isset($a['paging']['next'])||!is_array($items)||!array_is_list($items)||count($items)!==1)throw new RuntimeException('Unverified analytics');
        $v=$items[0];if(($v['start']??null)!==$start||($v['end']??null)!==$start+86400)throw new RuntimeException('Period mismatch');
        return ['count'=>nativeUsageCount($v['sent']??null),'delivered'=>nativeUsageCount($v['delivered']??null)];
    }
    $rows=$r['usage_records']??null;
    if(!array_key_exists('next_page_uri',$r)||$r['next_page_uri']!==null||!is_array($rows)||!array_is_list($rows)||count($rows)!==1)throw new RuntimeException('Incomplete usage');
    $v=$rows[0];$sms=$p['source']==='twilio-sms';
    if(($v['account_sid']??null)!==$p['account']||($v['category']??null)!==($sms?'sms-inbound':'calls-inbound')||($v['start_date']??null)!==$day||($v['end_date']??null)!==$day||($v['count_unit']??null)!==($sms?'messages':'calls'))throw new RuntimeException('Usage mismatch');
    return ['count'=>nativeUsageCount($v['count']??null)];
}
