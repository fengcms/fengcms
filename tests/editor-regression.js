/* Exercise the real adapter's menu callbacks without loading a browser/vendor bundle. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
let editorOptions, toolbarOptions, legacyOptions;
const registered={};
const textarea = {name:'content', value:'<p>已有正文</p>', after() {}};
const node = () => ({style:{}, before() {}, append() {}, setAttribute() {}, querySelectorAll() {return [];}});
const editor = {setHtml() {},getHtml:()=>'<p>同步正文</p>', isEmpty:()=>false, on() {}};
const context = {
    URL, console, alert() {},
    MutationObserver:class {observe() {}},
    document:{readyState:'complete', createElement:node, addEventListener(name,callback) {registered[name]=callback;},
        querySelectorAll:()=>[textarea]},
    window:{FengLegacyRichtext:{mount(options){legacyOptions=options;}},addEventListener() {}, wangEditor:{
        createEditor(options) {editorOptions=options;return editor;},
        createToolbar(options) {toolbarOptions=options;}
    }}
};
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname,'../admin/js/editor.js'),'utf8'),context);
const {insertImage, editImage} = editorOptions.config.MENU_CONF;
for (const url of ['https://images.example.com/a.jpg','http://images.example.com/view?id=1&size=large',' HTTPS://images.example.com/a.png ']) {
    assert.equal(insertImage.checkImage(url,'说明',''),true,url);
}
for (const url of ['', 'https://', '/upload/image/a.jpg', '//images.example.com/a.jpg',
    'javascript:alert(1)', 'data:image/svg+xml;base64,xxx', 'file:///tmp/a.jpg',
    'https://name:secret@example.com/a.jpg', 'http://example.com/\na.jpg', 'https:\\example.com/a.jpg']) {
    assert.equal(typeof insertImage.checkImage(url,'',''),'string',url);
}
assert.equal(insertImage.parseImageSrc(' https://images.example.com/a.jpg '),'https://images.example.com/a.jpg');
assert.equal(editImage.checkImage('/upload/image/20261026/a.jpg','已有图片',''),true);
assert.equal(editImage.checkImage('https://images.example.com/a.jpg','','/content_article_7.html'),true);
for (const link of ['javascript:alert(1)','data:text/html,evil','//evil.example/a','https://user:secret@example.com/']) {
    assert.equal(typeof insertImage.checkImage('https://images.example.com/a.jpg','',link),'string',link);
    assert.equal(typeof editImage.checkImage('/upload/image/a.jpg','',link),'string',link);
}
const group = toolbarOptions.config.toolbarKeys.find(item=>item.key==='group-image');
assert.equal(Array.from(group.menuKeys).join(','),'uploadImage,insertImage');
for(const key of ['cmsLegacyOptimize','cmsLegacySource','cmsLegacyUndo'])assert.ok(toolbarOptions.config.toolbarKeys.includes(key),'historical tools belong to the editor toolbar');
editorOptions.config.onChange();
assert.equal(textarea.value,'<p>同步正文</p>');
assert.equal(legacyOptions.originalHtml,'<p>已有正文</p>','original HTML snapshot survives editor synchronization');
console.log('编辑器校验通过：网络图片地址、危险协议、跳转链接、已有图片兼容、菜单入口与正文同步。');
// Parser failures must retain the immutable source and leave recovery controls mounted.
textarea.value='<p>无法直接解析的历史原文</p>';
let disabled=false;
const brokenEditor={...editor,setHtml(html){if(html===textarea.value)throw Error('synthetic parser failure');},clear(){},disable(){disabled=true;},enable(){disabled=false;}};
context.window.wangEditor.createEditor=options=>{editorOptions=options;return brokenEditor;};
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname,'../admin/js/editor.js'),'utf8'),context);
assert.equal(editorOptions.html,'<p><br></p>','create a stable editor before loading legacy HTML');
assert.equal(disabled,true);
assert.equal(legacyOptions.recovery,true,'failed parsing retains external recovery buttons');
assert.equal(textarea.readOnly,true);
assert.equal(textarea.hidden,false);
context.window.FengEditor.sync();
assert.equal(textarea.value,'<p>无法直接解析的历史原文</p>','failed parser cannot synchronize an empty editor over the source');
let blocked=false;registered.submit({preventDefault(){blocked=true;},stopImmediatePropagation(){}});
assert.equal(blocked,true,'failed legacy parsing blocks form submission');
const restore=legacyOptions.onApplied();
assert.equal(disabled,false);
blocked=false;registered.submit({preventDefault(){blocked=true;},stopImmediatePropagation(){}});assert.equal(blocked,false,'successful recovery permits normal save');
assert.equal(textarea.hidden,true);
restore();
assert.equal(disabled,true);
assert.equal(textarea.value,'<p>无法直接解析的历史原文</p>','undo of recovery restores the protected original');
console.log('编辑器初始化失败保护通过：原文、优化入口及撤销恢复均保留。');
// Base64 source must be protected even when an editor silently drops the image.
textarea.value='<p><img src="data:image/png;base64,AAAA"></p>';
const inlineOriginal=textarea.value;
context.window.FengLegacyConverter={hasInlineImages:html=>html.includes('data:image/'),forEditor:html=>html,forStorage:html=>html};
context.window.wangEditor.createEditor=options=>({...editor,enable(){},disable(){}});
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname,'../admin/js/editor.js'),'utf8'),context);
context.window.FengEditor.sync();assert.equal(textarea.value,inlineOriginal);
blocked=false;registered.submit({preventDefault(){blocked=true;},stopImmediatePropagation(){}});
assert.equal(blocked,true,'ordinary save must not silently strip an untransferred Base64 image');
const restoreInline=legacyOptions.onApplied();
blocked=false;registered.submit({preventDefault(){blocked=true;},stopImmediatePropagation(){}});assert.equal(blocked,false);
restoreInline();context.window.FengEditor.sync();assert.equal(textarea.value,inlineOriginal);
blocked=false;registered.submit({preventDefault(){blocked=true;},stopImmediatePropagation(){}});assert.equal(blocked,true);
console.log('Base64 保存保护通过：原文保持、转存前阻止保存、撤销恢复保护。');
// Real custom menus remain available without a selection and bind to each field's source.
const menus=new Map(),elements=[];
function menuNode(tag){const events={};const element={tag,hidden:false,disabled:false,children:[],
 append(...children){this.children.push(...children);},before(){},setAttribute(){},remove(){},
 addEventListener(name,fn){events[name]=fn;},click(){if(!this.disabled)events.click?.();},showModal(){this.open=true;}};
 elements.push(element);return element;}
const menuContext={window:{wangEditor:{Boot:{registerMenu:menu=>menus.set(menu.key,menu.factory())}}},
 document:{createElement:menuNode,body:menuNode('body')},AbortController,TextEncoder};
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname,'../admin/js/legacy-richtext.js'),'utf8'),menuContext);
const sourceMenu=menus.get('cmsLegacySource'),optimizeMenu=menus.get('cmsLegacyOptimize'),undoMenu=menus.get('cmsLegacyUndo');
const fieldA={on(){}},fieldB={on(){}};
assert.equal(sourceMenu.isDisabled(fieldA),true,'unmounted fields have no actions');
for(const [field,original] of [[fieldA,'<p>字段甲原文</p>'],[fieldB,'']])menuContext.window.FengLegacyRichtext.mount({
 editor:field,textarea:{},wrapper:menuNode('wrapper'),originalHtml:original});
assert.equal(sourceMenu.isDisabled(fieldA),false);assert.equal(sourceMenu.alwaysEnable,true,'source view needs no text selection');
assert.equal(optimizeMenu.isDisabled(fieldA),false);assert.equal(optimizeMenu.alwaysEnable,true);
assert.equal(optimizeMenu.isDisabled(fieldB),true,'empty original cannot optimize');
assert.equal(optimizeMenu.alwaysEnable,false,'selection bypass must not override an unavailable action');
assert.equal(undoMenu.isDisabled(fieldA),true,'undo is unavailable before applying');
sourceMenu.exec(fieldA);
assert.equal(elements.filter(el=>el.tag==='textarea').at(-1).value,'<p>字段甲原文</p>','toolbar reads the selected field original snapshot');
sourceMenu.exec(fieldB);
assert.equal(elements.filter(el=>el.tag==='textarea').at(-1).value,'','different fields keep independent sources');
console.log('历史正文工具栏通过：无选区可操作、空原文及撤销禁用、多字段原文独立。');
