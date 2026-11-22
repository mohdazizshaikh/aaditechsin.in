"""Headless smoke suite: inlines local HTML/CSS/JS for sandboxed browser environments.
Run: python tests/browser_smoke.py
These are UI behavioral tests, not substitute for real HTTPS/Hostinger integration tests.
"""
from pathlib import Path
import os
from bs4 import BeautifulSoup
from playwright.sync_api import sync_playwright
ROOT=Path(__file__).parents[1]/'public_html'
def self_contained(path):
    document=ROOT/path
    soup=BeautifulSoup(document.read_text(),'html.parser')
    for tag in list(soup.select('link[href],script[src]')):
        asset=tag.get('href') if tag.name=='link' else tag.get('src')
        if not asset or asset.startswith('https:'):
            if tag.name=='link' and tag.get('rel')==['stylesheet'] and asset and 'fonts' in asset: tag.decompose()
            continue
        # Only inline local CSS and JS; preserve other links so their hrefs can be validated.
        if not asset.endswith(('.css','.js')):continue
        resolved=(document.parent/asset).resolve()
        if not resolved.is_relative_to(ROOT) or not resolved.exists():continue
        replacement=soup.new_tag('style' if asset.endswith('.css') else 'script')
        replacement.string=resolved.read_text()
        tag.replace_with(replacement)
    return str(soup)
with sync_playwright() as p:
    browser=p.chromium.launch(headless=True,executable_path=os.environ.get('CHROMIUM_PATH','/usr/bin/chromium'),args=['--no-sandbox'])
    page=browser.new_page(viewport={'width':1366,'height':900},device_scale_factor=1)
    errors=[]
    page.on('pageerror',lambda exc: errors.append(str(exc)))
    page.set_content(self_contained('index.html'),wait_until='load')
    assert 'audio visual' in page.locator('#audio-visual').inner_text().lower()
    assert page.locator('.av-feature-grid a').count()==3
    assert page.locator('#navLinks a[href="#audio-visual"]').count()==1
    page.get_by_role('button',name='Audio Visual & Collaboration').click()
    assert page.locator('#services .service-card:visible').count()==4
    page.get_by_role('button',name='All services').click()
    assert page.locator('#services .service-card:visible').count()==14
    page.get_by_role('button',name='Aaditechs Smart Guide').click()
    assert page.locator('#aaditechsChat').is_visible()
    page.get_by_role('button',name='Audio Visual / Conferencing').click()
    for answer in ['Conference room','25 people','Andheri East - hybrid Teams room']:
        page.locator('.chat-input-row input').fill(answer)
        page.locator('.chat-input-row button').click()
    assert 'Your assessment summary is ready' in page.locator('.chat-log').inner_text()
    page.get_by_role('button',name='Submit inquiry').click()
    page.locator('.chat-contact-form input[name="fullName"]').fill('Browser Tester')
    page.locator('.chat-contact-form input[name="email"]').fill('test@example.com')
    page.locator('.chat-contact-form input[name="contactConsent"]').check()
    page.locator('.chat-contact-form button[type="submit"]').click()
    assert 'Demo mode' in page.locator('.chat-form-status').inner_text()
    page.screenshot(path='/mnt/data/aaditechs_work/chat-check.png')
    print('homepage + category filtering + chat assessment + demo mode: PASS')
    for slug in ('video-conferencing-solutions','auditorium-av-solutions','professional-sound-systems'):
        page.set_content(self_contained(slug+'/index.html'))
        assert page.locator('h1').count()==1
        assert page.locator('link[rel=canonical]').get_attribute('href')=='https://aaditechs.in/'+slug+'/'
        assert page.locator('a[href^="../av-project-planner/"]').count()>0
        assert 'Aaditechs' in page.title()
    for slug in ('data-recovery','it-amc'):
        page.set_content(self_contained(slug+'/index.html'))
        assert page.locator('h1').count()==1
        assert page.locator('link[rel=canonical]').get_attribute('href')=='https://aaditechs.in/'+slug+'/'
        assert page.get_by_role('link',name='Call Aaditechs').is_visible()
        assert 'Aaditechs' in page.title()
    print('3 dedicated AV + 2 new service pages: PASS')
    page.set_content(self_contained('av-project-planner/index.html'))
    page.locator('#projectType').select_option(label='Video Conferencing')
    page.locator('#capacity').select_option(label='11–25')
    page.locator('#siteCity').fill('Andheri East, Mumbai')
    page.locator('#siteStage').select_option(label='New installation')
    page.locator('input[value="Microphones"]').check()
    page.get_by_role('button',name='Preview scope').click()
    assert 'Microphones' in page.locator('#scopePreview').inner_text()
    page.locator('#fullName').fill('Browser Tester')
    page.locator('#email').fill('test@example.com')
    page.locator('[name=contactConsent]').check()
    page.get_by_role('button',name='Prepare AV inquiry').click()
    assert 'nothing has been sent or saved' in page.locator('#plannerStatus').inner_text()
    page.screenshot(path='/mnt/data/aaditechs_work/planner-check.png',full_page=True)
    print('AV builder summary + privacy-safe demo: PASS')
    page.set_content(self_contained('privacy/index.html'))
    assert 'owner review' in page.locator('main').inner_text().lower()
    mobile=browser.new_page(viewport={'width':390,'height':844},device_scale_factor=1)
    mobile.set_content(self_contained('video-conferencing-solutions/index.html'))
    assert mobile.locator('h1').is_visible()
    mobile.screenshot(path='/mnt/data/aaditechs_work/mobile-check.png',full_page=True)
    assert not errors,errors
    print('responsive + no JavaScript runtime errors: PASS')
    browser.close()
