<?php
declare(strict_types=1);
require dirname(__DIR__).'/public_html/api/strict-json.php';
require dirname(__DIR__).'/public_html/api/oauth-health-core.php';
require dirname(__DIR__).'/public_html/api/media-storage-core.php';
require dirname(__DIR__).'/secure-setup/maintenance/competitor-review-lib.php';
require dirname(__DIR__).'/secure-setup/maintenance/followup-batch-lib.php';
require dirname(__DIR__).'/secure-setup/maintenance/communication-review-batch-lib.php';
require dirname(__DIR__).'/secure-setup/maintenance/media-inbox-core.php';
function sjCheck(bool $ok,string $label):void{if(!$ok)throw new LogicException($label);}
function sjBlocked(callable $fn):void{try{$fn();}catch(InvalidArgumentException|JsonException $e){return;}throw new LogicException('Expected rejection');}
$cases=0;
for($i=0;$i<50;$i++){
    $key='field'.$i;$other='other'.$i;
    foreach(['{"'.$key.'":1,"'.$key.'":2}','{"'.$key.'":1,"\u0066ield'.$i.'":2}','{"'.$other.'":[{"'.$key.'":1,"'.$key.'":2}]}'] as $bad){
        sjBlocked(static fn()=>strictJsonDecode($bad,true,32));++$cases;
    }
    $valid='{"'.$key.'":1,"'.$other.'":{"'.$key.'":2}}';
    sjCheck(strictJsonDecode($valid,true,32)===json_decode($valid,true,32,JSON_THROW_ON_ERROR),'Independent scopes');++$cases;
}
foreach(['{"a":1,"\u0061":1}','{"":1,"":2}','{"0":1,"\u0030":2}','{"é":1,"\u00e9":2}','{"😀":1,"\ud83d\ude00":2}','{"a\"b":1,"a\u0022b":2}'] as $bad)
    sjBlocked(static fn()=>strictJsonDecode($bad,true,32));
foreach(['null','true','false','0','-12.3e+4','[]','{}','[{"a":1},{"a":2}]','{"a":"{\"a\":2}","b":[1,false,null,{}]}','{"0":1,"00":2}','{"a":1,"A":2}','{"é":1,"e\u0301":2}'," \t\r\n{\"a\":1}\n"] as $valid){
    foreach([true,false] as $assoc){
        $native=json_decode($valid,$assoc,32,JSON_THROW_ON_ERROR);
        sjCheck(serialize(strictJsonDecode($valid,$assoc,32))===serialize($native),'Native parity');
    }
}
foreach(['{"a":','[1,]','{"a":1,}','true false',"\xff",str_repeat('[',33).'0'.str_repeat(']',33),str_repeat(' ',1048577)] as $bad)
    sjBlocked(static fn()=>strictJsonDecode($bad,true,32));
sjCheck($cases===200,'200 matrix cases');
$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
$cfg=['growth'=>['enabled'=>true],'seo'=>['enabled'=>true,'competitor_review_batch_enabled'=>true],'discovery'=>['enabled'=>true,'followups_enabled'=>true,'followup_batch_enabled'=>true],'communications'=>['enabled'=>true,'reviews_enabled'=>true,'review_batch_enabled'=>true]];
$service=array_key_first(growthCatalog());$day=$now->format('Y-m-d');
$comp=json_encode(['version'=>1,'scope'=>'competitor-editorial-review','evidence_sha256'=>str_repeat('a',64),'review_day_utc'=>$day,'items'=>[['service_id'=>$service,'signal'=>'service_scope']]],JSON_THROW_ON_ERROR);
$follow='{"version":1,"action":"complete","ids":[1]}';
$comm=json_encode(['version'=>1,'action'=>'complete','items'=>[['channel'=>'email','day_utc'=>$now->modify('-1 day')->format('Y-m-d')]]],JSON_THROW_ON_ERROR);
$inbox='{"version":1,"items":[{"task_id":1,"revision":1,"manifest_sha256":"'.str_repeat('a',64).'"}]}';
$storage='{"version":1,"names":["'.str_repeat('a',64).'.png"]}';
$oauth='{"version":1,"providers":["facebook"]}';
$scopes=[
    [$comp,static fn($s)=>competitorReviewScope($cfg,$s,$now)],
    [$follow,static fn($s)=>followBatchScope($cfg,$s,$now)],
    [$comm,static fn($s)=>commReviewBatchScope($cfg,$s,$now)],
    [$inbox,'inboxBatchRequest'],[$storage,'storagePlanRequest'],[$oauth,'oauthBatchScope']
];
foreach($scopes as [$raw,$fn]){
    sjCheck(is_array($fn($raw)),'Valid integrated scope');
    sjBlocked(static fn()=>$fn(str_replace('"version":1','"version":0,"version":1',$raw)));
    sjBlocked(static fn()=>$fn(str_replace('"version":1','"\u0076ersion":0,"version":1',$raw)));
}
echo "Strict JSON: 200 matrix cases and six integrated scope regressions PASS\n";
