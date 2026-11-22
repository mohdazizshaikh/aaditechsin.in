/** Node-only V9-02 contract and regression tests; never accesses hosting or customer data. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '..');
const web = path.join(root, 'public_html');
const context = {window: {}};
vm.runInNewContext(fs.readFileSync(path.join(web, 'assets/service-playbooks.js'), 'utf8'), context);
const list = context.window.AADITECHS_SERVICE_PLAYBOOKS;
const match = context.window.AADITECHS_MATCH_SERVICE;
const codes = ['IT_AMC', 'VC', 'AUD_AV', 'SOUND', 'RECOVERY', 'HARDWARE', 'SOFTWARE'];
assert.deepEqual(Object.keys(list).sort(), codes.sort(), 'seven approved service playbooks');
for (const code of codes) {
  const entry = list[code];
  assert.ok(entry.label && entry.label.length <= 100, `${code}: valid lead service label`);
  assert.ok(entry.intro && /review|assess|confirm|guarantee|partner|scope|availability/i.test(entry.intro), `${code}: bounded claims`);
  assert.ok(entry.path.startsWith('/'), `${code}: local service destination`);
  assert.ok(entry.questions.length >= 3 && entry.questions.length <= 5, `${code}: 3-5 questions`);
  assert.ok(entry.questions.every(q => q.length <= 160), `${code}: concise questions`);
  // The V8 lead endpoint accepts 2,000 chars of message; 300-char answers fit all guides.
  const sample = `${entry.label} (${code})\n${entry.questions.map((q, i) => `${i + 1}. ${q}\n   ${'a'.repeat(300)}`).join('\n')}`;
  assert.ok(sample.length <= 2000, `${code}: full answers fit V8 lead API`);
}
const examples = [
  ['office AMC contract for 25 computers', 'IT_AMC'],
  ['Teams room video conferencing upgrade', 'VC'],
  ['boardroom camera and microphone', 'VC'],
  ['auditorium stage audio project', 'AUD_AV'],
  ['PA system microphone for a hall', 'SOUND'],
  ['SSD data recovery', 'RECOVERY'],
  ['buy laptop for office', 'HARDWARE'],
  ['custom website and automation', 'SOFTWARE']
];
for (const [message, expected] of examples) assert.equal(match(message), expected, `route ${message}`);
assert.equal(match('hello namaste'), null, 'unknown request must not be auto-qualified');
assert.equal(match({code: 'VC'}), null, 'non-string input ignored');
assert.equal(match(''), null, 'empty input ignored');
const chat = fs.readFileSync(path.join(web, 'assets/chat-v2.js'), 'utf8');
assert.ok(chat.includes('Edit answers') && chat.includes('edit-answer'), 'editable summary present');
assert.ok(chat.includes('contactConsent') && chat.includes("if(!enabled)"), 'consent and demo mode preserved');
assert.ok(chat.includes("state.step='review'"), 'summary review before submit');
assert.ok(chat.includes('no private data') && chat.includes('Never share passwords'), 'privacy guidance');
for (const file of fs.readdirSync(web, {withFileTypes: true})) {
  const page = file.isDirectory() ? path.join(web, file.name, 'index.html') : file.name === 'index.html' ? path.join(web, 'index.html') : null;
  if (!page || !fs.existsSync(page)) continue;
  const html = fs.readFileSync(page, 'utf8');
  if (!html.includes('chat-v2.js')) continue;
  const dataAt = html.indexOf('service-playbooks.js');
  const chatAt = html.indexOf('chat-v2.js');
  assert.ok(dataAt >= 0 && dataAt < chatAt, `playbooks load before guide: ${page}`);
}
console.log('V9-02 playbook, routing, consent and integration contracts: PASS');
