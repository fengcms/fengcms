/* FengCMS editor adapter. wangEditor is vendored; no CDN requests. */
(function () {
    'use strict';
    const instances = new Map();
    const failedEditors = new Set();
    const inlineEditors = new Set();
    function checkImageURL(value, allowRelative = false) {
        if (typeof value !== 'string' || !value.trim() || /[\\\x00-\x1f\x7f]/.test(value)) return false;
        const text = value.trim();
        if (allowRelative && /^\/(?!\/)/.test(text)) return true;
        if (!/^https?:\/\//i.test(text)) return false;
        try {
            const url = new URL(text);
            return !!url.hostname && !url.username && !url.password;
        } catch (_) { return false; }
    }
    function checkImage(src, alt, link, allowRelative = false) {
        if (!checkImageURL(src, allowRelative)) return allowRelative
            ? '图片地址须为 HTTP/HTTPS 网址或站内绝对路径。'
            : '请输入完整的 HTTP/HTTPS 图片网址。';
        if (link && !checkImageURL(link, true)) return '图片跳转链接须为 HTTP/HTTPS 网址或站内绝对路径。';
        return true;
    }
    const upload = (...args) => window.FengUpload.upload(...args);
    function sync() { instances.forEach((editor, textarea) => { if (!failedEditors.has(editor) && !inlineEditors.has(editor)) {const html=editor.isEmpty()?'':editor.getHtml();textarea.value=window.FengLegacyConverter?.forStorage(html)??html;} }); }
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
        const originalHtml = textarea.value;
        const wrapper = document.createElement('div'); wrapper.className = 'cms-editor';
        const toolbar = document.createElement('div'), body = document.createElement('div');
        wrapper.append(toolbar, body); textarea.after(wrapper);
        const editor = window.wangEditor.createEditor({
            selector:body, html:'<p><br></p>', mode:'default',
            config:{ autoFocus:false, placeholder:'请输入内容',
                onChange:() => sync(),
                MENU_CONF:{
                    insertImage:{checkImage, parseImageSrc:src=>src.trim()},
                    editImage:{checkImage:(src,alt,link)=>checkImage(src,alt,link,true), parseImageSrc:src=>src.trim()},
                    uploadImage:{maxFileSize:10 * 1024 * 1024,
                    async customUpload(file, insert) { const url = await upload(file); insert(url, '', ''); }
                }}
            }
        });
        let legacyControls;
        const recovery = document.createElement('p');
        const inlineNotice = document.createElement('p');inlineNotice.className='legacy-warning';inlineNotice.hidden=true;
        inlineNotice.textContent='原文包含 Base64 内嵌图片，请先使用“优化历史富文本”安全转存图片，再保存文章。';wrapper.before(inlineNotice);
        function protectInlineImages(){inlineEditors.add(editor);inlineNotice.hidden=false;textarea.value=originalHtml;}
        if(window.FengLegacyConverter?.hasInlineImages(originalHtml))protectInlineImages();
        recovery.className = 'legacy-warning'; recovery.hidden = true;
        recovery.textContent = '历史正文无法直接载入编辑器，原始 HTML 已保留。请先优化历史富文本并检查结果；处理完成前不能保存。';
        wrapper.before(recovery);
        function showRecovery() {
            failedEditors.add(editor); editor.disable();
            textarea.value = originalHtml; textarea.hidden = false; textarea.readOnly = true;
            wrapper.style.display = 'none'; recovery.hidden = false;legacyControls?.setRecovery(true);
        }
        try { const html=originalHtml || '<p><br></p>';editor.setHtml(window.FengLegacyConverter?.forEditor(html)??html); }
        catch (_) { editor.clear(); showRecovery(); }
        legacyControls=window.FengLegacyRichtext?.mount({textarea, editor, wrapper, originalHtml, canOptimize:()=>window.FengUpload.pending===0,recovery:failedEditors.has(editor),
            onApplied:()=>{
                const wasFailed=failedEditors.has(editor);
                const hadInline=inlineEditors.has(editor);inlineEditors.delete(editor);inlineNotice.hidden=true;
                failedEditors.delete(editor); editor.enable(); textarea.hidden=true; textarea.readOnly=false;
                wrapper.style.display=''; recovery.hidden=true;legacyControls?.setRecovery(false);
                return ()=>{if(wasFailed)showRecovery();if(hadInline)protectInlineImages();};
            }});
        window.wangEditor.createToolbar({editor, selector:toolbar, config:{toolbarKeys:[
            'headerSelect','bold','italic','underline','through','color','bgColor','fontSize',
            'bulletedList','numberedList','blockquote','justifyLeft','justifyCenter','justifyRight',
            'insertLink',{
                key:'group-image', title:'图片',
                iconSvg:'<svg viewBox="0 0 1024 1024"><path d="M896 128H128v768h768V128zM192 192h640v448L640 448 448 640 320 512 192 640V192zm64 128a64 64 0 1 0 128 0 64 64 0 0 0-128 0z"/></svg>',
                menuKeys:['uploadImage','insertImage']
            },'insertTable','undo','redo',...(window.FengLegacyRichtext?['|','cmsLegacyOptimize','cmsLegacySource','cmsLegacyUndo']:[]),'fullScreen'
        ]}});
        // Toolbar and group buttons are inserted asynchronously by wangEditor.
        const labelToolbar = () => {
            toolbar.querySelectorAll('button[data-tooltip]').forEach(button => {
                button.setAttribute('aria-label', button.getAttribute('data-tooltip').split('\n')[0]);
            });
            toolbar.querySelectorAll('button[data-menu-key="group-image"]').forEach(button => {
                button.setAttribute('aria-label', '图片');button.setAttribute('title', '图片');
            });
        };
        const toolbarObserver = new MutationObserver(labelToolbar);
        toolbarObserver.observe(toolbar, {childList:true, subtree:true});labelToolbar();
        body.setAttribute('aria-label', textarea.name || textarea.id || '富文本编辑区');
        const fullscreenObserver = new MutationObserver(updateFullscreenFrame);
        fullscreenObserver.observe(wrapper, {attributes:true, attributeFilter:['class']});
        editor.on('destroyed', () => { toolbarObserver.disconnect(); fullscreenObserver.disconnect(); updateFullscreenFrame(); });
        instances.set(textarea, editor); textarea.hidden = !failedEditors.has(editor);

    }
    function setField(id, urls, multiple) {
        const field = document.getElementById(id); if (!field) return;
        const previous = multiple ? field.value.split(',').filter(Boolean) : [field.value].filter(Boolean);
        if (previous.length === urls.length && previous.every((url,index) => url === urls[index])) return;
        field.value = multiple ? (urls.length ? urls.join(',') + ',' : '') : (urls[0] || '');
        field.dispatchEvent(new Event('change', {bubbles:true}));
    }
    function picker(button, restoreFocus) {
        window.FengResourcePicker.open(button, {upload, restoreFocus, onSelect:urls=>setField(button.dataset.cmsTarget,urls,button.hasAttribute('data-cms-multiple'))});
    }
    document.addEventListener('click',function(event){
        const pick=event.target.closest('[data-cms-target]');if(pick){event.preventDefault();picker(pick);return;}
        if(event.target.closest('#save, [type="submit"]')){
            if(inlineEditors.size){event.preventDefault();event.stopImmediatePropagation();alert('正文含 Base64 图片，请先使用“优化历史富文本”转存图片，避免保存时丢失。');return;}
            if(failedEditors.size){event.preventDefault();event.stopImmediatePropagation();alert('存在无法载入的历史正文，请先优化并检查，避免保存时丢失内容。');return;}
            if(window.FengUpload.pending){event.preventDefault();event.stopImmediatePropagation();alert('文件上传尚未完成，请稍后保存。');return;}sync();
        }
    },true);
    document.addEventListener('submit',function(event){if(inlineEditors.size){event.preventDefault();event.stopImmediatePropagation();alert('请先转存正文中的 Base64 图片。');}else if(failedEditors.size){event.preventDefault();event.stopImmediatePropagation();alert('请先处理无法载入的历史正文。');}else if(window.FengUpload.pending){event.preventDefault();event.stopImmediatePropagation();alert('文件上传尚未完成。');}else sync();},true);
    function start(){document.querySelectorAll('textarea[data-cms-editor]').forEach(mount);window.FengImageFields?.mount({open:picker});}
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);else start();
    window.addEventListener('pageshow', updateFullscreenFrame);
    window.addEventListener('pagehide',event=>{restoreFullscreenFrame();if(!event.persisted){instances.forEach(editor=>editor.destroy());instances.clear();}});
    window.FengEditor={sync,upload};
}());
