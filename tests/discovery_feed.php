<?php
declare(strict_types=1);
require dirname(__DIR__).'/secure-setup/maintenance/discovery-feed-lib.php';
function feedCheck(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);}
$src=feedDefinition('ci-feed',['authorized'=>true,'url'=>'https://example.com/feed.json','host'=>'example.com','source_type'=>'company_announcement']);
foreach(['127.0.0.1','10.1.2.3','169.254.169.254','100.64.1.1','198.18.0.1','192.0.2.1','224.0.0.1','::1'] as $ip)feedCheck(!feedPublicIp($ip),'Block '.$ip);
feedCheck(feedPublicIp('8.8.8.8'),'Public IPv4');
foreach([['authorized'=>false],['url'=>'https://example.com:444/feed'],['url'=>'https://other.example/feed'],['url'=>'https://example.com/feed?key=secret']] as $patch){
    try{feedDefinition('ci-feed',array_replace(['authorized'=>true,'url'=>'https://example.com/feed.json','host'=>'example.com','source_type'=>'company_announcement'],$patch));throw new RuntimeException('Bad config accepted');}catch(InvalidArgumentException $ignored){}
}
$item=['source_url'=>'https://example.com/ci-feed-evidence','organization'=>'Synthetic Feed Organization','location_label'=>'Mumbai','evidence'=>'Planned conference room and video conferencing upgrade','observed_on'=>gmdate('Y-m-d'),'signal_type'=>'upgrade'];
$payload=json_encode(['version'=>1,'items'=>[$item,array_replace($item,['source_url'=>'https://other.example/evidence'])]],JSON_THROW_ON_ERROR);
$parsed=feedItems($payload,$src);feedCheck(count($parsed['records'])===1&&$parsed['skipped']===1,'Match and source restriction');
try{feedItems('{"version":2,"items":[]}',$src);throw new RuntimeException('Bad version accepted');}catch(InvalidArgumentException $ignored){}
if(($argv[1]??'')!=='--database'){echo "Feed contract and network destination rules: PASS\n";exit;}
if(getenv('AADITECHS_DISPOSABLE_TEST')!=='yes'||getenv('CI_DB_NAME')!=='aaditechs_ci'||getenv('CI_DB_USER')!=='aaditechs_ci'||getenv('CI_DB_HOST')!=='127.0.0.1')exit(2);
$db=new PDO('mysql:host=127.0.0.1;port='.(int)getenv('CI_DB_PORT').';dbname=aaditechs_ci;charset=utf8mb4','aaditechs_ci',getenv('CI_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$r=feedRun($db,$src,fn($source)=>$payload);feedCheck($r['status']==='partial'&&$r['imported']===1&&$r['skipped']===1,'Import');
$r=feedRun($db,$src,fn($source)=>$payload);feedCheck($r['duplicates']===1&&$r['imported']===0,'Duplicate');
$r=feedRun($db,$src,fn($source)=>throw new RuntimeException('transport_failed'));feedCheck($r['status']==='failed'&&$r['error']==='transport_failed','Failure');
$db->beginTransaction();
$cfg=['growth'=>['enabled'=>true],'discovery'=>['enabled'=>true,'feeds_enabled'=>true]];
feedCheck(str_contains(growthCommand($db,'/discoveryruns',$cfg),'transport_failed'),'Owner report');
$db->rollBack();
echo "Synthetic feed intake, deduplication, failure ledger and report: PASS (no external fetch)\n";
