/* Optional real Chromium suite. See the customer-branch maintenance document. */
'use strict';
const fs=require('node:fs');
const path=require('node:path');
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {randomBytes}=require('node:crypto');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root=path.resolve(__dirname,'..');
process.chdir(root);
const tag=randomBytes(12).toString('hex'), uploads=new Set(), responseJobs=[];
const fixture=(action,...args)=>execFileSync('docker',['compose','exec','-T','php','php','tests/editor-browser-fixture.php',action,tag,...args],{encoding:'utf8'});
const read=()=>JSON.parse(fixture('read'));
let browser,page;
(async()=>{
    const account=JSON.parse(fixture('create'));
    const original=read().content;
    browser=await chromium.launch({headless:true,...(process.env.CHROME_PATH?{executablePath:process.env.CHROME_PATH}:{})});
    const context=await browser.newContext({viewport:{width:1440,height:1000}});
    page=await context.newPage();
    const errors=[],requests=[];
    page.on('pageerror',error=>errors.push(error.message));
    page.on('request',request=>requests.push(request.url()));
    page.on('dialog',dialog=>dialog.accept());
    page.on('response',response=>{
        if(response.url().includes('/admin/api/upload.php')) responseJobs.push(response.json().then(result=>{if(result.error===0)uploads.add(result.url);}).catch(()=>{}));
    });
    const origin=process.env.FENGCMS_BROWSER_ORIGIN || 'http://127.0.0.1:18080';
    await page.goto(origin+'/admin/login.php');
    await page.locator('#user').fill(account.user);await page.locator('#password').fill(account.password);await page.locator('#admincode').fill(account.admincode);
    const login=page.waitForResponse(response=>response.url().includes('controller=admin')&&response.request().method()==='POST');
    await page.locator('form').evaluate(form=>form.requestSubmit());await login;await page.waitForURL('**/admin/index.php?controller=admin');await page.waitForLoadState();
    await page.goto(origin+'/admin/index.php');
    const frame=page.locator('#ifmain').contentFrame();
    async function edit(){
        await page.locator('#ifmain').evaluate((el,id)=>el.src='?controller=module&project=article&operate=update&id='+id,account.id);
        await frame.locator('textarea[name="content"]').waitFor({state:'attached'});
        await frame.locator('body').evaluate(()=>new Promise((resolve,reject)=>{let tries=0;const id=setInterval(()=>{
            const note=document.querySelector('.legacy-warning');
            if(note && !note.textContent.includes('正在安全载入')){clearInterval(id);resolve();}
            else if(++tries>200){clearInterval(id);reject(Error('editor load timed out'));}
        },25);}));
    }
    async function save(){
        const response=page.waitForResponse(r=>r.url().includes('controller=module&project=article&operate=save')&&r.request().method()==='POST');
        await frame.locator('#save').click();const result=await (await response).json();assert.equal(result.status,'y');
    }
    await edit();
    assert.equal(await frame.locator('.ke-edit-iframe').count(),1);
    assert.equal(await frame.locator('textarea[name="content"]').isDisabled(),true);
    const imageValue=await frame.locator('#img').inputValue();
    await frame.getByRole('button',{name:'选择图片',exact:true}).click();await frame.getByRole('button',{name:'取消',exact:true}).click();assert.equal(await frame.locator('#img').inputValue(),imageValue,'standalone image-field cancellation must preserve its original value');
    await frame.locator('[name="title"]').fill('ke_title_'+tag);await save();
    assert.equal(read().content,original,'title-only save must preserve original bytes');
    console.log('PASS real form title-only save preserves legacy bytes');
    await edit();
    await frame.locator('body').evaluate(()=>KindEditor.instances[0].appendHtml('<p>浏览器修订正文</p>'));
    await save();assert.ok(read().content.includes('浏览器修订正文'));assert.ok(read().content.includes('rowspan="2"'));assert.equal(Number(read().status),0);
    await edit();
    const getHtml=()=>frame.locator('body').evaluate(()=>KindEditor.instances[0].html());
    const html=await getHtml();assert.ok(html.includes('line-height:1.8'));
    await frame.locator('[data-name="fullscreen"]').click();
    assert.ok(await page.locator('#ifmain').evaluate(el=>el.classList.contains('cms-editor-fullscreen-frame')));
    await frame.locator('[data-name="cmssource"]').click();
    assert.equal(await frame.getByRole('textbox',{name:'原始 HTML',exact:true}).inputValue(),read().content);
    await frame.getByRole('button',{name:'关闭',exact:true}).click();
    await frame.locator('[data-name="image"]').click();await frame.getByRole('dialog',{name:'选择图片',exact:true}).waitFor();
    await frame.getByRole('button',{name:'取消',exact:true}).click();assert.equal(await getHtml(),html);
    fs.mkdirSync('var/verification',{recursive:true});await page.screenshot({path:'var/verification/kindeditor-fullscreen.png'});
    await frame.locator('[data-name="image"]').click();await frame.getByRole('tab',{name:'上传文件',exact:true}).click();
    await frame.locator('.rp-file-input').setInputFiles({name:'ke_'+tag+'.png',mimeType:'image/png',buffer:fs.readFileSync('admin/public/kindeditor-4.1.7/themes/default/default.png')});
    await frame.getByText('上传完成',{exact:true}).waitFor();await frame.getByRole('button',{name:'使用所选图片',exact:true}).click();
    assert.equal((await getHtml()).match(/<img /g).length,2);
    await frame.locator('body').evaluate(()=>{const editor=KindEditor.instances[0];editor.cmd.range.selectNode(editor.edit.doc.querySelector('img'));editor.cmd.select();});
    await frame.locator('[data-name="image"]').click();await frame.getByRole('tab',{name:'上传文件',exact:true}).click();
    await frame.locator('.rp-file-input').setInputFiles({name:'ke_replace_'+tag+'.png',mimeType:'image/png',buffer:fs.readFileSync('admin/public/kindeditor-4.1.7/themes/default/default.png')});
    await frame.getByText('上传完成',{exact:true}).waitFor();await frame.getByRole('button',{name:'使用所选图片',exact:true}).click();
    assert.equal((await getHtml()).match(/<img /g).length,2,'replacing a selected image must not append a duplicate');
    assert.ok((await getHtml()).includes('alt="历史图片"'));assert.ok((await getHtml()).includes('width="120"'));
    await frame.locator('.ke-edit-iframe').contentFrame().locator('img').first().click({button:'right'});
    await frame.getByText('删除图片',{exact:true}).click();assert.equal((await getHtml()).match(/<img /g).length,1);
    await frame.locator('[data-name="undo"]').click();assert.equal((await getHtml()).match(/<img /g).length,2);
    await frame.locator('[data-name="image"]').click();assert.equal(await frame.getByRole('dialog',{name:'选择图片',exact:true}).count(),1);await frame.getByRole('button',{name:'取消',exact:true}).click();
    await frame.locator('[data-name="table"]').click();assert.equal(await frame.locator('.ke-dialog').count(),1);await frame.getByRole('button',{name:'取消',exact:true}).click();
    await frame.locator('body').evaluate(()=>KindEditor.instances[0].appendHtml('<p>附件插入位置</p>'));
    await frame.locator('[data-name="insertfile"]').click();await frame.getByRole('tab',{name:'上传文件',exact:true}).click();
    await frame.locator('.rp-file-input').setInputFiles({name:'ke_'+tag+'.txt',mimeType:'text/plain',buffer:Buffer.from('FengCMS editor browser regression '+tag)});
    await frame.getByText('上传完成',{exact:true}).waitFor();await frame.getByRole('button',{name:'使用所选附件',exact:true}).click();assert.ok((await getHtml()).includes('/upload/file/'));
    await frame.locator('[data-name="cmsnetworkimage"]').click();await frame.getByRole('textbox',{name:'网络图片地址',exact:true}).fill('javascript:alert(1)');await frame.getByRole('button',{name:'插入',exact:true}).click();
    assert.ok(await frame.getByText('请输入安全的完整网址或站内绝对路径。',{exact:true}).isVisible());await frame.getByRole('button',{name:'取消',exact:true}).click();
    await page.setViewportSize({width:780,height:900});await page.screenshot({path:'var/verification/kindeditor-narrow.png'});
    const editorBody=frame.locator('.ke-edit-iframe').contentFrame().locator('body');await editorBody.press('Escape');
    assert.equal(await page.locator('#ifmain').evaluate(el=>el.classList.contains('cms-editor-fullscreen-frame')),false);
    await page.setViewportSize({width:1440,height:1000});
    await save();assert.ok(read().content.includes('/upload/file/'));assert.equal((read().content.match(/<img /g)||[]).length,2);
    fixture('publish');
    const published=await context.request.get(origin+'/?controller=content&project=article&id='+account.id);
    assert.equal(published.status(),200);const rendered=await published.text();
    assert.ok(rendered.includes('line-height:1.8'));assert.ok(rendered.includes('colspan="2"'));assert.ok(rendered.includes('/upload/file/'));
    fixture('unpublish');
    console.log('PASS edit/reopen, iframe fullscreen/Escape, original view, modern image/attachment uploads, cancellation, unsafe URL and narrow viewport');
    fixture('inline');await edit();const inlineOriginal=read().content;
    assert.equal(await frame.locator('.ke-edit-iframe').count(),0);
    await frame.locator('[name="title"]').fill('ke_inline_'+tag);await save();assert.equal(read().content,inlineOriginal);
    await edit();await frame.getByRole('button',{name:'编辑 HTML / 转存内嵌图片',exact:true}).click();await frame.getByRole('button',{name:'检查并预览',exact:true}).click();await frame.getByRole('button',{name:'应用到编辑区',exact:true}).click();
    assert.ok((await getHtml()).includes('/upload/image/'));assert.ok((await getHtml()).includes('line-height:2'));
    await frame.getByRole('button',{name:'恢复本次打开的原文',exact:true}).click();
    assert.equal(await frame.locator('textarea[name="content"]').inputValue(),inlineOriginal);assert.equal(await frame.locator('textarea[name="content"]').isDisabled(),true);
    await frame.getByRole('button',{name:'编辑 HTML / 转存内嵌图片',exact:true}).click();await frame.getByRole('button',{name:'检查并预览',exact:true}).click();await frame.getByRole('button',{name:'应用到编辑区',exact:true}).click();await save();
    assert.ok(!read().content.includes('data:'));assert.ok(read().content.includes('line-height:2'));
    await edit();await frame.locator('body').evaluate(()=>{
        const editor=KindEditor.instances[0];editor.appendHtml('<p>粘贴位置</p>');
        const transfer=new DataTransfer();transfer.setData('text/html','<p>安全粘贴<script>window.parent.__keXss=1</script><img src="/image/logo.png" onerror="window.parent.__keXss=2"></p>');
        editor.edit.doc.body.dispatchEvent(new ClipboardEvent('paste',{clipboardData:transfer,bubbles:true,cancelable:true}));
    });
    await frame.locator('body').evaluate(()=>new Promise(resolve=>setTimeout(resolve,300)));
    assert.ok((await getHtml()).includes('安全粘贴'));assert.ok(!(await getHtml()).includes('<script'));assert.equal(await frame.locator('body').evaluate(()=>window.__keXss),undefined);
    const beforePaste=await getHtml();
    const pastedPNG=fs.readFileSync('admin/public/kindeditor-4.1.7/themes/default/default.png').toString('base64');
    await frame.locator('body').evaluate((_,png)=>{
        const editor=KindEditor.instances[0];editor.appendHtml('<p>图片粘贴位置</p>');
        const data=new DataTransfer();data.setData('text/html','<p>新粘贴图片<img src="data:image/png;base64,'+png+'" /></p>');
        editor.edit.doc.body.dispatchEvent(new ClipboardEvent('paste',{clipboardData:data,bubbles:true,cancelable:true}));
    },pastedPNG);
    await frame.getByRole('button',{name:'转存并预览',exact:true}).click();await frame.getByRole('button',{name:'应用到编辑区',exact:true}).click();
    assert.ok((await getHtml()).includes('安全粘贴'));assert.ok((await getHtml()).includes('新粘贴图片'));
    assert.equal((await getHtml()).match(/<img /g).length,(beforePaste.match(/<img /g)||[]).length+1,'inline paste inserts without replacing the document');
    console.log('PASS Base64 title-only preservation, authenticated transfer without reflow, undo/reapply and sanitized paste');
    // Render a second standard richtext field before DOMContentLoaded. No schema mutation.
    const addURL=origin+'/admin/index.php?controller=module&project=article&operate=add';
    await page.route(addURL,async route=>{const response=await route.fetch();const body=(await response.text()).replace('</form>','<textarea data-cms-editor name="qa_second" id="qa_second">&lt;p&gt;第二富文本&lt;/p&gt;</textarea></form>');await route.fulfill({response,body});});
    await page.goto(addURL);await page.locator('.ke-edit-iframe').nth(1).waitFor();
    await page.waitForFunction(()=>KindEditor.instances.length===2 && KindEditor.instances.every(editor=>editor.isCreated));
    await page.evaluate(()=>{KindEditor.instances[0].html('<p>第一字段</p>');KindEditor.instances[1].html('<p>第二字段</p>');FengEditor.sync();});
    assert.equal(await page.locator('textarea[name="content"]').inputValue(),'<p>第一字段</p>');assert.equal(await page.locator('#qa_second').inputValue(),'<p>第二字段</p>');
    await page.goto(origin+'/admin/index.php?controller=base');await page.locator('.ke-edit-iframe').waitFor();
    await page.waitForFunction(()=>document.getElementById('copyright').disabled===true);
    assert.equal(await page.locator('textarea[data-cms-editor]').count(),1);
    assert.deepEqual(errors,[]);assert.ok(!requests.some(url=>/\/htmledit\/|\.swf|\/plugins\/(image|insertfile)\//.test(url)));
    console.log('PASS multiple richtext fields, copyright field, no old upload endpoints or browser exceptions');
})().catch(async error=>{
    if(page)await page.screenshot({path:'var/verification/kindeditor-failure.png',fullPage:true}).catch(()=>{});
    console.error(error);process.exitCode=1;
}).finally(async()=>{
    await Promise.all(responseJobs);if(browser)await browser.close();
    fixture('cleanup',JSON.stringify([...uploads]));
});
