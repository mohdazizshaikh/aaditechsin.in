<?php
declare(strict_types=1);
require_once __DIR__.'/tender-review-lib.php';
require_once __DIR__.'/tender-intelligence-lib.php';
require_once __DIR__.'/discovery-feed-lib.php';

// The provider supplies normalized, permitted observations, never task selections.
function tenderPollConfig(array $cfg):array{
    $d=$cfg['discovery']??[];
    if(!discoveryEnabled($cfg)||($d['tender_review_enabled']??false)!==true||($d['tender_poll_enabled']??false)!==true)throw new RuntimeException('Tender polling disabled');
    $minutes=$d['tender_poll_minutes']??null;$feeds=$d['tender_feeds']??null;
    if(!is_int($minutes)||$minutes<60||$minutes>10080||!is_array($feeds)||count($feeds)<1||count($feeds)>5)throw new InvalidArgumentException('Tender poll configuration');
    $catalog=growthCatalog();$sources=[];
    foreach($feeds as $id=>$v){
        if(!is_string($id)||!isset(tenderSources()[$id])||$id==='mumbai-port-edp-archive'||!is_array($v))throw new InvalidArgumentException('Unknown active source');
        $keys=array_keys($v);sort($keys);
        if($keys!==['authorized','bearer_token','host','services','url']||!is_array($v['services'])||!array_is_list($v['services'])||!$v['services']||count($v['services'])>14)throw new InvalidArgumentException('Exact reviewed source configuration');
        $seen=[];foreach($v['services'] as $s){if(!is_string($s)||!isset($catalog[$s])||isset($seen[$s]))throw new InvalidArgumentException('Service preference');$seen[$s]=true;}
        $token=$v['bearer_token'];
        if($token!==null&&(!is_string($token)||!preg_match('/^[A-Za-z0-9._~+\/=\-]{16,512}$/D',$token)||str_starts_with($token,'REPLACE_')))throw new InvalidArgumentException('Private feed credential');
        $src=feedDefinition('tender-feed-'.$id,['authorized'=>$v['authorized'],'url'=>$v['url'],'host'=>$v['host'],'source_type'=>'tender']);
        $sources[$id]=$src+['official_source'=>$id,'services'=>$v['services'],'bearer_token'=>$token];
    }
    ksort($sources,SORT_STRING);return ['minutes'=>$minutes,'sources'=>$sources];
}
function tenderPollPrepare(string $raw,array $src,?DateTimeImmutable $now=null):array{
    if(strlen($raw)>65536)throw new InvalidArgumentException('Tender feed cap');
    $o=strictJsonDecode($raw,false,12);
    if(!$o instanceof stdClass)throw new InvalidArgumentException('Tender feed object');
    $d=get_object_vars($o);$keys=array_keys($d);sort($keys);
    if($keys!==['items','version']||$d['version']!==1||!is_array($d['items'])||count($d['items'])>25)throw new InvalidArgumentException('Tender feed contract');
    $counts=['review_candidate'=>0,'archived'=>0,'cancelled'=>0,'closed_by_observed_deadline'=>0,'requires_official_confirmation'=>0,'stale_observation'=>0,'deadline_unknown'=>0,'no_service_match'=>0,'outside_service_scope'=>0];
    if(!$d['items'])return ['selected'=>null,'counts'=>$counts,'notices'=>0];
    foreach($d['items'] as $item){
        if(!$item instanceof stdClass||($item->source??null)!==$src['official_source']||!property_exists($item,'selected_service')||$item->selected_service!==null)throw new InvalidArgumentException('Feed source or selection mismatch');
    }
    $scope=tenderShortlist($raw,$now);$selected=[];
    $rows=array_column($scope['rows'],null,'key');
    foreach($d['items'] as $item){
        $row=$rows[$item->source.':'.$item->reference];$state=$row['state'];
        if($state==='review_candidate'){
            foreach($src['services'] as $service){
                if(isset($row['service_matches'][$service])){$item->selected_service=$service;break;}
            }
            if($item->selected_service===null)$state='outside_service_scope';
            else $selected[]=$item;
        }
        ++$counts[$state];
    }
    return ['selected'=>$selected?json_encode(['version'=>1,'items'=>$selected],JSON_THROW_ON_ERROR):null,'counts'=>$counts,'notices'=>count($d['items'])];
}
function tenderPollStatus(PDO $db,array $cfg):array{
    $p=tenderPollConfig($cfg);$paused=$db->query('SELECT paused FROM growth_control WHERE id=1')->fetchColumn();
    if($paused===false||!in_array((string)$paused,['0','1'],true))throw new RuntimeException('Control unavailable');
    $q=$db->prepare("SELECT COUNT(CASE WHEN status='running' THEN 1 END) running,MAX(started_at) last_attempt,MAX(CASE WHEN started_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? MINUTE) THEN 1 ELSE 0 END) recent FROM growth_source_runs WHERE source_id=?");
    $rows=[];foreach($p['sources'] as $src){
        $q->execute([$p['minutes'],$src['id']]);$r=$q->fetch(PDO::FETCH_ASSOC);
        $rows[]=['source'=>$src['official_source'],'state'=>(string)$paused==='1'?'paused':((int)$r['running']>0?'unresolved':((int)$r['recent']>0?'not_due':'due')),'last_attempt_utc'=>$r['last_attempt']];
    }
    return ['interval_minutes'=>$p['minutes'],'sources'=>$rows,'advisory_only'=>true];
}
function tenderPollRun(PDO $db,callable $config,string $source,callable $fetch,?callable $clock=null):array{
    if($db->inTransaction())throw new LogicException('Unexpected transaction');
    $clock=$clock??static fn()=>new DateTimeImmutable('now');
    $initialCfg=$config();$initial=tenderPollConfig($initialCfg);$database=$initialCfg['database']??null;$intel=tenderIntelEnabled($initialCfg);$src=$initial['sources'][$source]??null;
    if(!$src)throw new InvalidArgumentException('Source not configured');
    $fresh=static function()use($config,$source,$src,$initial,$database,$intel):array{
        $cfg=$config();$p=tenderPollConfig($cfg);
        if(($cfg['database']??null)!==$database||tenderIntelEnabled($cfg)!==$intel||$p['minutes']!==$initial['minutes']||($p['sources'][$source]??null)!==$src)throw new RuntimeException('source_config_changed');
        return $cfg;
    };
    $result=['status'=>'blocked','created'=>0,'preserved'=>0,'skipped'=>0,'error'=>'','bid_authorized'=>false];
    $db->beginTransaction();
    try{$fresh();$id=feedAdmission($db,$src,$initial['minutes']);$db->commit();}
    catch(Throwable $e){$db->rollBack();if(!in_array($e->getMessage(),['Growth paused','source_run_unresolved','source_config_changed'],true))throw $e;$result['error']=$e->getMessage()==='Growth paused'?'growth_paused':$e->getMessage();return $result;}
    if($id===null){$result['status']='not_due';return $result;}
    try{
        // A read-only request, once admitted. Recheck policy and pause before importing.
        $fresh();$raw=$fetch($src);
        if(!is_string($raw))throw new InvalidArgumentException('Feed response');
        $db->beginTransaction();creativeActive($db);$cfg=$fresh();
        $q=$db->prepare("SELECT status FROM growth_source_runs WHERE id=? AND source_id=? FOR UPDATE");$q->execute([$id,$src['id']]);
        if($q->fetchColumn()!=='running')throw new RuntimeException('source_run_resolved');
        $now=$clock();$p=tenderPollPrepare($raw,$src,$now);
        $created=0;$preserved=0;
        if($p['selected']!==null){
            $plan=tenderReviewPlan($db,$cfg,$p['selected'],true,$now);
            $a=tenderReviewApply($db,$cfg,$p['selected'],$plan['digest'],$now);
            $created=$a['created'];$preserved=$a['preserved'];
        }
        $changes=['created'=>0,'preserved'=>0,'unverified_originals'=>0];
        if($intel&&$p['notices']>0){$plan=tenderIntelPlan($db,$cfg,$raw,true,$now);$changes=tenderIntelApply($db,$cfg,$raw,$plan['digest'],$now);}
        $fresh();$skip=$p['notices']-$created-$preserved;
        $q=$db->prepare("UPDATE growth_source_runs SET status='completed',imported_count=?,duplicate_count=?,skipped_count=?,finished_at=UTC_TIMESTAMP() WHERE id=? AND status='running'");
        $q->execute([$created,$preserved,$skip,$id]);if($q->rowCount()!==1)throw new RuntimeException('source_run_resolved');
        $db->commit();
        return ['status'=>'completed','created'=>$created,'preserved'=>$preserved,'skipped'=>$skip,'classification'=>$p['counts'],'amendment_reviews_created'=>$changes['created'],'amendment_reviews_preserved'=>$changes['preserved'],'unverified_originals'=>$changes['unverified_originals'],'error'=>'','bid_authorized'=>false];
    }catch(Throwable $e){
        if($db->inTransaction())$db->rollBack();
        $error=$e->getMessage()==='Growth paused'?'growth_paused':(in_array($e->getMessage(),['source_config_changed','source_run_resolved','dns_failed','unsafe_destination','transport_failed','http_status','content_type'],true)?$e->getMessage():'tender_feed_failed');
        $q=$db->prepare("UPDATE growth_source_runs SET status='failed',error_code=?,finished_at=UTC_TIMESTAMP() WHERE id=? AND status='running'");$q->execute([$error,$id]);
        $result['status']='failed';$result['error']=$error;return $result;
    }
}
