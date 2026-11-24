<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/telegram-album-send.php';
require dirname(__DIR__).'/public_html/api/whatsapp-inbound-core.php';
function aiCheck(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
function aiBlocked(callable $f):void{try{$f();}catch(RuntimeException|InvalidArgumentException|JsonException $e){return;}throw new LogicException('Expected refusal');}
$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$ts=$now->getTimestamp();$day=$now->modify('-1 day')->format('Y-m-d');
$cfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'media'=>['enabled'=>true],'social'=>['enabled'=>true,'telegram_channel'=>['enabled'=>true,'chat_id'=>'-1001234567890','bot_token'=>'123456:'.str_repeat('a',40)]],'media_upload'=>['enabled'=>true,'telegram_media_enabled'=>true,'telegram_album_enabled'=>true],'communications'=>['enabled'=>true,'whatsapp_inbound'=>['enabled'=>true,'authorized'=>true,'log_redaction_verified'=>true,'waba'=>'123','phone_id'=>'456','app_secret'=>str_repeat('a',32),'verify_token'=>str_repeat('b',64),'dedupe_key_hex'=>str_repeat('c',64)]]];
$p=taTarget($cfg);aiCheck($p!==null&&taTarget([])===null&&waInboundConfig([])===null,'Default off');
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+X2ZkAAAAASUVORK5CYII=');$mp4=pack('N',32).'ftypmp42'.str_repeat('v',20);$ih=hash('sha256',$png);$vh=hash('sha256',$mp4);
$bundle=taDigest($ih,$vh,strlen($png),strlen($mp4));
$row=['target_id'=>$p['id'],'image_hash'=>$ih,'video_hash'=>$vh,'asset_hash'=>$bundle,'byte_count'=>strlen($png)+strlen($mp4),'caption'=>'Plan IT requirements.'];
$calls=[];$mock=static function($p,$method,$data)use(&$calls,$png,$mp4){
    $calls[]=$method;
    $r=match($method){
        'getMe'=>['id'=>123,'is_bot'=>true],
        'getChat'=>['id'=>-1001234567890,'type'=>'channel'],
        'getChatMember'=>['user'=>['id'=>123],'status'=>'administrator','can_post_messages'=>true],
        'sendMediaGroup'=>[['message_id'=>111,'media_group_id'=>'789','chat'=>['id'=>-1001234567890],'photo'=>[]],['message_id'=>222,'media_group_id'=>'789','chat'=>['id'=>-1001234567890],'video'=>[]]]
    };
    if($method==='sendMediaGroup'){aiCheck(json_decode($data['media'],true)===[['type'=>'photo','media'=>'attach://photo1','caption'=>'Plan IT requirements.'],['type'=>'video','media'=>'attach://video2']]&&$data['photo1']->data===$png&&$data['video2']->data===$mp4,'Exact album bytes/order');}
    return ['http'=>200,'body'=>json_encode(['ok'=>true,'result'=>$r])];
};
$result=taSend($p,$row,$png,$mp4,$mock);
aiCheck($result===['status'=>'accepted','receipt'=>'789','messages'=>'111,222']&&$calls===['getMe','getChat','getChatMember','sendMediaGroup'],'Exact receipt');
aiCheck(taSend($p,$row,$png.'x',$mp4,$mock)['status']==='failed','Tamper');
$bad=static function($p,$method,$data)use($mock){$r=$mock($p,$method,$data);if($method==='sendMediaGroup'){$j=json_decode($r['body'],true);$j['result'][1]['media_group_id']='999';$r['body']=json_encode($j);}return $r;};
aiCheck(taSend($p,$row,$png,$mp4,$bad)['status']==='unknown','Partial/mismatched group');
$w=waInboundConfig($cfg);aiCheck(waInboundChallenge($w,['hub_mode'=>'subscribe','hub_verify_token'=>$w['verify_token'],'hub_challenge'=>'123'])==='123'&&waInboundChallenge($w,['hub_mode'=>'subscribe','hub_verify_token'=>'wrong','hub_challenge'=>'123'])===null,'Challenge');
$event=['object'=>'whatsapp_business_account','entry'=>[['id'=>'123','changes'=>[['field'=>'messages','value'=>['messaging_product'=>'whatsapp','metadata'=>['phone_number_id'=>'456'],'contacts'=>[['profile'=>['name'=>'Synthetic sender']]],'messages'=>[['id'=>'wamid.syntheticABC','timestamp'=>(string)($ts-86400),'type'=>'text','from'=>'synthetic-sender','text'=>['body'=>'Synthetic private text']]]]]]]]];
$encode=static function(array $doc)use($w):array{$raw=json_encode($doc,JSON_THROW_ON_ERROR);return [$raw,'sha256='.hash_hmac('sha256',$raw,$w['app_secret'])];};
[$raw,$sig]=$encode($event);$rows=waInboundDecode($w,$raw,$sig,$ts);
aiCheck(count($rows)===1&&array_values($rows)===[$day]&&!str_contains(json_encode($rows),'synthetic'),'Minimized digest');
aiBlocked(static fn()=>waInboundDecode($w,$raw.' ',$sig,$ts));
$wrong=$event;$wrong['entry'][0]['changes'][0]['value']['metadata']['phone_number_id']='999';[$a,$b]=$encode($wrong);aiBlocked(static fn()=>waInboundDecode($w,$a,$b,$ts));
$old=$event;$old['entry'][0]['changes'][0]['value']['messages'][0]['timestamp']=(string)($ts-8*86400);[$a,$b]=$encode($old);aiCheck(waInboundDecode($w,$a,$b,$ts)===[],'Stale event ignored');
$status=$event;unset($status['entry'][0]['changes'][0]['value']['messages']);$status['entry'][0]['changes'][0]['value']['statuses']=[['id'=>'wamid.syntheticABC']];[$a,$b]=$encode($status);aiCheck(waInboundDecode($w,$a,$b,$ts)===[],'Statuses excluded');
if(($argv[1]??'')!=='--database'){echo "Album ordered upload and WhatsApp signature rules: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);$id=creativeCreate($db,'infrastructure','telegram','education');
    creativeImport($db,['version'=>1,'task_id'=>$id,'revision'=>1,'model'=>'local-test:1b','caption'=>'Plan IT requirements.','visual_brief'=>'Illustration','video_brief'=>'Planning','review_notes'=>'Review']);growthCommand($db,"/approve $id 1",$cfg);
    $manifest=mediaRegister($db,json_encode(['version'=>1,'task_id'=>$id,'revision'=>1,'channel'=>'telegram','renderer'=>'template','width'=>1080,'height'=>1080,'image_sha256'=>$ih,'video_sha256'=>$vh]))['manifest_sha256'];
    growthCommand($db,"/approvemedia $id 1 $manifest",$cfg);$up=taRegister($db,$cfg,$id,1,$manifest,strlen($png),strlen($mp4));
    aiBlocked(static fn()=>tgMediaRegister($db,$cfg,$id,1,$manifest,'photo',$ih,strlen($png)));
    aiCheck(str_contains(growthCommand($db,"/approvetgalbum $up ".str_repeat('0',64)." -1001234567890",$cfg),'mismatch'),'Wrong bundle');
    aiCheck(str_contains(growthCommand($db,"/approvetgalbum $up $bundle -1001234567890",$cfg),'approved'),'Owner approval');
    aiBlocked(static fn()=>tgMediaClaim($db,$cfg,$up));$r=taClaim($db,$cfg,$up);taFinish($db,$r,$result);aiBlocked(static fn()=>taClaim($db,$cfg,$up));
    aiCheck(str_contains(growthCommand($db,"/tgalbum $up",$cfg),'111,222'),'Stored receipt');
    aiCheck(waInboundRecord($db,$cfg,$raw,$sig,$ts)===1&&waInboundRecord($db,$cfg,$raw,$sig,$ts)===0,'Event dedup');
    aiCheck(str_contains(growthCommand($db,'/wainbound',$cfg),'1 distinct recorded messages'),'Owner count');
    growthCommand($db,'/pause',$cfg);aiBlocked(static fn()=>waInboundRecord($db,$cfg,$raw,$sig,$ts));
    $db->rollBack();echo "Synthetic album and inbound event ledger: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Album/inbound test failed: '.$e->getMessage()."\n");exit(1);}
