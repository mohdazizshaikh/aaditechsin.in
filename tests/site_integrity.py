"""Validate local links/assets, canonical URLs and essential privacy barriers."""
from pathlib import Path
from bs4 import BeautifulSoup
from urllib.parse import urlsplit, unquote
import re
ROOT=Path(__file__).resolve().parents[1]
WEB=ROOT/'public_html'
issues=[]
for doc in WEB.rglob('*.html'):
    soup=BeautifulSoup(doc.read_text(encoding='utf8'),'html.parser')
    if not soup.select_one('h1'): issues.append(f'{doc}: missing H1')
    if doc.name=='index.html' and doc.parent!=WEB and doc.parent.name in ('portal','track'):continue
    canonical=soup.select_one('link[rel=canonical]')
    if doc.parent.name not in ('privacy',) and not canonical: issues.append(f'{doc}: missing canonical')
    for el in soup.select('a[href],link[href],script[src],img[src]'):
        raw=el.get('href') or el.get('src')
        if not raw or raw.startswith(('https://','http://','mailto:','tel:','data:','javascript:')): continue
        if raw.startswith('#'):
            if raw!='#' and not soup.select_one('[id="'+raw[1:]+'"]'):
                issues.append(f'{doc.relative_to(WEB)}: missing anchor {raw}')
            continue
        url=urlsplit(raw)
        if url.scheme or url.netloc: continue
        target=(WEB/url.path.lstrip('/')).resolve() if url.path.startswith('/') else (doc.parent/unquote(url.path)).resolve()
        if target.is_dir():target/= 'index.html'
        if not target.is_relative_to(WEB) or not target.is_file(): issues.append(f'{doc.relative_to(WEB)}: missing local target {raw}')
for name in ('data-recovery','it-amc','video-conferencing-solutions','auditorium-av-solutions','professional-sound-systems','av-project-planner'):
    if not (WEB/name/'index.html').is_file():issues.append(f'missing service page {name}')
public=(WEB/'assets/site-config.js').read_text()
if not re.search(r'leadSubmission:\s*false',public):issues.append('public lead feature unexpectedly on')
if issues:
    print('\n'.join(issues));raise SystemExit(f'FAILED: {len(issues)} issues')
print('Local links + source references + service pages + OFF flags: PASS')

# Service directory must be crawlable without running JavaScript.
import json
catalog=json.loads((WEB/'assets/service-registry.json').read_text())['services']
route_map={"infrastructure":"it-infrastructure","software-web":"software-web-development","it-amc":"it-amc","cloud":"cloud-modern-it","security":"security-compliance","data-backup":"data-management-backup","it-procurement":"it-asset-procurement","smart-office":"smart-office-solutions","special-projects":"it-special-projects","video-conferencing":"video-conferencing-solutions","auditorium-av":"auditorium-av-solutions","professional-sound":"professional-sound-systems","av-maintenance":"av-installation-maintenance","data-recovery":"data-recovery"}
assert set(route_map)=={s['id'] for s in catalog} and len(route_map)==14
home=BeautifulSoup((WEB/'index.html').read_text(),'html.parser')
assert len(home.select('#services article'))==14
for service in catalog:
    path=route_map[service['id']]
    assert home.select_one('#services a[href="'+path+'/"]'),path
    page=BeautifulSoup((WEB/path/'index.html').read_text(),'html.parser')
    assert len(page.select('h1'))==1 and page.select_one('link[rel=canonical]')['href']=='https://aaditechs.in/'+path+'/'
    assert page.select_one('a[href^="tel:"]'),path
    assert len(page.select('details'))>=3,path
assert not home.select('link[href*="fonts.googleapis.com"]')
assert home.select_one('.hero-photo[fetchpriority=high]') and home.select_one('link[rel=preload][as=image]')
print('14 dedicated service pages and static homepage navigation: PASS')
