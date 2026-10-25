'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync(require('node:path').join(__dirname,'../../planovac.php'),'utf8');
for (const failure of ['rejected','network']) test(`rename ${failure} restores the title and explains failure`,async()=>{
 const listeners={},toasts=[];let replacement;
 const input={value:'',addEventListener(n,f){listeners[n]=f;},focus(){},select(){},replaceWith(e){replacement=e;}};
 const span={dataset:{planId:'5'},textContent:'Původní název',replaceWith(){}};
 let dblclick;
 const document={addEventListener(n,f){if(n==='dblclick')dblclick=f;},createElement(tag){return tag==='input'?input:{dataset:{},style:{}};}};
 const start=source.indexOf("    document.addEventListener('dblclick'");
 const end=source.indexOf('\n})();',start);
 vm.runInNewContext(source.slice(start,end),{document,CSRF:'test',showToast:(...args)=>toasts.push(args),fetch:()=>failure==='network'?Promise.reject(new Error('offline')):Promise.resolve({json:()=>Promise.resolve({ok:false,msg:'Nemáte oprávnění.'})})});
 dblclick({target:{closest:()=>span}});input.value='Nový název';listeners.blur();
 await new Promise(resolve=>setImmediate(resolve));
 assert.equal(replacement.textContent,'Původní název');
 assert.equal(toasts.length,1);assert.equal(toasts[0][1],'danger');
 assert.match(toasts[0][0],failure==='network'?/Ověřte název/:/Nemáte oprávnění/);
});
test('planner inline JavaScript compiles after PHP rendering',()=>{
 let count=0;
 for(const match of source.replace(/<\?[\s\S]*?\?>/g,'[]').matchAll(/<script\b[^>]*>([\s\S]*?)<\/script[^>]*>/gi))if(match[1].trim()){new vm.Script(match[1]);count++;}
 assert.ok(count>0);
});
test('moving the last card refreshes both day counts and empty states without reload',()=>{
 const zone=n=>({n,badge:{textContent:'stale',style:{}},empty:{style:{}},querySelectorAll(){return Array(this.n).fill({});},querySelector(s){return s==='.day-count'?this.badge:this.empty;}});
 const from=zone(0),to=zone(2);
 const begin=source.indexOf('    function updateEmptyDays()');
 const end=source.indexOf('    // ── Inline editace',begin);
 const context={document:{querySelectorAll:()=>[from,to]}};
 vm.runInNewContext(source.slice(begin,end)+'\nupdateEmptyDays();',context);
 assert.equal(from.badge.textContent,'0');assert.equal(from.badge.style.display,'none');assert.equal(from.empty.style.display,'');
 assert.equal(to.badge.textContent,'2');assert.equal(to.badge.style.display,'');assert.equal(to.empty.style.display,'none');
});
