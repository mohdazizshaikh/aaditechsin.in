<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/search-console-core.php';
require dirname(__DIR__).'/public_html/api/live-seo-core.php';
function shCheck(bool $v,string $m):void{if(!$v)throw new RuntimeException($m);}
function shBlocked(callable $f):void{try{$f();}catch(RuntimeException|InvalidArgumentException $e){return;}throw new LogicException('Expected refusal');}
$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$w=scWindow($now);
$cfg=['growth'=>['enabled'=>true],'seo'=>['enabled'=>true,'live_crawl_enabled'=>true,'search_console'=>['enabled'=>true,'authorized'=>true,'history_enabled'=>true,'property'=>'sc-domain:aaditechs.in','generation'=>str_repeat('a',32),'review_expires_utc'=>$now->modify('+1 day')->format('Y-m-d\TH:i:s\Z'),'client_id'=>'synthetic-client','client_secret'=>'synthetic-secret','refresh_token'=>'synthetic-refresh']]];
shCheck(scConfig($cfg,$now->getTimestamp())!==null,'Canonical property');
$report=['version'=>1,'scope'=>'public-html-observation','origin'=>'https://aaditechs.in','captured_utc'=>$now->format('Y-m-d\TH:i:s\Z'),'pages'=>7,'counts'=>['html_ok'=>5,'http_non200'=>1,'unavailable'=>1,'missing_title'=>1,'missing_description'=>0,'h1_review'=>0,'canonical_review'=>0,'noindex_meta'=>0,'missing_alt_page'=>2]];
shCheck(liveSeoValidate($report,$now)['counts']['html_ok']===5,'Coverage');
$bad=$report;$bad['counts']['html_ok']=7;shBlocked(static fn()=>liveSeoValidate($bad,$now));
$bad=$report;$bad['counts']['missing_title']=6;shBlocked(static fn()=>liveSeoValidate($bad,$now));
shBlocked(static fn()=>liveSeoValidate($report,$now->modify('+2 days')));
if(($argv[1]??'')!=='--database'){echo "SEO observation validation: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1'||getenv('CI_DB_PASSWORD')!=='ci_only_not_real_password')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
try{
    growthCommand($db,'/resume',$cfg);
    $response=['responseAggregationType'=>'byProperty','rows'=>[['keys'=>[$w['start']],'clicks'=>2,'impressions'=>10,'position'=>3.0],['keys'=>[$w['end']],'clicks'=>3,'impressions'=>20,'position'=>4.0]]];
    scCapture($db,$cfg,$w,$response,$now);scCapture($db,$cfg,$w,$response,$now);
    $h=scHistory($db,$cfg,30,$now);shCheck(count($h['daily'])===30&&count(array_filter($h['daily']))===2,'Overlap dedup and missing days');
    $response['rows'][0]['clicks']=4;scCapture($db,$cfg,$w,$response,$now);
    shCheck(str_contains(scHistorySummary($db,$cfg,$now),'Clicks 7, impressions 30'),'Revised day not double counted');
    $rot=$cfg;$rot['seo']['search_console']['generation']=str_repeat('b',32);shCheck(count(array_filter(scHistory($db,$rot,30,$now)['daily']))===0,'Generation separation');
    liveSeoCapture($db,$cfg,$report,$now);shBlocked(static fn()=>liveSeoCapture($db,$cfg,$report,$now));
    shCheck(str_contains(growthCommand($db,'/liveseo',$cfg),'5/7 HTML pages')&&str_contains(growthCommand($db,'/searchhistory',$cfg),'Clicks 7'),'Owner commands');
    growthCommand($db,'/pause',$cfg);shBlocked(static fn()=>liveSeoCapture($db,$cfg,$report,$now));
    $db->rollBack();echo "Synthetic SEO history and observation import: PASS\n";
}catch(Throwable $e){if($db->inTransaction())$db->rollBack();fwrite(STDERR,'SEO history test failed: '.$e->getMessage()."\n");exit(1);}
