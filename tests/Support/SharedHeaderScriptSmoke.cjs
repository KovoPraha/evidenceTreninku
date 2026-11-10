// Comparative unit contract. Timed browser measurements use real requests, not these API doubles.
const assert=require('node:assert/strict');
const test=require('node:test');
const fs=require('node:fs');
const vm=require('node:vm');
const path=require('node:path');
const root=path.resolve(__dirname,'../..');
const reference=fs.readFileSync(path.join(__dirname,'SharedHeaderScriptReference.php.txt'),'utf8').replace(/\r\n/g,'\n');
const oldBody=reference.slice(reference.indexOf('?>">')+4,reference.indexOf('</script>'));
const bundle=fs.readFileSync(path.join(root,'assets/staff-header.js'),'utf8');
const fields=['pushPublicKey','serviceWorkerUrl','appBaseUrl','pushEndpoint','csrfToken','pushEndpoint','csrfToken','searchUrl','appBaseUrl'];
const initial={pushPublicKey:'AQIDBA',serviceWorkerUrl:'/club/sw.js',appBaseUrl:'/club/',pushEndpoint:'/club/push_subscribe.php',csrfToken:'a'.repeat(64),searchUrl:'/club/ajax_global_search.php'};
class Element {
  constructor(id=''){this.id=id;this.dataset={};this.style={};this.listeners={};this.innerHTML='';this.value='';this.classes=new Set();this.classList={add:x=>this.classes.add(x),remove:x=>this.classes.delete(x),contains:x=>this.classes.has(x),toggle:(x,on)=>on?this.classes.add(x):this.classes.delete(x)};}
  addEventListener(kind,callback){(this.listeners[kind]??=[]).push(callback);}
  removeEventListener(){}
  setAttribute(name,value){this[name]=value;}
  removeAttribute(name){delete this[name];}
  querySelectorAll(){return [];}
  contains(){return false;}
  closest(selector){return selector==='#pushToggleBtn'&&this.id==='pushToggleBtn'?this:null;}
  set href(value){this._href=new URL(value,'https://example.test/').href;}
  get href(){return this._href;}
  set textContent(value){this.innerHTML=String(value).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
}
async function exercise(source,options={}) {
  const cfg={...initial,...options.config};const log=[];const documentListeners={};const windowListeners={};const timers=new Map();
  const button=new Element('pushToggleBtn'),input=new Element('globalSearchInput'),dropdown=new Element('globalSearchDropdown');
  const subscription={endpoint:'https://push.example.test/synthetic',unsubscribe:async()=>{log.push(['unsubscribe']);},toJSON:()=>({endpoint:'https://push.example.test/synthetic',keys:{auth:'synthetic'}})};
  const registration={pushManager:{getSubscription:async()=>options.subscribed?subscription:null,subscribe:async value=>{log.push(['subscribe',value.userVisibleOnly,Array.from(value.applicationServerKey)]);return subscription;}}};
  const serviceWorker={register:(url,opts)=>{log.push(['register',url,opts.scope]);return Promise.resolve(registration);},ready:Promise.resolve(registration)};
  const elements={appStaffHeaderConfig:{textContent:JSON.stringify(cfg)},pushToggleBtn:button};
  if(options.search){elements.globalSearchInput=input;elements.globalSearchDropdown=dropdown;}
  const document={getElementById:id=>elements[id]??null,createElement:()=>new Element(),head:{appendChild:x=>log.push(['manifest',x.href])},addEventListener:(k,f)=>(documentListeners[k]??=[]).push(f),querySelectorAll:()=>[]};
  const context={document,navigator:options.noWorker?{}:{serviceWorker},location:{origin:'https://example.test'},HTMLFormElement:class{},URL,AbortController,Uint8Array,Promise,
    setTimeout:(f,ms)=>{const id=timers.size+1;timers.set(id,{f,ms});return id;},clearTimeout:id=>timers.delete(id),
    addEventListener:(k,f)=>(windowListeners[k]??=[]).push(f),atob:x=>Buffer.from(x,'base64').toString('binary'),confirm:()=>options.confirm!==false,
    fetch:async(url,opts)=>{log.push(['fetch',url,opts?.method??'GET',opts?.body?JSON.parse(opts.body):null]);return {json:async()=>({sportovci:[{url:'/club/person?id=1',icon:'bi-person',label:'<Synthetic>'}]})};},
    Notification:{requestPermission:async()=>options.permission??'granted'},console:{warn:(...x)=>log.push(['warn',String(x[0])]),error:(...x)=>log.push(['error',String(x[0])])},
    showToast:(...x)=>log.push(['toast',...x])};
  if(!options.noPush)context.PushManager=class{};context.window=context;vm.createContext(context);vm.runInContext(source(cfg),context);
  const settle=()=>new Promise(resolve=>setImmediate(resolve));await settle();
  if(options.clickPush){for(const f of documentListeners.click??[])f({target:button,preventDefault(){},stopImmediatePropagation(){}});await settle();await settle();}
  if(options.search){input.value=' Žluťoučký <test> ';for(const f of input.listeners.input)f();for(const [id,t] of timers){if(t.ms===300){timers.delete(id);t.f();}}await settle();log.push(['search-html',dropdown.innerHTML]);}
  const confirmed=await context.appConfirm('Synthetic confirmation');
  return {log,confirmed,button:{html:button.innerHTML,subscribed:button.dataset.subscribed,display:button.style.display},document_events:Object.keys(documentListeners).sort(),window_events:Object.keys(windowListeners).sort()};
}
const oldSource=cfg=>{let index=0;return oldBody.replace(/<\?=[\s\S]*?\?>/g,()=>JSON.stringify(cfg[fields[index++]]));};
for(const [name,options] of [
  ['empty VAPID preserves PWA but no push',{config:{pushPublicKey:''}}],
  ['unsupported browser does not register push',{noWorker:true,noPush:true}],
  ['subscribe URL, decoded key and fresh CSRF',{clickPush:true}],
  ['unsubscribe endpoint and fresh CSRF',{subscribed:true,clickPush:true}],
  ['denied permission sends nothing',{clickPush:true,permission:'denied'}],
  ['substring search and escaped result',{search:true}],
  ['confirm fallback false',{confirm:false}],
])test(name,async()=>{
  const before=await exercise(oldSource,options),after=await exercise(()=>bundle,options);assert.deepEqual(after,before);
  if(name.startsWith('subscribe URL')){const call=after.log.find(x=>x[0]==='fetch');assert.equal(call[1],initial.pushEndpoint);assert.equal(call[3].csrf_token,initial.csrfToken);assert.deepEqual(after.log.find(x=>x[0]==='subscribe').slice(1),[true,[1,2,3,4]]);}
  if(options.permission==='denied')assert.equal(after.log.filter(x=>x[0]==='fetch').length,0);
  if(options.search){const call=after.log.find(x=>x[0]==='fetch');assert.equal(new URL(call[1]).searchParams.get('q'),'Žluťoučký <test>');assert.match(after.log.find(x=>x[0]==='search-html')[1],/&lt;Synthetic&gt;/);}
});

test('DOM context rejects external paths, malformed tokens and wrong types before use',async()=>{
  for(const key of ['serviceWorkerUrl','appBaseUrl','pushEndpoint','searchUrl']) {
    for(const value of ['https://attacker.example/','//attacker.example/','javascript:alert(1)','/\\attacker.example/','/bad path/',42]) {
      await assert.rejects(()=>exercise(()=>bundle,{config:{[key]:value}}),/Invalid staff header path/);
    }
  }
  await assert.rejects(()=>exercise(()=>bundle,{config:{csrfToken:'invalid'}}),/Invalid staff header context/);
  await assert.rejects(()=>exercise(()=>bundle,{config:{pushPublicKey:42}}),/Invalid staff header context/);
});
