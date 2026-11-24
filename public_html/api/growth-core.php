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
    if(in_array($command,['/start','/help'],true))return "Aaditechs owner controls\n/today /health /leads /visitors /pages /services /sources /calls\n/opportunities /opportunity ID /qualify ID /dismiss ID /draftoutreach ID /outreach /discoveryruns\n/createcontent SERVICE CHANNEL OBJECTIVE /content /coverage\n/creativeworker /retrycreative ID REV\n/media ID /approvemedia ID REV SHA256 /rejectmedia ID REV SHA256\n/uploads /upload ID /approveupload ID SHA256 VISIBILITY CHANNEL_ID /cancelupload ID\n/igreels /igreel ID /approveig ID SHA256 ACCOUNT_ID /cancelig ID\n/carousels /carousel ID /approvecarousel ID BUNDLE_SHA256 PROVIDER ACCOUNT_ID /cancelcarousel ID\n/metaimages /metaimage ID /approveimage ID JPEG_SHA256 PROVIDER ACCOUNT_ID /approvethreadsvideo ID MP4_SHA256 threads ACCOUNT_ID /cancelimage ID\n/xvideos /xvideo ID /approvexvideo ID SHA256 ACCOUNT_ID /cancelxvideo ID\n/livideos /livideo ID /approvelivideo ID SHA256 ORG_ID /cancellivideo ID\n/mediarecovery\n/netmedia /netasset ID /approvenetmedia ID SHA256 PROVIDER ACCOUNT_ID /cancelnetmedia ID\n/tgalbum ID /approvetgalbum ID BUNDLE_SHA256 CHANNEL_ID\n/tgmedia /tgasset ID /approvetgmedia ID SHA256 photo|video CHANNEL_ID /canceltgmedia ID\n/communications /gmailhealth /nativeusage /wainbound /intelligence\n/followups /followup ID YYYY-MM-DD /completefollowup ID /cancelfollowup ID\n/fbmedia /fbasset ID /approvefbmedia ID SHA256 photo|video PAGE_ID /cancelfbmedia ID\n/expert SERVICE assessment|planning|escalation /experts\n/seo /seotopics SERVICE\n/connections /connectionalerts\n/publications /network /networktarget /networkschedule PROVIDER TASK REV ACCOUNT_ID UTC_TIME /networkcancel ID\n/meta /metatarget /metaschedule PROVIDER TASK REV ACCOUNT_ID UTC_TIME /metacancel ID\n/socialtarget /social /schedulepost TASK REV CHANNEL_ID UTC_TIME /cancelpost ID\n/errors /notifications /reporthistory /notifydaily on|off /notifyerrors on|off\n/approvals /task ID\n/approve ID REV /reject ID REV /regenerate ID REV\n/pause /resume\nApproval records review only; separate publication authorization required.";
    require_once __DIR__.'/reports-core.php';
    $report=reportCommand($db,$cfg,$command);
    if($report!==null)return $report;
    require_once __DIR__.'/oauth-health-core.php';
    $oauth=oauthCommand($db,$cfg,$command);
    if($oauth!==null)return $oauth;
    require_once __DIR__.'/publication-overview.php';
    $overview=publicationOverview($db,$cfg,$command);
    if($overview!==null)return $overview;
    require_once __DIR__.'/network-core.php';
    $network=networkCommand($db,$cfg,$command);
    if($network!==null)return $network;
    require_once __DIR__.'/meta-core.php';
    $meta=metaCommand($db,$cfg,$command);
    if($meta!==null)return $meta;
    require_once __DIR__.'/social-core.php';
    $social=socialCommand($db,$cfg,$command);
    if($social!==null)return $social;
    require_once __DIR__.'/seo-core.php';
    $seo=seoCommand($db,$cfg,$command);
    if($seo!==null)return $seo;
    require_once __DIR__.'/expert-core.php';
    $expert=expertCommand($db,$cfg,$command);
    if($expert!==null)return $expert;
    require_once __DIR__.'/carousel-core.php';
    $ca=caCommand($db,$cfg,$command);
    if($ca!==null)return $ca;
    require_once __DIR__.'/meta-image-core.php';
    $mi=miCommand($db,$cfg,$command);
    if($mi!==null)return $mi;
    require_once __DIR__.'/x-video-core.php';
    $xv=xvCommand($db,$cfg,$command);
    if($xv!==null)return $xv;
    require_once __DIR__.'/linkedin-video-core.php';
    $lv=lvCommand($db,$cfg,$command);
    if($lv!==null)return $lv;
    require_once __DIR__.'/network-media-core.php';
    $recovery=nmRecoveryReport($db,$cfg,$command);
    if($recovery!==null)return $recovery;
    $nm=nmCommand($db,$cfg,$command);
    if($nm!==null)return $nm;
    require_once __DIR__.'/telegram-media-core.php';
    require_once __DIR__.'/telegram-album-core.php';
    $album=taCommand($db,$cfg,$command);if($album!==null)return $album;
    $tgmedia=tgMediaCommand($db,$cfg,$command);
    if($tgmedia!==null)return $tgmedia;
    require_once __DIR__.'/intelligence-core.php';
    $intel=intelligenceCommand($db,$cfg,$command);
    if($intel!==null)return $intel;
    $follow=followCommand($db,$cfg,$command);
    if($follow!==null)return $follow;
    require_once __DIR__.'/communications-core.php';
    $communications=communicationsCommand($db,$cfg,$command);
    if($communications!==null)return $communications;
    require_once __DIR__.'/facebook-media-core.php';
    $fbmedia=fbMediaCommand($db,$cfg,$command);
    if($fbmedia!==null)return $fbmedia;
    require_once __DIR__.'/instagram-core.php';
    $ig=igCommand($db,$cfg,$command);
    if($ig!==null)return $ig;
    require_once __DIR__.'/upload-core.php';
    $upload=uploadCommand($db,$cfg,$command);
    if($upload!==null)return $upload;
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
        if(($cfg['growth']['enabled']??false)!==true||($cfg['discovery']['enabled']??false)!==true||(($cfg['discovery']['feeds_enabled']??false)!==true&&($cfg['discovery']['local_batch_enabled']??false)!==true))return 'Discovery feeds and local batches disabled or unconfigured.';
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
