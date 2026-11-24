"""Disposable MySQL + PHP HTTP integration tests with synthetic identities only.

Only run in CI or a local disposable environment. NEVER point at a live database.
The script creates a private test-only config, imports schema into the CI database,
runs a local PHP server, then removes all generated secrets and test sessions.
"""
from __future__ import annotations

import contextlib
from http.client import HTTPConnection
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import os
from pathlib import Path
import re
import shutil
import ssl
import threading
import socket
import subprocess
import tempfile
import time
from urllib.parse import urlparse, parse_qs

from bs4 import BeautifulSoup
import pymysql
import requests
import urllib3

# Test-only self-signed loopback TLS. Never disable validation for external hosts.
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

ROOT = Path(__file__).resolve().parents[1]
WEB = ROOT / 'public_html'
CONFIG = ROOT / 'secure' / 'aaditechs-config.php'
OWNER_EMAIL = 'ci-owner@example.invalid'
OWNER_PASSWORD = 'TEST-ONLY-Aaditechs-Owner-Password-12345'
PARTNER_PASSWORD = 'TEST-ONLY-Aaditechs-Partner-Password-12345'
DB_HOST = os.getenv('CI_DB_HOST', '127.0.0.1')
DB_PORT = int(os.getenv('CI_DB_PORT', '3306'))
DB_NAME = os.getenv('CI_DB_NAME', 'aaditechs_ci')
DB_USER = os.getenv('CI_DB_USER', 'aaditechs_ci')
DB_PASSWORD = os.environ.get('CI_DB_PASSWORD', '')
assert os.getenv('AADITECHS_DISPOSABLE_TEST') == 'yes', 'Explicit disposable-test flag required'
assert DB_NAME == 'aaditechs_ci' and DB_USER == 'aaditechs_ci', 'Refusing non-CI database'
assert DB_HOST in ('127.0.0.1', 'localhost') and DB_PASSWORD == 'ci_only_not_real_password', 'Refusing unexpected DB credentials'
assert not CONFIG.exists(), 'Refusing to overwrite any existing private config'


def php_quote(value: str) -> str:
    return "'" + value.replace('\\', '\\\\').replace("'", "\\'") + "'"


def csrf(session: requests.Session, base: str) -> str:
    response = session.get(base + '/portal/', timeout=5)
    assert response.status_code == 200, f'Portal GET status {response.status_code}'
    inp = BeautifulSoup(response.text, 'html.parser').select_one('input[name="csrf"]')
    assert inp and inp.get('value'), 'Portal CSRF field missing'
    return str(inp['value'])


def form(session: requests.Session, base: str, values: dict) -> requests.Response:
    response = session.post(base + '/portal/', data={'csrf': csrf(session, base), **values}, timeout=5)
    assert response.status_code == 200, f'Portal POST returned {response.status_code}'
    return response


def require_status(cursor: pymysql.cursors.Cursor, job_id: int, expected: str) -> None:
    cursor.execute('SELECT status FROM jobs WHERE id=%s', (job_id,))
    got = cursor.fetchone()[0]
    assert got == expected, f'Job {job_id}: expected {expected}, got {got}'


def setup_schema(cursor: pymysql.cursors.Cursor) -> None:
    schema = (ROOT / 'secure-setup/schema.sql').read_text(encoding='utf-8')
    for statement in schema.split(';'):
        if statement.strip() and statement.strip().strip('-\n '):
            cursor.execute(statement)


def start_test_tls_proxy(php_port: int):
    """Serve real TLS to browser clients; forward internally to local PHP test server.

    Hostinger TLS is not tested here. The localhost-only PHP router simulates the
    backend HTTPS flag after our disposable TLS terminator.
    """
    cert_dir = Path(tempfile.mkdtemp(prefix='aaditechs-ci-tls-'))
    key_path = cert_dir / 'key.pem'
    cert_path = cert_dir / 'cert.pem'
    subprocess.run(['openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-nodes',
                    '-keyout', str(key_path), '-out', str(cert_path), '-days', '1',
                    '-subj', '/CN=localhost', '-addext', 'subjectAltName=IP:127.0.0.1'],
                   stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, check=True)

    class ProxyHandler(BaseHTTPRequestHandler):
        def do_GET(self): self.forward()
        def do_POST(self): self.forward()
        def log_message(self, *args): pass
        def forward(self):
            length = int(self.headers.get('Content-Length', '0'))
            if length > 16384:
                self.send_error(413)
                return
            body = self.rfile.read(length) if length else None
            forward_headers = {k: v for k, v in self.headers.items()
                               if k.lower() not in ('connection', 'transfer-encoding')}
            conn = HTTPConnection('127.0.0.1', php_port, timeout=5)
            try:
                conn.request(self.command, self.path, body=body, headers=forward_headers)
                response = conn.getresponse()
                data = response.read()
                self.send_response(response.status)
                for key, value in response.getheaders():
                    if key.lower() not in ('connection', 'transfer-encoding', 'content-length'):
                        self.send_header(key, value)
                self.send_header('Content-Length', str(len(data)))
                self.end_headers()
                self.wfile.write(data)
            finally:
                conn.close()

    server = ThreadingHTTPServer(('127.0.0.1', 0), ProxyHandler)
    context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
    context.load_cert_chain(str(cert_path), str(key_path))
    server.socket = context.wrap_socket(server.socket, server_side=True)
    worker = threading.Thread(target=server.serve_forever, daemon=True)
    worker.start()
    return server, worker, cert_dir, f'https://127.0.0.1:{server.server_address[1]}'


def run() -> None:
    assert DB_PASSWORD, 'Test DB credentials missing'
    db = pymysql.connect(host=DB_HOST, port=DB_PORT, user=DB_USER,
                         password=DB_PASSWORD, database=DB_NAME, autocommit=True)
    with contextlib.closing(db), db.cursor() as cursor:
        setup_schema(cursor)
        hashed = subprocess.check_output(['php', '-r', 'echo password_hash($argv[1], PASSWORD_DEFAULT);', OWNER_PASSWORD], text=True).strip()
        CONFIG.parent.mkdir(parents=True, exist_ok=False)
        CONFIG.write_text('''<?php\nreturn [
  'public_base_url' => 'https://ci-staging.invalid',
  'tracking_ttl_days' => 7,
  'retention_days' => 30,
  'rate_limit_salt' => 'CI_ONLY_RANDOM_SALT_NOT_PRODUCTION_32_BYTES_LONG',
  'database' => ['enabled'=>true, 'host'=>%s, 'port'=>%s, 'name'=>%s, 'username'=>%s, 'password'=>%s],
  'leads' => ['enabled'=>true],
  'growth' => ['enabled'=>true, 'telegram'=>['enabled'=>false]],
  'intelligence_dashboard' => ['enabled'=>true], 'discovery'=>['enabled'=>true,'followups_enabled'=>true], 'communications'=>['enabled'=>true],
  'analytics' => ['enabled'=>true, 'salt'=>'CI_ONLY_ANALYTICS_SALT_SYNTHETIC_32_BYTES'],
  'portal' => ['enabled'=>true, 'admin_email'=>%s, 'admin_password_hash'=>%s,
               'admin_totp_enabled'=>false, 'admin_totp_secret'=>''],
  'mail' => ['enabled'=>true], 'google' => ['enabled'=>false]
];\n''' % tuple(map(php_quote, [DB_HOST, str(DB_PORT), DB_NAME, DB_USER, DB_PASSWORD, OWNER_EMAIL, hashed])), encoding='utf-8')
        CONFIG.chmod(0o600)
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        test_vendor_created = False
        with tempfile.TemporaryFile() as server_log:
            server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(WEB),
                                       str(ROOT / 'tests/ci_https_router.php')],
                                      cwd=str(ROOT), stdout=server_log, stderr=server_log)
            proxy = None
            cert_dir = None
            worker = None
            try:
                proxy, worker, cert_dir, base = start_test_tls_proxy(port)
                for _ in range(40):
                    try:
                        if requests.get(base + '/portal/', verify=False, timeout=1).status_code == 200:
                            break
                    except requests.RequestException:
                        time.sleep(.1)
                else:
                    raise AssertionError('Local PHP server not ready')
                public = requests.Session()
                public.verify = False
                reviews = public.get(base + '/api/google-reviews.php', timeout=5)
                assert reviews.status_code == 503, 'Unapproved Google review API unexpectedly active'
                # Test-only consent analytics, using the simulated TLS proxy.
                event = {'event_id':'a'*32,'session':'b'*32,'page':'home','event':'page_view',
                         'service':'','channel':'search','automation':False,'consent':True}
                ep = base + '/api/analytics.php'
                headers = {'Origin':'https://ci-staging.invalid','User-Agent':'SyntheticBrowser'}
                assert public.post(ep, json=event, headers={**headers,'Origin':'https://evil.example'}, timeout=5).status_code == 403
                assert public.post(ep, json={**event,'consent':False}, headers=headers, timeout=5).status_code == 422
                assert public.post(ep, json={**event,'page':'track'}, headers=headers, timeout=5).status_code == 422
                assert public.post(ep, json={**event,'raw_url':'sensitive'}, headers=headers, timeout=5).status_code == 422
                assert public.post(ep, json=event, headers={**headers,'Sec-GPC':'1'}, timeout=5).status_code == 204
                cursor.execute('SELECT COUNT(*) FROM growth_analytics_events')
                assert cursor.fetchone()[0] == 0, 'Rejected/opt-out event persisted'
                a = public.post(ep, json=event, headers=headers, timeout=5)
                assert a.status_code == 200 and a.json()['status']=='accepted', ('Analytics event',a.status_code,a.text[:150])
                a = public.post(ep, json=event, headers=headers, timeout=5)
                assert a.status_code == 200 and a.json()['status']=='duplicate'
                a = public.post(ep, json={**event,'event_id':'c'*32,'event':'service_interest','service':'it-procurement'},headers=headers,timeout=5)
                assert a.status_code==200 and a.json()['status']=='accepted'
                a = public.post(ep, json={**event,'event_id':'d'*32,'automation':True},headers=headers,timeout=5)
                assert a.status_code==200 and a.json()['status']=='accepted'
                cursor.execute('SELECT COUNT(*),COUNT(DISTINCT session_hash),SUM(automation_signal) FROM growth_analytics_events')
                assert cursor.fetchone()==(3,1,1), 'Analytics counts/replay/session mismatch'
                cursor.execute('SELECT session_hash FROM growth_analytics_events LIMIT 1')
                assert len(cursor.fetchone()[0])==64, 'Session hash missing'
                # Global cap reached: no new row or counter inflation.
                cursor.execute('UPDATE growth_analytics_budget SET accepted_count=10000')
                assert public.post(ep,json={**event,'event_id':'e'*32},headers=headers,timeout=5).status_code==429
                cursor.execute('UPDATE growth_analytics_budget SET accepted_count=3')
                # Test retention only against an isolated, synthetic old record.
                cursor.execute("INSERT INTO growth_analytics_events(event_day,event_id,session_hash,page_id,event_type,service_id,channel) VALUES('2000-01-01',%s,%s,'home','page_view','','direct')",('f'*32,'f'*64))
                cmd=['php',str(ROOT/'secure-setup/maintenance/analytics-prune.php')]
                dry=subprocess.run(cmd,cwd=str(ROOT),text=True,capture_output=True,timeout=10)
                assert dry.returncode==0 and 'Eligible analytics events: 1' in dry.stdout and 'READ-ONLY' in dry.stdout
                cursor.execute('SELECT COUNT(*) FROM growth_analytics_events')
                assert cursor.fetchone()[0]==4
                run_prune=subprocess.run(cmd+['--run'],cwd=str(ROOT),text=True,capture_output=True,timeout=10)
                assert run_prune.returncode==0 and 'Deleted analytics events: 1' in run_prune.stdout
                cursor.execute('SELECT COUNT(*) FROM growth_analytics_events')
                assert cursor.fetchone()[0]==3
                report=subprocess.run(['php',str(ROOT/'secure-setup/maintenance/growth-owner.php'),'--today'],cwd=str(ROOT),text=True,capture_output=True,timeout=10)
                assert report.returncode==0 and 'Consented page views: 2' in report.stdout and 'Estimated tab sessions (not people): 1' in report.stdout
                print('V9.2 synthetic analytics HTTP consent, quotas, reports and retention: PASS')
                payload = {
                    'fullName': 'CI Synthetic Alice', 'email': 'alice-ci@example.invalid',
                    'phone': '0000000000', 'service': 'Data Recovery',
                    'device': 'CI test disk', 'message': 'Synthetic failure scenario; no real customer records',
                    'source': 'website', 'contactConsent': 'yes',
                }
                bad = public.post(base + '/api/lead.php', json={**payload, 'contactConsent':'no'}, timeout=5)
                assert bad.status_code == 422, f'Consent bypass: {bad.status_code}'
                cross = public.post(base + '/api/lead.php', json=payload, headers={'Origin':'https://evil.example'}, timeout=5)
                assert cross.status_code == 403, f'Origin bypass: {cross.status_code}'
                saved = public.post(base + '/api/lead.php', json=payload, timeout=5)
                assert saved.status_code == 201 and saved.json()['ok'] is True, ('Lead insert', saved.status_code, saved.text[:150])
                ref = saved.json()['reference']
                cursor.execute('SELECT id,consent_at FROM leads WHERE reference=%s', (ref,))
                lead = cursor.fetchone()
                assert lead and lead[1] is not None, 'Lead/consent not persisted'
                cursor.execute('SELECT status,attempts FROM notification_outbox WHERE lead_id=%s', (lead[0],))
                outbox = cursor.fetchone()
                assert outbox == ('pending', 0), f'Email alert was not atomically queued: {outbox}'
                dry = subprocess.run(['php', str(ROOT / 'secure-setup/maintenance/notification-worker.php'), '--dry-run'],
                                     cwd=str(ROOT), text=True, capture_output=True, timeout=10)
                assert dry.returncode == 0 and 'pending: 1' in dry.stdout and 'READ-ONLY' in dry.stdout, 'Dry-run worker failed'
                cursor.execute('SELECT status,attempts FROM notification_outbox WHERE lead_id=%s', (lead[0],))
                assert cursor.fetchone() == ('pending', 0), 'Dry-run unexpectedly changed queue state'
                blocked = subprocess.run(['php', str(ROOT / 'secure-setup/maintenance/notification-worker.php'), '--run'],
                                         cwd=str(ROOT), text=True, capture_output=True, timeout=10)
                assert blocked.returncode == 1 and 'BLOCKED' in blocked.stderr, 'Worker accepted incomplete private SMTP config'
                cursor.execute('SELECT status,attempts FROM notification_outbox WHERE lead_id=%s', (lead[0],))
                assert cursor.fetchone() == ('pending', 0), 'Invalid SMTP worker unexpectedly consumed notification'
                rejected = public.post(base + '/api/lead.php', json=payload, timeout=5)
                assert rejected.status_code == 429, 'Duplicate/flood guard failed'
                cursor.execute('SELECT COUNT(*) FROM leads')
                assert cursor.fetchone()[0] == 1, 'Duplicate lead unexpectedly persisted'

                assert public.get(base + '/portal/intelligence.php', timeout=5).status_code == 404, 'Guest accessed intelligence'
                owner = requests.Session()
                owner.verify = False
                before = csrf(owner, base)
                wrong = form(owner, base, {'action':'login', 'email':OWNER_EMAIL,'password':'incorrect'})
                assert 'Invalid sign-in details' in wrong.text, 'Invalid owner password not rejected'
                logged = form(owner, base, {'action':'login', 'email':OWNER_EMAIL,'password':OWNER_PASSWORD})
                assert 'Owner Dashboard' in logged.text, 'Owner login unsuccessful'
                assert '/portal/intelligence.php' in logged.text, 'Dashboard navigation missing'
                dash = owner.get(base + '/portal/intelligence.php?days=30', timeout=5)
                assert dash.status_code == 200 and 'Business intelligence' in dash.text and 'No data' in dash.text, 'Owner intelligence missing'
                assert 'no-store' in dash.headers.get('Cache-Control','') and 'noindex' in dash.headers.get('X-Robots-Tag',''), 'Private headers missing'
                assert OWNER_EMAIL not in dash.text and 'ci_only_not_real_password' not in dash.text, 'Private configuration leaked'
                assert owner.get(base + '/portal/intelligence.php?days=365', timeout=5).status_code == 404
                assert owner.post(base + '/portal/intelligence.php', data={'action':'approve'}, timeout=5).status_code == 404
                assert 'Owner notification delivery' in logged.text and 'pending: 1' in logged.text, 'Owner queue summary missing'
                assert csrf(owner, base) != before, 'CSRF did not rotate after privilege elevation'
                invalid_csrf = owner.post(base + '/portal/', data={'csrf':'bad', 'action':'set_lead_status', 'lead_id':'1', 'status':'closed'}, timeout=5)
                assert invalid_csrf.status_code == 403, 'CSRF bypass in owner portal'
                # An owner can recover a dead mail alert only via an authenticated,
                # CSRF-protected portal action. Sending remains CLI-only.
                cursor.execute("UPDATE notification_outbox SET status='dead',attempts=6,last_error_code='smtp_unavailable' WHERE lead_id=%s", (lead[0],))
                cursor.execute('SELECT id FROM notification_outbox WHERE lead_id=%s', (lead[0],))
                notification_id=cursor.fetchone()[0]
                guest = requests.Session()
                guest.verify = False
                unauth = guest.post(base + '/portal/', data={'action':'retry_notification',
                                           'csrf':csrf(guest, base),'notification_id':str(notification_id)}, timeout=5)
                assert unauth.status_code == 403, 'Guest could requeue a private notification'
                cursor.execute('SELECT status FROM notification_outbox WHERE id=%s', (notification_id,))
                assert cursor.fetchone()[0] == 'dead', 'Unauthenticated guest changed notification status'
                retried = form(owner, base, {'action':'retry_notification','notification_id':str(notification_id)})
                assert 'Notification requeued' in retried.text, 'Owner could not requeue dead alert'
                cursor.execute('SELECT status,attempts FROM notification_outbox WHERE id=%s', (notification_id,))
                assert cursor.fetchone() == ('pending',0), 'Dead alert retry state was not reset'
                # Use a disposable PHPMailer double with ZERO network access.
                # The private test configuration and stub live under secure/ and
                # are removed in finally. This exercises real SQL queue behavior
                # without pretending we delivered an SMTP message.
                vendor = ROOT / 'secure-setup/vendor'
                assert not vendor.exists(), 'Refusing to overwrite an installed Composer vendor directory'
                vendor.mkdir(mode=0o700)
                test_vendor_created = True
                mail_mode = vendor / 'ci_mail_mode'
                mail_mode.write_text('fail', encoding='utf-8')
                (vendor / 'autoload.php').write_text(r'''<?php
namespace PHPMailer\PHPMailer;
class PHPMailer {
    public const ENCRYPTION_STARTTLS = 'tls';
    public string $Host = '', $Username = '', $Password = '', $SMTPSecure = '';
    public string $CharSet = '', $Subject = '', $Body = '';
    public int $Port = 0, $Timeout = 0;
    public bool $SMTPAuth = false;
    public function __construct(bool $exceptions = true) {}
    public function isSMTP(): void {}
    public function isHTML(bool $flag): void {}
    public function setFrom(string $address, string $name = ''): void {}
    public function addAddress(string $address, string $name = ''): void {}
    public function addReplyTo(string $address, string $name = ''): void {}
    public function send(): bool {
        $mode = trim((string)file_get_contents(__DIR__ . '/ci_mail_mode'));
        if ($mode === 'fail') throw new \RuntimeException('CI injected simulated SMTP failure');
        if ($mode !== 'success') throw new \RuntimeException('CI mailer mode missing');
        return true;
    }
}
''', encoding='utf-8')
                cfg_before = CONFIG.read_text(encoding='utf-8')
                assert "'mail' => ['enabled'=>true]" in cfg_before
                cfg_after = cfg_before.replace("'mail' => ['enabled'=>true]", 
                    "'mail' => ['enabled'=>true, 'host'=>'127.0.0.1', "
                    "'username'=>'ci-fake', 'password'=>'CI_INERT_MAILER_ONLY', "
                    "'from_email'=>'no-reply@example.invalid', 'inquiry_recipient'=>'owner@example.invalid']")
                CONFIG.write_text(cfg_after, encoding='utf-8')
                # Six failed attempts must back off and finally become 'dead'.
                # No real SMTP service or internet is contacted by the fake.
                for attempt in range(1, 7):
                    cursor.execute("UPDATE notification_outbox SET next_attempt_at=UTC_TIMESTAMP() WHERE id=%s", (notification_id,))
                    result = subprocess.run(['php', str(ROOT / 'secure-setup/maintenance/notification-worker.php'), '--run'],
                                            cwd=str(ROOT), text=True, capture_output=True, timeout=15)
                    assert result.returncode == 0, f'Worker run failed on attempt {attempt}'
                    cursor.execute('SELECT status,attempts,last_error_code,locked_at FROM notification_outbox WHERE id=%s', (notification_id,))
                    state = cursor.fetchone()
                    expected = 'dead' if attempt == 6 else 'pending'
                    assert state[0] == expected and state[1] == attempt and state[2] == 'smtp_unavailable' and state[3] is None, \
                        f'Wrong worker backoff/terminal behavior: {attempt}, {state}'
                    assert 'CI injected simulated SMTP failure' not in result.stdout + result.stderr, 'SMTP exception leaked'
                mail_mode.write_text('success', encoding='utf-8')
                revived = form(owner, base, {'action':'retry_notification','notification_id':str(notification_id)})
                assert 'Notification requeued' in revived.text, 'Owner could not revive simulated terminal SMTP failure'
                result = subprocess.run(['php', str(ROOT / 'secure-setup/maintenance/notification-worker.php'), '--run'],
                                        cwd=str(ROOT), text=True, capture_output=True, timeout=15)
                assert result.returncode == 0 and 'processed sent=1' in result.stdout, 'Successful fake delivery not acknowledged'
                cursor.execute('SELECT status,attempts,sent_at,locked_at FROM notification_outbox WHERE id=%s', (notification_id,))
                state = cursor.fetchone()
                assert state[0] == 'sent' and state[1] == 1 and state[2] is not None and state[3] is None, \
                    f'Successful fake send not persisted: {state}'
                print('Synthetic failure, bounded retries, dead-letter, owner requeue, success: PASS (no SMTP network)')
                for index in (1, 2):
                    created = form(owner, base, {'action':'add_partner', 'name':f'CI Partner {index}',
                                             'email':f'partner{index}-ci@example.invalid',
                                             'specialties':'synthetic AV', 'password':PARTNER_PASSWORD})
                    assert 'Partner created' in created.text, f'Partner {index} creation failed'
                cursor.execute('SELECT id FROM partners WHERE login_email=%s', ('partner1-ci@example.invalid',))
                partner_id = cursor.fetchone()[0]
                created = form(owner, base, {'action':'create_job', 'lead_id':str(lead[0]),
                                             'title':'CI PRIVATE project title', 'description':'Synthetic scope only',
                                             'partner_id':str(partner_id), 'partner_scope_checked':'yes'})
                assert 'Case ' in created.text and 'created' in created.text, 'Case create failed'
                cursor.execute('SELECT id,case_code,status FROM jobs WHERE lead_id=%s', (lead[0],))
                job_id, case_code, status = cursor.fetchone()
                assert status == 'assessment', 'Unexpected job initial status'
                link = BeautifulSoup(created.text, 'html.parser').select_one('section.alert code')
                assert link is not None, 'One-time tracking link not displayed'
                token = parse_qs(urlparse(link.text).query).get('token', [''])[0]
                assert re.fullmatch('[a-f0-9]{64}', token), 'Tracking token malformed'
                status_page = public.get(base + '/track/?token=' + token, timeout=5)
                assert status_page.status_code == 200 and case_code in status_page.text, 'Tracking link failed'
                assert 'CI PRIVATE project title' not in status_page.text and payload['fullName'] not in status_page.text, 'Tracking leaked private details'

                partner = requests.Session()
                partner.verify = False
                partner_login = form(partner, base, {'action':'login', 'email':'partner1-ci@example.invalid','password':PARTNER_PASSWORD})
                assert 'Your assigned jobs' in partner_login.text and case_code in partner_login.text, 'Partner cannot view assigned case'
                assert '/portal/intelligence.php' not in partner_login.text and partner.get(base + '/portal/intelligence.php', timeout=5).status_code == 404, 'Partner intelligence access'
                other = requests.Session()
                other.verify = False
                other_login = form(other, base, {'action':'login', 'email':'partner2-ci@example.invalid','password':PARTNER_PASSWORD})
                assert 'Your assigned jobs' in other_login.text and case_code not in other_login.text, 'Partner isolation failed'
                forged = form(other, base, {'action':'partner_update', 'job_id':str(job_id),
                                           'status':'assessment','note':'Unauthorized partner attempt'})
                assert 'not assigned' in forged.text, 'Partner changed another partner job'
                admin_only = partner.post(base + '/portal/', data={'csrf':csrf(partner, base), 'action':'set_lead_status',
                                                                       'lead_id':str(lead[0]), 'status':'closed'}, timeout=5)
                assert admin_only.status_code == 403, 'Partner accessed admin-only operation'
                illegal = form(partner, base, {'action':'partner_update', 'job_id':str(job_id),
                                               'status':'in_progress','note':'Try bypassing approval'})
                assert 'approval not recorded' in illegal.text, 'Partner bypass was not refused'
                require_status(cursor, job_id, 'assessment')
                def owner_update(new_status: str, proof: bool=False, quote: str='1200.00') -> requests.Response:
                    d={'action':'update_job', 'job_id':str(job_id), 'status':new_status,
                       'partner_id':str(partner_id), 'customer_quote':quote, 'partner_cost':'500.00',
                       'note':'Synthetic owner update'}
                    if proof:
                        d.update({'approval_method':'customer_email', 'approval_reference':'CI synthetic approval email 12345'})
                    return form(owner, base, d)
                owner_update('quote_pending')
                require_status(cursor, job_id, 'quote_pending')
                owner_update('awaiting_customer_approval')
                require_status(cursor, job_id, 'awaiting_customer_approval')
                no_proof = owner_update('approved')
                assert 'Record a customer approval method' in no_proof.text, 'Approval proof missing but allowed'
                require_status(cursor, job_id, 'awaiting_customer_approval')
                owner_update('approved', proof=True)
                require_status(cursor, job_id, 'approved')
                changed_quote = owner_update('approved', quote='1500.00')
                assert 'Return case to quote pending' in changed_quote.text, 'Approved quote changed without reopening approval'
                cursor.execute('SELECT customer_quote FROM jobs WHERE id=%s', (job_id,))
                assert float(cursor.fetchone()[0]) == 1200.00, 'Approved quote was modified'
                cursor.execute("SELECT COUNT(*) FROM job_activity WHERE job_id=%s AND event_type='customer_approval_recorded'", (job_id,))
                assert cursor.fetchone()[0] == 1, 'Approval audit event missing'
                started = form(partner, base, {'action':'partner_update', 'job_id':str(job_id),
                                               'status':'in_progress','note':'Synthetic work started'})
                assert 'Work update recorded' in started.text, 'Approved partner start failed'
                require_status(cursor, job_id, 'in_progress')
                form(partner, base, {'action':'partner_update', 'job_id':str(job_id),
                                     'status':'work_completed', 'note':'Synthetic completion'})
                require_status(cursor, job_id, 'work_completed')
                form(owner, base, {'action':'manage_partner', 'partner_id':str(partner_id), 'mode':'disable'})
                after_disable = partner.get(base + '/portal/', timeout=5)
                assert 'Secure Sign-in' in after_disable.text and case_code not in after_disable.text, 'Disabled partner session remained active'
                cursor.execute('UPDATE jobs SET tracking_expires_at=%s WHERE id=%s', ('2000-01-01 00:00:00',job_id))
                expired = public.get(base + '/track/?token=' + token, timeout=5)
                assert expired.status_code == 404, f'Expired tracking link accepted: {expired.status_code}'
                # Retention is dry-run by default. Only unassigned, expired
                # leads with resolved alerts can be deleted on explicit --run.
                cursor.execute("UPDATE leads SET created_at=DATE_SUB(UTC_TIMESTAMP(), INTERVAL 70 DAY) WHERE id=%s", (lead[0],))
                for code, alert_status in [('RET-SENT', 'sent'), ('RET-PENDING', 'pending'), ('RET-DEAD', 'dead')]:
                    cursor.execute("INSERT INTO leads(reference,full_name,email,phone,service,device,message,source,consent_at,created_at) VALUES(%s,%s,%s,'','IT Support','','CI retention data','website',UTC_TIMESTAMP(),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 70 DAY))", (code, 'Synthetic CI Retention', f'{code.lower()}@example.invalid'))
                    lid = cursor.lastrowid
                    cursor.execute('INSERT INTO notification_outbox(lead_id,status) VALUES(%s,%s)', (lid,alert_status))
                retention = ROOT / 'secure-setup/maintenance/prune.php'
                default_dry = subprocess.run(['php', str(retention)], cwd=str(ROOT), capture_output=True, text=True, timeout=10)
                assert default_dry.returncode == 0 and 'READ-ONLY' in default_dry.stdout and 'Eligible unassigned leads: 1' in default_dry.stdout, 'Retention dry-run failed'
                cursor.execute("SELECT COUNT(*) FROM leads WHERE reference LIKE 'RET-%'")
                assert cursor.fetchone()[0] == 3, 'Default retention unexpectedly deleted records'
                invalid = subprocess.run(['php', str(retention), '--unrecognized'], cwd=str(ROOT), capture_output=True, text=True, timeout=10)
                assert invalid.returncode == 2, 'Retention accepted an unknown argument'
                executed = subprocess.run(['php', str(retention), '--run'], cwd=str(ROOT), capture_output=True, text=True, timeout=10)
                assert executed.returncode == 0 and 'Deleted eligible unassigned leads: 1' in executed.stdout, 'Explicit retention cleanup failed'
                cursor.execute("SELECT reference FROM leads WHERE reference LIKE 'RET-%' ORDER BY reference")
                assert [x[0] for x in cursor.fetchall()] == ['RET-DEAD','RET-PENDING'], 'Pending/dead notifications were pruned'
                cursor.execute('SELECT COUNT(*) FROM leads WHERE id=%s', (lead[0],))
                assert cursor.fetchone()[0] == 1, 'Assigned customer case was pruned'
                print('Synthetic retention dry-run, explicit deletion and notification protections: PASS')
                print('Synthetic MariaDB + HTTP lead/portal/partner/approval/tracking E2E: PASS')
            finally:
                if proxy is not None:
                    proxy.shutdown()
                    proxy.server_close()
                    worker.join(timeout=5)
                if cert_dir is not None:
                    shutil.rmtree(cert_dir)
                server.terminate()
                try: server.wait(timeout=5)
                except subprocess.TimeoutExpired: server.kill(); server.wait()
                # Only remove the mailer fake if this test created its directory.
                if test_vendor_created:
                    shutil.rmtree(ROOT / 'secure-setup/vendor')
                # Directory was created solely by this test (exist_ok=False above).
                shutil.rmtree(CONFIG.parent)


if __name__ == '__main__':
    run()
