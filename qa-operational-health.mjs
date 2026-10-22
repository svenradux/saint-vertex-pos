/* Copyright © 2026 Saint Vertex. Local operational health smoke checks. */
import {chromium} from './app/node_modules/playwright-core/index.mjs';
import fs from 'node:fs';
import crypto from 'node:crypto';
import assert from 'node:assert/strict';

const base='http://127.0.0.1:8074';
const credentials=JSON.parse(fs.readFileSync('secrets/local-credentials.json','utf8'));
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
page.on('pageerror',e=>errors.push(e.message));
page.on('response',r=>{if(r.status()>=400)failed.push({url:r.url(),status:r.status()})});
const branded=async()=>{
    await page.waitForLoadState('networkidle');
    assert.equal(await page.locator('.vtx-global-footer').innerText(),'© 2026 Saint Vertex');
    assert.match(await page.title(),/Vertex POS/);
    assert.doesNotMatch(await page.locator('body').innerText(),/\bNexoPOS\b/i);
};
try{
    await page.goto(base+'/sign-in');await page.locator('#username').waitFor();await branded();
    checks.push('Login renders with preserved product/copyright branding');
    await page.locator('#username').fill(credentials.username);await page.locator('#password').fill(credentials.password);
    await page.locator('#password').press('Enter');await page.waitForURL(/dashboard/);await page.locator('.ns-widget-grid').waitFor();await branded();
    checks.push('Native login and dashboard render after health middleware runs');
    await page.locator('#notification-button').click();await page.waitForLoadState('networkidle');
    assert.doesNotMatch(await page.locator('body').innerText(),/Penjadwalan Tugas Dinonaktifkan|Tautan Simbolik Rusak|Izin Tidak Benar|Task Scheduling Disabled|Symbolic Link Broken|Incorrect Permissions/);
    await page.screenshot({path:'logs/vtx006-dashboard-health.png',fullPage:true});
    checks.push('Notification panel no longer shows the three verified stale health warnings');
    const hash=data=>crypto.createHash('sha256').update(data).digest('hex');
    for(const asset of ['brand.css','brand-ui.js','vertex-mark.svg','vertex-lockup.svg','lang/id.json']){
        const response=await context.request.get(base+'/modules/saintvertex/'+asset);
        assert.equal(response.status(),200,asset);
        assert.equal(hash(await response.body()),hash(fs.readFileSync('app/modules/SaintVertex/Public/'+asset)),asset);
    }
    checks.push('Five module assets served through the junction exactly match their source bytes');
    await page.goto(base+'/dashboard/pos');await page.waitForFunction(()=>window.POS?.products?.getValue);await branded();
    assert.deepEqual(await page.evaluate(()=>({items:POS.products.getValue().length,total:POS.order.getValue().total})),{items:0,total:0});
    await page.getByText('Bayar',{exact:true}).waitFor();checks.push('POS access, empty cart, zero total and payment controls remain available');
    assert.deepEqual(errors,[]);assert.deepEqual(failed,[]);assert.deepEqual(external,[]);
    checks.push('No browser JavaScript errors, failed responses or external browser requests');
    fs.writeFileSync('logs/vtx006-browser.json',JSON.stringify({passed:true,checks,errors,failed,external},null,2));
    console.log(JSON.stringify({passed:true,checks},null,2));
}catch(error){
    fs.writeFileSync('logs/vtx006-browser.json',JSON.stringify({passed:false,checks,errors,failed,external,error:error.stack},null,2));
    await page.screenshot({path:'logs/vtx006-failure.png',fullPage:true});throw error;
}finally{await browser.close()}
