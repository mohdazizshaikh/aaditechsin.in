<?php
declare(strict_types=1);
require_once __DIR__.'/creative-core.php';
function expertEnabled(array $cfg):bool {
    return ($cfg['growth']['enabled']??false)===true&&($cfg['expert']['enabled']??false)===true;
}
function expertKnowledge():array {
    $v=json_decode((string)file_get_contents(dirname(__DIR__).'/assets/expert-knowledge.json'),true,32,JSON_THROW_ON_ERROR);
    if(($v['version']??null)!==1||array_diff_key(growthCatalog(),$v['services'])||array_diff_key($v['services'],growthCatalog()))throw new RuntimeException('Knowledge coverage mismatch');
    return $v['services'];
}
function expertIntents():array {return ['assessment','planning','escalation'];}
function expertCreate(PDO $db,string $service,string $intent):int {
    creativeActive($db);
    if(!isset(expertKnowledge()[$service])||!in_array($intent,expertIntents(),true))throw new InvalidArgumentException('Invalid expert question');
    $id=growthCreate($db,['service_id'=>$service,'task_type'=>'research','title'=>'Technical expert: '.$service.' / '.$intent,'draft'=>'Awaiting private local expert draft.']);
    $db->prepare("UPDATE growth_tasks SET status='regeneration_requested' WHERE id=?")->execute([$id]);
    $db->prepare('INSERT INTO growth_expert(task_id,intent) VALUES(?,?)')->execute([$id,$intent]);
    growthAudit($db,$id,1,'expert_requested','owner');
    return $id;
}
function expertExport(PDO $db,int $id):array {
    creativeActive($db);
    $q=$db->prepare("SELECT t.id,t.revision,t.service_id,e.intent FROM growth_tasks t JOIN growth_expert e ON e.task_id=t.id WHERE t.id=? AND t.status='regeneration_requested'");$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);
    if(!$r)throw new RuntimeException('No pending expert request');
    return ['version'=>1,'task_id'=>(int)$r['id'],'revision'=>(int)$r['revision'],'service_id'=>$r['service_id'],'intent'=>$r['intent'],'service'=>growthCatalog()[$r['service_id']],'knowledge'=>expertKnowledge()[$r['service_id']]];
}
function expertValidate(array $v):array {
    $keys=['version','task_id','revision','model','assessment','questions','next_steps'];
    if(count($v)!==count($keys)||array_diff(array_keys($v),$keys)||($v['version']??null)!==1)throw new InvalidArgumentException('Invalid expert result');
    foreach(['task_id','revision'] as $k)if(!is_int($v[$k])||$v[$k]<1)throw new InvalidArgumentException('Invalid ID');
    if(!is_string($v['model'])||!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,99}$/D',$v['model'])||stripos($v['model'],'cloud')!==false)throw new InvalidArgumentException('Invalid local model');
    foreach(['assessment'=>500,'questions'=>400,'next_steps'=>500] as $k=>$limit){
        if(!is_string($v[$k])||trim($v[$k])===''||strlen($v[$k])>$limit||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$v[$k]))throw new InvalidArgumentException('Invalid text');
        if(preg_match('/guaranteed|100\s*%|https?:\/\/|[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}|\+?\d[\d .()-]{8,}\d/i',$v[$k]))throw new InvalidArgumentException('Unsupported claims or contact');
        $v[$k]=trim($v[$k]);
    }
    return $v;
}
function expertImport(PDO $db,array $v):bool {
    creativeActive($db);$v=expertValidate($v);
    $q=$db->prepare("SELECT t.service_id FROM growth_tasks t JOIN growth_expert e ON e.task_id=t.id WHERE t.id=? AND t.revision=? AND t.status='regeneration_requested' FOR UPDATE");$q->execute([$v['task_id'],$v['revision']]);$svc=$q->fetchColumn();
    if(!$svc)return false;
    $safe=expertKnowledge()[$svc]['escalation'];
    $draft="AI assessment (unverified):\n".$v['assessment']."\nQuestions:\n".$v['questions']."\nProposed next steps:\n".$v['next_steps']."\nMandatory human review: ".$safe;
    if(!growthReplace($db,$v['task_id'],$v['revision'],$draft))return false;
    growthAudit($db,$v['task_id'],$v['revision'],'expert_imported','private_cli');
    return true;
}
function expertCommand(PDO $db,array $cfg,string $command):?string {
    if(!preg_match('/^\\/(?:expert|experts)(?: |$)/D',$command))return null;
    if(!expertEnabled($cfg))return 'Technical expert disabled.';
    if($command==='/experts'){
        $lines=['Private technical drafts:'];
        foreach($db->query("SELECT t.id,t.revision,t.service_id,t.status,e.intent FROM growth_tasks t JOIN growth_expert e ON e.task_id=t.id ORDER BY t.id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC) as $r)$lines[]='#'.$r['id'].' rev '.$r['revision'].' '.$r['service_id'].' '.$r['intent'].' '.$r['status'];
        return count($lines)===1?'No expert requests.':implode("\n",$lines);
    }
    if(preg_match('/^\\/expert ([a-z0-9-]+) ([a-z-]+)$/D',$command,$m)&&isset(expertKnowledge()[$m[1]])&&in_array($m[2],expertIntents(),true)){
        $p=$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn();
        if($p===false||(int)$p!==0)return 'Growth paused.';
        $id=expertCreate($db,$m[1],$m[2]);return 'Expert draft requested: /task '.$id.' — private local model and human review required.';
    }
    return 'Use /expert SERVICE assessment|planning|escalation or /experts. Do not send customer data, credentials or free-text case details.';
}
