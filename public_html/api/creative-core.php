<?php
declare(strict_types=1);
require_once __DIR__.'/growth-core.php';
function creativeEnabled(array $cfg):bool {
    return ($cfg['growth']['enabled']??false)===true&&($cfg['creative']['enabled']??false)===true;
}
function creativeChannels():array {
    return ['facebook','instagram','linkedin','youtube','google-business','x','threads','telegram','blog'];
}
function creativeObjectives():array {
    return ['education','awareness','enquiry','case-planning'];
}
function creativeActive(PDO $db):void {
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $paused=$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn();
    if($paused===false||(int)$paused!==0)throw new RuntimeException('Growth paused');
}
function creativeCreate(PDO $db,string $service,string $channel,string $objective):int {
    creativeActive($db);
    if(!isset(growthCatalog()[$service])||!in_array($channel,creativeChannels(),true)||!in_array($objective,creativeObjectives(),true))throw new InvalidArgumentException('Invalid creative request');
    $id=growthCreate($db,['service_id'=>$service,'task_type'=>'content','title'=>'Creative draft: '.$service.' / '.$channel,'draft'=>'Awaiting local model generation. Not a publishable draft.']);
    $db->prepare("UPDATE growth_tasks SET status='regeneration_requested' WHERE id=?")->execute([$id]);
    $db->prepare('INSERT INTO growth_creative(task_id,channel,objective) VALUES(?,?,?)')->execute([$id,$channel,$objective]);
    growthAudit($db,$id,1,'creative_requested','owner');
    return $id;
}
function creativeExport(PDO $db,int $id):array {
    creativeActive($db);
    $q=$db->prepare("SELECT t.id,t.revision,t.service_id,c.channel,c.objective FROM growth_tasks t JOIN growth_creative c ON c.task_id=t.id WHERE t.id=? AND t.status='regeneration_requested'");
    $q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
    if(!$r)throw new RuntimeException('No pending generation request');
    $service=growthCatalog()[$r['service_id']]??null;
    if(!$service)throw new RuntimeException('Unknown catalog service');
    // Public catalog only. No leads, prospect evidence, contacts, old drafts or secrets.
    return ['version'=>1,'task_id'=>(int)$r['id'],'revision'=>(int)$r['revision'],'service'=>$service,'channel'=>$r['channel'],'objective'=>$r['objective']];
}
function creativeValidate(array $result):array {
    $keys=['version','task_id','revision','model','caption','visual_brief','video_brief','review_notes'];
    if(count($result)!==count($keys)||array_diff(array_keys($result),$keys)||($result['version']??null)!==1)throw new InvalidArgumentException('Invalid result shape');
    foreach(['task_id','revision'] as $k)if(!is_int($result[$k])||$result[$k]<1)throw new InvalidArgumentException('Invalid IDs');
    foreach(['model'=>100,'caption'=>900,'visual_brief'=>400,'video_brief'=>400,'review_notes'=>160] as $key=>$limit){
        $v=$result[$key];
        if(!is_string($v)||trim($v)===''||strlen($v)>$limit||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$v))throw new InvalidArgumentException('Invalid creative field');
        $result[$key]=trim($v);
    }
    // Review aid, not truth verification. Block common unsupported claims/contacts.
    $text=$result['caption']." ".$result['visual_brief']." ".$result['video_brief'];
    if(preg_match('/100\s*%|guaranteed|zero\s*risk|number\s*one|#1\b|certified|authori[sz]ed\s+partner|https?:\/\/|[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}|\+?\d[\d .()-]{8,}\d/i',$text))throw new InvalidArgumentException('Unsupported claim or contact detail');
    if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,99}$/D',$result['model']))throw new InvalidArgumentException('Invalid model label');
    return $result;
}
function creativeImport(PDO $db,array $result):bool {
    creativeActive($db);
    $v=creativeValidate($result);$id=$v['task_id'];$rev=$v['revision'];
    $q=$db->prepare("SELECT t.status,t.revision,c.channel FROM growth_tasks t JOIN growth_creative c ON c.task_id=t.id WHERE t.id=? FOR UPDATE");
    $q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
    if(!$r||$r['status']!=='regeneration_requested'||(int)$r['revision']!==$rev)return false;
    $limit=match($r['channel']){'x'=>280,'threads'=>500,default=>900};
    // Conservative byte caps, not provider-side character validation.
    if(strlen($v['caption'])>$limit)throw new InvalidArgumentException('Channel draft too long');
    $draft="Caption:\n".$v['caption']."\nVisual brief:\n".$v['visual_brief']."\nVideo brief:\n".$v['video_brief']."\nReview: ".$v['review_notes'];
    if(!growthReplace($db,$id,$rev,$draft))return false;
    $db->prepare('INSERT INTO growth_creative_results(task_id,revision,model_label,caption,visual_brief,video_brief,review_notes) VALUES(?,?,?,?,?,?,?)')->execute([$id,$rev,$v['model'],$v['caption'],$v['visual_brief'],$v['video_brief'],$v['review_notes']]);
    growthAudit($db,$id,$rev,'creative_imported','private_cli');
    return true;
}
function creativeCommand(PDO $db,array $cfg,string $command):?string {
    if(!preg_match('/^\/(?:createcontent|content|coverage)(?: |$)/D',$command))return null;
    if(!creativeEnabled($cfg))return 'Creative engine disabled or unconfigured.';
    if($command==='/content'){
        $lines=['Creative tasks (latest 10):'];
        foreach($db->query('SELECT t.id,t.revision,t.status,t.service_id,c.channel FROM growth_tasks t JOIN growth_creative c ON c.task_id=t.id ORDER BY t.id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC) as $r)
            $lines[]='#'.$r['id'].' rev '.$r['revision'].' '.$r['service_id'].' '.$r['channel'].' '.$r['status'];
        return count($lines)===1?'No creative tasks yet.':implode("\n",$lines);
    }
    if($command==='/coverage'){
        $rows=$db->query("SELECT t.service_id,COUNT(*) total,SUM(t.status='approved') approved FROM growth_tasks t JOIN growth_creative c ON c.task_id=t.id GROUP BY t.service_id")->fetchAll(PDO::FETCH_ASSOC);
        $map=array_column($rows,null,'service_id');$lines=['Content coverage (task inventory, not published posts):'];
        foreach(growthCatalog() as $id=>$s)$lines[]=$id.': '.($map[$id]['total']??0).' tasks / '.($map[$id]['approved']??0).' approved';
        return implode("\n",$lines);
    }
    if(preg_match('/^\/createcontent ([a-z0-9-]+) ([a-z-]+) ([a-z-]+)$/D',$command,$m)){
        if(!isset(growthCatalog()[$m[1]])||!in_array($m[2],creativeChannels(),true)||!in_array($m[3],creativeObjectives(),true))return 'Invalid service/channel/objective. Use /coverage and /help.';
        $paused=$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn();
        if($paused===false||(int)$paused!==0)return 'Growth paused.';
        $id=creativeCreate($db,$m[1],$m[2],$m[3]);
        return 'Generation requested: /task '.$id.' — local worker must export and return a draft. Nothing published.';
    }
    return 'Use /createcontent SERVICE CHANNEL OBJECTIVE, /content or /coverage.';
}
