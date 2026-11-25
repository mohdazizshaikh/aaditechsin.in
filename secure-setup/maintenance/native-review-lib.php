<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/media-core.php';
function nativeReviewCanonical(mixed $v):string{
    $sort=static function(mixed $x)use(&$sort):mixed{
        if(is_array($x)){
            if(!array_is_list($x))ksort($x,SORT_STRING);
            foreach($x as &$child)$child=$sort($child);unset($child);
        }
        return $x;
    };
    return json_encode($sort($v),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_LINE_TERMINATORS);
}
function nativeReviewHash(mixed $v):bool{return is_string($v)&&preg_match('/^[a-f0-9]{64}$/D',$v)===1;}
function nativeReviewKeys(array $r,array $expected):bool{$keys=array_keys($r);sort($keys,SORT_STRING);sort($expected,SORT_STRING);return $keys===$expected;}
function nativeReviewScope(array $cfg,string $raw,string $digest,?DateTimeImmutable $now=null):array{
    if(!mediaEnabled($cfg)||($cfg['media']['native_review_enabled']??false)!==true)throw new RuntimeException('Disabled');
    if(strlen($raw)>65536||!nativeReviewHash($digest)||!hash_equals($digest,hash('sha256',$raw)))throw new InvalidArgumentException('Packet integrity');
    $p=strictJsonDecode($raw,true,16);
    $day=($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
    if(!is_array($p)||!nativeReviewKeys($p,['version','scope','review_day_utc','plan_sha256','index_sha256','decision_sha256','publication_authorized','items'])||$p['version']!==1||$p['scope']!=='native-video-owner-review'||$p['review_day_utc']!==$day||$p['publication_authorized']!==false||nativeReviewCanonical($p)."\n"!==$raw)throw new InvalidArgumentException('Packet scope');
    foreach(['plan_sha256','index_sha256','decision_sha256'] as $k)if(!nativeReviewHash($p[$k]))throw new InvalidArgumentException('Packet binding');
    if(!is_array($p['items'])||!array_is_list($p['items'])||count($p['items'])<1||count($p['items'])>12)throw new InvalidArgumentException('Packet size');
    $seen=[];
    foreach($p['items'] as $item){
        if(!is_array($item)||!nativeReviewKeys($item,['review_sha256','review'])||!nativeReviewHash($item['review_sha256'])||!is_array($item['review']))throw new InvalidArgumentException('Review item');
        $r=$item['review'];
        if(!nativeReviewKeys($r,['version','scope','task_id','revision','channel','dimensions','model_sha256','job_sha256','seed','poster_sha256','candidate_sha256'])||$r['version']!==1||$r['scope']!=='native-video-review-only'||!is_int($r['task_id'])||$r['task_id']<1||$r['task_id']>999999999999999||!is_int($r['revision'])||$r['revision']<1||$r['revision']>999999999||!is_int($r['seed'])||$r['seed']<0||$r['seed']>2147483647||!is_string($r['channel'])||$r['dimensions']!==mediaDimensions($r['channel'])||isset($seen[$r['task_id']]))throw new InvalidArgumentException('Review profile');
        foreach(['model_sha256','job_sha256','poster_sha256','candidate_sha256'] as $k)if(!nativeReviewHash($r[$k]))throw new InvalidArgumentException('Review binding');
        if(!hash_equals($item['review_sha256'],hash('sha256',nativeReviewCanonical($r)."\n")))throw new InvalidArgumentException('Review digest');
        $seen[$r['task_id']]=true;
    }
    return $p;
}
function nativeReviewPlan(PDO $db,array $cfg,string $raw,string $digest,?DateTimeImmutable $now=null):array{
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $p=nativeReviewScope($cfg,$raw,$digest,$now);creativeActive($db);
    $rows=[];
    $source=$db->prepare('SELECT service_id FROM growth_tasks WHERE id=? FOR UPDATE');
    $find=$db->prepare("SELECT id,status,revision,draft FROM growth_tasks WHERE service_id=? AND task_type='system' AND title=? ORDER BY id LIMIT 2 FOR UPDATE");
    foreach($p['items'] as $item){
        $r=$item['review'];$job=mediaExport($db,$r['task_id'],$r['revision']);
        if(!hash_equals($r['job_sha256'],hash('sha256',nativeReviewCanonical($job)))||$r['channel']!==$job['channel']||$r['dimensions']!==$job['dimensions'])throw new RuntimeException('Approved source changed');
        $source->execute([$r['task_id']]);$service=$source->fetchColumn();
        if(!is_string($service)||!isset(growthCatalog()[$service]))throw new RuntimeException('Service unavailable');
        $title='Native video review: task '.$r['task_id'].' r'.$r['revision'].' '.$item['review_sha256'];
        $find->execute([$service,$title]);$old=$find->fetchAll(PDO::FETCH_ASSOC);
        if(count($old)>1)throw new RuntimeException('Ambiguous existing review');
        $existing=$old[0]??null;
        if($existing){$existing['draft_sha256']=hash('sha256',$existing['draft']);unset($existing['draft']);}
        $rows[]=['source_task_id'=>$r['task_id'],'source_revision'=>$r['revision'],'service_id'=>$service,'title'=>$title,'review_sha256'=>$item['review_sha256'],'poster_sha256'=>$r['poster_sha256'],'candidate_sha256'=>$r['candidate_sha256'],'model_sha256'=>$r['model_sha256'],'existing'=>$existing];
    }
    $plan=['packet_sha256'=>$digest,'rows'=>$rows,'create_count'=>count(array_filter($rows,static fn($r)=>$r['existing']===null)),'publication_authorized'=>false];
    $plan['plan_sha256']=hash('sha256',nativeReviewCanonical($plan));return $plan;
}
function nativeReviewDraft(array $r,string $digest):string{
    return "Internal editorial review only. Source task ".$r['source_task_id']." revision ".$r['source_revision'].".\nPrivate review SHA-256: ".$r['review_sha256']."\nPoster SHA-256: ".$r['poster_sha256']."\nNative video SHA-256: ".$r['candidate_sha256']."\nModel inventory SHA-256: ".$r['model_sha256']."\nHandoff packet SHA-256: ".$digest."\nIndependently inspect actual private bytes, model provenance, rights, brand, accuracy, and privacy. Metadata only; no verified inference, media registration, upload or publication authorized.";
}
function nativeReviewApply(PDO $db,array $cfg,string $raw,string $digest,string $planDigest,?DateTimeImmutable $now=null):array{
    if(!$db->inTransaction()||!nativeReviewHash($planDigest))throw new InvalidArgumentException('Reviewed plan required');
    $db->exec('SAVEPOINT native_review_bridge');
    try{
        $plan=nativeReviewPlan($db,$cfg,$raw,$digest,$now);
        if(!hash_equals($plan['plan_sha256'],$planDigest))throw new RuntimeException('Plan changed');
        $ids=[];
        foreach($plan['rows'] as $r){
            if($r['existing']!==null)continue;
            $draft=nativeReviewDraft($r,$digest);
            $id=growthCreate($db,['service_id'=>$r['service_id'],'task_type'=>'system','title'=>$r['title'],'draft'=>$draft]);
            growthAudit($db,$id,1,'native_video_review_created','private_cli');$ids[]=$id;
        }
        $db->exec('RELEASE SAVEPOINT native_review_bridge');
        return ['created'=>count($ids),'preserved'=>count($plan['rows'])-count($ids),'task_ids'=>$ids,'publication_authorized'=>false];
    }catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT native_review_bridge');$db->exec('RELEASE SAVEPOINT native_review_bridge');throw $e;}
}
