/* FengCMS editor adapter. wangEditor is vendored; no CDN requests. */
(function () {
    'use strict';
    const instances = new Map();
    let pending = 0;
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    async function upload(file, dir = 'image') {
        if (file.size > 10 * 1024 * 1024) throw new Error('文件不能超过 10MB');
        const data = new FormData();
        data.append('imgFile', file); data.append('_csrf', csrf());
        pending++;
        try {
            const response = await fetch('/admin/api/upload.php?dir=' + dir, {method:'POST', body:data, credentials:'same-origin'});
            const result = await response.json();
            if (!response.ok || result.error !== 0) throw new Error(result.message || '上传失败');
            return result.url;
        } finally { pending--; }
    }
    function sync() { instances.forEach((editor, textarea) => { textarea.value = editor.isEmpty() ? '' : editor.getHtml(); }); }
    function mount(textarea) {
        if (instances.has(textarea)) return;
        const wrapper = document.createElement('div'); wrapper.className = 'cms-editor';
        const toolbar = document.createElement('div'), body = document.createElement('div');
        wrapper.append(toolbar, body); textarea.after(wrapper);
        const editor = window.wangEditor.createEditor({
            selector:body, html:textarea.value || '<p><br></p>', mode:'default',
            config:{ placeholder:'请输入内容',
                onChange:() => sync(),
                MENU_CONF:{uploadImage:{maxFileSize:10 * 1024 * 1024,
                    async customUpload(file, insert) { const url = await upload(file); insert(url, '', ''); }
                }}
            }
        });
        window.wangEditor.createToolbar({editor, selector:toolbar, config:{toolbarKeys:[
            'headerSelect','bold','italic','underline','through','color','bgColor','fontSize',
            'bulletedList','numberedList','blockquote','justifyLeft','justifyCenter','justifyRight',
            'insertLink','uploadImage','insertTable','undo','redo','fullScreen'
        ]}});
        instances.set(textarea, editor); textarea.hidden = true;
    }
    function setField(id, urls, multiple) {
        const field = document.getElementById(id); if (!field) return;
        field.value = multiple ? urls.join(',') + ',' : urls[0];
        field.dispatchEvent(new Event('change', {bubbles:true}));
    }
    function picker(button) {
        const dir = button.dataset.cmsKind || 'image', target = button.dataset.cmsTarget;
        const multiple = button.hasAttribute('data-cms-multiple');
        const dialog = document.createElement('dialog'); dialog.className = 'cms-picker';
        const heading = document.createElement('h3'); heading.textContent = dir === 'template' ? '选择模板' : '上传或选择文件';
        const close = document.createElement('button'); close.type='button'; close.textContent='关闭'; close.onclick=()=>dialog.close();
        const status=document.createElement('p'); status.setAttribute('role','status');
        const list=document.createElement('div'); list.className='cms-file-list';
        const selected=new Set();
        const select=(url)=>{ if(multiple) { selected.add(url); status.textContent='已选择 '+selected.size+' 个文件'; }
            else {setField(target,[url],false);dialog.close();} };
        dialog.append(heading,close);
        if (dir !== 'template') {
            const input=document.createElement('input'); input.type='file'; input.multiple=multiple;
            input.setAttribute('aria-label','上传文件');
            if(dir==='image')input.accept='image/jpeg,image/png,image/gif,image/bmp';
            input.onchange=async()=>{
                input.disabled=true; status.textContent='正在上传…';
                try { const urls=[]; for(const file of input.files) urls.push(await upload(file,dir));
                    urls.forEach(select); if(multiple) await load('');
                } catch(error){status.textContent=error.message;} finally {input.disabled=false;input.value='';}
            }; dialog.append(input);
        }
        dialog.append(status,list);
        if(multiple){const done=document.createElement('button');done.type='button';done.textContent='使用所选文件';
            done.onclick=()=>{if(selected.size){setField(target,Array.from(selected),true);dialog.close();}};dialog.append(done);}
        let sequence=0;
        async function load(path){
            const current=++sequence;
            try{
                const query=new URLSearchParams({dir:dir==='template'?'':dir,path});
                const response=await fetch('/admin/api/files.php?'+query,{credentials:'same-origin'});
                const result=await response.json();if(!response.ok)throw new Error(result.message || '读取失败');
                if(current!==sequence || !dialog.open)return;
                list.replaceChildren();
                if(path){const up=document.createElement('button');up.type='button';up.textContent='上一级';up.onclick=()=>load(result.moveup_dir_path);list.append(up);}
                result.file_list.forEach(file=>{
                    const item=document.createElement('button');item.type='button';item.textContent=(file.is_dir?'📁 ':'')+file.filename;
                    item.onclick=()=>file.is_dir?load(result.current_dir_path+file.filename+'/'):select(result.current_url+encodeURIComponent(file.filename));
                    list.append(item);
                });
            }catch(error){status.textContent=error.message;}
        }
        dialog.addEventListener('close',()=>{sequence++;dialog.remove();});
        document.body.append(dialog);dialog.showModal();load('');
    }
    document.addEventListener('click',function(event){
        const pick=event.target.closest('[data-cms-target]');if(pick){event.preventDefault();picker(pick);return;}
        if(event.target.closest('#save, [type="submit"]')){
            if(pending){event.preventDefault();event.stopImmediatePropagation();alert('文件上传尚未完成，请稍后保存。');return;}sync();
        }
    },true);
    document.addEventListener('submit',function(event){if(pending){event.preventDefault();event.stopImmediatePropagation();alert('文件上传尚未完成。');}else sync();},true);
    function start(){document.querySelectorAll('textarea[data-cms-editor]').forEach(mount);}
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);else start();
    window.addEventListener('pagehide',()=>{instances.forEach(editor=>editor.destroy());instances.clear();});
    window.FengEditor={sync,upload};
}());
