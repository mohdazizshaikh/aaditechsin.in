(() => {
  'use strict';
  if(window.AADITECHS_PUBLIC_CONFIG?.features?.analytics!==true || location.protocol!=='https:')return;
  const pages={'home':'','data-recovery':'data-recovery','it-amc':'it-amc','video-conferencing-solutions':'video-conferencing','auditorium-av-solutions':'auditorium-av','professional-sound-systems':'professional-sound','av-project-planner':'av-maintenance'};
  const page=location.pathname.replace(/\/index\.html$/,'/').replace(/^\/|\/$/g,'')||'home';
  if(!Object.prototype.hasOwnProperty.call(pages,page))return;
  const services=['infrastructure','software-web','it-amc','cloud','security','data-backup','it-procurement','smart-office','special-projects','video-conferencing','auditorium-av','professional-sound','av-maintenance','data-recovery'];
  const preference='aaditechs-analytics-choice-v1',sessionKey='aaditechs-analytics-tab-v1',channelKey='aaditechs-analytics-channel-v1';
  const privacySignal=()=>navigator.globalPrivacyControl===true||navigator.doNotTrack==='1'||window.doNotTrack==='1';
  if(privacySignal())return;
  const get=(storage,key)=>{try{return storage.getItem(key)}catch{return null}};
  const set=(storage,key,value)=>{try{storage.setItem(key,value);return true}catch{return false}};
  const remove=(storage,key)=>{try{storage.removeItem(key)}catch{}};
  let consent=false,sentPage=false;
  const random=()=>Array.from(crypto.getRandomValues(new Uint8Array(16)),b=>b.toString(16).padStart(2,'0')).join('');
  function classify(){
    // Classify only locally; never send raw query strings/referrer URLs.
    const params=new URLSearchParams(location.search),source=(params.get('utm_source')||'').toLowerCase();
    if(['google','bing','duckduckgo','yahoo'].includes(source))return 'search';
    if(['linkedin','facebook','instagram','youtube','threads','twitter','x','telegram'].includes(source))return 'social';
    if(['email','newsletter'].includes(source)||params.get('utm_medium')==='email')return 'email';
    try{
      if(!document.referrer)return 'direct';
      const host=new URL(document.referrer).hostname.toLowerCase();
      if(host===location.hostname)return 'direct';
      if(/(^|\.)(google\.com|google\.co\.in|bing\.com|duckduckgo\.com|search\.yahoo\.com)$/.test(host))return 'search';
      if(/(^|\.)(linkedin\.com|facebook\.com|instagram\.com|youtube\.com|t\.co|x\.com|twitter\.com|threads\.net)$/.test(host))return 'social';
      return 'referral';
    }catch{return 'other'}
  }
  function send(event,service=pages[page]){
    if(!consent||privacySignal())return;
    try{
      let session=get(sessionStorage,sessionKey);
      if(!session||!(/^[a-f0-9]{32}$/).test(session)){session=random();if(!set(sessionStorage,sessionKey,session))return}
      let channel=get(sessionStorage,channelKey);
      if(!['direct','search','social','email','referral','other'].includes(channel)){channel=classify();if(!set(sessionStorage,channelKey,channel))return}
      const body={event_id:random(),session,page,event,service,channel,automation:navigator.webdriver===true,consent:true};
      fetch('/api/analytics.php',{method:'POST',credentials:'omit',referrerPolicy:'no-referrer',headers:{'Content-Type':'application/json'},body:JSON.stringify(body),keepalive:true}).catch(()=>{});
    }catch{/* optional analytics must not interrupt customer actions */}
  }
  function view(){if(!sentPage&&consent){sentPage=true;send('page_view')}}
  function apply(value){
    consent=value==='allow'&&!privacySignal();
    if(!set(localStorage,preference,value)&&consent)consent=false;
    if(!consent){remove(sessionStorage,sessionKey);remove(sessionStorage,channelKey)}
    status.textContent=consent?'Optional analytics allowed. You can turn it off at any time.':'Optional analytics off.';
    view();
  }
  const panel=document.createElement('section');
  panel.setAttribute('aria-label','Optional website analytics');
  Object.assign(panel.style,{padding:'12px 20px',background:'#eef4fa',color:'#14283e',font:'14px/1.5 system-ui,sans-serif',borderBottom:'1px solid #b8c9db'});
  const description=document.createElement('span');description.textContent='Help improve this website with optional page and service analytics. No form content is included. ';
  const link=document.createElement('a');link.href='/privacy/';link.textContent='Privacy notice';link.style.color='#134b8c';
  const allow=document.createElement('button');allow.type='button';allow.textContent='Allow analytics';
  const deny=document.createElement('button');deny.type='button';deny.textContent='Turn off analytics';
  for(const button of [allow,deny])Object.assign(button.style,{margin:'4px 6px',padding:'6px 10px',border:'1px solid #516c87',borderRadius:'4px',background:'#fff',color:'#14283e',cursor:'pointer'});
  const status=document.createElement('span');status.setAttribute('role','status');
  panel.append(description,link,allow,deny,status);
  (document.querySelector('main')||document.body).prepend(panel);
  allow.addEventListener('click',()=>apply('allow'));deny.addEventListener('click',()=>apply('deny'));
  consent=get(localStorage,preference)==='allow';
  status.textContent=consent?'Optional analytics allowed.':'Optional analytics off unless you allow it.';
  view();
  window.addEventListener('storage',e=>{if(e.key===preference){consent=e.newValue==='allow'&&!privacySignal();if(!consent){remove(sessionStorage,sessionKey);remove(sessionStorage,channelKey)}status.textContent=consent?'Optional analytics allowed.':'Optional analytics off.';view()}});
  document.addEventListener('click',e=>{
    const el=e.target instanceof Element?e.target:null;if(!el)return;
    const a=el.closest('a[href]');
    if(a){const href=(a.getAttribute('href')||'').toLowerCase();if(href.startsWith('tel:'))send('call_click');else if(href.startsWith('mailto:'))send('email_click')}
    const button=el.closest('button[data-service]');
    if(button){const id=services[Number(button.dataset.service)];if(id)send('service_interest',id)}
    if(el.closest('.chat-launch[aria-expanded="true"]'))send('chat_open');
  });
  document.addEventListener('change',e=>{
    const el=e.target;if(el instanceof HTMLSelectElement&&el.id==='service'&&el.options.length===services.length+1){
      const id=services[el.selectedIndex-1];if(id)send('service_interest',id);
    }
  });
})();
