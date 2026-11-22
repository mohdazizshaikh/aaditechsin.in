<?php
declare(strict_types=1);
/** Deterministic server-side rules: independent of HTML, sessions and database. */
function opsCanAdminTransition(string $from, string $to): bool {
    $routes = [
        'assessment'=>['quote_pending','awaiting_customer_approval','cancelled'],
        'quote_pending'=>['assessment','awaiting_customer_approval','cancelled'],
        'awaiting_customer_approval'=>['approved','quote_pending','cancelled'],
        'approved'=>['in_progress','quote_pending','cancelled'],
        'in_progress'=>['work_completed','approved','cancelled'],
        'work_completed'=>['closed','in_progress','cancelled'],
        'closed'=>[], 'cancelled'=>[],
    ];
    return array_key_exists($from,$routes) && ($from === $to || in_array($to,$routes[$from],true));
}
function opsCanPartnerUpdate(string $from, string $to): bool {
    // Partners may document an assessment but cannot start work until owner
    // explicitly records customer approval (state 'approved').
    return match ($to) {
        'assessment' => $from === 'assessment',
        'in_progress' => in_array($from,['approved','in_progress'],true),
        'work_completed' => in_array($from,['in_progress','work_completed'],true),
        default => false,
    };
}
function opsValidApprovalProof(string $method, string $reference): bool {
    return in_array($method,['signed_document','customer_email','recorded_call','other_record'],true)
        && strlen(trim($reference)) >= 8 && strlen(trim($reference)) <= 300
        && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $reference);
}
function opsTrackingTtlDays(array $cfg): int {
    $n = array_key_exists('tracking_ttl_days',$cfg) ? $cfg['tracking_ttl_days'] : 90;
    if (!is_int($n) || $n < 7 || $n > 365) throw new RuntimeException('Tracking TTL must be between 7 and 365 days');
    return $n;
}
function opsTrackingExpiry(array $cfg): string {
    return gmdate('Y-m-d H:i:s', time() + opsTrackingTtlDays($cfg) * 86400);
}
function opsPublicBaseUrl(array $cfg): string {
    $url = rtrim((string)($cfg['public_base_url'] ?? ''),'/');
    $parts = parse_url($url);
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
        || empty($parts['host']) || !filter_var($url, FILTER_VALIDATE_URL)
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['query'])
        || isset($parts['fragment']) || isset($parts['path'])) {
        throw new RuntimeException('Set a valid HTTPS public_base_url without a path or query');
    }
    return $url;
}
