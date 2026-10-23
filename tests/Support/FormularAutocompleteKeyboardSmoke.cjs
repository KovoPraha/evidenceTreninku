'use strict';
// No browser/network/database: execute the actual inline handlers with a small DOM boundary.
// Native Enter/Space activates a button's click; browser UAT verifies that platform mapping.
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../../formular.php'),'utf8');

function environment(mode) {
    const timers=[];
    const document={activeElement:null,listeners:{},addEventListener(type,callback){this.listeners[type]=callback;}};
    class Element {
        constructor(){this.listeners={};this.children=[];this.value='';this.parent=null;this.html='';}
        addEventListener(type,callback){(this.listeners[type]??=[]).push(callback);}
        fire(type){const event={target:this,defaultPrevented:false,preventDefault(){this.defaultPrevented=true;}};
            for(const callback of this.listeners[type]??[])callback(event);return event;}
        appendChild(child){child.parent=this;this.children.push(child);}
        contains(target){return this===target||this.children.some(child=>child.contains(target));}
        set innerHTML(value){this.html=value;this.children=[];}
        get innerHTML(){return this.html;}
        querySelector(selector){if(selector==='button'){if(!this.children.length)this.appendChild(new Element());return this.children[0];}throw new Error(selector);}
        focus(){document.activeElement=this;this.fire('focus');}
    }
    const input=new Element(),sug=new Element(),wrap=new Element(),hidden=new Element();
    const elements={ucastnici_input:input,ucastnici_suggestions:sug,ucastnici_selected:wrap,ucastnici:hidden};
    document.getElementById=id=>elements[id];document.createElement=()=>new Element();
    const row={querySelector:selector=>({'.js-sp-name':input,'.js-sp-sug':sug,'.js-sp-id':hidden})[selector]};
    const context={document,window:{},DUPLIKAT:null,AbortController,setTimeout:callback=>timers.push(callback),
        fetch:async()=>({json:async()=>[{id:123,prijmeni:'TEST',jmeno:'Anna'}]}),row,
        getSelectedUcastnici:()=>[{id:123,label:'TEST Anna'}],syncHidden(){context.syncCalls++;},syncCalls:0};
    let code;
    if(mode==='participants'){
        const start=source.indexOf('(() => {',source.indexOf('// ── Účastníci:'));
        code=source.slice(start,source.indexOf('// ── MĚŘENÍ:',start)).replace(/<\?= [\s\S]*?\?>/g,'[]');
    }else{
        const start=source.indexOf('    function makeSportovecAutocomplete(row)');
        code=source.slice(start,source.indexOf('    function addRow()',start))+'makeSportovecAutocomplete(row);';
    }
    vm.runInNewContext(code,context);
    return {input,sug,wrap,hidden,context,document,flush:()=>{while(timers.length)timers.shift()();},outside:()=>new Element()};
}

async function search(env){env.input.focus();env.input.value='TEST';env.input.fire('input');await new Promise(resolve=>setImmediate(resolve));assert.equal(env.sug.children.length,1);return env.sug.children[0];}

test('participant: Tab to result survives blur; keyboard-generated click adds one chip and hidden ID',async()=>{
    const e=environment('participants');const button=await search(e);
    e.input.fire('blur');button.focus();e.flush();assert.equal(e.sug.children[0],button);
    button.fire('click');assert.equal(e.context.window.__ucastniciSelected.length,1);
    assert.equal(e.hidden.value,'123:TEST Anna');assert.equal(e.wrap.children.length,1);
    assert.equal(e.document.activeElement,e.input);assert.equal(e.sug.children.length,0);
});

test('participant: pointer click selects once and duplicate selection does not add another chip',async()=>{
    const e=environment('participants');let button=await search(e);
    assert.equal(button.fire('mousedown').defaultPrevented,true);
    assert.equal(e.context.window.__ucastniciSelected.length,0);button.fire('click');
    button=await search(e);button.fire('click');assert.equal(e.context.window.__ucastniciSelected.length,1);
    assert.equal(e.wrap.children.length,1);
});

test('participant: leaving suggestions by keyboard closes the list',async()=>{
    const e=environment('participants');const button=await search(e);
    button.focus();e.sug.fire('focusout');e.outside().focus();e.flush();assert.equal(e.sug.children.length,0);
    assert.equal(e.context.window.__ucastniciSelected.length,0);
});

test('measurement: keyboard result selection survives blur, preserves ID/label and syncs exactly once',()=>{
    const e=environment('measurement');e.input.focus();const button=e.sug.children[0];assert.ok(button);
    e.input.fire('blur');button.focus();e.flush();assert.equal(e.sug.children[0],button);
    button.fire('click');assert.equal(e.hidden.value,'123');assert.equal(e.input.value,'TEST Anna');
    assert.equal(e.context.syncCalls,1);assert.equal(e.document.activeElement,e.input);
    assert.equal(e.sug.children.length,0);
});

test('measurement: leaving without a selected identity clears free text, as before',()=>{
    const e=environment('measurement');e.input.focus();e.input.value='Unselected';
    e.input.fire('blur');e.outside().focus();e.flush();assert.equal(e.input.value,'');assert.equal(e.hidden.value,'');
});
