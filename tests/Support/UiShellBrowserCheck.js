async (page) => {
 const results=[];
 for (const width of [1440,390]) {
  await page.setViewportSize({width,height:900});
  for (const role of ['public','family','athlete','trainer']) {
   await page.goto('http://127.0.0.1:18413/'+role+'.html');
   await page.locator('body.app-ui-ready').waitFor();
   if(width===390){const toggle=page.getByRole('button',{name:'Otevřít hlavní nabídku'});await toggle.focus();await page.keyboard.press('Enter');await page.locator('#appPublicNav.show').waitFor();}
   const data=await page.evaluate(()=>({overflow:document.documentElement.scrollWidth>innerWidth,
    fields:[...document.querySelectorAll('main input,main textarea')].map(x=>({id:x.id,name:x.getAttribute('aria-label'),label:x.labels[0]?.textContent})),
    active:document.querySelector('[aria-current="page"]')?.getAttribute('href'),
    links:[...document.querySelectorAll('nav a')].map(x=>x.getAttribute('href')),
    injected:document.querySelectorAll('main img').length,
    alert:document.querySelector('[role="alert"]')?.textContent,
    scripts:[...document.scripts].map(x=>x.src)}));
   if(data.overflow||data.injected||data.fields[0].id!=='app-admin-field-2'||data.fields[1].id!=='app-admin-field-4'||data.active!=='/booking/eshop.php')throw Error(JSON.stringify({role,width,data}));
   if((role==='family')!==data.links.includes('/booking/sportovni_prehled.php'))throw Error('Wrong family navigation');
   if(role==='family'){await page.locator('summary').focus();await page.keyboard.press('Enter');if(!await page.locator('details').evaluate(x=>x.open))throw Error('Keyboard account menu');}
   await page.screenshot({path:'output/playwright/'+role+'-'+width+'.png',fullPage:true});
   results.push({role,width,...data});
  }
 }
 await page.goto('http://127.0.0.1:18413/family.html');
 await page.getByRole('textbox',{name:'Hledat',exact:true}).fill('Test <unsafe>');
 await page.getByRole('button',{name:'Hledat',exact:true}).click();
 if(new URL(page.url()).searchParams.get('q')!=='Test <unsafe>')throw Error('Filter submission');
 await page.getByRole('textbox',{name:'Poznámka',exact:true}).fill('Synthetic only');
 await page.getByRole('button',{name:'Odeslat',exact:true}).click();
 if(await page.locator('body').innerText()!=='Synthetic submission received.')throw Error('POST submission');
 return {status:'pass',matrix:results,keyboard:true,filter:true,post:true};
}
