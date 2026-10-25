'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync(require('node:path').join(__dirname,'../../planovac.php'),'utf8');
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
