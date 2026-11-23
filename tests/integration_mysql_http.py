"""Disposable MySQL + PHP HTTP integration tests with synthetic identities only.

Only run in CI or a local disposable environment. NEVER point at a live database.
The script creates a private test-only config, imports schema into the CI database,
runs a local PHP server, then removes all generated secrets and test sessions.
"""
from __future__ import annotations

import contextlib
import os
from pathlib import Path
import re
import shutil
import socket
import subprocess
import tempfile
import time
from urllib.parse import urlparse, parse_qs

from bs4 import BeautifulSoup
import pymysql
import requests

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
  'rate_limit_salt' => 'CI_ONLY_RANDOM_SALT_NOT_PRODUCTION_32_BYTES_LONG',
  'database' => ['enabled'=>true, 'host'=>%s, 'port'=>%s, 'name'=>%s, 'username'=>%s, 'password'=>%s],
  'leads' => ['enabled'=>true],
  'portal' => ['enabled'=>true, 'admin_email'=>%s, 'admin_password_hash'=>%s,
               'admin_totp_enabled'=>false, 'admin_totp_secret'=>''],
  'mail' => ['enabled'=>false], 'google' => ['enabled'=>false]
];\n''' % tuple(map(php_quote, [DB_HOST, str(DB_PORT), DB_NAME, DB_USER, DB_PASSWORD, OWNER_EMAIL, hashed])), encoding='utf-8')
        CONFIG.chmod(0o600)
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        base = f'http://127.0.0.1:{port}'
        with tempfile.TemporaryFile() as server_log:
            server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(WEB),
                                       str(ROOT / 'tests/ci_https_router.php')],
                                      cwd=str(ROOT), stdout=server_log, stderr=server_log)
            try:
                for _ in range(40):
                    try:
                        if requests.get(base + '/portal/', timeout=1).status_code == 200:
                            break
                    except requests.RequestException:
                        time.sleep(.1)
                else:
                    raise AssertionError('Local PHP server not ready')
                public = requests.Session()
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
                rejected = public.post(base + '/api/lead.php', json=payload, timeout=5)
                assert rejected.status_code == 429, 'Duplicate/flood guard failed'
                cursor.execute('SELECT COUNT(*) FROM leads')
                assert cursor.fetchone()[0] == 1, 'Duplicate lead unexpectedly persisted'

                owner = requests.Session()
                before = csrf(owner, base)
                wrong = form(owner, base, {'action':'login', 'email':OWNER_EMAIL,'password':'incorrect'})
                assert 'Invalid sign-in details' in wrong.text, 'Invalid owner password not rejected'
                logged = form(owner, base, {'action':'login', 'email':OWNER_EMAIL,'password':OWNER_PASSWORD})
                assert 'Owner Dashboard' in logged.text, 'Owner login unsuccessful'
                assert csrf(owner, base) != before, 'CSRF did not rotate after privilege elevation'
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
                partner_login = form(partner, base, {'action':'login', 'email':'partner1-ci@example.invalid','password':PARTNER_PASSWORD})
                assert 'Your assigned jobs' in partner_login.text and case_code in partner_login.text, 'Partner cannot view assigned case'
                other = requests.Session()
                other_login = form(other, base, {'action':'login', 'email':'partner2-ci@example.invalid','password':PARTNER_PASSWORD})
                assert 'Your assigned jobs' in other_login.text and case_code not in other_login.text, 'Partner isolation failed'
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
                print('Synthetic MariaDB + HTTP lead/portal/partner/approval/tracking E2E: PASS')
            finally:
                server.terminate()
                try: server.wait(timeout=5)
                except subprocess.TimeoutExpired: server.kill(); server.wait()
                # Directory was created solely by this test (exist_ok=False above).
                shutil.rmtree(CONFIG.parent)


if __name__ == '__main__':
    run()
