<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/social-core.php';
require dirname(__DIR__).'/secure-setup/maintenance/telegram-send.php';
function socialCheck(bool $ok,string $msg):void{if(!$ok)throw new RuntimeException($msg);}
$chat='-1001234567890';
$cfg=['growth'=>['enabled'=>true],'creative'=>['enabled'=>true],'social'=>['enabled'=>true,'telegram_channel'=>['enabled'=>true,'chat_id'=>$chat,'bot_token'=>'123456:'.str_repeat('a',30)]]];
socialCheck(socialTarget([])===null&&socialTarget($cfg)===$chat,'Default OFF and target');
socialCheck(telegramReceipt(200,json_encode(['ok'=>true,'result'=>['message_id'=>12,'chat'=>['id'=>(int)$chat]]]),$chat)['status']==='accepted','Provider receipt');
socialCheck(telegramReceipt(200,json_encode(['ok'=>true,'result'=>['message_id'=>12,'chat'=>['id'=>123]]]),$chat)['status']==='unknown','Wrong destination');
socialCheck(telegramReceipt(429,'{"ok":false}',$chat)['status']==='failed','Provider rejection');
socialCheck(telegramReceipt(500,'{"ok":false}',$chat)['status']==='unknown','Ambiguous transport');
if(($argv[1]??'')!=='--database'){echo "Social destination and receipt rules: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);
    $id=creativeCreate($db,'it-procurement','telegram','education');
    $v=['version'=>1,'task_id'=>$id,'revision'=>1,'model'=>'local-test:1b','caption'=>'Plan device procurement with a requirements review.','visual_brief'=>'Illustrative device layout.','video_brief'=>'Three planning steps.','review_notes'=>'Check claims.'];
    creativeImport($db,$v);
    $when=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d\TH:i\Z');
    try{socialSchedule($db,$cfg,$id,1,$chat,$when);throw new RuntimeException('Unapproved schedule');}catch(InvalidArgumentException $ignored){}
    growthCommand($db,"/approve $id 1",$cfg);
    try{socialSchedule($db,$cfg,$id,1,'-1009876543210',$when);throw new RuntimeException('Wrong target');}catch(InvalidArgumentException $ignored){}
    $pub=socialSchedule($db,$cfg,$id,1,$chat,$when);
    try{socialSchedule($db,$cfg,$id,1,$chat,$when);throw new RuntimeException('Duplicate schedule');}catch(InvalidArgumentException $ignored){}
    socialCheck(socialClaim($db,$cfg)===null,'Future post not claimed');
    $db->prepare('UPDATE growth_publications SET due_at=UTC_TIMESTAMP() WHERE id=?')->execute([$pub]);
    growthCommand($db,'/pause',$cfg);
    try{socialClaim($db,$cfg);throw new LogicException('Pause bypass');}catch(RuntimeException $e){socialCheck($e->getMessage()==='Growth paused','Pause gate');}
    growthCommand($db,'/resume',$cfg);
    $row=socialClaim($db,$cfg);socialCheck((int)$row['id']===$pub,'Claim approved due revision');
    socialFinish($db,$row,['status'=>'accepted','receipt'=>'12','error'=>'']);
    socialCheck(socialClaim($db,$cfg)===null,'Accepted never requeued');
    socialCheck(str_contains(growthCommand($db,'/social',$cfg),'accepted'),'Report provider state');
    $db->rollBack();echo "Synthetic Telegram scheduling, approval, pause and receipt state: PASS (no network)\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Social integration failed: '.$e->getMessage()."\n");exit(1);}
