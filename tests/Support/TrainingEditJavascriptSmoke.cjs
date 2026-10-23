'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../../edit_trenink.php'),'utf8');
const rendered=source.replace(/<\?[\s\S]*?\?>/g,'[]');

test('all training edit inline scripts compile (PHP lint does not check JavaScript)',()=>{
    let count=0;
    for(const match of rendered.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script[^>]*>/gi)){
        if(match[1].trim()){new vm.Script(match[1]);count++;}
    }
    assert.ok(count>=2);
});

class Element {
    constructor(){this.value='';this.children=[];this.listeners={};}
    addEventListener(type,fn){(this.listeners[type]??=[]).push(fn);}
    fire(type){for(const fn of this.listeners[type]??[])fn({target:this});}
    appendChild(child){this.children.push(child);}
    set innerHTML(value){this.children=[];}
    contains(child){return this.children.includes(child);}
}

test('changing measurement search text clears the old athlete identity before any new selection',async()=>{
    const spName=new Element(), spId=new Element(), spSug=new Element();
    spId.value='99';spName.value='Former person';
    let syncCalls=0;
    const context={spName,spId,spSug,AbortController,encodeURIComponent,
        document:{createElement:()=>new Element(),addEventListener(){}},
        fetch:async()=>({json:async()=>[{id:123,jmeno:'Anna',prijmeni:'TEST',label:'TEST Anna · nar. 2014-01-01'}]}),
        syncHidden(){syncCalls++;}};
    const start=source.indexOf('        // autocomplete sportovec pro měření');
    const end=source.indexOf('        const setFieldValue',start);
    vm.runInNewContext(source.slice(start,end),context);
    spName.value='A';spName.fire('input');assert.equal(spId.value,'');assert.equal(syncCalls,1);
    spName.value='Anna';spName.fire('input');await new Promise(resolve=>setImmediate(resolve));
    assert.equal(spId.value,'');assert.equal(spSug.children.length,1);
    assert.equal(spSug.children[0].textContent,'TEST Anna · nar. 2014-01-01');
    spSug.children[0].fire('click');assert.equal(spId.value,123);assert.equal(spName.value,'TEST Anna');
    assert.equal(spSug.children.length,0);assert.equal(syncCalls,3);
});

test('existing measurement fields prefill safely, including optional missing fields and zero values',()=>{
    const start=source.indexOf('        const setFieldValue');
    const end=source.indexOf("        row.addEventListener('input'",start);
    for(const typ of ['kolo','beh','posilovna','kolo_krouzek','kolo_silnice','kolo_mtb']){
        const fields=new Map(['.js-vzdalenost','.js-distance-unit','.js-cas','.js-prevod','.js-vaha','.js-opakovani','.js-rpe','.js-cvik_id','.js-segment_id'].map(k=>[k,new Element()]));
        const spId=new Element(),spName=new Element();
        const prefill={typ,sportovec_id:123,sportovec_label:'TEST Anna',vzdalenost:0,distance_unit:'km',cas:'01:02.500',prevod:'50/15',vaha:0,opakovani:5,rpe:0,cvik_id:2,segment_id:3};
        vm.runInNewContext(source.slice(start,end),{row:{querySelector:key=>fields.get(key)},spId,spName,prefill,typSel:new Element(),renderFields(){}});
        assert.equal(spId.value,123);assert.equal(spName.value,'TEST Anna');
        if(typ==='kolo'||typ==='beh'){assert.equal(fields.get('.js-vzdalenost').value,0);assert.equal(fields.get('.js-distance-unit').value,'km');}
        if(typ==='posilovna'){assert.equal(fields.get('.js-vaha').value,0);assert.equal(fields.get('.js-rpe').value,0);}
        else assert.equal(fields.get('.js-cas').value,'01:02.500');
    }
});
