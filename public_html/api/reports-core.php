<?php
declare(strict_types=1);
require_once __DIR__.'/growth-core.php';
require_once __DIR__.'/oauth-health-core.php';
function ownerReportTarget(array $cfg):?string {
    $t=$cfg['growth']['telegram']??[];
    if(($cfg['growth']['enabled']??false)!==true||($t['enabled']??false)!==true||($t['reports_enabled']??false)!==true)return null;
    $id=$t['owner_user_id']??null;$chat=$t['owner_chat_id']??null;$token=$t['bot_token']??null;
    if(!is_string($id)||!is_string($chat)||$id!==$chat||!preg_match('/^[1-9][0-9]{0,15}$/D',$id)||
        !is_string($token)||!preg_match('/^[0-9]{5,16}:[A-Za-z0-9_-]{20,128}$/D',$token))return null;
    $other=$cfg['social']['telegram_channel']['bot_token']??'';
    if(is_string($other)&&$other!==''&&hash_equals($other,$token))return null;
    return $chat;
}
function reportPreferences(PDO $db,bool $lock=false):array {
    $r=$db->query('SELECT daily_enabled,errors_enabled FROM growth_report_preferences WHERE id=1'.($lock?' FOR UPDATE':''))->fetch(PDO::FETCH_ASSOC);
    if(!$r)throw new RuntimeException('Preferences missing');return $r;
}
function reportErrors(PDO $db,string $from,string $to):array {
    // Counts only; never expose raw logs or customer data. Limited module coverage.
    $out=[];
    foreach([
        'Discovery partial/failed'=> "SELECT COUNT(*) FROM growth_source_runs WHERE status IN ('partial','failed') AND finished_at>=? AND finished_at<?",
        'Publication failed/unknown'=> "SELECT COUNT(*) FROM growth_publications WHERE status IN ('failed','unknown') AND finished_at>=? AND finished_at<?",
        'Owner report failed/unknown'=> "SELECT COUNT(*) FROM growth_report_runs WHERE status IN ('failed','unknown') AND finished_at>=? AND finished_at<?"
    ] as $label=>$sql){$q=$db->prepare($sql);$q->execute([$from,$to]);$out[$label]=(int)$q->fetchColumn();}
    return $out;
}
function reportErrorText(PDO $db,DateTimeImmutable $now):string {
    $start=$now->setTimezone(new DateTimeZone('Asia/Kolkata'))->setTime(0,0);
    $utc=new DateTimeZone('UTC');$rows=reportErrors($db,$start->setTimezone($utc)->format('Y-m-d H:i:s'),$now->setTimezone($utc)->format('Y-m-d H:i:s'));
    $lines=['Recorded module errors today (IST)'];
    foreach($rows as $label=>$n)$lines[]=$label.': '.$n;
    $lines[]='Current dead SMTP owner alerts: '.(int)$db->query("SELECT COUNT(*) FROM notification_outbox WHERE status='dead'")->fetchColumn();
    foreach(reportPublicationAttention($db) as $label=>$count)$lines[]=$label.': '.$count;
    $lines[]='Not full website/server/telephony/mail or third-party monitoring.';
    return implode("\n",$lines);
}
function reportCommand(PDO $db,array $cfg,string $cmd):?string {
    if(!preg_match('/^\/(?:errors|notifications|notifydaily|notifyerrors|reporthistory)(?: |$)/',$cmd))return null;
    if(($cfg['growth']['enabled']??false)!==true)return 'Growth disabled.';
    if($cmd==='/errors')return reportErrorText($db,new DateTimeImmutable('now'));
    if($cmd==='/reporthistory'){
        $lines=['Owner notification history (latest 10, provider receipts only):'];
        foreach($db->query('SELECT report_type,period_key,status,provider_message_id,error_code FROM growth_report_runs ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC) as $r)
            $lines[]=$r['report_type'].' '.$r['period_key'].' '.$r['status'].($r['provider_message_id']?' receipt '.$r['provider_message_id']:'').($r['error_code']?' '.$r['error_code']:'');
        return count($lines)===1?'No owner report recorded.':implode("\n",$lines);
    }
    if($cmd==='/notifications'){
        $r=reportPreferences($db);
        return 'Proactive daily report: '.((int)$r['daily_enabled']?'ON':'OFF')."\nHourly completed-period error report: ".((int)$r['errors_enabled']?'ON':'OFF')."\nDelivery configuration: ".(ownerReportTarget($cfg)!==null?'configured, not verified':'disabled or unconfigured')."\n/notifydaily on|off /notifyerrors on|off";
    }
    if(preg_match('/^\/notify(daily|errors) (on|off)$/D',$cmd,$m)){
        if(!$db->inTransaction())throw new LogicException('Transaction required');
        $field=$m[1]==='daily'?'daily_enabled':'errors_enabled';
        reportPreferences($db,true);$db->prepare("UPDATE growth_report_preferences SET $field=? WHERE id=1")->execute([$m[2]==='on'?1:0]);
        growthAudit($db,null,null,'notify_'.$m[1].'_'.$m[2],'owner');
        return $m[1].' notification preference '.$m[2].'.';
    }
    return 'Use /notifications, /notifydaily on|off, /notifyerrors on|off or /errors.';
}
function reportClaim(PDO $db,array $cfg,string $kind,DateTimeImmutable $now):?array {
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $chat=ownerReportTarget($cfg);if($chat===null)throw new RuntimeException('Owner reports disabled');
    $pref=reportPreferences($db,true);$utc=new DateTimeZone('UTC');
    $local=$now->setTimezone(new DateTimeZone('Asia/Kolkata'));
    if($kind==='daily'){
        if((int)$pref['daily_enabled']!==1)return null;
        $after=$cfg['growth']['telegram']['daily_after_ist']??'18:00';
        if(!is_string($after)||!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$after))throw new InvalidArgumentException('Invalid time');
        if($local->format('H:i')<$after)return null;
        $key=$local->format('Y-m-d');
        $text="Daily snapshot (IST; day-to-date, not full-day)\n".growthCommand($db,'/today',$cfg)."\n\n".reportErrorText($db,$now);
        if(($cfg['oauth_lifecycle']['alerts_enabled']??false)===true)$text.="\n\n".oauthAlertText($db,$cfg,$now->getTimestamp());
    }elseif($kind==='errors'){
        if((int)$pref['errors_enabled']!==1)return null;
        $end=$now->setTimezone($utc);$end=$end->setTime((int)$end->format('H'),0);
        $start=$end->modify('-1 hour');$key=$start->format('Y-m-d\TH');
        $counts=reportErrors($db,$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s'));
        // Avoid an alert loop if sending the error alert itself fails.
        unset($counts['Owner report failed/unknown']);
        if(array_sum($counts)===0)return null;
        $lines=['Recorded errors in last completed UTC hour: '.$key];
        foreach($counts as $label=>$n)$lines[]=$label.': '.$n;
        $lines[]='Check /errors, /discoveryruns and /social. No raw logs or customer details.';
        $text=implode("\n",$lines);
    }else throw new InvalidArgumentException('Report type');
    $q=$db->prepare('SELECT id FROM growth_report_runs WHERE report_type=? AND period_key=?');$q->execute([$kind,$key]);
    if($q->fetchColumn()!==false)return null;
    // Never truncate away the caveats or retry the same period silently.
    if(strlen($text)>4000)$text="Report exceeds safe message size. Query /today and /errors individually.";
    $db->prepare('INSERT INTO growth_report_runs(report_type,period_key,target_chat_id) VALUES(?,?,?)')->execute([$kind,$key,$chat]);
    return ['id'=>(int)$db->lastInsertId(),'chat'=>$chat,'text'=>$text];
}
function reportFinish(PDO $db,int $id,array $r):void {
    if(!$db->inTransaction())throw new LogicException('Transaction required');
    $s=$r['status']??null;$receipt=$r['receipt']??null;$error=$r['error']??'';
    if(!in_array($s,['accepted','failed','unknown'],true)||!is_string($error)||!preg_match('/^[a-z_]{0,32}$/D',$error)||($s==='accepted'&&(!is_string($receipt)||!preg_match('/^[1-9][0-9]{0,20}$/D',$receipt)))||($s!=='accepted'&&$receipt!==null))throw new InvalidArgumentException('Receipt');
    $q=$db->prepare("UPDATE growth_report_runs SET status=?,provider_message_id=?,error_code=?,finished_at=UTC_TIMESTAMP() WHERE id=? AND status='sending'");
    $q->execute([$s,$receipt,$error,$id]);if($q->rowCount()!==1)throw new RuntimeException('Report state changed');
}

function reportPublicationAttention(PDO $db):array {
    $tables=['growth_publications','growth_meta_publications','growth_network_publications','growth_media_uploads','growth_instagram_uploads','growth_facebook_media','growth_network_media','growth_meta_images','growth_linkedin_video','growth_x_video','growth_carousels'];
    $uncertain=0;$inflight=0;$processing=0;
    foreach($tables as $table){
        $q=$db->query("SELECT status,COUNT(*) AS n FROM $table GROUP BY status");
        foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r){
            if(in_array($r['status'],['unknown','failed'],true))$uncertain+=(int)$r['n'];
            elseif(in_array($r['status'],['sending','uploading','fetching','assembling','publishing'],true))$inflight+=(int)$r['n'];
            elseif(in_array($r['status'],['processing','parent_ready'],true))$processing+=(int)$r['n'];
        }
    }
    return ['Historical failed/unknown publication rows (all ledgers)'=>$uncertain,'Current in-flight publication rows (all ledgers)'=>$inflight,'Current processing/parent-ready rows (all ledgers)'=>$processing];
}
