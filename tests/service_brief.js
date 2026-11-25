const assert = require('node:assert/strict');
const {buildBrief}=require('../public_html/assets/service-brief.js');
const serviceNames=["IT Infrastructure","Software & Web Development","IT AMC & Managed Support","Cloud & Modern IT","Security & Compliance","Data Management & Backup","IT Asset & Procurement","Smart Office Solutions","Special IT Projects","Video Conferencing","Auditorium AV","Professional Sound & PA","AV Installation & Maintenance","Data Recovery"];
let count=0;
for(const name of serviceNames){
  for(let n=1;n<=4;n++){
    const topics=Array.from({length:n},(_,i)=>'Topic '+i);
    const brief=buildBrief(name,topics);
    assert.ok(brief.includes('Service: '+name));
    assert.equal(brief.split('\n').filter(x=>x.startsWith('- ')).length,n);
    count++;
  }
}
assert.equal(count,56);
for(const topics of [[],['same','same'],[''],['x'.repeat(161)],Array(5).fill('x'),[true]])assert.equal(buildBrief('Service',topics),'');
console.log('Service brief: 14 services, 56 selection combinations PASS');
