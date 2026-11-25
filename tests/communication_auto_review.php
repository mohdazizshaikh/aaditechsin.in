<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/communication-auto-review-lib.php';
function arCheck(bool $ok,string $m):void{if(!$ok)throw new LogicException($m);}
function arBlocked(callable $f):void{try{$f();}catch(RuntimeException|InvalidArgumentException $e){return;}throw new LogicException('Expected refusal');}
$cfg=['growth'=>['enabled'=>true],'communications'=>['enabled'=>true,'reviews_enabled'=>true,'auto_review_enabled'=>true,'auto_review_lookback_days'=>7]];
arCheck(autoReviewDays($cfg)===7,'Lookback');
foreach([0,31,'7',true] as $v){$bad=$cfg;$bad['communications']['auto_review_lookback_days']=$v;arBlocked(static fn()=>autoReviewDays($bad));}
arBlocked(static fn()=>autoReviewDays([]));
echo "Communication auto-review config PASS\n";
if(($argv[1]??'')!=='--database')exit;
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    $now=new DateTimeImmutable('2031-01-20',new DateTimeZone('UTC'));$day='2031-01-19';
    growthCommand($db,'/pause',$cfg);arBlocked(static fn()=>autoReviewRun($db,$cfg,$now));
    growthCommand($db,'/resume',$cfg);
    $q=$db->prepare('INSERT INTO growth_communication_daily(channel,day_utc,received_count,answered_count,missed_count) VALUES(?,?,3,1,2)');
    $ids=[];foreach(['email','whatsapp','sms','calls'] as $channel){$q->execute([$channel,$day]);$ids[]=(int)$db->lastInsertId();}
    $q->execute(['email','2031-01-01']);
    $db->exec("INSERT INTO growth_communication_daily(channel,day_utc,received_count,answered_count,missed_count) VALUES('sms','2031-01-18',3,3,0)");
    $q=$db->prepare("INSERT INTO growth_communication_reviews(aggregate_id,due_on,status) VALUES(?,'2031-01-19',?)");
    $q->execute([$ids[0],'completed']);$q->execute([$ids[1],'cancelled']);
    $p=autoReviewPlan($db,$cfg,$now);arCheck($p['create_count']===2,'Plan only eligible');
    $r=autoReviewRun($db,$cfg,$now);arCheck($r['created']===2&&$r['preserved']===2&&!$r['outreach_sent'],'Create review reminders');
    arCheck($r['created_by_channel']===['email'=>0,'whatsapp'=>0,'sms'=>1,'calls'=>1],'Channel counts');
    $r=autoReviewRun($db,$cfg,$now);arCheck($r['created']===0&&$r['preserved']===4,'Idempotent');
    $q=$db->prepare('SELECT status FROM growth_communication_reviews WHERE aggregate_id=?');
    foreach(['completed','cancelled','open','open'] as $i=>$expected){$q->execute([$ids[$i]]);arCheck($q->fetchColumn()===$expected,'Preserve states');}
    $db->rollBack();echo "Disposable missed-channel automatic reminder, no reopen, idempotency PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Automatic communication review failed: '.$e->getMessage()."\n");exit(1);}
