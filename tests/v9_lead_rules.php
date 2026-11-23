<?php
declare(strict_types=1);
// Offline pure-function tests: synthetic records, no DB/SMTP/Hostinger credentials.
require dirname(__DIR__).'/public_html/api/v9/lead_rules.php';
function v9Test(bool $condition,string $message): void {
    if (!$condition) {fwrite(STDERR,'FAIL: '.$message."\n");exit(1);}
}
function v9Reject(callable $operation,string $message): void {
    try {$operation();} catch(InvalidArgumentException $e) {return;}
    v9Test(false,$message);
}
$salt=str_repeat('x',48);
$key='6748c100-3e4e-42ef-b7ca-2f77d8dd44da';
v9Test(v9LeadKeyValid($key),'valid UUID accepted');
v9Test(v9LeadKeyValid(str_repeat('a',32)),'128-bit hex key accepted');
v9Test(!v9LeadKeyValid('weak-key') && !v9LeadKeyValid(''),'weak keys rejected');
$raw=[
    'service_code'=>'VC','fullName'=>' Synthetic Person ',
    'email'=>'synthetic@example.invalid','phone'=>'','device'=>'',
    'message'=>'New meeting room assessment','source'=>'guided-chat',
    'notice_version'=>'synthetic-review-v1','contact_consent'=>true,
    'marketing_email_opt_in'=>false,
    'answers'=>['room_capacity'=>'20','platform'=>'Teams']
];
$lead=v9LeadNormalize($raw);
v9Test($lead['fullName']==='Synthetic Person','name trimmed');
v9Test($lead['service_code']==='VC' && $lead['answers']['platform']==='Teams','approved service and answers');
v9Test(str_contains(v9LeadCombinedMessage($lead),'room_capacity: 20'),'answers included in V8 master lead message');
$rawReordered=$raw;
$rawReordered['answers']=['platform'=>'Teams','room_capacity'=>'20'];
$a=v9LeadAttribution(['landing_path'=>'/video-conferencing-solutions/','utm_source'=>'google','utm_medium'=>'organic','utm_campaign'=>'local_vc','referrer_host'=>'Example.org'],'guided-chat');
v9Test($a['utm_source']==='google' && $a['referrer_host']==='example.org','safe attribution retained');
v9Test(v9LeadPayloadHash($lead,$a,$salt)===v9LeadPayloadHash(v9LeadNormalize($rawReordered),$a,$salt),'answer order independent');
v9Test(v9LeadHash('idempotency',$key,$salt)!==v9LeadHash('rate',$key,$salt),'hash domains separated');
v9Test(strlen(v9LeadHash('payload','synthetic',$salt))===64,'only salted hash stored');
$bad=v9LeadAttribution(['landing_path'=>'https://evil.test/?email=private','utm_medium'=>'<script>','referrer_host'=>'evil.test/path'],'guided-chat');
v9Test($bad['landing_path']==='/' && $bad['medium']==='' && $bad['referrer_host']==='','unsafe attribution dropped');
foreach (['IT_AMC','VC','AUD_AV','SOUND','RECOVERY','HARDWARE','SOFTWARE','OTHER'] as $service) {
    $valid=$raw;$valid['service_code']=$service;
    v9Test(v9LeadNormalize($valid)['service_code']===$service,'valid service '.$service);
}
foreach ([
    ['service_code','UNLISTED'],['contact_consent',false],['marketing_email_opt_in',true],
    ['source','cold-scrape'],['email','not an email'],['notice_version',''],
    ['answers',['unsupported key'=>'bad']],['answers',['q1'=>str_repeat('x',301)]]
] as [$field,$value]) {
    $invalid=$raw;$invalid[$field]=$value;
    v9Reject(fn()=>v9LeadNormalize($invalid),'reject invalid '.$field);
}
$tooLong=$lead;$tooLong['message']=str_repeat('z',1999);
v9Reject(fn()=>v9LeadCombinedMessage($tooLong),'combined summary cannot silently truncate');
echo "V9-03 intake normalization, privacy, attribution and idempotency hash rules: PASS\n";
