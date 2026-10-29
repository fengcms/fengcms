/* Decode inline raster images locally; the authenticated upload API validates real bytes. */
(function(){
 'use strict';
 const records=new Map();
 const mimeTypes={'image/jpeg':['image/jpeg','jpg'],'image/jpg':['image/jpeg','jpg'],'image/png':['image/png','png'],'image/gif':['image/gif','gif'],'image/bmp':['image/bmp','bmp'],'image/x-ms-bmp':['image/bmp','bmp']};
 function decode(source){
  if(typeof source!=='string')throw Error('内嵌图片地址不正确。');
  const match=/^data:([^;,]+);base64,([\s\S]*)$/i.exec(source.trim());
  const type=match&&mimeTypes[match[1].toLowerCase()];
  if(!type)throw Error('仅支持 JPEG、PNG、GIF、BMP 格式的 Base64 图片；其他内嵌内容请人工处理。');
  const payload=match[2].replace(/[\t\n\r\f ]/g,'');
  if(!payload.length || payload.length>Math.ceil(10*1024*1024/3)*4)throw Error('内嵌图片为空或超过 10MB。');
  if(payload.length%4!==0 || !/^[A-Za-z0-9+/]*={0,2}$/.test(payload))throw Error('内嵌图片的 Base64 编码不正确。');
  let binary;try{binary=atob(payload);}catch(_){throw Error('内嵌图片无法解码。');}
  if(!binary.length || binary.length>10*1024*1024 || btoa(binary)!==payload)throw Error('内嵌图片编码不完整或大小不正确。');
  const bytes=new Uint8Array(binary.length);for(let i=0;i<binary.length;i++)bytes[i]=binary.charCodeAt(i);
  return {file:new File([bytes],'legacy-base64.'+type[1],{type:type[0]}),cacheKey:type[0]+':'+payload};
 }
 function checkAbort(signal){if(signal?.aborted)throw new DOMException('操作已取消','AbortError');}
 async function transfer(html,{upload,signal,onProgress=()=>{}}){
  if(typeof html!=='string'||new TextEncoder().encode(html).length>2*1024*1024)throw Error('原始正文超过 2MB 或格式不正确。');
  checkAbort(signal);
  const template=document.createElement('template');template.innerHTML=html;
  const images=Array.from(template.content.querySelectorAll('img[src]')).filter(image=>/^data:/i.test(image.getAttribute('src').trim()));
  if(!images.length)return {html,replacements:new Map(),count:0};
  if(typeof upload!=='function')throw Error('图片上传组件未加载，请刷新页面后重试。');
  // Preflight every inline image before starting any upload.
  const items=images.map(image=>({image,source:image.getAttribute('src'),...decode(image.getAttribute('src'))}));
  const unique=new Map(items.map(item=>[item.cacheKey,item]));let done=0;
  for(const item of unique.values()){
   checkAbort(signal);
   let record=records.get(item.cacheKey);
   if(!record){record={key:Array.from(crypto.getRandomValues(new Uint8Array(16)),n=>n.toString(16).padStart(2,'0')).join(''),file:item.file,url:null};records.set(item.cacheKey,record);}
   onProgress(done,unique.size);
   if(!record.url){
    const url=await upload(record.file,'image',()=>{},signal,record.key);
    if(typeof url!=='string'||!/^\/upload\/image\/[0-9]{8}\/[a-f0-9]{32}\.(?:jpg|jpeg|png|gif|bmp)$/.test(url))throw Error('图片上传返回的站内路径不正确。');
    record.url=url;
   }
   checkAbort(signal);onProgress(++done,unique.size);
  }
  const replacements=new Map();
  for(const item of items){const url=records.get(item.cacheKey).url;item.image.setAttribute('src',url);replacements.set(item.source,url);}
  return {html:template.innerHTML,replacements,count:images.length};
 }
 window.FengInlineImages={decode,transfer};
}());
