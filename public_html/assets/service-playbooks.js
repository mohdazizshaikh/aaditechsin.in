/** Public, owner-reviewable V9 service qualification data. No prices, guarantees or secrets. */
(() => {
  'use strict';
  const playbooks = {
    IT_AMC: {
      label: 'IT AMC & Office Support', path: '/it-amc/',
      intro: 'We coordinate office IT support and AMC assessments. Coverage, visits and terms need owner confirmation.',
      questions: [
        'How many users/computers and office locations need support?',
        'Which systems need help (desktops, network, server, printers, backups, or others)?',
        'Do you need ongoing AMC or one-time support, and what response needs matter?',
        'Which locality is the office in, and when would you like an assessment?'
      ],
      terms: [/(?:\bamc\b|maintenance contract|managed it|annual maintenance)/i, /(?:it support|office support|desktop support|computer support|network support|server support)/i]
    },
    VC: {
      label: 'Video Conferencing & Meeting Rooms', path: '/video-conferencing-solutions/',
      intro: 'We help scope video conferencing and hybrid meeting rooms with specialist AV partners. Final compatibility and quotation follow assessment.',
      questions: [
        'How many meeting rooms, and approximately how many seats in each?',
        'Which calling platform is used (Teams, Zoom, Google Meet, other or undecided)?',
        'Do you need new cameras, microphones, speakers, displays, control or an upgrade?',
        'Where is the site, and what is the preferred installation timeline?'
      ],
      terms: [/(?:video conferenc|video call|hybrid meeting|meeting room|conference room|boardroom|huddle room)/i, /(?:\bteams room\b|\bzoom room\b|meeting camera|conference camera)/i]
    },
    AUD_AV: {
      label: 'Auditorium AV', path: '/auditorium-av-solutions/',
      intro: 'We coordinate auditorium AV scoping with specialist partners; a site assessment is required before any commitment.',
      questions: [
        'What type of venue is this (auditorium, classroom, training hall or other)?',
        'What is the approximate audience capacity and intended use?',
        'What projection, microphones, speakers or control equipment is needed?',
        'What is the venue location and ideal completion window?'
      ],
      terms: [/(?:auditorium|lecture hall|stage audio|projection system|lecture theatre)/i, /(?:\bav integration\b|audio visual system)/i]
    },
    SOUND: {
      label: 'Professional Sound & PA', path: '/professional-sound-systems/',
      intro: 'We coordinate professional sound and PA requirements; equipment availability and specifications are confirmed with partners.',
      questions: [
        'Is this for announcements, events, background music or another sound use?',
        'What venue size, capacity or coverage area is involved?',
        'Do you need speakers, microphones, mixer, amplifier, cabling or installation?',
        'What is the location and expected project timeline?'
      ],
      terms: [/(?:sound system|pa system|public address|microphone|loudspeaker|amplifier|sound installation)/i, /(?:\bspeakers\b|audio mixer)/i]
    },
    RECOVERY: {
      label: 'Data Recovery', path: '/data-recovery/',
      intro: 'Please stop using a failing drive where practical. Avoid repeated repair attempts or formatting. Data recovery cannot be guaranteed.',
      questions: [
        'Which device is affected (HDD, SSD, USB, RAID/NAS or unsure)?',
        'What symptoms are visible? Do not send filenames, passwords or confidential data.',
        'Which locality is the device in, and how urgent is assessment?'
      ],
      terms: [/(?:data recover|disk recover|file recover|deleted file|lost data)/i, /(?:hard disk|\bhdd\b|\bssd\b|\braid\b|\bnas\b|corrupt drive)/i]
    },
    HARDWARE: {
      label: 'IT Hardware & Procurement', path: '/#services',
      intro: 'We coordinate sourcing through equipment partners. Stock, compatibility, final pricing and delivery need confirmation.',
      questions: [
        'Which equipment is required (laptop, workstation, server, network, printer or other)?',
        'What quantity and important specifications are needed?',
        'Where should equipment be delivered and when is it required?'
      ],
      terms: [/(?:buy laptop|buy desktop|buy computer|hardware purchase|it equipment|procurement)/i, /(?:quotation for laptop|purchase server|network equipment|desktop supply)/i]
    },
    SOFTWARE: {
      label: 'Software & Automation', path: '/#services',
      intro: 'We coordinate software, websites and automation with development partners. Scope and delivery require review.',
      questions: [
        'What business problem should the software, website or automation solve?',
        'Is there an existing system that needs integration or replacement?',
        'Where is your team based, and when do you want to discuss the project?'
      ],
      terms: [/(?:software|website|web app|application develop|automation|integration|crm system)/i]
    }
  };
  // Specific intent takes priority over generic terms; an unknown request stays in the menu.
  const order = ['VC', 'AUD_AV', 'SOUND', 'RECOVERY', 'IT_AMC', 'HARDWARE', 'SOFTWARE'];
  const match = value => {
    if (typeof value !== 'string') return null;
    const text = value.slice(0, 400);
    return order.find(code => playbooks[code].terms.some(term => term.test(text))) || null;
  };
  const publicPlaybooks = Object.fromEntries(Object.entries(playbooks).map(([code, entry]) => [code, {
    label: entry.label, path: entry.path, intro: entry.intro, questions: entry.questions
  }]));
  Object.freeze(publicPlaybooks);
  window.AADITECHS_SERVICE_PLAYBOOKS = publicPlaybooks;
  window.AADITECHS_MATCH_SERVICE = match;
})();
