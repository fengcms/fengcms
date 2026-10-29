/* Explicit, reversible legacy conversion. Original HTML never comes from getHtml(). */
(function(){
 'use strict';
 function button(text,fn){const b=document.createElement('button');b.type='button';b.textContent=text;b.addEventListener('click',fn);return b;}
 async function clean(html,signal){
  const response=await fetch('/admin/api/richtext.php',{method:'POST',credentials:'same-origin',signal,
   headers:{'X-CSRF-Token':document.querySelector('meta[name="csrf-token"]')?.content||''},body:new URLSearchParams({html})});
  const result=await response.json();
  if(!response.ok||result.error!==0||typeof result.html!=='string')throw Error(result.message||'正文清洗失败，请刷新页面后重试。');
  return result.html;
 }
 function replaceHtml(editor,html){editor.deselect();editor.blur();editor.setHtml(html);}
 function mount({textarea,editor,wrapper,originalHtml,canOptimize=()=>true}){
  const row=document.createElement('div');row.className='legacy-actions';wrapper.before(row);
  let previous=null,applied=null;
  const note=document.createElement('span');note.setAttribute('role','status');
  const undo=button('撤销本次优化',()=>{if(previous!==null){if(editor.getHtml()!==applied&&!window.confirm('撤销优化将放弃优化后新增的编辑内容，是否继续？'))return;replaceHtml(editor,previous);textarea.value=previous;previous=null;undo.hidden=true;note.textContent='已恢复优化前的编辑内容。';}});undo.hidden=true;
  function dialog(title){
   const d=document.createElement('dialog');d.className='legacy-dialog';
   const h=document.createElement('h2');h.textContent=title;const area=document.createElement('div');area.className='legacy-dialog-body';
   const footer=document.createElement('div');footer.className='legacy-dialog-footer';d.append(h,area,footer);document.body.append(d);
   d.addEventListener('close',()=>d.remove(),{once:true});d.showModal();return {d,area,footer};
  }
  const source=button('查看原始 HTML',()=>{
   const {d,area,footer}=dialog('原始 HTML（本次打开时）');const input=document.createElement('textarea');input.readOnly=true;input.value=originalHtml;input.setAttribute('aria-label','原始 HTML');area.append(input);
   const status=document.createElement('span');status.setAttribute('role','status');footer.append(status,button('复制',async()=>{try{await navigator.clipboard.writeText(originalHtml);status.textContent='已复制';}catch(_){input.focus();input.select();status.textContent='请按 Ctrl/Cmd+C 复制';}}),button('关闭',()=>d.close()));
  });
  const optimize=button('优化历史富文本',()=>{
   if(!canOptimize()){note.textContent='请等待文件上传完成后再优化。';return;}
   const {d,area,footer}=dialog('优化历史富文本');
   const warning=document.createElement('p');warning.className='legacy-warning';warning.textContent='警告：使用该功能可能破坏文档的页面布局，请确认历史富文本确实需要处理再使用该功能！';
   const help=document.createElement('p');help.textContent='从本次打开时的原始 HTML 重新排版，将替换当前编辑区未保存的内容。字体、颜色、图片尺寸等可能丢失，保存文章后才会写入数据库。';
   const status=document.createElement('p');status.setAttribute('role','status');area.append(warning,help,status);
   const controller=new AbortController();
   d.addEventListener('close',()=>controller.abort());
   const cancel=button('取消',()=>d.close());
   const confirm=button('确认',async()=>{
    confirm.disabled=true;status.textContent='正在处理，请稍候…';
    const before=editor.getHtml();
    try{
     if(new TextEncoder().encode(originalHtml).length>2*1024*1024)throw Error('原始正文超过 2MB，暂不支持在线优化。');
     if(!window.FengLegacyConverter)throw Error('转换组件未加载，请刷新页面后重试。');
     FengLegacyConverter.inspect(originalHtml);
     const safe=await clean(originalHtml,controller.signal);
     const html=await clean(FengLegacyConverter.convert(safe),controller.signal);
     if(!d.open)return;
     FengLegacyConverter.checkContent(safe,html);
     const host=document.createElement('iframe');host.className='legacy-preview';host.title='转换结果预览';host.setAttribute('sandbox','');host.referrerPolicy='no-referrer';
     host.srcdoc='<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src http: https:; style-src \'unsafe-inline\'; base-uri \'none\'; form-action \'none\'"><style>body{font:16px/1.7 system-ui;padding:12px;overflow-wrap:anywhere}img{max-width:100%;height:auto}table{border-collapse:collapse}td,th{border:1px solid #ccc;padding:6px}</style></head><body>'+html+'</body></html>';area.append(host);
     status.textContent='处理完成，请检查预览；应用后仍需保存文章。';confirm.hidden=true;
     footer.prepend(button('应用到编辑区',()=>{
      if(editor.getHtml()!==before){status.textContent='编辑内容已变化，请取消并重新优化。';return;}
      try{
       replaceHtml(editor,html);const rendered=editor.isEmpty()?'':editor.getHtml();
       FengLegacyConverter.checkContent(html,rendered);
       previous=before;applied=rendered;textarea.value=rendered;undo.hidden=false;note.textContent='已优化，请检查内容并保存文章。';d.close();
      }catch(error){replaceHtml(editor,before);status.textContent='编辑器无法完整接收转换结果，已恢复原内容。'+error.message;}

     }));
    }catch(error){if(error.name!=='AbortError'){area.querySelector('.legacy-preview')?.remove();status.textContent=error.message||'处理失败，原内容保持原样。';confirm.disabled=false;}}
   });footer.append(cancel,confirm);
  });
  optimize.disabled=!originalHtml.trim();if(optimize.disabled)optimize.title='本次打开时没有历史正文。';
  row.append(optimize,source,undo,note);
 }
 window.FengLegacyRichtext={mount};
}());
