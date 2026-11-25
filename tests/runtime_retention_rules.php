<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/seo-retention-lib.php';
require dirname(__DIR__).'/secure-setup/maintenance/gbp-media-check-lib.php';
function rtCheck(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function rtBlocked(callable $f):void{try{$f();}catch(RuntimeException $e){return;}throw new LogicException('Expected refusal');}
$p=['provider'=>'google-business','id'=>'123:456','token'=>'synthetic-token'];
rtCheck(gbpMediaReadTarget([])===null,'Default off');
rtCheck(gbpMediaReadCheck($p,static function($p,$m,$path,$data){rtCheck($m==='GET'&&$path==='v4/accounts/123/locations/456/media'&&$data===['pageSize'=>1],'Exact scoped read');return ['http'=>200,'json'=>['mediaItems'=>[['name'=>'accounts/123/locations/456/media/789']]]];}),'Scoped read');
rtCheck(!gbpMediaReadCheck($p,static fn()=>['http'=>200,'json'=>['mediaItems'=>[['name'=>'accounts/123/locations/999/media/789']]]]),'Wrong location');
rtCheck(!gbpMediaReadCheck($p,static fn()=>['http'=>403,'json'=>[]]),'Permission refusal');
if(($argv[1]??'')!=='--database'){echo "GBP read-only media check: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$pt=$now->setTimezone(new DateTimeZone('America/Los_Angeles'));
$cfg=['growth'=>['enabled'=>true],'seo'=>['history_retention_enabled'=>true]];
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);rtBlocked(static fn()=>seoRetentionPlan($db,$cfg,$now));
    growthCommand($db,'/pause',$cfg);
    $q=$db->prepare('INSERT INTO growth_search_console_daily(property_hash,generation,day_pt,clicks,impressions,average_position,captured_at) VALUES(?,?,?,1,2,3,UTC_TIMESTAMP())');
    $h=str_repeat('f',64);$g=str_repeat('e',32);$old=$pt->modify('-181 days')->format('Y-m-d');$keep=$pt->modify('-180 days')->format('Y-m-d');
    $q->execute([$h,$g,$old]);$q->execute([$h,$g,$keep]);
    $plan=seoRetentionPlan($db,$cfg,$now);rtCheck(in_array($old,array_column($plan['rows'],'day_pt'),true)&&!in_array($keep,array_column($plan['rows'],'day_pt'),true),'Boundary');
    $q->execute([$h,$g,$pt->modify('-182 days')->format('Y-m-d')]);rtBlocked(static fn()=>seoRetentionApply($db,$cfg,$plan['digest'],$now));
    $plan=seoRetentionPlan($db,$cfg,$now);rtCheck(seoRetentionApply($db,$cfg,$plan['digest'],$now)===count($plan['rows']),'Exact plan');
    $q=$db->prepare('SELECT COUNT(*) FROM growth_search_console_daily WHERE property_hash=? AND generation=? AND day_pt=?');$q->execute([$h,$g,$keep]);rtCheck((int)$q->fetchColumn()===1,'Boundary preserved');
    $db->rollBack();echo "Synthetic paused SEO retention: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Runtime/retention test failed: '.$e->getMessage()."\n");exit(1);}
