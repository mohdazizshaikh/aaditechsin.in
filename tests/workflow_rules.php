<?php
declare(strict_types=1);
require dirname(__DIR__) . '/public_html/api/workflow.php';
function check(bool $yes, string $reason): void {
    if (!$yes) {fwrite(STDERR,"FAIL: $reason\n");exit(1);}
}
foreach (['assessment','quote_pending','awaiting_customer_approval','approved','in_progress','work_completed','closed','cancelled'] as $state) {
    check(opsCanAdminTransition($state,$state), 'owner can update notes without state change: '.$state);
}
check(opsCanAdminTransition('assessment','quote_pending'),'assessment to quote');
check(opsCanAdminTransition('quote_pending','awaiting_customer_approval'),'quote to customer');
check(opsCanAdminTransition('awaiting_customer_approval','approved'),'customer acceptance');
check(!opsCanAdminTransition('assessment','in_progress'),'skip approval denied');
check(!opsCanAdminTransition('quote_pending','approved'),'approval must be awaited');
check(!opsCanAdminTransition('closed','in_progress'),'closed immutable');
check(!opsCanAdminTransition('cancelled','assessment'),'cancelled immutable');
check(!opsCanPartnerUpdate('assessment','in_progress'),'partner cannot skip approval');
check(!opsCanPartnerUpdate('awaiting_customer_approval','in_progress'),'partner cannot self-approve');
check(!opsCanPartnerUpdate('quote_pending','work_completed'),'partner cannot skip work');
check(opsCanPartnerUpdate('assessment','assessment'),'partner can log assessment note');
check(opsCanPartnerUpdate('approved','in_progress'),'partner can start after approval');
check(opsCanPartnerUpdate('in_progress','in_progress'),'partner can log in-progress note');
check(opsCanPartnerUpdate('in_progress','work_completed'),'partner can report completed');
check(!opsCanPartnerUpdate('cancelled','work_completed'),'cancelled cannot complete');
check(opsValidApprovalProof('customer_email','Message-ID 12345'),'accept proof');
check(!opsValidApprovalProof('customer_email','x'),'reject vague proof');
check(!opsValidApprovalProof('unknown','Message-ID 12345'),'reject unknown method');
check(opsPublicBaseUrl(['public_base_url'=>'https://aaditechs.in'])==='https://aaditechs.in','valid public origin');
foreach (['http://aaditechs.in','https://aaditechs.in/path','https://evil@aaditechs.in','javascript:alert(1)'] as $origin) {
    try {opsPublicBaseUrl(['public_base_url'=>$origin]);check(false,'reject unsafe origin '.$origin);}
    catch (RuntimeException $ignored) {}
}
check(opsTrackingTtlDays([])===90,'default ttl');
check(opsTrackingTtlDays(['tracking_ttl_days'=>30])===30,'configured ttl');
foreach ([0,366,'30',null] as $ttl) {
    try {opsTrackingTtlDays(['tracking_ttl_days'=>$ttl]);check(false,'reject invalid ttl');}
    catch (RuntimeException $ignored) {}
}
echo "Workflow security / approval / tracking rules: PASS\n";
