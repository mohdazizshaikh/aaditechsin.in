"""Static source-only invariants for V9-001; not a DB integration or security audit."""
from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]
migration = (root / "secure-setup/migrations/2026-11-23-v9-001.sql").read_text()
sql = "\n".join(line.split("--", 1)[0] for line in migration.splitlines())
for table in ("lead_events", "lead_attribution", "lead_consent", "contact_suppression", "lead_requests"):
    assert re.search(r"CREATE TABLE IF NOT EXISTS\s+" + table + r"\b", sql), table
assert not re.search(r"\b(DROP|ALTER|TRUNCATE|DELETE)\s+(TABLE|FROM)\b", sql, re.I)
assert "UNIQUE KEY uq_v9_request_key" in sql
assert "ON DELETE RESTRICT" in sql
assert "utm_source" in sql
config = (root / "secure-setup/aaditechs-config.php.example").read_text()
public = (root / "public_html/assets/site-config.js").read_text()
endpoint = (root / "public_html/api/v9/leads.php").read_text()
assert "'lead_intake_enabled' => false" in config
assert "'privacy_notice_version' => 'REPLACE_" in config
assert re.search(r"leadSubmission:\s*false", public)
for required in ("Idempotency-Key", "lead_requests", "lead_events", "lead_consent", "lead_attribution", "beginTransaction", "rollBack", "commit()", "contact_consent", "v9LeadCombinedMessage"):
    # The HTTP header is represented as the PHP server key rather than literal text.
    if required == "Idempotency-Key":
        assert "HTTP_IDEMPOTENCY_KEY" in endpoint
    elif required == "contact_consent":
        assert required in (root / "public_html/api/v9/lead_rules.php").read_text()
    else:
        assert required in endpoint, required
assert not re.search(r"\b(mail|curl_exec|exec|shell_exec)\s*\(", endpoint)
assert not (root / "public_html/api/v9/lead-config.php").exists()
print("V9-001 migration, disabled endpoint, transactional consent/idempotency checks: PASS")
