/* Exercise field omission and recovery against the real customer adapter. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const read = name => fs.readFileSync(path.join(__dirname,'../admin/js/',name),'utf8');
const boundary = {window:{}, URL};
vm.runInNewContext(read('richtext-compat.js'), boundary);
for (const url of ['https://example.com/a.png','http://example.com/a.png','/upload/image/a.png']) assert.equal(boundary.window.FengRichtext.validURL(url,true),true);
for (const url of ['javascript:alert(1)','data:image/png;base64,AAAA','//host/a','https://u:p@host/a','https://host/\nx','/\\host/a']) assert.equal(boundary.window.FengRichtext.validURL(url,true),false,url);
assert.equal(boundary.window.FengRichtext.validURL('mailto:a@example.com'),true);
assert.equal(boundary.window.FengRichtext.validURL('mailto:a@example.com',true),false);
assert.equal(boundary.window.FengRichtext.escape('<a "x">&'), '&lt;a &quot;x&quot;&gt;&amp;');
class Node {
    constructor(){this.children=[];this.style={};this.dataset={};this.events={};}
    append(...children){this.children.push(...children);} after(){} setAttribute(){} remove(){}
    addEventListener(name,fn){this.events[name]=fn;}
}
async function setup({existing=true, fail=false, inline=false, original='<div>历史排版</div>', settings=false}={}) {
    const registered={}, editors=[];
    const textarea={name:'content',value:original,after(){},form:{elements:{namedItem:name=>(name==='id'&&existing)||(name==='_settings_version'&&settings)?{value:'123'}:null}}};
    const context={console,URL,Event,AbortController,document:{readyState:'complete',createElement:()=>new Node(),
        querySelectorAll:()=>[textarea],addEventListener:(name,fn)=>{registered[name]=fn;}},
        window:{FengUpload:{pending:0},addEventListener(){},FengRichtext:{
            ...boundary.window.FengRichtext,hasInlineImages:()=>inline,checkImages(){},
            clean:async()=>{if(fail)throw Error('synthetic cleaning failure');return '<div>安全排版</div>';}
        },KindEditor:{lang(){},plugin(){},create(field,options){
            let html=field.value;
            const editor={html(value){if(value!==undefined)html=value;return html;},
                edit:{iframe:[{}],doc:{addEventListener(){}}},readonly(){},remove(){}};
            editors.push({editor,options});queueMicrotask(()=>options.afterCreate.call(editor));return editor;
        }}}, alert(){}};
    vm.runInNewContext(read('editor.js'), context);
    await new Promise(setImmediate);
    function save(){let blocked=false;registered.submit({preventDefault(){blocked=true;},stopImmediatePropagation(){}});return blocked;}
    return {context,textarea,editors,save};
}
(async()=>{
    const a=await setup();
    assert.equal(a.save(),false);assert.equal(a.textarea.disabled,true);assert.equal(a.textarea.value,'<div>历史排版</div>');
    a.editors[0].editor.html('<p>修改正文</p>');a.save();assert.equal(a.textarea.disabled,false);assert.equal(a.textarea.value,'<p>修改正文</p>');
    a.editors[0].editor.html('<div>安全排版</div>');a.save();assert.equal(a.textarea.disabled,true,'undo to initial editor view preserves original bytes');
    a.context.window.FengUpload.pending=1;assert.equal(a.save(),true,'upload blocks submission');
    for(const options of [{fail:true},{inline:true}]) {
        const preserved=await setup(options);assert.equal(preserved.save(),false);assert.equal(preserved.textarea.disabled,true);assert.equal(preserved.textarea.value,'<div>历史排版</div>');
        const blocked=await setup({...options,existing:false});assert.equal(blocked.save(),true,'new record cannot save a failed richtext field');
    }
    const copyright=await setup({existing:false,settings:true});copyright.save();assert.equal(copyright.textarea.disabled,true,'associated settings controls need not be descendants of the form');
    const fresh=await setup({existing:false,original:''});fresh.save();assert.equal(fresh.textarea.disabled,false,'new fields are never omitted');
    assert.equal(a.editors[0].options.filterMode,true);assert.equal(a.editors[0].options.syncType,'none');
    assert.equal(a.editors[0].options.allowFileManager,false);
    assert.ok(!a.editors[0].options.items.includes('source'),'raw source must pass the safe preview flow');
    console.log('KindEditor 适配回归通过：地址校验、未改正文省略、编辑/撤销、新建、载入失败、内嵌图片及上传保护。');
})().catch(error=>{console.error(error);process.exitCode=1;});
