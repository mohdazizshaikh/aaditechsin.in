<?php
declare(strict_types=1);
require_once __DIR__.'/gmail-health-core.php';
require_once __DIR__.'/native-usage-core.php';
require_once __DIR__.'/whatsapp-inbound-core.php';
require_once __DIR__.'/twilio-inbound-core.php';
require_once __DIR__.'/opportunity-followup-core.php';
require_once __DIR__.'/reports-core.php';
function intelligenceSummary(PDO $db,array $cfg,?DateTimeImmutable $now=null):string{
    $lines=['Owner intelligence (stored evidence, not live performance):'];
    $pause=$db->query('SELECT paused FROM growth_control WHERE id=1')->fetchColumn();
    $lines[]='Growth: '.($pause===false?'unavailable':((int)$pause===1?'paused':'resumed'));
    if(discoveryEnabled($cfg)){
        $counts=['pending_review'=>0,'qualified'=>0,'rejected'=>0];
        foreach($db->query('SELECT status,COUNT(*) AS n FROM growth_opportunities GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $r)$counts[$r['status']]=(int)$r['n'];
        $lines[]='Opportunities: '.$counts['pending_review'].' pending, '.$counts['qualified'].' qualified, '.$counts['rejected'].' rejected (not verified sales).';
        if(followEnabled($cfg))$lines[]=followSummary($db,$now);
    }else $lines[]='Discovery disabled.';
    if(communicationsEnabled($cfg)){
        $lines[]=communicationsSummary($db,$now);
        if(waInboundConfig($cfg))$lines[]=waInboundSummary($db,$cfg,$now);
        if(twInboundConfig($cfg,'sms')||twInboundConfig($cfg,'calls'))$lines[]=twInboundSummary($db,$cfg,$now);
        if(($cfg['communications']['native_usage_enabled']??false)===true)$lines[]=nativeUsageSummary($db,$cfg,$now);
        if(($cfg['communications']['gmail']['enabled']??false)===true)$lines[]=gmailSummary($db,$cfg,$now);
    }else $lines[]='Communications disabled.';
    if(creativeEnabled($cfg)&&($cfg['social']['enabled']??false)===true)foreach(reportPublicationAttention($db) as $label=>$n)$lines[]=$label.': '.$n;
    $lines[]='Use /opportunities /followups /communications /gmailhealth /publications /connections for details.';
    return implode("\n",$lines);
}
function intelligenceCommand(PDO $db,array $cfg,string $cmd):?string{
    if(!in_array($cmd,['/intelligence','/gmailhealth','/nativeusage','/wainbound','/twinbound'],true))return null;
    if(($cfg['growth']['enabled']??false)!==true)return 'Growth disabled.';
    if($cmd==='/twinbound')return twInboundSummary($db,$cfg);
    if($cmd==='/wainbound')return waInboundSummary($db,$cfg);
    if($cmd==='/nativeusage')return communicationsEnabled($cfg)&&($cfg['communications']['native_usage_enabled']??false)===true?nativeUsageSummary($db,$cfg):'Native usage disabled.';
    return $cmd==='/gmailhealth'?gmailSummary($db,$cfg):intelligenceSummary($db,$cfg);
}
