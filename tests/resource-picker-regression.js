/* Verify that opening and confirming the real picker preserves existing references. */
'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const path=require('node:path');

class Node {
    constructor(tag='div') {this.tag=tag;this.children=[];this.dataset={};this.attributes={};this.textContent='';
        this.classList={add(){},remove(){},toggle(){}};this.disabled=false;}
    append(...children) {this.children.push(...children);}
    replaceChildren(...children) {this.children=children;}
    setAttribute(name,value) {this.attributes[name]=String(value);}
    hasAttribute(name) {return Object.hasOwn(this.attributes,name);}
    getAttribute(name) {return this.attributes[name]??null;}
    querySelectorAll() {return [];}
    addEventListener(name,handler) {if(name==='close')this.onclose=handler;}
    showModal() {this.open=true;}
    close() {this.open=false;this.onclose?.();}
    remove() {}
    focus() {}
}
function find(root,predicate) {
    if(predicate(root))return root;
    for(const child of root.children){const match=find(child,predicate);if(match)return match;}
    return null;
}
const fields=new Map();
const document={body:new Node('body'),createElement:tag=>new Node(tag),getElementById:id=>fields.get(id)};
const context={document,window:{},location:{origin:'http://localhost'},URL,URLSearchParams,AbortController,
    setTimeout,clearTimeout,console,
    fetch:async()=>({ok:true,json:async()=>({page:1,page_count:1,current_dir_path:'',total_count:0,file_list:[]})})};
vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../admin/js/resource-picker.js'),'utf8'),context);
function picker(value) {
    const field={value};fields.set('gallery',field);
    const trigger={dataset:{cmsKind:'image',cmsTarget:'gallery'},
        attributes:{'data-cms-multiple':''},hasAttribute(name){return Object.hasOwn(this.attributes,name);},focus(){}};
    let result=null;
    context.window.FengResourcePicker.open(trigger,{upload:async()=>'',onSelect:urls=>{result=Array.from(urls);}});
    const dialog=document.body.children.at(-1);
    const click=label=>{const control=find(dialog,node=>node.tag==='button' && node.textContent===label);
        assert.ok(control,`missing button ${label}`);control.onclick();};
    return {dialog,click,result:()=>result};
}
const urls=Array.from({length:61},(_,i)=>'/upload/image/20261027/g'+String(i+1).padStart(3,'0')+'.png');
let current=picker(urls.join(',')+',');
assert.ok(find(current.dialog,node=>node.textContent==='已选择 61 张图片'));
assert.ok(find(current.dialog,node=>node.textContent.includes('已有内容超过 60 项')));
current.click('取消');assert.equal(current.result(),null);
current=picker(urls.join(',')+',');current.click('使用所选图片');
assert.deepEqual(current.result(),urls,'61 original references must survive an unchanged confirmation');
const absolute='http://localhost/upload/image/20261027/original.png';
current=picker(urls[0]+','+absolute+',');
assert.ok(find(current.dialog,node=>node.textContent.includes('不是当前资源库地址')));
current.click('使用所选图片');
assert.deepEqual(current.result(),[urls[0],absolute],'same-origin absolute URLs must survive confirmation');
current=picker(urls[0]+','+urls[0]+',');current.click('使用所选图片');
assert.deepEqual(current.result(),[urls[0],urls[0]],'duplicate references keep their original order');
current=picker(urls.join(',')+',');current.click('清空选择');current.click('使用所选图片');
assert.deepEqual(current.result(),[],'explicit clear may remove all references');
console.log('资源选择器回填检查通过：61 项、绝对地址、重复项、取消与明确清空。');
