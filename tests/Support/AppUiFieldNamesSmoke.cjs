"use strict";
const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
const path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../../assets/app-ui.js'),'utf8');

function fields(size,admin=true) {
 const listeners={}; let scans=0;
 const occupied=[{id:'app-admin-field-1'},{id:'app-admin-field-3'}];
 const items=Array.from({length:size},(_,i)=>{
  const label={textContent:'Pole '+i,htmlFor:'',matches:s=>s==='label',querySelector:()=>null};
  return {id:'',labels:[],attrs:{name:'q'},previousElementSibling:label,
   hasAttribute(k){return Object.hasOwn(this.attrs,k);},getAttribute(k){return this.attrs[k]||null;},
   setAttribute(k,v){this.attrs[k]=v;}};
 });
 const document={body:{classList:{contains:()=>admin,add(){}}},addEventListener(k,f){listeners[k]=f;},
  querySelector:()=>true,querySelectorAll(s){
   if(s==='[id]'){scans++;return [...occupied,...items].filter(x=>x.id);}
   if(s.startsWith('[id="')){scans++;return [...occupied,...items].filter(x=>x.id===s.slice(5,-2));}
   if(s.startsWith('input:'))return items;
   return [];
  }};
 vm.runInNewContext(source,{document,window:{addEventListener(){}}});
 listeners.DOMContentLoaded();
 return {items,scans};
}
test('1000 legacy labels keep names and collision-free IDs with one document ID scan',()=>{
 const {items,scans}=fields(1000);
 assert.equal(items[0].id,'app-admin-field-2');
 assert.equal(items[1].id,'app-admin-field-4');
 assert.equal(new Set(items.map(x=>x.id)).size,1000);
 items.forEach((item,i)=>{assert.equal(item.previousElementSibling.htmlFor,item.id);assert.equal(item.attrs['aria-label'],'Pole '+i);});
 assert.ok(scans<=1,`Full-document ID scans: ${scans}`);
});
test('public fields receive their existing accessible fallback without admin IDs',()=>{
 const {items,scans}=fields(5,false);
 assert.equal(scans,0);
 items.forEach((item,i)=>{assert.equal(item.id,'');assert.equal(item.attrs['aria-label'],'Pole '+i);});
});
