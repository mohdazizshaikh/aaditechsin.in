<?php
declare(strict_types=1);
require __DIR__ . '/../api/operations.php';
opsHeaders();
$token=(string)($_GET['token']??'');
$job=null;$error='';
if(!preg_match('/^[a-f0-9]{64}$/D',$token)){$error='This tracking link is invalid or incomplete.';}
else {
    try {
        $cfg=opsConfig();
        if (($cfg['portal']['enabled']??false)!==true || !opsSsl()) throw new RuntimeException('Unavailable');
        $pdo=opsDb($cfg);
        $stmt=$pdo->prepare('SELECT case_code,title,status,updated_at FROM jobs WHERE tracking_token_hash=? AND tracking_expires_at > UTC_TIMESTAMP() LIMIT 1');
        $stmt->execute([hash('sha256',$token)]);$job=$stmt->fetch();
        if(!$job)$error='No active case was found for this tracking link. Please contact Aaditechs.';
    } catch(Throwable $e){http_response_code(503);$error='Case tracking is temporarily unavailable.';}
}
if($error&&http_response_code()===200)http_response_code(404);
?><!doctype html><html lang="en-IN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>Private Service Case Tracking | Aaditechs</title><link rel="stylesheet" href="../assets/portal.css"></head><body><header><div class="shell top"><a class="brand" href="/">Aaditechs <small>Customer case tracking</small></a></div></header><main class="shell"><section class="panel narrow"><h1>Service status</h1><?php if($job): ?><p><strong>Case:</strong> <?=opsH((string)$job['case_code'])?></p><p><strong>Project:</strong> <?=opsH((string)$job['title'])?></p><p><strong>Current status:</strong> <?=opsH(ucwords(str_replace('_',' ',(string)$job['status'])))?></p><p><strong>Last updated (UTC):</strong> <?=opsH((string)$job['updated_at'])?></p><p class="small">This page gives a high-level status only. It does not confirm customer approval, service outcome, quotation or payment. Please contact Aaditechs for further details.</p><?php else: ?><p role="status"><?=opsH($error)?></p><?php endif; ?><p><a href="mailto:info@aaditechs.in">Email Aaditechs</a> · <a href="tel:+919773693440">Call +91 97736 93440</a></p></section><p class="small">Tracking links expire automatically; keep your private tracking link confidential. Anyone possessing it can view this limited status; Aaditechs can revoke it if exposed.</p></main></body></html>
