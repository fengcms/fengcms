'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const root=path.join(__dirname,'..');
let checked=0;
const sources=[
    'admin/js/signup_from.js','admin/js/classify_form.js','admin/js/modulemanage_form.js',
    'admin/js/field_system.js','admin/js/recover.js','admin/js/filemanage.js',
    'admin/template/message/reply.html','admin/template/module/add.html',
    'admin/template/module/update.html','admin/template/fieldmanage/add.html','admin/template/fieldmanage/update.html','admin/template/fieldmanage/system.html'
];
for(const source of sources){
    let text=fs.readFileSync(path.join(root,source),'utf8');
    if(source.endsWith('.html'))text=Array.from(text.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/g),m=>m[1]).filter(s=>s.includes('Validform(')).join('\n');
    for(const status of ['y','n'])for(const id of [undefined,null,false,0,'','undefined','null',-1,[],{},'1 OR 1=1',37]){
        let callback;
        const location={href:'http://localhost/admin/index.php?controller=fieldmanage&project=goods'};
        const chain={attr(){return '?controller=recover&project=goods&operate=batch';},Validform(options){callback=options.callback;return chain;},click(){return chain;},on(){return chain;},ajaxError(){return chain;},keypress(){return chain;},ready(fn){fn();return chain;}};
        const $=arg=>{if(typeof arg==='function')arg();return chain;};
        const context={URL,URLSearchParams,encodeURIComponent,document:{},window:{location},location,$,alert(){}};
        vm.runInNewContext(fs.readFileSync(path.join(root,'admin/js/navigation.js'),'utf8'),context);
        context.FengAdmin=context.window.FengAdmin;
        vm.runInNewContext(text,context,{filename:source});
        assert.equal(typeof callback,'function',source);
        callback({status,id});
        if(source.includes('filemanage.js') && status==='n'){assert.equal(location.href,'http://localhost/admin/index.php?controller=fieldmanage&project=goods');checked++;continue;}
        if((source.includes('module/add.html') || source.includes('module/update.html')) && status==='n'){
            assert.equal(location.href,'http://localhost/admin/index.php?controller=fieldmanage&project=goods',source+' retains the form after save failure');
            checked++;
            continue;
        }
        const url=new URL(location.href,'http://localhost/admin/index.php');
        assert.equal(url.searchParams.get('operate'),'execution',source);
        assert.equal(url.searchParams.get('re'),status==='y'?'1':'0',source);
        const batch=source.includes('signup_from') || source.includes('field_system') || source.includes('message/reply') || source.includes('recover.js') || source.includes('filemanage.js') || source.includes('fieldmanage/system');
        assert.equal(url.searchParams.has('id'),id===37&&!batch,source+' ID='+String(id));
        if(source.includes('module/add') || source.includes('module/update') || source.includes('fieldmanage/') || source.includes('field_system'))assert.equal(url.searchParams.get('project'),'goods',source);
        checked++;
    }
}
console.log(`后台结果跳转检查通过：${checked} 个成功/失败与有效/缺失/异常 ID 组合，执行真实页面回调。`);
