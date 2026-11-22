(() => {
  'use strict';
  const form=document.getElementById('avPlanner'); if(!form) return;
  const cfg=window.AADITECHS_PUBLIC_CONFIG||{};
  const enabled=cfg.features?.leadSubmission===true && typeof cfg.leadEndpoint==='string' && cfg.leadEndpoint.startsWith('/');
  const status=document.getElementById('plannerStatus');
  const pre=document.getElementById('scopePreview');
  const submit=document.getElementById('sendScope');
  const options={
    'video-conferencing-solutions':'Video Conferencing',
    'auditorium-av-solutions':'Auditorium AV',
    'professional-sound-systems':'Professional Sound / PA'
  };
  const type=new URLSearchParams(location.search).get('type');
  if(type && Object.prototype.hasOwnProperty.call(options,type)) form.elements.projectType.value=options[type];
  const stringify=()=>{
    const data=new FormData(form);
    const req=data.getAll('requirements');
    return [
      `AV project: ${data.get('projectType')||'Not specified'}`,
      `Capacity: ${data.get('capacity')||'Not specified'}`,
      `Location: ${data.get('siteCity')||'Not specified'}`,
      `Stage: ${data.get('siteStage')||'Not specified'}`,
      `Timeline: ${data.get('timeline')||'Not specified'}`,
      `Budget: ${data.get('budgetRange')||'Not disclosed'}`,
      `Requested components: ${req.length?req.join(', '):'To be assessed'}`,
      `Organization: ${data.get('company')||'Not provided'}`,
      `Notes: ${data.get('extraNotes')||'None'}`
    ].join('\n');
  };
  document.getElementById('previewScope').addEventListener('click',()=>{
    pre.textContent=stringify();pre.hidden=false;
  });
  form.addEventListener('submit',async event=>{
    event.preventDefault();status.textContent='';
    if(!form.reportValidity()) return;
    const message=stringify();
    if(message.length>2000){status.textContent='Please shorten your notes before submitting.';return;}
    if(!enabled){
      pre.textContent=message;pre.hidden=false;
      status.textContent='Preview ready — nothing has been sent or saved. Call or email Aaditechs for a real project inquiry.';
      return;
    }
    const data=new FormData(form);
    const payload={fullName:String(data.get('fullName')||''),email:String(data.get('email')||''),phone:String(data.get('phone')||''),service:String(data.get('projectType')||''),message,device:'',contactConsent:String(data.get('contactConsent')||''),company_website:String(data.get('company_website')||''),source:'av-planner'};
    submit.disabled=true;status.textContent='Sending inquiry…';
    try{
      const response=await fetch(cfg.leadEndpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify(payload)});
      if(!response.ok)throw Error('Unavailable');
      const result=await response.json();
      if(result.ok!==true)throw Error('Unavailable');
      status.textContent=`Thank you. Aaditechs received your project request${result.reference?' (reference '+result.reference+')':''}.`;
      form.reset();pre.hidden=true;
    }catch{
      status.textContent='Could not submit your request. Please contact info@aaditechs.in or call +91 97736 93440.';
    }finally{submit.disabled=false;}
  });
})();
