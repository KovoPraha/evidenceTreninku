"use strict";
const {test}=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
const path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../../assets/app-ui.js'),'utf8');
function harness(download){
 const listeners={},frames=[];
 class Form {constructor(){this.method='post';this.dataset=download?{noLock:''}:{};this.target='';this.attrs={};}setAttribute(k,v){this.attrs[k]=v;}}
 const document={addEventListener(k,f){listeners[k]=f;},body:{classList:{add(){}}}};
 vm.runInNewContext(source,{document,window:{addEventListener(){}},HTMLFormElement:Form,requestAnimationFrame:f=>frames.push(f)});
 const form=new Form();let prevented=0;
 const submit=()=>listeners.submit({target:form,defaultPrevented:false,preventDefault(){prevented++;}});
 return {form,submit,frames,prevented:()=>prevented};
}
test('download form stays usable for a second attachment response',()=>{const h=harness(true);h.submit();h.submit();assert.equal(h.prevented(),0);assert.equal(h.frames.length,0);assert.equal(h.form.dataset.appSubmitting,undefined);assert.equal(h.form.attrs['aria-busy'],undefined);});
test('ordinary mutating POST still blocks duplicate submit',()=>{const h=harness(false);h.submit();h.frames[0]();h.submit();assert.equal(h.prevented(),1);assert.equal(h.form.attrs['aria-busy'],'true');});
