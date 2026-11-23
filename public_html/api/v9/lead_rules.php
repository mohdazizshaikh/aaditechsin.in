<?php
declare(strict_types=1);
// Pure V9 intake rules. Side effects live only in leads.php; safe for CLI tests.

function v9LeadKeyValid(string $key): bool {
    return preg_match('/\A(?:[a-f0-9]{32}|[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})\z/iD', $key) === 1;
}
function v9LeadLength(string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}
function v9LeadScalar(array $data, string $name): string {
    $value=$data[$name]??'';
    if (!is_string($value)) throw new InvalidArgumentException('Invalid field');
    return trim($value);
}
function v9LeadNormalize(array $data): array {
    $codes=['IT_AMC','VC','AUD_AV','SOUND','RECOVERY','HARDWARE','SOFTWARE','OTHER'];
    $code=v9LeadScalar($data,'service_code');
    $name=v9LeadScalar($data,'fullName');
    $email=v9LeadScalar($data,'email');
    $phone=v9LeadScalar($data,'phone');
    $device=v9LeadScalar($data,'device');
    $message=v9LeadScalar($data,'message');
    $source=v9LeadScalar($data,'source');
    $notice=v9LeadScalar($data,'notice_version');
    if (!in_array($code,$codes,true) || $name==='' || v9LeadLength($name)>100
        || !filter_var($email,FILTER_VALIDATE_EMAIL) || v9LeadLength($email)>120
        || v9LeadLength($phone)>20 || v9LeadLength($device)>100
        || $message==='' || v9LeadLength($message)>2000
        || !in_array($source,['website','av-planner','guided-chat'],true)
        || $notice==='' || v9LeadLength($notice)>60
        || ($data['contact_consent']??null)!==true
        || ($data['marketing_email_opt_in']??false)!==false) {
        throw new InvalidArgumentException('Invalid lead fields or contact permission');
    }
    $answers=$data['answers']??[];
    if (!is_array($answers) || (array_is_list($answers) && count($answers)>0) || count($answers)>8) {
        throw new InvalidArgumentException('Invalid answers');
    }
    $normalized=[];
    foreach ($answers as $question=>$value) {
        if (!is_string($question) || preg_match('/\A[a-z][a-z0-9_]{0,39}\z/D',$question)!==1
            || !is_string($value) || v9LeadLength($value)>300) throw new InvalidArgumentException('Invalid answer');
        $normalized[$question]=trim($value);
    }
    ksort($normalized,SORT_STRING);
    return [
        'service_code'=>$code,'fullName'=>$name,'email'=>$email,'phone'=>$phone,
        'device'=>$device,'message'=>$message,'source'=>$source,
        'notice_version'=>$notice,'answers'=>$normalized
    ];
}
function v9LeadAttribution(array $raw, string $source): array {
    $path=$raw['landing_path']??'/';
    if (!is_string($path) || strlen($path)>180 || str_contains($path,'..')
        || preg_match('~\A/(?!/)[a-zA-Z0-9/_-]*\z~D',$path)!==1) $path='/';
    $fields=['utm_source'=>'utm_source','medium'=>'utm_medium','campaign'=>'utm_campaign','content'=>'utm_content'];
    $result=['landing_path'=>$path,'source'=>$source];
    foreach ($fields as $column=>$key) {
        $value=$raw[$key]??'';
        $result[$column]=is_string($value) && strlen($value)<=64
            && preg_match('/\A[a-zA-Z0-9._-]*\z/D',$value)===1 ? $value : '';
    }
    $host=$raw['referrer_host']??'';
    $result['referrer_host']=is_string($host) && strlen($host)<=180
        && ($host==='' || filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)) ? strtolower($host) : '';
    return $result;
}
function v9LeadHash(string $purpose, string $text, string $secret): string {
    if (strlen($secret)<32 || str_starts_with($secret,'REPLACE_')) throw new RuntimeException('Private salt unavailable');
    return hash_hmac('sha256', $purpose.'|'.$text, $secret);
}
function v9LeadPayloadHash(array $normalized, array $attribution, string $salt): string {
    $encoded=json_encode([$normalized,$attribution],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    return v9LeadHash('payload',$encoded,$salt);
}
function v9LeadCombinedMessage(array $lead): string {
    $message=$lead['message'];
    foreach ($lead['answers'] as $question=>$answer) $message.="\n".$question.': '.$answer;
    if (v9LeadLength($message)>2000) throw new InvalidArgumentException('Assessment too long');
    return $message;
}
