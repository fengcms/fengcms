/* FengCMS editor adapter. wangEditor is vendored; no CDN requests. */
(function () {
    'use strict';
    const instances = new Map();
    let pending = 0;
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    async function upload(file, dir = 'image', onProgress = () => {}, signal) {
        if (file.size <= 0 || file.size > 10 * 1024 * 1024) throw new Error('文件必须大于 0 且不能超过 10MB');
        const data = new FormData();
        data.append('imgFile', file); data.append('_csrf', csrf());
        pending++;
        try {
            return await new Promise((resolve,reject) => {
                const xhr = new XMLHttpRequest();
                const abort=()=>xhr.abort();
                const cleanup=()=>signal?.removeEventListener('abort',abort);
                xhr.open('POST','/admin/api/upload.php?dir='+encodeURIComponent(dir));
                xhr.timeout=120000;
                xhr.upload.onprogress=event=>{if(event.lengthComputable)onProgress(Math.round(event.loaded/event.total*100));};
                xhr.onload=()=>{cleanup();try{const result=JSON.parse(xhr.responseText);
                    if(xhr.status<200 || xhr.status>=300 || result.error!==0)throw new Error(result.message || '上传失败');
                    resolve(result.url);
                }catch(error){reject(error instanceof SyntaxError?new Error('服务器返回异常，请重试'):error);}};
                xhr.onerror=()=>{cleanup();reject(new Error('网络连接失败，请重试'));};
                xhr.ontimeout=()=>{cleanup();reject(new Error('上传超时，请重试'));};
                xhr.onabort=()=>{cleanup();reject(new DOMException('上传已取消','AbortError'));};
                if(signal?.aborted){reject(new DOMException('上传已取消','AbortError'));return;}
                signal?.addEventListener('abort',abort,{once:true});xhr.send(data);
            });
        } finally { pending--; }
    }
    function sync() { instances.forEach((editor, textarea) => { textarea.value = editor.isEmpty() ? '' : editor.getHtml(); }); }
    // The administration shell embeds forms in an iframe. Expand that frame
    // while an editor is fullscreen; removing the class restores its layout.
    function updateFullscreenFrame() {
        const active = document.querySelector('.cms-editor.w-e-full-screen-container');
        try {
            const frame = window.frameElement;
            if (!frame) return;
            const parentDocument = frame.ownerDocument;
            if (!parentDocument.getElementById('cms-editor-frame-style')) {
                const style = parentDocument.createElement('style'); style.id = 'cms-editor-frame-style';
                style.textContent = '.cms-editor-fullscreen-frame{position:fixed!important;inset:0!important;width:100vw!important;height:100vh!important;max-width:none!important;max-height:none!important;margin:0!important;z-index:100000!important;}';
                parentDocument.head.append(style);
            }
            frame.classList.toggle('cms-editor-fullscreen-frame', !!active);
        } catch (_) { /* Standalone or cross-origin embedding: fill this viewport. */ }
    }
    function restoreFullscreenFrame() {
        try { window.frameElement?.classList.remove('cms-editor-fullscreen-frame'); } catch (_) {}
    }
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            instances.forEach(editor => { if (editor.isFullScreen) editor.unFullScreen(); });
            updateFullscreenFrame();
        }
    });
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
        toolbar.querySelectorAll('button[data-tooltip]').forEach(button => {
            button.setAttribute('aria-label', button.getAttribute('data-tooltip').split('\n')[0]);
        });
        body.setAttribute('aria-label', textarea.name || textarea.id || '富文本编辑区');
        const fullscreenObserver = new MutationObserver(updateFullscreenFrame);
        fullscreenObserver.observe(wrapper, {attributes:true, attributeFilter:['class']});
        editor.on('destroyed', () => { fullscreenObserver.disconnect(); updateFullscreenFrame(); });
        instances.set(textarea, editor); textarea.hidden = true;
    }
    function setField(id, urls, multiple) {
        const field = document.getElementById(id); if (!field) return;
        field.value = multiple ? (urls.length ? urls.join(',') + ',' : '') : (urls[0] || '');
        field.dispatchEvent(new Event('change', {bubbles:true}));
    }
    function picker(button) {
        window.FengResourcePicker.open(button, {upload, onSelect:urls=>setField(button.dataset.cmsTarget,urls,button.hasAttribute('data-cms-multiple'))});
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
    window.addEventListener('pageshow', updateFullscreenFrame);
    window.addEventListener('pagehide',event=>{restoreFullscreenFrame();if(!event.persisted){instances.forEach(editor=>editor.destroy());instances.clear();}});
    window.FengEditor={sync,upload};
}());
