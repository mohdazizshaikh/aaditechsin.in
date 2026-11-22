/** Optional local Chromium smoke test (no server, outbound API or customer records). */
'use strict';
const assert = require('node:assert/strict');
const path = require('node:path');
const { pathToFileURL } = require('node:url');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..', 'public_html');
const codes = [
  ['IT_AMC', 'IT AMC & Office Support', 4],
  ['VC', 'Video Conferencing & Meeting Rooms', 4],
  ['AUD_AV', 'Auditorium AV', 4],
  ['SOUND', 'Professional Sound & PA', 4],
  ['RECOVERY', 'Data Recovery', 3],
  ['HARDWARE', 'IT Hardware & Procurement', 3],
  ['SOFTWARE', 'Software & Automation', 3]
];
async function run() {
  const browser = await chromium.launch({
    headless: true,
    executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE || '/usr/bin/chromium',
    args: ['--no-sandbox']
  });
  try {
    const page = await browser.newPage({viewport:{width:390,height:844}});
    const errors=[];
    page.on('pageerror', error=>errors.push(error.message));
    await page.goto(pathToFileURL(path.join(root,'index.html')).href);
    await page.locator('.chat-launch').click();
    for (const [code, label, count] of codes) {
      await page.locator('.chat-options button').getByText(label,{exact:true}).click();
      for (let i=0;i<count;i++) {
        await page.locator('.chat-input-row input').fill(`Test assessment ${code} answer ${i+1}`);
        await page.locator('.chat-input-row button[type=submit]').click();
      }
      const summary=await page.locator('.chat-summary').textContent();
      assert.ok(summary.includes(`(${code})`), `${code} appears in scope summary`);
      await page.locator('.chat-options button').getByText('Edit answers').click();
      await page.locator('.chat-options button').first().click();
      await page.locator('.chat-input-row input').fill('Updated safely');
      await page.locator('.chat-input-row button[type=submit]').click();
      assert.ok((await page.locator('.chat-summary').textContent()).includes('Updated safely'), `${code} edit persisted`);
      if (code==='VC') {
        await page.locator('.chat-options button').getByText('Submit inquiry').click();
        await page.locator('.chat-contact-form input[name=fullName]').fill('Test Person');
        await page.locator('.chat-contact-form input[name=email]').fill('test@example.invalid');
        await page.locator('.chat-contact-form button[type=submit]').click();
        assert.equal(await page.locator('.chat-form-status').textContent(), '', 'consent required before submission');
        await page.locator('.chat-contact-form input[name=contactConsent]').check();
        await page.locator('.chat-contact-form button[type=submit]').click();
        assert.match(await page.locator('.chat-form-status').textContent(), /Demo mode: no information was submitted or saved/);
      }
      await page.locator('.chat-reset').click();
    }
    assert.deepEqual(errors, [], 'browser scripts load without exceptions');
    await page.goto(pathToFileURL(path.join(root,'video-conferencing-solutions/index.html')).href);
    await page.locator('.chat-launch').click();
    assert.match(await page.locator('.chat-log').textContent(), /How many meeting rooms/,'VC page preselects VC guide');
    await page.locator('.chat-close').click();
    assert.equal(await page.locator('.chat-launch').isVisible(), true, 'chat closes accessibly');
    console.log('V9-02 mobile Chromium guided chat, 7 flows, edits, consent, fallback: PASS');
  } finally { await browser.close(); }
}
run().catch(e=>{console.error(e);process.exitCode=1;});
