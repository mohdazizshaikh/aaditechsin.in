/* Aaditechs public integration. Google OAuth credentials NEVER enter the browser. */
(() => {
  'use strict';
  const cfg = window.AADITECHS_PUBLIC_CONFIG || {};
  const feature = cfg.features || {};
  const reviewCard = document.querySelector('.google-review-card');
  const isGoogleLink = value => {
    try { const u = new URL(value); return u.protocol === 'https:' && (u.hostname === 'share.google' || u.hostname === 'google.com' || u.hostname.endsWith('.google.com') || u.hostname === 'g.page'); }
    catch { return false; }
  };
  const addLink = (parent, label, url, extraClass = '') => {
    if (!isGoogleLink(url)) return;
    const a = document.createElement('a');
    a.href = url; a.target = '_blank'; a.rel = 'noopener noreferrer';
    a.className = extraClass; a.textContent = label; parent.append(a);
  };
  if (reviewCard) {
    const heading = reviewCard.querySelector('.google-review-heading small');
    const pill = reviewCard.querySelector('.integration-pill');
    const title = reviewCard.querySelector('.rating-placeholder strong');
    const body = reviewCard.querySelector('.rating-placeholder p');
    if (heading) heading.textContent = 'Official Aadi Tech Solutions profile';
    if (pill) pill.textContent = 'Verified profile · API pending';
    if (title) title.textContent = 'Authentic Google reviews only';
    if (body) body.textContent = 'Google Business Profile API access remains pending until the 60-day eligibility requirement and Google approval are complete. No ratings or testimonials are invented.';
    const actions = document.createElement('div');
    actions.className = 'review-owner-actions';
    addLink(actions, 'View official Google profile ↗', cfg.googleBusinessProfileUrl);
    addLink(actions, 'Write a Google review ↗', cfg.googleReviewRequestUrl, 'review-write-link');
    reviewCard.append(actions);
    if (!cfg.googleReviewRequestUrl) {
      const help = document.createElement('p');
      help.className = 'review-status-text';
      help.textContent = 'The direct “Write a review” link will be added after confirming the correct Google Business Profile link.';
      reviewCard.append(help);
    }
    const status = reviewCard.querySelector('.rating-placeholder');
    const placeholderRows = [...reviewCard.querySelectorAll('.review-placeholder-row')];
    if (feature.googleReviewsApi === true && cfg.googleReviewsEndpoint) {
      fetch(cfg.googleReviewsEndpoint, { credentials:'same-origin', cache:'no-store', headers:{ Accept:'application/json' } })
        .then(async response => { if (!response.ok) throw new Error('Unavailable'); return response.json(); })
        .then(data => {
          if (!Array.isArray(data.reviews) || typeof data.totalReviewCount !== 'number') throw new Error('Invalid response');
          const entries = data.reviews.filter(x => typeof x.author === 'string' && Number.isFinite(x.rating) && typeof x.comment === 'string');
          const list = document.createElement('div'); list.className = 'review-real-list';
          for (const entry of entries.slice(0, 6)) {
            const article = document.createElement('article');
            const header = document.createElement('header');
            const name = document.createElement('strong'); name.textContent = entry.author;
            const date = document.createElement('time'); date.textContent = entry.date || '';
            header.append(name,date);
            const stars = document.createElement('div'); stars.className = 'review-stars';
            stars.textContent = '★'.repeat(Math.max(1, Math.min(5, Math.round(entry.rating))));
            const comment = document.createElement('p'); comment.textContent = entry.comment;
            article.append(header,stars,comment); list.append(article);
          }
          if (typeof data.averageRating === 'number' && data.totalReviewCount > 0) {
            const score = document.createElement('p'); score.className = 'review-live-rating';
            score.textContent = `${data.averageRating.toFixed(1)} / 5 · ${data.totalReviewCount} Google reviews`;
            status.replaceChildren(score);
          } else if (status) {
            status.replaceChildren(document.createTextNode('There are no published Google reviews yet.'));
          }
          placeholderRows.forEach(x => x.remove());
          if (entries.length) reviewCard.append(list);
          if (pill) pill.textContent = 'Connected to Google';
        })
        .catch(() => { if (pill) pill.textContent = 'Review sync temporarily unavailable'; });
    }
  }

  const form = document.getElementById('contactForm');
  if (form && feature.leadSubmission === true && cfg.leadEndpoint) {
    const submit = form.querySelector('[type=submit]');
    if (submit) submit.textContent = 'Send Inquiry ↗';
    const note = form.querySelector('.form-note');
    if (note) note.textContent = 'Your inquiry is sent securely to Aaditechs. Do not include passwords or confidential files.';
    const agreement = document.createElement('label'); agreement.className = 'lead-consent';
    const checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.name = 'contactConsent'; checkbox.required = true; checkbox.value = 'yes';
    agreement.append(checkbox,document.createTextNode(' I agree to be contacted by Aaditechs about this inquiry and have read the '));const privacyLink=document.createElement('a');privacyLink.href='/privacy/';privacyLink.target='_blank';privacyLink.rel='noopener';privacyLink.textContent='privacy notice';agreement.append(privacyLink,document.createTextNode('.'));
    submit.before(agreement);
    const honeypot = document.createElement('input');
    honeypot.type = 'text'; honeypot.name = 'company_website'; honeypot.tabIndex = -1; honeypot.autocomplete = 'off'; honeypot.setAttribute('aria-hidden', 'true');
    honeypot.hidden = true; form.append(honeypot);
    form.addEventListener('submit', async event => {
      event.preventDefault(); if (!form.reportValidity()) return;
      const message = document.getElementById('formMessage');
      if (submit) submit.disabled = true;
      if (message) { message.style.display='block'; message.textContent='Sending your inquiry…'; }
      try {
        const data = Object.fromEntries(new FormData(form));
        const response = await fetch(cfg.leadEndpoint, { method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json','Accept':'application/json'}, body:JSON.stringify(data) });
        if (!response.ok) throw new Error('Could not send inquiry');
        const result=await response.json();if(result.ok!==true)throw new Error('Could not save inquiry');
        if (message) message.textContent='Thank you. Your inquiry has been received'+(result.reference?' (reference '+result.reference+')':'')+'.';
        form.reset();
      } catch {
        if (message) message.textContent='We could not submit your inquiry. Please email info@aaditechs.in or call +91 97736 93440.';
      } finally { if (submit) submit.disabled=false; }
    });
  }
})();
