/* Copyright © 2026 Saint Vertex. Original local UI smoke checks. */
import {chromium} from './app/node_modules/playwright-core/index.mjs';
import fs from 'node:fs';
import assert from 'node:assert/strict';

const credentials=JSON.parse(fs.readFileSync('secrets/local-credentials.json','utf8'));
const base='http://127.0.0.1:8074';
const checks=[],errors=[],failed=[],external=[];
const browser=await chromium.launch({channel:'chrome',headless:true});
const context=await browser.newContext({viewport:{width:1440,height:1000}});
await context.route('**/*',route=>{
    const url=new URL(route.request().url());
    if(['http:','https:'].includes(url.protocol)&&url.hostname!=='127.0.0.1'){
        external.push(url.origin);return route.abort();
    }
    return route.continue();
});
const page=await context.newPage();
page.on('pageerror',error=>errors.push(error.message));
page.on('response',response=>{if(response.status()>=400)failed.push({url:response.url(),status:response.status()})});
const branded=async()=>{
    await page.waitForLoadState('networkidle');
    assert.doesNotMatch(await page.locator('body').innerText(),/\bNexoPOS\b/i);
    assert.match(await page.title(),/Vertex POS/);
    assert.doesNotMatch(await page.title(),/NexoPOS/i);
    assert.equal(await page.locator('.vtx-global-footer').count(),1);
    assert.equal(await page.locator('.vtx-global-footer').innerText(),'© 2026 Saint Vertex');
    assert.equal(await page.locator('meta[name="author"]').getAttribute('content'),'Saint Vertex');
};
try{
    await page.goto(base+'/sign-in');await page.locator('#username').waitFor();await branded();
    assert.equal(await page.locator('h1').innerText(),'Vertex POS');
    await page.screenshot({path:'logs/vtx005-login.png',fullPage:true});checks.push('Login product identity, company metadata, copyright footer');
    await page.locator('#username').fill(credentials.username);await page.locator('#password').fill(credentials.password);
    await page.locator('#password').press('Enter');await page.waitForURL(/dashboard/);
    await page.locator('.ns-widget-grid').waitFor();await branded();checks.push('Native login and dashboard');
    await page.locator('#notification-button').click();await branded();
    assert.doesNotMatch(await page.locator('body').innerText(),/Unauthenticated|Authentication Failed|Kegagalan Autentikasi/);
    checks.push('Operational notification panel without upstream product/authentication promotion');
    for(const path of ['/dashboard/settings/about','/dashboard/users/profile','/dashboard/settings/general','/dashboard/products','/dashboard/orders','/dashboard/modules']){
        const response=await page.goto(base+path);assert.equal(response.status(),200,path);await branded();
        if(path.endsWith('/about')){
            assert.match(await page.locator('body').innerText(),/Hak cipta kontribusi asli Saint Vertex/);
            assert.match(await page.locator('body').innerText(),/Copyright © 2026 Saint Vertex/);
            assert.match(await page.locator('body').innerText(),/6\.2\.4/);
            await page.screenshot({path:'logs/vtx005-about.png',fullPage:true});
        }
        checks.push(path+' renders with exact footer and product identity');
    }
    await page.goto(base+'/dashboard/pos');await page.waitForFunction(()=>window.POS?.products?.getValue);await branded();
    assert.deepEqual(await page.evaluate(()=>({items:POS.products.getValue().length,total:POS.order.getValue().total})),{items:0,total:0});
    await page.getByText('Bayar',{exact:true}).waitFor();checks.push('POS navigation, empty cart, zero total and payment control');
    assert.deepEqual(errors,[]);assert.deepEqual(failed,[]);assert.deepEqual(external,[]);
    checks.push('No JavaScript errors, failed HTTP responses or external requests');
    fs.writeFileSync('logs/vtx005-browser.json',JSON.stringify({passed:true,checks,errors,failed,external},null,2));
    console.log(JSON.stringify({passed:true,checks},null,2));
}catch(error){
    fs.writeFileSync('logs/vtx005-browser.json',JSON.stringify({passed:false,checks,errors,failed,external,error:error.stack},null,2));
    await page.screenshot({path:'logs/vtx005-failure.png',fullPage:true});throw error;
}finally{await browser.close()}
