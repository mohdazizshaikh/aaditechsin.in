<?php
declare(strict_types=1);
// Review-only task foundation. Approval never triggers external execution.
function growthCatalog(): array {
    $data=json_decode((string)file_get_contents(dirname(__DIR__).'/assets/service-registry.json'),true,512,JSON_THROW_ON_ERROR);
    return array_column($data['services'],null,'id');
}
function growthAudit(PDO $db,?int $id,?int $rev,string $action,string $actor): void {
    $db->prepare('INSERT INTO growth_audit(task_id,revision,action,actor) VALUES(?,?,?,?)')->execute([$id,$rev,$action,$actor]);
}
function growthCreate(PDO $db,array $input): int {
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $service=$input['service_id']??null;$type=$input['task_type']??null;
    $title=$input['title']??null;$draft=$input['draft']??null;
    if(!is_string($service)||!isset(growthCatalog()[$service])||
        !in_array($type,['research','seo','content','outreach','social','system'],true)||
        !is_string($title)||trim($title)===''||strlen($title)>160||
        !is_string($draft)||trim($draft)===''||strlen($draft)>2000)
        throw new InvalidArgumentException('Invalid service, type, title or draft');
    $db->prepare('INSERT INTO growth_tasks(service_id,task_type,title,draft) VALUES(?,?,?,?)')->execute([$service,$type,$title,$draft]);
    $id=(int)$db->lastInsertId();growthAudit($db,$id,1,'created','private_cli');return $id;
}
function growthReplace(PDO $db,int $id,int $rev,string $draft): bool {
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    if(trim($draft)===''||strlen($draft)>2000)throw new InvalidArgumentException('Invalid draft');
    $q=$db->prepare("UPDATE growth_tasks SET draft=?,status='pending_review' WHERE id=? AND revision=? AND status='regeneration_requested'");
    $q->execute([$draft,$id,$rev]);
    if($q->rowCount()!==1)return false;
    growthAudit($db,$id,$rev,'draft_replaced','private_cli');return true;
}
function growthOwnerUpdate(array $u,array $cfg,string $header,?int $now=null): bool {
    $t=$cfg['growth']['telegram']??[];
    if(($cfg['growth']['enabled']??false)!==true||($t['enabled']??false)!==true)return false;
    $secret=$t['webhook_secret']??'';
    if(!is_string($secret)||!preg_match('/^[A-Za-z0-9_-]{32,256}$/D',$secret)||str_starts_with($secret,'REPLACE_')||!hash_equals($secret,$header))return false;
    $owner=$t['owner_user_id']??'';$chat=$t['owner_chat_id']??'';
    if(!is_string($owner)||!is_string($chat)||!preg_match('/^[1-9][0-9]{0,15}$/D',$owner)||$owner!==$chat)return false;
    $m=$u['message']??null;
    if(!is_array($m)||!is_int($u['update_id']??null)||$u['update_id']<0||!is_int($m['date']??null))return false;
    if(!is_int($m['from']['id']??null)||!is_int($m['chat']['id']??null)||($m['from']['is_bot']??true)!==false)return false;
    $age=($now??time())-$m['date'];
    return $age>=-60&&$age<=3600&&($m['chat']['type']??'')==='private'&&
        (string)$m['from']['id']===$owner&&(string)$m['chat']['id']===$chat&&
        is_string($m['text']??null)&&strlen($m['text'])<=200;
}
function growthCommand(PDO $db,string $command,array $cfg=[]): string {
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $command=trim($command);
    if(in_array($command,['/start','/help'],true))return "Aaditechs V9.6 owner controls\n/today /health /leads /visitors /pages /services /sources /calls\n/opportunities /opportunity ID /qualify ID /dismiss ID /draftoutreach ID /outreach /discoveryruns\n/createcontent SERVICE CHANNEL OBJECTIVE /content /coverage\n/creativeworker /retrycreative ID REV\n/media ID /approvemedia ID REV SHA256 /rejectmedia ID REV SHA256\n/socialtarget /social /schedulepost TASK REV CHANNEL_ID UTC_TIME /cancelpost ID\n/errors /notifications /reporthistory /notifydaily on|off /notifyerrors on|off\n/approvals /task ID\n/approve ID REV /reject ID REV /regenerate ID REV\n/pause /resume\nApproval records review only; separate Telegram channel scheduling required.";
    require_once __DIR__.'/reports-core.php';
    $report=reportCommand($db,$cfg,$command);
    if($report!==null)return $report;
    require_once __DIR__.'/social-core.php';
    $social=socialCommand($db,$cfg,$command);
    if($social!==null)return $social;
    require_once __DIR__.'/media-core.php';
    $media=mediaCommand($db,$cfg,$command);
    if($media!==null)return $media;
    require_once __DIR__.'/creative-transport-core.php';
    $transport=creativeTransportCommand($db,$cfg,$command);
    if($transport!==null)return $transport;
    require_once __DIR__.'/creative-core.php';
    $creative=creativeCommand($db,$cfg,$command);
    if($creative!==null)return $creative;
    if($command==='/discoveryruns'){
        if(($cfg['growth']['enabled']??false)!==true||($cfg['discovery']['enabled']??false)!==true||($cfg['discovery']['feeds_enabled']??false)!==true)return 'External discovery feeds disabled or unconfigured.';
        $lines=['Recent source runs (up to 10; not confirmed leads):'];
        foreach($db->query('SELECT source_id,status,imported_count,duplicate_count,skipped_count,error_code FROM growth_source_runs ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC) as $r)
            $lines[]=$r['source_id'].' '.$r['status'].' | imported '.$r['imported_count'].' duplicate '.$r['duplicate_count'].' skipped '.$r['skipped_count'].($r['error_code']!==''?' | '.$r['error_code']:'');
        return count($lines)===1?'No source run recorded.':implode("\n",$lines);
    }
    require_once __DIR__.'/discovery-core.php';
    $discovery=discoveryCommand($db,$cfg,$command);
    if($discovery!==null)return $discovery;
    if(in_array($command,['/pause','/resume'],true)){
        $paused=$command==='/pause'?1:0;
        $db->prepare('UPDATE growth_control SET paused=? WHERE id=1')->execute([$paused]);
        growthAudit($db,null,null,$paused?'paused':'resumed','owner');
        return $paused?'Growth control paused.':'Growth control resumed; only enabled, authorized workers can run.';
    }
    if(in_array($command,['/today','/health','/leads','/visitors','/pages','/services','/sources','/calls'],true)){
        require_once __DIR__.'/analytics-core.php';
        if(in_array($command,['/visitors','/pages','/services','/sources','/calls'],true))return analyticsReport($db,$cfg,$command);
        $tz=new DateTimeZone('Asia/Kolkata');$utc=new DateTimeZone('UTC');$start=new DateTimeImmutable('today',$tz);
        $q=$db->prepare('SELECT source,COUNT(*) total FROM leads WHERE created_at>=? AND created_at<? GROUP BY source');
        $q->execute([$start->setTimezone($utc)->format('Y-m-d H:i:s'),$start->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s')]);
        $rows=$q->fetchAll(PDO::FETCH_ASSOC);
        $lines=['Aaditechs V9.6 — '.$start->format('Y-m-d').' IST','Saved leads today: '.array_sum(array_column($rows,'total'))];
        foreach($rows as $r)$lines[]='Lead intake '.$r['source'].': '.$r['total'];
        if($command==='/leads')return implode("\n",$lines)."\nSaved inquiries, not won projects or marketing-source attribution.";
        $paused=$db->query('SELECT paused FROM growth_control WHERE id=1')->fetchColumn();
        if($paused===false)throw new RuntimeException('Control missing');
        $lines[]='Growth control: '.((int)$paused?'PAUSED':'RESUMED');
        foreach($db->query('SELECT status,COUNT(*) AS total FROM growth_tasks GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $r)$lines[]='Task inventory '.$r['status'].': '.$r['total'];
        $lines[]=analyticsReport($db,$cfg,'/visitors');
        if(discoveryEnabled($cfg)){
            foreach($db->query('SELECT status,COUNT(*) total FROM growth_opportunities GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $r)$lines[]='Opportunity inventory '.$r['status'].': '.$r['total'];
        }else $lines[]='Opportunity intake disabled or unconfigured.';
        $bounds=[$start->setTimezone($utc)->format('Y-m-d H:i:s'),$start->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s')];
        if(creativeEnabled($cfg)){
            $q=$db->prepare('SELECT COUNT(*) FROM growth_creative_results WHERE created_at>=? AND created_at<?');$q->execute($bounds);
            $lines[]='Creative draft revisions recorded today: '.$q->fetchColumn();
        }else $lines[]='Creative generation disabled or unconfigured.';
        if(socialTarget($cfg)!==null){
            $q=$db->prepare("SELECT COUNT(*) FROM growth_publications WHERE status='accepted' AND finished_at>=? AND finished_at<?");$q->execute($bounds);
            $lines[]='Telegram channel posts accepted today: '.$q->fetchColumn().' (provider receipts, not views)';
            foreach($db->query("SELECT status,COUNT(*) total FROM growth_publications WHERE status IN ('queued','sending','failed','unknown') GROUP BY status")->fetchAll(PDO::FETCH_ASSOC) as $r)$lines[]='Publication inventory '.$r['status'].': '.$r['total'];
        }else $lines[]='Telegram business publishing disabled or unconfigured.';
        if(discoveryEnabled($cfg)&&($cfg['discovery']['feeds_enabled']??false)===true){
            $q=$db->prepare('SELECT COUNT(*) runs,COALESCE(SUM(imported_count),0) imported,COALESCE(SUM(duplicate_count),0) duplicates FROM growth_source_runs WHERE finished_at>=? AND finished_at<?');
            $q->execute($bounds);$s=$q->fetch(PDO::FETCH_ASSOC);
            $lines[]='Source runs finished today: '.$s['runs'].' / imported '.$s['imported'].' / duplicates '.$s['duplicates'];
        }
        $lines[]='Actual calls, sales messages/email, reach, other social providers and full external errors: not connected.';
        $lines[]='Database query succeeded; not a full system health test.';
        return implode("\n",$lines);
    }
    if($command==='/approvals'){
        $lines=['Pending reviews (up to 10):'];
        foreach($db->query("SELECT id,revision,service_id,task_type FROM growth_tasks WHERE status='pending_review' ORDER BY id LIMIT 10")->fetchAll(PDO::FETCH_ASSOC) as $r)
            $lines[]='#'.$r['id'].' rev '.$r['revision'].' '.$r['service_id'].' / '.$r['task_type'].' — /task '.$r['id'];
        return count($lines)===1?'No pending reviews.':implode("\n",$lines);
    }
    if(preg_match('/^\/task ([1-9][0-9]{0,14})$/D',$command,$m)){
        $q=$db->prepare('SELECT id,revision,service_id,task_type,title,draft,status FROM growth_tasks WHERE id=?');$q->execute([(int)$m[1]]);$r=$q->fetch(PDO::FETCH_ASSOC);
        if(!$r)return 'Task not found.';
        return '#'.$r['id'].' rev '.$r['revision'].' | '.$r['status']."\n".$r['service_id'].' / '.$r['task_type']."\n".$r['title']."\n".$r['draft']."\nReview commands require exact ID and revision.";
    }
    if(preg_match('/^\/(approve|reject|regenerate) ([1-9][0-9]{0,14}) ([1-9][0-9]{0,8})$/D',$command,$m)){
        $id=(int)$m[2];$rev=(int)$m[3];$action=$m[1];
        $status=['approve'=>'approved','reject'=>'rejected','regenerate'=>'regeneration_requested'][$action];
        $q=$db->prepare("UPDATE growth_tasks SET status=?,revision=revision+? WHERE id=? AND revision=? AND status='pending_review'");
        $q->execute([$status,$action==='regenerate'?1:0,$id,$rev]);
        if($q->rowCount()!==1)return 'Task not pending at that revision. Refresh /task ID.';
        growthAudit($db,$id,$rev,$action,'owner');
        return $action==='regenerate'?'Revision requested. A new local-worker result or private reviewed draft is required.':$status.' — review recorded only; no external action sent.';
    }
    return 'Unsupported command. Use /help. Metrics not connected are unavailable, not zero.';
}
