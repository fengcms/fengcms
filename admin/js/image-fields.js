/* Thumbnail fields keep the legacy input IDs and storage contract. */
(function(){
 'use strict';
 const mounted=new WeakSet();
 function element(tag,className,text){const node=document.createElement(tag);node.className=className||'';if(text!==undefined)node.textContent=text;return node;}
 function button(text,label,action){const node=element('button','if-button',text);node.type='button';node.setAttribute('aria-label',label);node.title=label;node.onclick=action;return node;}
 function imageURL(value){
  if(typeof value!=='string'||!value||/[\\\x00-\x20\x7f]/.test(value)||value.startsWith('//'))return '';
  try{const url=new URL(value,location.href);return ['http:','https:'].includes(url.protocol)&&!url.username&&!url.password?url.href:'';}catch(_){return '';}
 }
 function thumbnail(value){const safe=imageURL(value);if(!safe)return '';const url=new URL(safe);
  if(url.origin===location.origin&&url.pathname.startsWith('/upload/image/'))return '/admin/api/thumbnail.php?path='+encodeURIComponent(decodeURIComponent(url.pathname.slice('/upload/image/'.length)));
  return safe;
 }
 function preview(urls,start,trigger){
  let index=start;const dialog=element('dialog','if-preview');dialog.setAttribute('aria-label','图片放大预览');
  const heading=element('div','if-preview-heading'),counter=element('span'),status=element('p','if-preview-status');status.setAttribute('role','status');
  const image=element('img');image.referrerPolicy='no-referrer';const close=button('×','关闭图片预览',()=>dialog.close());
  const previous=button('‹','上一张图片',()=>show(index-1)),next=button('›','下一张图片',()=>show(index+1));
  const stage=element('div','if-preview-stage');stage.append(previous,image,next);heading.append(counter,close);dialog.append(heading,stage,status);
  function show(value){index=(value+urls.length)%urls.length;counter.textContent=(index+1)+' / '+urls.length;image.alt='图片 '+(index+1);image.hidden=true;image.removeAttribute('src');
   const safe=imageURL(urls[index]);status.textContent=safe?'正在加载图片…':'图片地址不受支持，原地址已保留：'+urls[index];
   image.onload=()=>{image.hidden=false;status.textContent='';};image.onerror=()=>{image.hidden=true;status.textContent='图片无法加载，原地址：'+urls[index];};if(safe)image.src=safe;
   previous.disabled=next.disabled=urls.length<2;
  }
  dialog.addEventListener('keydown',event=>{if(event.key==='ArrowLeft'||event.key==='ArrowRight'){event.preventDefault();show(index+(event.key==='ArrowLeft'?-1:1));}});
  dialog.addEventListener('click',event=>{if(event.target===dialog)dialog.close();});
  dialog.addEventListener('close',()=>{dialog.remove();trigger.focus();},{once:true});document.body.append(dialog);dialog.showModal();show(index);
 }
 function mount({open}){
  document.querySelectorAll('[data-cms-kind="image"][data-cms-target]').forEach(trigger=>{
   if(mounted.has(trigger))return;const field=document.getElementById(trigger.dataset.cmsTarget);if(!field)return;mounted.add(trigger);
   const multiple=trigger.hasAttribute('data-cms-multiple'),root=element('div','cms-image-field');root.setAttribute('role','group');
   const label=field.closest('tr')?.querySelector('th')?.textContent.trim()||field.name;root.setAttribute('aria-label',label);
   const list=element('div','if-list'),notice=element('span','if-notice');notice.setAttribute('role','status');root.append(list,notice);
   field.classList.add('if-value');field.tabIndex=-1;field.setAttribute('aria-hidden','true');trigger.classList.add('if-trigger');trigger.hidden=true;trigger.after(root);
   const values=()=>multiple?field.value.split(',').filter(Boolean):[field.value].filter(Boolean);
   let dragged=null;
   function write(urls,focusIndex){field.value=multiple?(urls.length?urls.join(',')+',':''):(urls[0]||'');field.dispatchEvent(new Event('change',{bubbles:true}));
    notice.textContent='图片选择已更新，保存内容后生效。';if(focusIndex!==undefined)list.querySelectorAll('.if-preview-button')[focusIndex]?.focus();
   }
   function move(from,to){const urls=values();if(to<0||to>=urls.length||from===to)return;const [url]=urls.splice(from,1);urls.splice(to,0,url);write(urls,to);}
   const choose=()=>open(trigger,()=>(list.querySelector('.if-add')||list.querySelector('.if-preview-button'))?.focus());
   function render(){list.replaceChildren();const urls=values();
    urls.forEach((url,index)=>{
     const card=element('div','if-card');card.draggable=multiple&&urls.length>1;
     card.addEventListener('dragstart',event=>{dragged=index;event.dataTransfer.effectAllowed='move';event.dataTransfer.setData('text/plain',String(index));card.classList.add('is-dragging');});
     card.addEventListener('dragend',()=>{dragged=null;card.classList.remove('is-dragging');});
     card.addEventListener('dragover',event=>{if(dragged!==null)event.preventDefault();});
     card.addEventListener('drop',event=>{event.preventDefault();if(dragged!==null){move(dragged,index);dragged=null;}});
     const zoom=button('','预览第 '+(index+1)+' 张图片',()=>preview(values(),index,zoom));zoom.className='if-preview-button';zoom.title=url;
     const img=element('img');img.alt='图片 '+(index+1);img.loading='lazy';img.referrerPolicy='no-referrer';img.draggable=false;
     const fallback=element('span','if-fallback','图片加载中');img.onerror=()=>{img.hidden=true;fallback.textContent='图片不可用';};img.onload=()=>{fallback.hidden=true;};
     let src='';try{src=thumbnail(url);}catch(_){}if(src)img.src=src;else{img.hidden=true;fallback.textContent='地址不支持';}zoom.append(img,fallback);
     const controls=element('div','if-controls');
     if(multiple){const left=button('←','左移第 '+(index+1)+' 张图片',()=>move(index,index-1));left.disabled=index===0;const right=button('→','右移第 '+(index+1)+' 张图片',()=>move(index,index+1));right.disabled=index===urls.length-1;controls.append(left,right);}
     else controls.append(button('更换','更换图片',choose));
     controls.append(button('×','移除第 '+(index+1)+' 张图片',()=>{const current=values();current.splice(index,1);write(current);list.querySelector('.if-add')?.focus();}));
     card.append(zoom,controls);list.append(card);
    });
    if(multiple||!urls.length){const add=button('+',multiple?'添加图片':'选择图片',choose);add.className='if-add';add.append(element('span','',multiple?'添加图片':'选择图片'));list.append(add);}
   }
   field.addEventListener('change',render);field.addEventListener('focus',()=>list.querySelector('button')?.focus());
   field.form?.addEventListener('reset',()=>setTimeout(()=>{render();notice.textContent='';},0));render();
  });
 }
 window.FengImageFields={mount,imageURL,thumbnail};
}());
