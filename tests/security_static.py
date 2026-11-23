"""Offline repository secret/leak and feature-flag checks (not a substitute for security audit)."""
from pathlib import Path
import re
root=Path(__file__).resolve().parents[1]
# Source must never contain real credentials or customer data.
config=(root/'secure-setup/aaditechs-config.php.example').read_text()
public=(root/'public_html/assets/site-config.js').read_text()
assert "'client_secret' => 'REPLACE_" in config
assert "'refresh_token' => 'REPLACE_" in config
assert "'password' => 'REPLACE_" in config
assert "'admin_password_hash' => 'REPLACE_" in config
assert "'enabled' => false" in config
assert re.search(r'googleReviewsApi:\s*false',public)
assert re.search(r'leadSubmission:\s*false',public)
assert not list(root.glob('**/aaditechs-config.php')), 'Real config detected'
assert not list(root.glob('**/.env')), 'Environment secrets detected'
assert 'original-logo.png' in (root/'public_html/index.html').read_text()
track=(root/'public_html/track/index.php').read_text()
assert 'case_code,status,updated_at FROM jobs' in track
assert "job['title']" not in track, 'Public tracking page must not display arbitrary case titles'
assert "'admin_totp_enabled' => false" in config
assert 'opsVerifyTotp(' in (root/'public_html/portal/index.php').read_text()
for term in ('Zenith Soles','Robotics Lab'):
    assert term not in (root/'public_html/index.html').read_text()
print('Static security + public feature flags: PASS')

assert 'notification_outbox' in (root/'secure-setup/schema.sql').read_text()
assert 'beginTransaction()' in (root/'public_html/api/lead.php').read_text()
assert 'queueMail($cfg,$row)' in (root/'secure-setup/maintenance/notification-worker.php').read_text()
assert "PHP_SAPI !== 'cli'" in (root/'secure-setup/maintenance/notification-worker.php').read_text()
assert "o.status <> 'sent'" in (root/'secure-setup/maintenance/prune.php').read_text()

prune=(root/'secure-setup/maintenance/prune.php').read_text()
assert "$argv[1] ?? '--dry-run'" in prune
assert "--run" in prune and "READ-ONLY" in prune
assert '$e->getMessage()' not in prune, 'Prune must not log sensitive raw exceptions'
