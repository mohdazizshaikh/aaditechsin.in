<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/public_html/api/strict-json.php';
require_once dirname(__DIR__,2).'/public_html/api/discovery-core.php';
require_once dirname(__DIR__,2).'/public_html/api/creative-core.php';
function tenderSources():array{
    return [
        'mahatenders'=>'https://mahatenders.gov.in/nicgep/app',
        'cppp'=>'https://eprocure.gov.in/eprocure/app',
        'gem'=>'https://bidplus.gem.gov.in/all-bids',
        'nicsi'=>'https://nicsi.nic.in/nicsi/nicsi-gem-bids/',
        'mumbai-port-meed'=>'https://www.mumbaiport.gov.in/show_tenders.php?catid=3&depid=2&lang=1',
        'mumbai-port-edp-archive'=>'https://mumbaiport.gov.in/tendersArc.php?arch=1&catid=3&depid=4&lang=1&level=0'
    ];
}
function tenderAliases():array{
    return [
        'infrastructure'=>['lan wiring','network cabling','network switches','storage switches','network router','network routers'],
        'software-web'=>['web portal development','application development','software maintenance'],
        'it-amc'=>['ict maintenance','computer amc','ict infrastructure maintenance'],
        'cloud'=>['object storage','cloud hosting'],
        'security'=>['network filter gateway','network firewall','security gateway'],
        'data-backup'=>['backup solution','backup software'],
        'it-procurement'=>['desktop computers','laptops','tablet computer'],
        'smart-office'=>['biometric access control','cctv camera','cctv surveillance'],
        'special-projects'=>['office it setup','temporary it infrastructure'],
        'video-conferencing'=>['vc unit','video conferencing camera','video conference system','ptz camera'],
        'auditorium-av'=>['auditorium av','auditorium sound'],
        'professional-sound'=>['surround sound system','audio speakerphone','public address system'],
        'av-maintenance'=>['audio visual modernization','audio-visual modernization','audio visual system','audio-visual system','av system maintenance'],
        'data-recovery'=>['hard disk recovery','ssd recovery']
    ];
}
function tenderMatch(string $title):array{
    $out=discoveryMatch($title);
    foreach(tenderAliases() as $service=>$terms){
        foreach($terms as $term)if(preg_match('/(?<![a-z0-9])'.preg_quote($term,'/').'(?![a-z0-9])/i',$title))$out[$service][]=$term;
    }
    foreach($out as &$hits){$hits=array_values(array_unique($hits));sort($hits,SORT_STRING);}unset($hits);
    ksort($out,SORT_STRING);return $out;
}
function tenderText(mixed $v,int $max):string{
    if(!is_string($v)||trim($v)!==$v||$v===''||strlen($v)>$max||!preg_match('//u',$v)||preg_match('/[\x00-\x1f\x7f<>]|[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}|\+?\d[\d .()-]{8,}\d/i',$v))throw new InvalidArgumentException('Invalid notice text');
    return $v;
}
function tenderTime(string $value,string $format,DateTimeZone $tz):DateTimeImmutable{
    $d=DateTimeImmutable::createFromFormat('!'.$format,$value,$tz);
    if(!$d||$d->format($format)!==$value)throw new InvalidArgumentException('Invalid notice date');
    return $d;
}
function tenderShortlist(string $raw,?DateTimeImmutable $now=null):array{
    if(strlen($raw)>65536)throw new InvalidArgumentException('Notice batch cap');
    $doc=strictJsonDecode($raw,false,12);
    if(!$doc instanceof stdClass)throw new InvalidArgumentException('Object required');
    $d=get_object_vars($doc);$keys=array_keys($d);sort($keys);
    if($keys!==['items','version']||$d['version']!==1||!is_array($d['items'])||count($d['items'])<1||count($d['items'])>25)throw new InvalidArgumentException('Notice batch contract');
    $tz=new DateTimeZone('Asia/Kolkata');$now=($now??new DateTimeImmutable('now'))->setTimezone($tz);$today=$now->format('Y-m-d');
    $rows=[];$seen=[];$sources=tenderSources();
    foreach($d['items'] as $o){
        if(!$o instanceof stdClass)throw new InvalidArgumentException('Notice object');
        $v=get_object_vars($o);$keys=array_keys($v);sort($keys);
        if($keys!==['deadline_ist','location','observed_on','reference','selected_service','source','state','title']||!is_string($v['source'])||!isset($sources[$v['source']])||!is_string($v['reference'])||!preg_match('/^[A-Z0-9][A-Z0-9 ._\/-]{1,79}$/D',$v['reference'])||!is_string($v['state'])||!in_array($v['state'],['listed','unconfirmed','archived','cancelled'],true)||!is_string($v['observed_on']))throw new InvalidArgumentException('Notice contract');
        $ref=$v['reference'];$key=$v['source'].':'.$ref;
        if(isset($seen[$key]))throw new InvalidArgumentException('Duplicate notice');$seen[$key]=true;
        $title=tenderText($v['title'],500);$location=tenderText($v['location'],120);
        $observed=tenderTime($v['observed_on'],'Y-m-d',$tz);
        if($v['observed_on']>$today||$observed<$now->setTime(0,0)->modify('-180 days'))throw new InvalidArgumentException('Observation date');
        $deadline=null;
        if($v['deadline_ist']!==null){
            if(!is_string($v['deadline_ist']))throw new InvalidArgumentException('Deadline');
            $deadline=tenderTime($v['deadline_ist'],'Y-m-d\TH:i:s',$tz);
            if($deadline>$now->modify('+365 days'))throw new InvalidArgumentException('Deadline horizon');
        }
        $matches=tenderMatch($title);$selected=$v['selected_service'];
        if($selected!==null&&(!is_string($selected)||!isset($matches[$selected])))throw new InvalidArgumentException('Selected service must match evidence');
        $state=match(true){
            $v['state']==='cancelled'=>'cancelled',
            $v['state']==='archived'=>'archived',
            $deadline!==null&&$deadline<=$now=>'closed_by_observed_deadline',
            $v['state']==='unconfirmed'=>'requires_official_confirmation',
            $observed<$now->setTime(0,0)->modify('-1 day')=>'stale_observation',
            $deadline===null=>'deadline_unknown',
            !$matches=>'no_service_match',
            default=>'review_candidate'
        };
        $rows[]=['key'=>$key,'source'=>$v['source'],'reference'=>$ref,'source_url'=>$sources[$v['source']],
            'title'=>$title,'location'=>$location,'observed_on'=>$v['observed_on'],'deadline_ist'=>$v['deadline_ist'],
            'source_state'=>$v['state'],'state'=>$state,'service_matches'=>$matches,'selected_service'=>$selected,
            'urgency'=>$state==='review_candidate'?($deadline<=$now->modify('+3 days')?'within_3_days':($deadline<=$now->modify('+7 days')?'within_7_days':'later')):null];
    }
    usort($rows,static fn($a,$b)=>[$a['state']!=='review_candidate',$a['deadline_ist']??'9999',$a['key']]<=>[$b['state']!=='review_candidate',$b['deadline_ist']??'9999',$b['key']]);
    return ['version'=>1,'scope'=>'tender-review-only','review_day_ist'=>$today,'rows'=>$rows,'bid_authorized'=>false];
}
function tenderReviewPlan(PDO $db,array $cfg,string $raw,bool $lock=false,?DateTimeImmutable $now=null):array{
    if(!$db->inTransaction()||!discoveryEnabled($cfg)||($cfg['discovery']['tender_review_enabled']??false)!==true)throw new RuntimeException('Tender review disabled');
    $scope=tenderShortlist($raw,$now);$suffix=$lock?' FOR UPDATE':'';
    $paused=$db->query('SELECT paused FROM growth_control WHERE id=1'.$suffix)->fetchColumn();
    if((string)$paused!=='0')throw new RuntimeException('Growth paused');
    $q=$db->prepare("SELECT id,service_id,status,revision FROM growth_tasks WHERE task_type='research' AND title=? ORDER BY id LIMIT 2".$suffix);
    $tasks=[];
    foreach($scope['rows'] as $row){
        if($row['selected_service']===null)continue;
        if($row['state']!=='review_candidate')throw new RuntimeException('Selected notice no longer eligible');
        $title='Tender review: '.hash('sha256',$row['key']);
        $q->execute([$title]);$found=$q->fetchAll(PDO::FETCH_ASSOC);
        if(count($found)>1)throw new RuntimeException('Ambiguous existing task');
        $old=$found[0]??null;
        if($old&&$old['service_id']!==$row['selected_service'])throw new RuntimeException('Existing service differs; manual review');
        $tasks[]=['key'=>$row['key'],'title'=>$title,'existing'=>$old,'operation'=>$old?'preserve':'create'];
    }
    if(!$tasks)throw new InvalidArgumentException('Select at least one eligible notice');
    $plan=['scope'=>$scope,'tasks'=>$tasks,'create_count'=>count(array_filter($tasks,static fn($x)=>$x['operation']==='create'))];
    $plan['digest']=hash('sha256',json_encode($plan,JSON_THROW_ON_ERROR));return $plan;
}
function tenderReviewApply(PDO $db,array $cfg,string $raw,string $digest,?DateTimeImmutable $now=null):array{
    if(!$db->inTransaction()||!preg_match('/^[a-f0-9]{64}$/D',$digest))throw new InvalidArgumentException('Reviewed digest required');
    $db->exec('SAVEPOINT tender_review_batch');
    try{
        $plan=tenderReviewPlan($db,$cfg,$raw,true,$now);
        if(!hash_equals($plan['digest'],$digest))throw new RuntimeException('Review plan changed');
        $rows=array_column($plan['scope']['rows'],null,'key');$ids=[];
        foreach($plan['tasks'] as $task){
            if($task['operation']!=='create')continue;
            $row=$rows[$task['key']];
            $draft="Internal tender eligibility review, not a qualified lead or bid authorization.\nReference: ".$row['reference']."\nOfficial listing: ".$row['source_url']."\nObserved ".$row['observed_on']." IST; listed deadline ".$row['deadline_ist']." IST (recheck corrigenda).\nScope summary: ".$row['title']."\nLocation: ".$row['location']."\nCheck current official status, full tender/BOQ, eligibility, partner capacity, OEM authorizations, fees/EMD, delivery obligations and submission deadline. Do not send outreach or submit a bid. Existing tasks are never automatically revised.";
            $id=growthCreate($db,['service_id'=>$row['selected_service'],'task_type'=>'research','title'=>$task['title'],'draft'=>$draft]);
            growthAudit($db,$id,1,'tender_review_created','private_cli');$ids[]=$id;
        }
        $db->exec('RELEASE SAVEPOINT tender_review_batch');
        return ['created'=>count($ids),'preserved'=>count($plan['tasks'])-count($ids),'task_ids'=>$ids,'bid_authorized'=>false];
    }catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT tender_review_batch');$db->exec('RELEASE SAVEPOINT tender_review_batch');throw $e;}
}
