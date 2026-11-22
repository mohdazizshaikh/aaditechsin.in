<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(405, ['error' => 'Method not allowed']);
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) jsonResponse(413, ['error' => 'Request too large']);

// Reject cross-origin browser posting. Public lead forms still need spam controls.
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$currentHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
if ($origin !== '') {
    $parts = parse_url($origin);
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
        || strtolower((string)($parts['host'] ?? '')) !== explode(':', $currentHost)[0]) {
        jsonResponse(403, ['error' => 'Origin not allowed']);
    }
}
$body = file_get_contents('php://input', false, null, 0, 8193);
$data = is_string($body) ? json_decode($body, true) : null;
if (!is_array($data)) jsonResponse(400, ['error' => 'Invalid form']);
if (!empty($data['company_website'])) jsonResponse(200, ['ok' => true]); // Honeypot.

$name = trim((string)($data['fullName'] ?? ''));
$email = trim((string)($data['email'] ?? ''));
$phone = trim((string)($data['phone'] ?? ''));
$service = trim((string)($data['service'] ?? ''));
$device = trim((string)($data['device'] ?? ''));
$message = trim((string)($data['message'] ?? ''));
$consent = (string)($data['contactConsent'] ?? '') === 'yes';
if (!$consent || $name === '' || mb_strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || mb_strlen($email) > 120 || mb_strlen($phone) > 20 || mb_strlen($service) > 100
    || $service === '' || mb_strlen($device) > 100 || $message === '' || mb_strlen($message) > 2000) {
    jsonResponse(422, ['error' => 'Please check the required fields']);
}
$cfg = appConfig();
$mailCfg = $cfg['mail'] ?? [];
if (($mailCfg['enabled'] ?? false) !== true) jsonResponse(503, ['error' => 'Inquiry email not configured']);

// Simple 5-minute per-IP cooldown. Only a salted hash is stored, not the IP.
$salt = (string)($cfg['rate_limit_salt'] ?? '');
if (strlen($salt) < 32 || str_starts_with($salt,'REPLACE_')) jsonResponse(503, ['error' => 'Spam protection not configured']);
$ipHash = hash('sha256', $salt . (string)($_SERVER['REMOTE_ADDR'] ?? ''));
$rateDir = privateDirectory() . '/rate';
if (!is_dir($rateDir)) @mkdir($rateDir, 0700, true);
if (!is_dir($rateDir) || !is_writable($rateDir)) {
    jsonResponse(503, ['error' => 'Spam protection unavailable']);
}
$rateFile = $rateDir . '/' . $ipHash;
if (is_file($rateFile) && time() - (int)filemtime($rateFile) < 300) jsonResponse(429, ['error' => 'Please wait before sending another inquiry']);

$autoload = privateDirectory() . '/vendor/autoload.php';
if (!is_file($autoload)) jsonResponse(503, ['error' => 'Mail dependency not installed']);
require_once $autoload;

try {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = (string)$mailCfg['host'];
    $mail->Port = (int)$mailCfg['port'];
    $mail->SMTPAuth = true;
    $mail->Username = (string)$mailCfg['username'];
    $mail->Password = (string)$mailCfg['password'];
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom((string)$mailCfg['from_email'], 'Aaditechs Website');
    $mail->addAddress((string)$mailCfg['inquiry_recipient']);
    $mail->addReplyTo($email, $name);
    $mail->isHTML(false);
    $mail->Subject = 'Aaditechs website inquiry: ' . preg_replace('/[\r\n]+/', ' ', $service);
    $mail->Body = "Name: {$name}\nEmail: {$email}\nPhone: {$phone}\nService: {$service}\nDevice: {$device}\n\nMessage:\n{$message}\n\nContact consent: yes";
    $mail->send();
    // Throttle counter is best-effort after confirmed SMTP acceptance.
    @file_put_contents($rateFile, (string)time(), LOCK_EX);
    @chmod($rateFile, 0600);
    jsonResponse(200, ['ok' => true]);
} catch (Throwable $e) {
    error_log('Aaditechs SMTP delivery failed');
    jsonResponse(503, ['error' => 'Could not send inquiry']);
}
