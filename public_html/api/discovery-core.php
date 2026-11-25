<?php
declare(strict_types=1);
require_once __DIR__.'/growth-core.php';
function discoveryEnabled(array $cfg):bool {
    return ($cfg['growth']['enabled']??false)===true&&($cfg['discovery']['enabled']??false)===true;
}
function discoveryTerms():array {
    return json_decode((string)file_get_contents(dirname(__DIR__).'/assets/opportunity-matching.json'),true,512,JSON_THROW_ON_ERROR);
}
function discoveryMatch(string $evidence):array {
    $out=[];
    foreach(discoveryTerms() as $id=>$terms){
        $hits=[];
        foreach($terms as $term)if(preg_match('/(?<![a-z0-9])'.preg_quote($term,'/').'(?![a-z0-9])/i',$evidence))$hits[]=$term;
        if($hits)$out[$id]=$hits;
    }
    return $out;
}
function discoveryNormalize(array $in,?string $today=null):array {
    $fields=['source_type','source_url','organization','location_label','evidence','observed_on','signal_type','service_id'];
    if(count($in)!==count($fields)||array_diff(array_keys($in),$fields))throw new InvalidArgumentException('Unexpected opportunity fields');
    foreach($fields as $f){
        if(!is_string($in[$f]??null)||preg_match('//u',$in[$f])!==1)throw new InvalidArgumentException('Invalid field');
        $in[$f]=trim($in[$f]);
        if($in[$f]===''||preg_match('/[\x00-\x1F\x7F]/',$in[$f]))throw new InvalidArgumentException('Invalid text');
    }
    if(!in_array($in['source_type'],['tender','company_announcement','authorized_directory','referral','manual_research'],true)||
       !in_array($in['signal_type'],['explicit_request','expansion','upgrade','general_fit'],true)||
       !isset(growthCatalog()[$in['service_id']]))throw new InvalidArgumentException('Unknown source, signal or service');
    foreach(['source_url'=>500,'organization'=>160,'location_label'=>120,'evidence'=>1200] as $k=>$max)if(strlen($in[$k])>$max)throw new InvalidArgumentException('Field too long');
    $url=parse_url($in['source_url']);
    // Evidence links only: never fetched by this code. No credentials, query, fragment, or private IP.
    if(!filter_var($in['source_url'],FILTER_VALIDATE_URL)||!is_array($url)||($url['scheme']??'')!=='https'||
       isset($url['user'])||isset($url['pass'])||isset($url['query'])||isset($url['fragment'])||isset($url['port'])||
       !preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/iD',$url['host']??'')||
       preg_match('/(?:^|\.)(localhost|local|internal|invalid)$/i',$url['host']))throw new InvalidArgumentException('Use a clean public HTTPS evidence URL');
    $in['source_url']='https://'.strtolower($url['host']).($url['path']??'/');
    // No personal-contact fields; reject obvious email/telephone text in evidence.
    foreach(['organization','location_label','evidence'] as $k){
        if(preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}|\+?\d[\d .()-]{8,}\d/i',$in[$k]))throw new InvalidArgumentException('Remove personal contact details');
    }
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$in['observed_on'],new DateTimeZone('UTC'));
    $now=new DateTimeImmutable($today??gmdate('Y-m-d'),new DateTimeZone('UTC'));
    if(!$d||$d->format('Y-m-d')!==$in['observed_on']||$d>$now||$d<$now->modify('-180 days'))throw new InvalidArgumentException('Observation date outside 180-day window');
    $hits=discoveryMatch($in['evidence'])[$in['service_id']]??[];
    if(!$hits)throw new InvalidArgumentException('No matching service evidence');
    // Ranking only; source/intent supplied by importer, never independently verified.
    $base=['explicit_request'=>40,'expansion'=>25,'upgrade'=>25,'general_fit'=>10][$in['signal_type']];
    $age=(int)$d->diff($now)->days;
    $in['relevance_score']=min(100,$base+min(30,count($hits)*10)+($age<=30?20:($age<=90?10:0)));
    $in['fingerprint']=hash('sha256',strtolower($in['organization'])."\n".$in['source_url']."\n".$in['service_id']);
    return $in;
}
function discoveryImport(PDO $db,array $input):array {
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $v=discoveryNormalize($input);
    // One opportunity per organization/evidence URL/service, immutable on re-import.
    $q=$db->prepare('SELECT id FROM growth_opportunities WHERE fingerprint=?');$q->execute([$v['fingerprint']]);
    if(($id=$q->fetchColumn())!==false)return ['id'=>(int)$id,'status'=>'duplicate'];
    $db->exec('SAVEPOINT opportunity_intake');
    try{
    $q=$db->prepare('INSERT INTO growth_opportunities(fingerprint,source_type,source_url,organization,location_label,evidence,observed_on,signal_type,service_id,relevance_score) VALUES(?,?,?,?,?,?,?,?,?,?)');
    try{$q->execute([$v['fingerprint'],$v['source_type'],$v['source_url'],$v['organization'],$v['location_label'],$v['evidence'],$v['observed_on'],$v['signal_type'],$v['service_id'],$v['relevance_score']]);}
    catch(PDOException $e){if(($e->errorInfo[1]??0)!==1062)throw $e;throw new RuntimeException('Concurrent duplicate; retry import');}
    $id=(int)$db->lastInsertId();growthAudit($db,null,null,'opportunity_import_'.$id,'private_cli');
    $db->exec('RELEASE SAVEPOINT opportunity_intake');
    return ['id'=>$id,'status'=>'pending_review'];
    }catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT opportunity_intake');$db->exec('RELEASE SAVEPOINT opportunity_intake');throw $e;}
}
function discoveryCommand(PDO $db,array $cfg,string $cmd):?string {
    if(!preg_match('/^\/(?:opportunities|opportunity|qualify|dismiss|draftoutreach|outreach|tenders)(?: |$)/',$cmd))return null;
    if(!discoveryEnabled($cfg))return 'Discovery disabled or unconfigured.';
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    if($cmd==='/tenders'){
        if(($cfg['discovery']['tender_review_enabled']??false)!==true)return 'Tender review disabled.';
        $lines=['Pending tender research tasks (up to 10; recorded notices, not current bid status):'];
        foreach($db->query("SELECT id,service_id,title FROM growth_tasks WHERE task_type='research' AND status='pending_review' AND title LIKE 'Tender review: %' ORDER BY id LIMIT 10")->fetchAll(PDO::FETCH_ASSOC) as $r)$lines[]=$r['title'].' | '.$r['service_id'].' — /task '.$r['id'];
        $lines[]='Recheck official deadlines and eligibility; no bid or outreach authorized.';
        return implode("\n",$lines);
    }
    if($cmd==='/opportunities'){
        $lines=['Pending opportunities (top 10; ranking, not probability):'];
        foreach($db->query("SELECT id,service_id,relevance_score FROM growth_opportunities WHERE status='pending_review' ORDER BY relevance_score DESC,id LIMIT 10")->fetchAll(PDO::FETCH_ASSOC) as $r)$lines[]='#'.$r['id'].' '.$r['service_id'].' '.$r['relevance_score'].'/100 — /opportunity '.$r['id'];
        return count($lines)===1?'No imported pending opportunities.':implode("\n",$lines);
    }
    if($cmd==='/outreach'){
        $lines=['Outreach draft inventory (not sent):'];
        foreach($db->query("SELECT t.status,COUNT(*) total FROM growth_tasks t JOIN growth_opportunities o ON o.outreach_task_id=t.id GROUP BY t.status")->fetchAll(PDO::FETCH_ASSOC) as $r)$lines[]=$r['status'].': '.$r['total'];
        $lines[]='Outbound delivery and replies not connected.';
        return implode("\n",$lines);
    }
    if(!preg_match('/^\/(opportunity|qualify|dismiss|draftoutreach) ([1-9][0-9]{0,14})$/D',$cmd,$m))return 'Use /opportunities, /opportunity ID, /qualify ID, /dismiss ID, /draftoutreach ID or /outreach.';
    $id=(int)$m[2];$action=$m[1];
    // Acquire control before opportunity locks, matching follow-up and feed lock order.
    $paused=null;
    if($action==='draftoutreach')$paused=$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn();
    $q=$db->prepare('SELECT * FROM growth_opportunities WHERE id=? FOR UPDATE');$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
    if(!$r)return 'Opportunity not found.';
    if($action==='opportunity')return '#'.$id.' '.$r['status']."\n".$r['organization'].' | '.$r['location_label']."\n".$r['service_id'].' | ranking '.$r['relevance_score']."/100\n".$r['source_type'].' / '.$r['signal_type']."\n".$r['observed_on']."\n".$r['source_url']."\n".$r['evidence']."\nImported evidence, not verified buying intent. Owner must check source.";
    if($action==='qualify'||$action==='dismiss'){
        if($r['status']!=='pending_review')return 'Opportunity is no longer pending review.';
        $state=$action==='qualify'?'qualified':'rejected';
        $db->exec('SAVEPOINT opportunity_review');
        try{
            $db->prepare('UPDATE growth_opportunities SET status=? WHERE id=?')->execute([$state,$id]);
            growthAudit($db,null,null,'opportunity_'.$action.'_'.$id,'owner');
            $db->exec('RELEASE SAVEPOINT opportunity_review');
        }catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT opportunity_review');$db->exec('RELEASE SAVEPOINT opportunity_review');throw $e;}
        return $state.' — owner review recorded. No message sent.';
    }
    if($r['status']!=='qualified')return 'Qualify opportunity first.';
    if($r['outreach_task_id']!==null)return 'Existing outreach draft /task '.$r['outreach_task_id'];
    if($paused===false||$paused===null||(int)$paused!==0)return 'Growth paused; resume before creating outreach draft.';
    $service=growthCatalog()[$r['service_id']]['title'];
    $draft="Review-only outreach concept for ".$r['organization']."\nService: ".$service."\nHello, Aaditechs coordinates ".$service." services. If you are currently evaluating this area, would a requirements discussion be useful?\nBefore sending: verify current need, recipient permission, channel policy and service availability. Source: ".$r['source_url']."\nNo verified contact or delivery; edit and review first.";
    $db->exec('SAVEPOINT opportunity_draft');
    try{
    $task=growthCreate($db,['service_id'=>$r['service_id'],'task_type'=>'outreach','title'=>'Outreach concept for opportunity #'.$id,'draft'=>$draft]);
    $db->prepare('UPDATE growth_opportunities SET outreach_task_id=? WHERE id=?')->execute([$task,$id]);
    growthAudit($db,$task,1,'opportunity_draft_linked','owner');
    $db->exec('RELEASE SAVEPOINT opportunity_draft');
    return 'Review-only outreach draft created: /task '.$task.' — no message sent.';
    }catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT opportunity_draft');$db->exec('RELEASE SAVEPOINT opportunity_draft');throw $e;}
}
