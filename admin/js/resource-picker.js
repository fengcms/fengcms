/* Local resource chooser. File names are always rendered as text, never HTML. */
(function () {
    'use strict';
    const formats = {image:['jpg','jpeg','png','gif','bmp'],file:['pdf','txt','zip','doc','docx','xls','xlsx','ppt','pptx'],media:['mp3','mp4','wav']};
    const sizeText = bytes => bytes < 1024 ? bytes+' B' : bytes < 1048576 ? (bytes/1024).toFixed(1)+' KB' : (bytes/1048576).toFixed(1)+' MB';
    function el(tag, className, text) { const node=document.createElement(tag);if(className)node.className=className;if(text!==undefined)node.textContent=text;return node; }
    function button(text, className, action) { const node=el('button',className,text);node.type='button';node.onclick=action;return node; }
    function filename(url) { try { return decodeURIComponent(url.split('/').pop()); } catch (_) { return url.split('/').pop(); } }
    function open(trigger, options) {
        const kind=trigger.dataset.cmsKind || 'image', isImage=kind==='image', isTemplate=kind==='template';
        const multiple=trigger.hasAttribute('data-cms-multiple');
        const field=document.getElementById(trigger.dataset.cmsTarget);if(!field)return;
        const title=isTemplate?'选择模板':isImage?(multiple?'选择多张图片':'选择图片'):'选择附件';
        const rootLabel=isTemplate?'模板目录':isImage?'图片库':'附件库';
        const selected=new Map(), queue=[];
        let path='', page=1, pages=1, view=isImage?'grid':'list', tab='library', running=false, closed=false, requestId=0, searchTimer, queueOverflow=false;
        let listingController, uploadController;
        const dialog=el('dialog','cms-picker');dialog.setAttribute('aria-label',title);
        const header=el('header','rp-header');
        const headingBox=el('div','rp-heading');headingBox.append(el('span','rp-eyebrow','FENGCMS · 资源选择'),el('h2','',title));
        const close=button('×','rp-close',()=>dialog.close());close.setAttribute('aria-label','关闭资源选择器');header.append(headingBox,close);
        const tabs=el('div','rp-tabs');tabs.setAttribute('role','tablist');
        const libraryTab=button('资源库','rp-tab',()=>switchTab('library'));libraryTab.setAttribute('role','tab');
        const uploadTab=button('上传文件','rp-tab',()=>switchTab('upload'));uploadTab.setAttribute('role','tab');
        tabs.append(libraryTab);if(!isTemplate)tabs.append(uploadTab);
        const main=el('div','rp-main');
        const library=el('section','rp-library');library.setAttribute('aria-label','资源库');
        const tools=el('div','rp-tools');
        const search=el('input','rp-search');search.type='search';search.placeholder='搜索当前目录中的文件名';search.setAttribute('aria-label','搜索文件名');
        search.oninput=()=>{clearTimeout(searchTimer);searchTimer=setTimeout(()=>{page=1;load();},250);};
        const sort=el('select','rp-sort');sort.setAttribute('aria-label','排序方式');
        [['time:desc','最近修改'],['name:asc','名称 A–Z'],['name:desc','名称 Z–A'],['size:desc','文件从大到小']].forEach(([value,text])=>{const opt=el('option','',text);opt.value=value;sort.append(opt);});
        sort.value=isTemplate?'name:asc':'time:desc';sort.onchange=()=>{page=1;load();};
        const refresh=button('刷新','rp-button',()=>load());
        const gridButton=button('网格','rp-view',()=>{view='grid';load();});
        const listButton=button('列表','rp-view',()=>{view='list';load();});
        tools.append(search,sort,refresh);if(isImage)tools.append(gridButton,listButton);
        const breadcrumbs=el('nav','rp-breadcrumb');breadcrumbs.setAttribute('aria-label','目录路径');
        const list=el('div','rp-files');
        const pager=el('div','rp-pager');
        const previous=button('上一页','rp-button',()=>{page--;load();});
        const next=button('下一页','rp-button',()=>{page++;load();});
        const pageInfo=el('span');pager.append(pageInfo,previous,next);
        library.append(tools,breadcrumbs,list,pager);
        const uploadPanel=el('section','rp-upload');uploadPanel.setAttribute('aria-label','上传文件');
        const drop=el('div','rp-drop');
        const fileInput=el('input','rp-file-input');fileInput.type='file';fileInput.multiple=multiple;fileInput.accept=(formats[kind] || []).map(ext=>'.'+ext).join(',');fileInput.setAttribute('aria-label','选择待上传文件');
        fileInput.onchange=()=>{enqueue(Array.from(fileInput.files));fileInput.value='';};
        const choose=button('选择文件','rp-primary',()=>fileInput.click());
        drop.append(el('span','rp-drop-icon','↑'),el('h3','','拖拽文件到这里'),el('p','','也可以从电脑选择文件，上传后自动加入已选列表。'),choose,fileInput,
            el('small','',((formats[kind] || []).join(' / ').toUpperCase())+' · 单个文件不超过 10MB'));
        drop.ondragover=event=>{event.preventDefault();drop.classList.add('is-dragover');};
        drop.ondragleave=()=>drop.classList.remove('is-dragover');
        drop.ondrop=event=>{event.preventDefault();drop.classList.remove('is-dragover');enqueue(Array.from(event.dataTransfer.files));};
        const queueList=el('div','rp-queue');uploadPanel.append(drop,queueList);
        const status=el('p','rp-status');status.setAttribute('role','status');status.setAttribute('aria-live','polite');
        main.append(library,uploadPanel,status);
        const selection=el('section','rp-selection');selection.setAttribute('aria-label','已选择的资源');
        const selectedHeader=el('div','rp-selection-heading'), selectedInfo=el('span'), chips=el('div','rp-chips');
        const clear=button('清空选择','rp-text-button',()=>{selected.clear();renderSelection();});selectedHeader.append(selectedInfo,clear);selection.append(selectedHeader,chips);
        const footer=el('footer','rp-footer');
        const hint=el('span','rp-hint',multiple?'按选择顺序回填，可移除不需要的图片。':'单击选择，确认后回填；双击文件可快速使用。');
        const cancel=button('取消','rp-button',()=>dialog.close());
        const confirm=button(isTemplate?'使用所选模板':isImage?'使用所选图片':'使用所选附件','rp-primary',commit);
        footer.append(hint,cancel,confirm);dialog.append(header,tabs,main,selection,footer);

        function validURL(url) {
            if(typeof url!=='string' || !url.startsWith('/') || url.startsWith('//') || /[\\\x00-\x1f]/.test(url))return false;
            if(isTemplate)return url.endsWith('.html') && !url.split('/').includes('..');
            try { const parsed=new URL(url,location.origin);return parsed.origin===location.origin && parsed.pathname.startsWith('/upload/'+kind+'/'); } catch(_){return false;}
        }
        function resource(url) {
            const item={url,filename:filename(url),is_photo:isImage};
            if(isImage) { try { item.thumbnail_url='/admin/api/thumbnail.php?path='+encodeURIComponent(decodeURIComponent(url.slice('/upload/image/'.length))); } catch(_) {} }
            return item;
        }
        const initial=multiple?field.value.split(',').filter(Boolean):[field.value].filter(Boolean);
        initial.slice(0,60).filter(validURL).forEach(url=>selected.set(url,resource(url)));
        function setStatus(text,error=false) { status.textContent=text;status.classList.toggle('is-error',error); }
        function commit() { if(confirm.disabled)return;options.onSelect(Array.from(selected.keys()));dialog.close(); }
        function pick(item, fast=false) {
            if(!validURL(item.url))return;
            if(multiple) { if(selected.has(item.url))selected.delete(item.url);else if(selected.size<60)selected.set(item.url,item);else setStatus('一次最多选择 60 个文件。',true); }
            else {selected.clear();selected.set(item.url,item);}
            renderSelection();if(fast && !multiple)commit();
        }
        function thumb(item,className) {
            const box=el('div',className);
            if(item.thumbnail_url && item.is_photo) {
                const image=el('img');image.src=item.thumbnail_url;image.alt='';image.loading='lazy';image.decoding='async';image.onerror=()=>{image.remove();box.append(el('span','rp-file-symbol','图片'));};box.append(image);
            }else box.append(el('span','rp-file-symbol',item.is_dir?'目录':isTemplate?'HTML':(item.filetype || '文件').toUpperCase()));
            return box;
        }
        function renderSelection() {
            selectedInfo.textContent='已选择 '+selected.size+(isImage?' 张图片':' 个文件');clear.disabled=!selected.size;
            chips.replaceChildren();
            selected.forEach(item=>{
                const chip=el('div','rp-chip');if(isImage)chip.append(thumb(item,'rp-chip-thumb'));
                chip.append(el('span','',item.filename));const remove=button('×','rp-chip-remove',()=>{selected.delete(item.url);renderSelection();});remove.setAttribute('aria-label','取消选择 '+item.filename);chip.append(remove);chips.append(chip);
            });
            if(!selected.size)chips.append(el('span','rp-selection-empty','尚未选择资源'));
            list.querySelectorAll('[data-resource]').forEach(card=>{const chosen=selected.has(card.dataset.resource);card.classList.toggle('is-selected',chosen);card.querySelector('.rp-entry').setAttribute('aria-pressed',String(chosen));});
            confirm.disabled=running || queue.some(item=>item.state==='waiting') || (!selected.size && !(multiple && initial.length));
        }
        function switchTab(value) {
            tab=value;library.hidden=tab!=='library';uploadPanel.hidden=tab!=='upload';
            libraryTab.setAttribute('aria-selected',String(tab==='library'));uploadTab.setAttribute('aria-selected',String(tab==='upload'));
            if(tab==='library')load();
        }
        function navigate(nextPath) {path=nextPath;page=1;search.value='';load();}
        function renderBreadcrumbs() {
            breadcrumbs.replaceChildren(button(rootLabel,'rp-crumb',()=>navigate('')));
            let relative='';path.split('/').filter(Boolean).forEach(part=>{relative+=part+'/';const dest=relative;breadcrumbs.append(el('span','rp-divider','/'),button(part,'rp-crumb',()=>navigate(dest)));});
        }
        function preview(item) {
            if(!isImage || !validURL(item.url))return;
            const modal=el('dialog','rp-preview');modal.setAttribute('aria-label','图片预览');
            const top=el('div','rp-preview-header');top.append(el('span','',item.filename),button('关闭预览','rp-button',()=>modal.close()));
            const image=el('img');image.src=item.url;image.alt=item.filename;image.onerror=()=>{image.replaceWith(el('p','','图片无法加载'));};modal.append(top,image);
            modal.addEventListener('close',()=>modal.remove());dialog.append(modal);modal.showModal();
        }
        async function load() {
            listingController?.abort();listingController=new AbortController();const id=++requestId;
            previous.disabled=next.disabled=true;list.setAttribute('aria-busy','true');list.replaceChildren(el('div','rp-empty','正在读取资源…'));renderBreadcrumbs();
            gridButton.setAttribute('aria-pressed',String(view==='grid'));listButton.setAttribute('aria-pressed',String(view==='list'));
            list.className='rp-files rp-'+view;
            const [order,direction]=sort.value.split(':');
            try {
                const query=new URLSearchParams({dir:isTemplate?'':kind,path,q:search.value,order,direction,page:String(page),limit:'24'});
                const response=await fetch('/admin/api/files.php?'+query,{credentials:'same-origin',signal:listingController.signal});
                const result=await response.json();if(!response.ok)throw new Error(result.message || '读取资源失败');
                if(id!==requestId || closed)return;
                page=result.page;pages=result.page_count;path=result.current_dir_path;renderBreadcrumbs();list.replaceChildren();
                pageInfo.textContent=result.total_count+' 项 · 第 '+page+' / '+pages+' 页';
                result.file_list.forEach(item=>{
                    const card=el('div','rp-card');if(!item.is_dir)card.dataset.resource=item.url;
                    const entry=button('','rp-entry',()=>item.is_dir?navigate(path+item.filename+'/'):pick(item));
                    entry.setAttribute('aria-label',(item.is_dir?'打开目录 ':'选择 ')+item.filename);entry.title=item.filename;
                    entry.append(thumb(item,'rp-thumb'));
                    const info=el('div','rp-file-info');info.append(el('span','rp-filename',item.filename));
                    const details=item.is_dir?'文件夹':(item.width?item.width+' × '+item.height+' · ':'')+sizeText(item.filesize);
                    info.append(el('span','rp-file-details',details),el('span','rp-file-time',item.datetime));
                    if(isTemplate && !item.is_dir)info.append(el('span','rp-file-path',item.url));
                    entry.append(info);card.append(entry);
                    if(!item.is_dir)entry.ondblclick=()=>{if(!multiple){selected.clear();selected.set(item.url,item);renderSelection();commit();}};
                    if(isImage && item.is_photo){const zoom=button('预览','rp-preview-button',()=>preview(item));zoom.setAttribute('aria-label','预览 '+item.filename);card.append(zoom);}
                    list.append(card);
                });
                if(!result.file_list.length)list.append(el('div','rp-empty',search.value?'没有匹配的文件，请调整关键词。':'此目录还没有文件。'+(isTemplate?'':'可切换到“上传文件”添加资源。')));
                setStatus(search.value?'仅搜索当前目录，不包含子目录。':'');renderSelection();
            } catch(error) {
                if(error.name==='AbortError' || closed || id!==requestId)return;
                pages=page=1;pageInfo.textContent='';list.replaceChildren(el('div','rp-empty','资源加载失败'));setStatus(error.message,true);
            } finally {if(id===requestId && !closed){list.setAttribute('aria-busy','false');previous.disabled=page<=1;next.disabled=page>=pages;}}
        }
        function enqueue(files) {
            const room=60-queue.length;
            queueOverflow=files.length>room;
            files.slice(0,room).forEach(file=>{
                const ext=file.name.split('.').pop().toLowerCase();let error='';
                if(!(formats[kind] || []).includes(ext))error='不支持此文件格式';else if(file.size===0 || file.size>10*1024*1024)error='文件必须大于 0 且不超过 10MB';
                queue.push({file,state:error?'invalid':'waiting',progress:0,error});
            });renderQueue();runQueue();
        }
        function renderQueue() {
            queueList.replaceChildren();
            queue.forEach(item=>{
                const row=el('div','rp-upload-row'), info=el('div','rp-upload-info');
                info.append(el('strong','',item.file.name),el('span','',sizeText(item.file.size)));row.append(info);
                const state=el('span','rp-upload-state',item.state==='done'?'上传完成':item.state==='uploading'?'上传中 '+item.progress+'%':item.state==='waiting'?'等待上传':item.error);row.append(state);
                if(item.state==='uploading'){const progress=el('progress');progress.max=100;progress.value=item.progress;progress.setAttribute('aria-label',item.file.name+' 上传进度');row.append(progress);}
                if(item.state==='error')row.append(button('重试','rp-button',()=>{item.state='waiting';item.error='';renderQueue();runQueue();}));
                if(item.state==='invalid' || item.state==='error')row.classList.add('is-error');queueList.append(row);
            });renderSelection();
        }
        async function runQueue() {
            if(running || closed)return;running=true;renderSelection();
            for(const item of queue){
                if(closed)break;if(item.state!=='waiting')continue;
                item.state='uploading';uploadController=new AbortController();renderQueue();
                try {
                    const url=await options.upload(item.file,kind,progress=>{item.progress=progress;if(!closed)renderQueue();},uploadController.signal);
                    if(closed)break;if(!validURL(url))throw new Error('上传返回了无效路径');
                    item.state='done';if(!multiple)selected.clear();if(selected.size<60)selected.set(url,resource(url));
                }catch(error){item.state='error';item.error=error.name==='AbortError'?'上传已取消':error.message;}
                if(!closed)renderQueue();
            }
            running=false;if(!closed){
                renderQueue();
                const done=queue.filter(item=>item.state==='done').length;
                const failed=queue.filter(item=>item.state==='error' || item.state==='invalid').length;
                setStatus('上传完成 '+done+' 个'+(failed?'，失败 '+failed+' 个，请查看原因。':'。')+
                    (done?'请确认下方已选列表后使用。':'')+
                    (queueOverflow?'一次最多上传 60 个文件，超出部分未加入队列。':''),failed>0 || queueOverflow);
            }
        }
        // Native dialog contains focus; cancel/close never writes back to the form.
        dialog.addEventListener('keydown',event=>{if(event.key==='Escape')event.stopPropagation();});
        dialog.addEventListener('close',()=>{closed=true;clearTimeout(searchTimer);listingController?.abort();uploadController?.abort();dialog.remove();trigger.focus();});
        document.body.append(dialog);dialog.showModal();switchTab('library');renderSelection();
    }
    window.FengResourcePicker={open};
}());
