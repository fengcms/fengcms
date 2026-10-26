/* Exercise the real adapter's menu callbacks without loading a browser/vendor bundle. */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
let editorOptions, toolbarOptions;
const textarea = {name:'content', value:'<p>已有正文</p>', after() {}};
const node = () => ({append() {}, setAttribute() {}, querySelectorAll() {return [];}});
const editor = {getHtml:()=>'<p>同步正文</p>', isEmpty:()=>false, on() {}};
const context = {
    URL, console,
    MutationObserver:class {observe() {}},
    document:{readyState:'complete', createElement:node, addEventListener() {},
        querySelectorAll:()=>[textarea]},
    window:{addEventListener() {}, wangEditor:{
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
editorOptions.config.onChange();
assert.equal(textarea.value,'<p>同步正文</p>');
console.log('编辑器校验通过：网络图片地址、危险协议、跳转链接、已有图片兼容、菜单入口与正文同步。');
