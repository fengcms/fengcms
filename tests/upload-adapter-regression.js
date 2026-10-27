/* Check that one picker queue item keeps its upload key across retries. */
'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const path=require('node:path');
const {webcrypto}=require('node:crypto');

class Node {
    constructor(tag='div'){this.tag=tag;this.children=[];this.dataset={};this.attributes={};this.textContent='';
        this.classList={add(){},remove(){},toggle(){}};this.disabled=false;}
    append(...children){this.children.push(...children);}
    replaceChildren(...children){this.children=children;}
    setAttribute(name,value){this.attributes[name]=String(value);}
    hasAttribute(name){return Object.hasOwn(this.attributes,name);}
    querySelectorAll(){return [];}
    addEventListener(name,handler){if(name==='close')this.onclose=handler;}
    showModal(){} close(){this.onclose?.();} remove(){} focus(){}
}
function find(root,predicate){
    if(predicate(root))return root;
    for(const child of root.children){const match=find(child,predicate);if(match)return match;}
    return null;
}
async function main(){
    const field={value:''};
    const document={body:new Node('body'),createElement:tag=>new Node(tag),getElementById:()=>field};
    const keys=[];let calls=0;
    const context={document,window:{},location:{origin:'http://localhost'},URL,URLSearchParams,AbortController,
        crypto:webcrypto,setTimeout,clearTimeout,console,
        fetch:async()=>({ok:true,json:async()=>({page:1,page_count:1,current_dir_path:'',total_count:0,file_list:[]})})};
    vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../admin/js/resource-picker.js'),'utf8'),context);
    const trigger={dataset:{cmsKind:'image',cmsTarget:'gallery'},attributes:{'data-cms-multiple':''},
        hasAttribute(name){return Object.hasOwn(this.attributes,name);},focus(){}};
    context.window.FengResourcePicker.open(trigger,{onSelect(){},upload:async(_file,_dir,_progress,_signal,key)=>{
        keys.push(key);calls++;if(calls===1)throw new Error('network lost');
        return '/upload/image/20261027/recovered.png';
    }});
    const dialog=document.body.children.at(-1);
    const input=find(dialog,node=>node.tag==='input' && node.type==='file');
    assert.ok(input);input.files=[{name:'photo.png',size:100}];input.onchange();
    await new Promise(setImmediate);
    const retry=find(dialog,node=>node.tag==='button' && node.textContent==='重试');
    assert.ok(retry,'failed upload has a retry action');retry.onclick();
    await new Promise(setImmediate);
    assert.equal(keys.length,2);
    assert.match(keys[0],/^[a-f0-9]{32}$/);
    assert.equal(keys[1],keys[0],'queue retry must reuse the same request key');
    const transportKeys=[];
    class FakeXHR {
        constructor(){this.upload={};this.status=200;this.responseText='{"error":0,"url":"/upload/image/a.png"}';}
        open(){}
        send(data){transportKeys.push(data.get('_upload_key'));this.onload();}
    }
    const editorContext={window:{addEventListener(){}},document:{readyState:'loading',addEventListener(){},
        querySelector:()=>({content:'test-csrf'})},crypto:webcrypto,FormData,Blob,DOMException,
        XMLHttpRequest:FakeXHR,URL,MutationObserver:class {},console};
    vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../admin/js/editor.js'),'utf8'),editorContext);
    await editorContext.window.FengEditor.upload(new Blob(['first upload']));
    await editorContext.window.FengEditor.upload(new Blob(['first upload']));
    assert.equal(transportKeys.length,2);
    assert.match(transportKeys[0],/^[a-f0-9]{32}$/);
    assert.notEqual(transportKeys[0],transportKeys[1],'new upload operations need distinct keys');
    console.log('资源上传队列检查通过：失败重试沿用同一随机请求标识。');
}
main().catch(error=>{console.error(error);process.exitCode=1;});
