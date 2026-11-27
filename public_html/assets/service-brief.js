/* Local-only service discussion brief. No submissions, storage or network requests. */
(() => {
  'use strict';
  function buildBrief(name, topics) {
    if (typeof name !== 'string' || !name.trim() || name.length > 100 ||
        !Array.isArray(topics) || topics.length < 1 || topics.length > 4 ||
        topics.some(x => typeof x !== 'string' || !x.trim() || x.length > 160) ||
        new Set(topics).size !== topics.length) return '';
    return 'Service: ' + name + '\n\nPlease discuss the following proposal topics:\n' +
      topics.map(x => '- ' + x).join('\n') +
      '\n\nPlease confirm scope, exclusions, pricing, availability and next steps. This is a discussion brief, not an approved quotation.';
  }
  if (typeof module !== 'undefined' && module.exports) module.exports = {buildBrief};
  if (typeof document === 'undefined') return;
  for (const root of document.querySelectorAll('.service-brief')) {
    const boxes = [...root.querySelectorAll('input[type=checkbox]')];
    const actions = root.querySelector('.brief-actions'), result = root.querySelector('.brief-result');
    const output = root.querySelector('textarea'), status = root.querySelector('[data-brief-status]');
    if (boxes.length !== 4 || !actions || !result || !output || !status) continue;
    boxes.forEach(x => { x.disabled = false; });
    actions.hidden = false;
    const invalidate = () => { result.hidden = true; output.value = ''; status.textContent = ''; };
    boxes.forEach(x => x.addEventListener('change', invalidate));
    root.querySelector('[data-brief-build]').addEventListener('click', () => {
      const topics = boxes.filter(x => x.checked).map(x => x.nextElementSibling.textContent.trim());
      const brief = buildBrief(root.dataset.serviceName, topics);
      if (!brief) { invalidate(); status.textContent = 'Choose at least one topic.'; return; }
      output.value = brief; result.hidden = false; status.textContent = 'Brief ready. Review or edit before copying.'; output.focus();
    });
    root.querySelector('[data-brief-reset]').addEventListener('click', () => {
      boxes.forEach(x => { x.checked = false; }); invalidate(); boxes[0].focus();
    });
    root.querySelector('[data-brief-copy]').addEventListener('click', async () => {
      try {
        if (!output.value.trim() || !navigator.clipboard?.writeText) throw new Error('Copy unavailable');
        await navigator.clipboard.writeText(output.value);
        status.textContent = 'Copied. Paste into your chosen contact channel when ready.';
      } catch {
        output.focus(); output.select(); status.textContent = 'Select and copy the brief manually.';
      }
    });
  }
})();
