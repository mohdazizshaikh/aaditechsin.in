<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/strict-json.php';
// Private CLI library. No endpoint accepts arbitrary fetch URLs.
require_once dirname(__DIR__,2).'/public_html/api/discovery-core.php';
require_once dirname(__DIR__,2).'/public_html/api/creative-core.php';
function feedPublicIp(string $ip): bool {
    if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))return false;
    // Explicit special-purpose IPv4 exclusions (including shared and benchmark ranges).
    $n=ip2long($ip);
    foreach([['0.0.0.0',8],['10.0.0.0',8],['100.64.0.0',10],['127.0.0.0',8],['169.254.0.0',16],['172.16.0.0',12],['192.0.0.0',24],['192.0.2.0',24],['192.88.99.0',24],['192.168.0.0',16],['198.18.0.0',15],['198.51.100.0',24],['203.0.113.0',24],['224.0.0.0',3]] as [$net,$bits]){
        $mask=(-1 << (32-$bits))&0xffffffff;
        if(($n&$mask)===(ip2long($net)&$mask))return false;
    }
    return true;
}
function feedDefinition(string $id,array $src):array {
    if(!preg_match('/^[a-z][a-z0-9-]{0,39}$/D',$id)||($src['authorized']??false)!==true)throw new InvalidArgumentException('Source not authorized');
    $url=$src['url']??null;$host=$src['host']??null;
    if(!is_string($url)||!is_string($host)||strlen($url)>500||!preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/D',$host))throw new InvalidArgumentException('Invalid feed configuration');
    $p=parse_url($url);
    if(!filter_var($url,FILTER_VALIDATE_URL)||!is_array($p)||($p['scheme']??'')!=='https'||($p['host']??'')!==$host||
       isset($p['user'])||isset($p['pass'])||isset($p['port'])||isset($p['fragment'])||isset($p['query'])||
       preg_match('/(?:^|\.)(localhost|local|internal|invalid)$/',$host))throw new InvalidArgumentException('Invalid feed URL');
    $type=$src['source_type']??'';
    if(!in_array($type,['tender','company_announcement','authorized_directory','referral','manual_research'],true))throw new InvalidArgumentException('Invalid source type');
    return ['id'=>$id,'url'=>$url,'host'=>$host,'source_type'=>$type];
}
function feedFetch(array $src,?string $bearer=null):string {
    if($bearer!==null&&!preg_match('/^[A-Za-z0-9._~+\/=\-]{16,512}$/D',$bearer))throw new InvalidArgumentException('Invalid private feed credential');
    $headers=['Accept: application/json'];if($bearer!==null)$headers[]='Authorization: Bearer '.$bearer;
    // Resolve and pin one public IPv4 destination. Fail closed on private DNS answers.
    $ips=gethostbynamel($src['host']);
    if(!$ips)throw new RuntimeException('dns_failed');
    foreach($ips as $ip)if(!feedPublicIp($ip))throw new RuntimeException('unsafe_destination');
    $ch=curl_init($src['url']);if(!$ch)throw new RuntimeException('transport_failed');
    $body='';
    try{
        if(!curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,
            CURLOPT_PROXY=>'',CURLOPT_IPRESOLVE=>CURL_IPRESOLVE_V4,
            CURLOPT_RESOLVE=>[$src['host'].':443:'.$ips[0]],CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_USERAGENT=>'AaditechsAuthorizedFeed/1.0',
            CURLOPT_WRITEFUNCTION=>function($ch,string $chunk)use(&$body):int{
                if(strlen($body)+strlen($chunk)>262144)return 0;
                $body.=$chunk;return strlen($chunk);
            }]))throw new RuntimeException('transport_failed');
        if(curl_exec($ch)!==true)throw new RuntimeException('transport_failed');
        if(curl_getinfo($ch,CURLINFO_RESPONSE_CODE)!==200)throw new RuntimeException('http_status');
        $type=strtolower(trim(explode(';',(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE))[0]));
        if($type!=='application/json')throw new RuntimeException('content_type');
        return $body;
    }finally{curl_close($ch);}
}
function feedItems(string $body,array $src):array {
    if(strlen($body)>262144)throw new InvalidArgumentException('feed_size');
    $doc=strictJsonDecode($body,true,32);
    if(!is_array($doc)||count($doc)!==2||($doc['version']??null)!==1||!is_array($doc['items']??null)||!array_is_list($doc['items'])||count($doc['items'])>25)throw new InvalidArgumentException('feed_contract');
    $records=[];$skipped=0;
    foreach($doc['items'] as $item){
        $fields=['source_url','organization','location_label','evidence','observed_on','signal_type'];
        if(!is_array($item)||count($item)!==count($fields)||array_diff(array_keys($item),$fields)){++$skipped;continue;}
        foreach($fields as $k)if(!is_string($item[$k]??null)){++$skipped;continue 2;}
        if(strtolower((string)parse_url($item['source_url'],PHP_URL_HOST))!==$src['host']){++$skipped;continue;}
        $matches=discoveryMatch($item['evidence']);
        if(!$matches){++$skipped;continue;}
        foreach(array_keys($matches) as $service){
            $candidate=$item+['source_type'=>$src['source_type'],'service_id'=>$service];
            try{discoveryNormalize($candidate);$records[]=$candidate;}catch(InvalidArgumentException $e){++$skipped;}
        }
    }
    return ['records'=>$records,'skipped'=>$skipped];
}
function feedCadence(array $cfg):int {
    if(($cfg['discovery']['cadence_enabled']??false)!==true)return 0;
    $minutes=$cfg['discovery']['minimum_interval_minutes']??null;
    if(!is_int($minutes)||$minutes<60||$minutes>10080)throw new InvalidArgumentException('Invalid cadence');
    return $minutes;
}
// A scheduled invocation must be separately approved and cadence-limited.
function feedConfiguredSources(array $cfg,bool $scheduled=false):array{
    if(!discoveryEnabled($cfg)||($cfg['discovery']['feeds_enabled']??false)!==true)throw new RuntimeException('Disabled');
    $feeds=$cfg['discovery']['feeds']??null;
    if(!is_array($feeds)||count($feeds)<1||count($feeds)>5)throw new RuntimeException('Configure 1-5 authorized sources');
    $minutes=feedCadence($cfg);
    if($scheduled&&(($cfg['discovery']['scheduler_enabled']??false)!==true||$minutes===0))throw new RuntimeException('Scheduler disabled or cadence missing');
    $sources=[];
    foreach($feeds as $id=>$src){
        if(!is_string($id)||str_starts_with($id,'tender-feed-')||!is_array($src))throw new InvalidArgumentException('Invalid source definition');
        $sources[]=feedDefinition($id,$src);
    }
    return ['sources'=>$sources,'minutes'=>$minutes];
}
// Read-only advisory snapshot, not a promise of a future execution.
function feedScheduleStatus(PDO $db,array $cfg):array{
    $plan=feedConfiguredSources($cfg,true);
    $paused=$db->query('SELECT paused FROM growth_control WHERE id=1')->fetchColumn();
    if($paused===false||!in_array((string)$paused,['0','1'],true))throw new RuntimeException('Control unavailable');
    $q=$db->prepare("SELECT COUNT(CASE WHEN status='running' THEN 1 END) AS running,MAX(started_at) AS last_attempt,MAX(CASE WHEN started_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? MINUTE) THEN 1 ELSE 0 END) AS recent FROM growth_source_runs WHERE source_id=?");
    $rows=[];
    foreach($plan['sources'] as $src){
        $q->execute([$plan['minutes'],$src['id']]);$row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new RuntimeException('Status unavailable');
        $state=(string)$paused==='1'?'paused':((int)$row['running']>0?'unresolved':((int)$row['recent']>0?'not_due':'due'));
        $rows[]=['id'=>$src['id'],'state'=>$state,'last_attempt_utc'=>$row['last_attempt']];
    }
    return ['minimum_interval_minutes'=>$plan['minutes'],'sources'=>$rows,'advisory_only'=>true];
}
function feedAdmission(PDO $db,array $src,int $minutes):?int{
    if($minutes!==0&&($minutes<60||$minutes>10080))throw new InvalidArgumentException('Invalid cadence');
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    creativeActive($db);
    $q=$db->prepare("SELECT COUNT(*) FROM growth_source_runs WHERE source_id=? AND status='running'");$q->execute([$src['id']]);
    if((int)$q->fetchColumn()>0)throw new RuntimeException('source_run_unresolved');
    if($minutes>0){
        $q=$db->prepare('SELECT COUNT(*) FROM growth_source_runs WHERE source_id=? AND started_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? MINUTE)');
        $q->execute([$src['id'],$minutes]);if((int)$q->fetchColumn()>0)return null;
    }
    $db->prepare('INSERT INTO growth_source_runs(source_id,started_at) VALUES(?,UTC_TIMESTAMP())')->execute([$src['id']]);
    return (int)$db->lastInsertId();
}
function feedRun(PDO $db,array $src,callable $fetch,int $minutes=0):array {
    if($db->inTransaction())throw new LogicException('Unexpected transaction');
    $db->beginTransaction();
    try{$id=feedAdmission($db,$src,$minutes);$db->commit();}
    catch(Throwable $e){$db->rollBack();if(in_array($e->getMessage(),['Growth paused','source_run_unresolved'],true))return ['status'=>'blocked','imported'=>0,'duplicates'=>0,'skipped'=>0,'error'=>$e->getMessage()==='Growth paused'?'growth_paused':'source_run_unresolved'];throw $e;}
    if($id===null)return ['status'=>'not_due','imported'=>0,'duplicates'=>0,'skipped'=>0,'error'=>''];
    $n=0;$dups=0;$skip=0;$state='completed';$error='';
    try{
        $parsed=feedItems($fetch($src),$src);$skip=$parsed['skipped'];
        // Recheck pause even for an empty result after the network request.
        $db->beginTransaction();creativeActive($db);$db->commit();
        foreach($parsed['records'] as $i=>$item){
            $db->beginTransaction();
            try{creativeActive($db);$check=$db->prepare("SELECT COUNT(*) FROM growth_source_runs WHERE id=? AND status='running'");$check->execute([$id]);if((int)$check->fetchColumn()!==1)throw new RuntimeException('source_run_resolved');$r=discoveryImport($db,$item);$db->commit();if($r['status']==='duplicate')++$dups;else ++$n;}
            catch(Throwable $e){
                if($db->inTransaction())$db->rollBack();
                if(in_array($e->getMessage(),['Growth paused','source_run_resolved'],true)){$skip+=count($parsed['records'])-$i;$error=$e->getMessage()==='Growth paused'?'growth_paused':'source_run_resolved';$state='partial';break;}
                ++$skip;$error='record_import_failed';
            }
        }
        if($skip>0){$state='partial';if($error==='')$error='records_skipped';}
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        $state='failed';$error=$e->getMessage()==='Growth paused'?'growth_paused':(in_array($e->getMessage(),['dns_failed','unsafe_destination','transport_failed','http_status','content_type','feed_contract','feed_size'],true)?$e->getMessage():'feed_unavailable');
    }
    $db->prepare("UPDATE growth_source_runs SET status=?,imported_count=?,duplicate_count=?,skipped_count=?,error_code=?,finished_at=UTC_TIMESTAMP() WHERE id=? AND status='running'")->execute([$state,$n,$dups,$skip,$error,$id]);
    return ['status'=>$state,'imported'=>$n,'duplicates'=>$dups,'skipped'=>$skip,'error'=>$error];
}
