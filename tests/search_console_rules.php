<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/search-console-read.php';
function scCheck(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
function scBlocked(callable $f):void{try{$f();}catch(RuntimeException $e){return;}throw new LogicException('Expected refusal');}
$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$w=scWindow($now);
$cfg=['growth'=>['enabled'=>true],'seo'=>['enabled'=>true,'search_console'=>['enabled'=>true,'authorized'=>true,'revoked'=>false,'property'=>'sc-domain:aaditechsin.in','generation'=>str_repeat('a',32),'review_expires_utc'=>$now->modify('+1 day')->format('Y-m-d\TH:i:s\Z'),'client_id'=>'synthetic-client','client_secret'=>'synthetic-secret','refresh_token'=>'synthetic-refresh']]];
$p=scConfig($cfg,$now->getTimestamp());scCheck($p!==null&&scConfig([])===null,'Default off');
scCheck(scWindow(new DateTimeImmutable('2026-11-25T02:00:00Z'))===['start'=>'2026-11-15','end'=>'2026-11-21'],'Pacific window');
$grant=['access_token'=>str_repeat('b',40),'expires_in'=>3600,'token_type'=>'Bearer','scope'=>'https://www.googleapis.com/auth/webmasters.readonly'];
$response=['responseAggregationType'=>'byProperty','rows'=>[['keys'=>[$w['start']],'clicks'=>2,'impressions'=>10,'position'=>3.0],['keys'=>[$w['end']],'clicks'=>3,'impressions'=>30,'position'=>5.0]]];
$exchange=static fn($data)=>$grant;
$read=static function($p,$token,$resource,$body)use($response,$w){if($resource==='site')return ['siteUrl'=>$p['property'],'permissionLevel'=>'siteRestrictedUser'];scCheck($body['dataState']==='final'&&$body['dimensions']===['date']&&$body['startDate']===$w['start']&&$body['endDate']===$w['end'],'Exact query');return $response;};
scCheck(scParse(scCollect($p,$w,$exchange,$read),$w)===['days'=>2,'clicks'=>5,'impressions'=>40,'position'=>4.5],'Weighted position and coverage');
foreach([['scope'=>'https://www.googleapis.com/auth/webmasters'],['scope'=>null],['refresh_token'=>'rotated'],['expires_in'=>600]] as $bad)scBlocked(static fn()=>scToken($p,static fn()=>array_replace($grant,$bad)));
scBlocked(static fn()=>scCollect($p,$w,$exchange,static fn()=>['siteUrl'=>'sc-domain:other.example','permissionLevel'=>'siteOwner']));
$bad=$response;$bad['rows'][]=$bad['rows'][0];scBlocked(static fn()=>scParse($bad,$w));
scCheck(scParse(['responseAggregationType'=>'byProperty'],$w)['days']===0,'No rows');
if(($argv[1]??'')!=='--database'){echo "Search Console scope, property and finalized metric rules: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);scCapture($db,$cfg,$w,$response,$now);
    $s=scSummary($db,$cfg,$now);scCheck(str_contains($s,'2/7 days returned')&&str_contains($s,'Clicks 5, impressions 40')&&!str_contains($s,'synthetic-refresh'),'Aggregate only');
    scCheck(str_contains(growthCommand($db,'/searchconsole',$cfg),'Clicks 5')&&str_contains(growthCommand($db,'/intelligence',$cfg),'Clicks 5'),'Owner reporting');
    $rot=$cfg;$rot['seo']['search_console']['generation']=str_repeat('c',32);scCheck(str_contains(scSummary($db,$rot,$now),'no current evidence'),'Rotation hides snapshot');
    scCapture($db,$cfg,$w,['responseAggregationType'=>'byProperty'],$now);scCheck(str_contains(scSummary($db,$cfg,$now),'not zero traffic'),'Missing distinct from zero');
    growthCommand($db,'/pause',$cfg);scBlocked(static fn()=>scCapture($db,$cfg,$w,$response,$now));
    $db->rollBack();echo "Synthetic Search Console snapshot: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'Search Console test failed: '.$e->getMessage()."\n");exit(1);}
