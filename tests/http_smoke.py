"""Minimal HTTP negative-path checks using PHP's built-in server without a real DB."""
from pathlib import Path
from subprocess import Popen, DEVNULL
import urllib.request, urllib.error, time, socket
WEB=Path(__file__).parents[1]/'public_html'
sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close()
server=Popen(['php','-S',f'127.0.0.1:{port}','-t',str(WEB)],stdout=DEVNULL,stderr=DEVNULL)
def request(path,method='GET',payload=None,content_type=None):
    req=urllib.request.Request(f'http://127.0.0.1:{port}{path}',data=payload,method=method,headers={'Content-Type':content_type} if content_type else {})
    try:
        with urllib.request.urlopen(req,timeout=2) as r: return r.status,r.read()
    except urllib.error.HTTPError as e: return e.code,e.read()
try:
    for _ in range(30):
        try: request('/');break
        except Exception:time.sleep(.1)
    status,_=request('/portal/')
    assert status==503,('unconfigured portal',status)
    status,_=request('/portal/intelligence.php')
    assert status==404,('unconfigured intelligence',status)
    status,_=request('/portal/intelligence-export.php')
    assert status==404,('unconfigured intelligence export',status)
    status,_=request('/api/google-reviews.php')
    assert status==503,('unconfigured google api',status)
    status,_=request('/api/lead.php','POST',b'{}','application/json')
    assert status==403,('HTTP lead blocked',status)
    status,_=request('/api/analytics.php','POST',b'{}','application/json')
    assert status==403,('HTTP analytics blocked',status)
    status,_=request('/track/?token=invalid')
    assert status==404,('invalid tracking token',status)
    status,body=request('/data-recovery/')
    assert status==200 and b'Data Recovery Assessment' in body,('data recovery page',status)
    print('PHP HTTP disabled/unconfigured/invalid-token paths: PASS')
finally:
    server.terminate();server.wait(timeout=5)
