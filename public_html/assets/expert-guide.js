/** Optional static technical guide. No AI, personal data, storage or external calls. */
(() => {
  'use strict';
  if(window.AADITECHS_PUBLIC_CONFIG?.features?.technicalGuide!==true)return;
  const attach=async()=>{
    const panel=document.querySelector('#aaditechsChat');
    if(!panel)return;
    const section=document.createElement('section');
    section.className='chat-options';
    const button=document.createElement('button');
    button.type='button';button.textContent='Explore all 14 technical services';
    const content=document.createElement('div');content.hidden=true;
    section.append(button,content);panel.querySelector('.chat-footnote').before(section);
    button.addEventListener('click',async()=>{
      if(!content.hidden){content.hidden=true;return;}
      content.hidden=false;content.textContent='Loading service guide…';
      try{
        const [a,b]=await Promise.all(['/assets/service-registry.json','/assets/expert-knowledge.json'].map(url=>fetch(url,{credentials:'same-origin',cache:'no-cache'}).then(r=>{if(!r.ok)throw Error();return r.json();})));
        if(a.version!==1||b.version!==1||a.services.length!==14)throw Error();
        const select=document.createElement('select');select.setAttribute('aria-label','Technical service');
        for(const service of a.services){const opt=document.createElement('option');opt.value=service.id;opt.textContent=service.title;select.append(opt);}
        const output=document.createElement('div');output.setAttribute('aria-live','polite');
        function show(){
          const data=b.services[select.value];
          output.replaceChildren();
          for(const [label,key] of [['Requirements','intake'],['Planning','planning'],['Human assessment','escalation']]){
            const p=document.createElement('p');p.textContent=label+': '+data[key];output.append(p);
          }
        }
        const notice=document.createElement('p');notice.textContent='Static general guidance, not live AI or a final engineering recommendation. No inquiry is sent.';
        content.replaceChildren(notice,select,output);select.addEventListener('change',show);show();
      }catch{content.textContent='Technical guide unavailable. Please use the existing guided assessment.';}
    });
  };
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',attach,{once:true});else attach();
})();
