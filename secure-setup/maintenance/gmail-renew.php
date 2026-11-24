<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/public_html/api/operations.php';
require __DIR__.'/gmail-renew-lib.php';
try{
    if(count($argv)!==2||!in_array($argv[1],['--check','--candidate'],true))throw new RuntimeException('Explicit mode required');
    $cfg=opsConfig();gmailRenewConfig($cfg);$dir=gmailRenewDirectory($cfg,dirname(__DIR__,2).'/public_html');
    if(file_exists($dir.'/gmail-renewal-candidate.json')||is_link($dir.'/gmail-renewal-candidate.json'))throw new RuntimeException('Candidate exists');
    if($argv[1]==='--check'){echo "Local renewal configuration valid; no network access.\n";exit;}
    $db=opsDb($cfg);$db->beginTransaction();
    if((int)$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn()!==1)throw new RuntimeException('Pause required');
    $db->commit();
    $candidate=gmailRenewCandidate($cfg,'gmailRenewRequest','gmailRead');
    $db->beginTransaction();
    if((int)$db->query('SELECT paused FROM growth_control WHERE id=1 FOR UPDATE')->fetchColumn()!==1)throw new RuntimeException('Pause required');
    gmailRenewStore($dir,$candidate);growthAudit($db,null,null,'gmail_renew_candidate','private_cli');$db->commit();
    echo "Private candidate saved; active config unchanged. Inspect and rotate manually.\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();fwrite(STDERR,"BLOCKED: verify authorization, paused growth, private candidate and provider scope. No response or credential logged; inspect before retry.\n");exit(1);}
