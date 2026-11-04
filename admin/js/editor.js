/* Customer branch: KindEditor edits richtext; modern APIs own every resource. */
(function () {
    'use strict';
    const fields = new Map();
    const safe = window.FengRichtext;
    const upload = (...args) => window.FengUpload.upload(...args);
    let pending = 0;
    const busy = () => pending > 0 || window.FengUpload.pending > 0;
    const items = ['undo','redo','|','formatblock','fontname','fontsize','forecolor','hilitecolor',
        'bold','italic','underline','strikethrough','subscript','superscript','removeformat','|',
        'justifyleft','justifycenter','justifyright','justifyfull','insertorderedlist','insertunorderedlist',
        'indent','outdent','|','image','cmsnetworkimage','insertfile','link','unlink','table','hr','|','cmshtml','cmssource','fullscreen'];
    // An explicit client allowlist complements (never replaces) HTML Purifier.
    const styles = ['.color','.background-color','.font-size','.font-family','.font-weight','.font-style',
        '.text-decoration','.text-align','.text-indent','.line-height','.width','.height','.border',
        '.border-collapse','.padding','.padding-left','.padding-right','.padding-top','.padding-bottom',
        '.margin','.margin-left','.margin-right','.margin-top','.margin-bottom','.float','.vertical-align'];
    const htmlTags = {
        'div,p,h1,h2,h3,h4,h5,h6,span,blockquote,pre,ul,ol,li,dl,dt,dd,center':styles.concat(['align','start']),
        'strong,b,em,i,u,s,strike,sub,sup,code,br,hr':[],
        font:['color','face','size'], a:['href','title','target'],
        img:styles.concat(['src','alt','title','width','height','align','border','hspace','vspace']),
        table:styles.concat(['width','height','align','border','cellpadding','cellspacing','bgcolor']),
        'thead,tbody,tfoot,tr,th,td,caption':styles.concat(['colspan','rowspan','width','height','align','valign','bgcolor'])
    };
    function button(label, action) {
        const node = document.createElement('button'); node.type = 'button'; node.textContent = label;
        node.addEventListener('click', action); return node;
    }
    function dialog(title) {
        const node = document.createElement('dialog'); node.className = 'legacy-dialog';
        const heading = document.createElement('h2'); heading.textContent = title;
        const body = document.createElement('div'); body.className = 'legacy-dialog-body';
        const footer = document.createElement('div'); footer.className = 'legacy-dialog-footer';
        node.append(heading, body, footer); document.body.append(node);
        node.addEventListener('close', () => node.remove(), {once:true});
        node.addEventListener('keydown', event => { if (event.key === 'Escape') event.stopPropagation(); });
        node.showModal(); return {node, body, footer};
    }
    function updateFrame() {
        const active = Array.from(fields.values()).some(state => state.editor?.fullscreenMode);
        try {
            const frame = window.frameElement; if (!frame) return;
            const doc = frame.ownerDocument;
            if (!doc.getElementById('cms-editor-frame-style')) {
                const style = doc.createElement('style'); style.id = 'cms-editor-frame-style';
                style.textContent = '.cms-editor-fullscreen-frame{position:fixed!important;inset:0!important;width:100vw!important;height:100vh!important;max-width:none!important;max-height:none!important;margin:0!important;z-index:100000!important;}';
                doc.head.append(style);
            }
            frame.classList.toggle('cms-editor-fullscreen-frame', active);
            if (active) window.dispatchEvent(new Event('resize'));
        } catch (_) { /* A standalone form fills its own viewport. */ }
    }
    function restoreSelection(editor, range) { editor.cmd.range = range; editor.cmd.select(); editor.focus(); }
    function richPicker(editor, kind) {
        editor.cmd.selection(); const range = editor.cmd.range.cloneRange();
        const selectedImage = kind === 'image' ? editor.plugin.getSelectedImage()?.[0] : null;
        const trigger = document.createElement('button'); trigger.dataset.cmsKind = kind;
        window.FengResourcePicker.open(trigger, {initial:[], upload,
            restoreFocus:() => restoreSelection(editor, range),
            onSelect:urls => {
                restoreSelection(editor, range);
                urls.forEach(url => {
                    if (!safe.validURL(url, kind === 'image')) throw Error('资源地址不正确。');
                    if (selectedImage) {
                        editor.addBookmark(false);
                        selectedImage.setAttribute('src',url); selectedImage.setAttribute('data-ke-src',url);
                        editor.addBookmark(false); sync(); return;
                    }
                    editor.insertHtml(kind === 'image' ? '<img src="'+safe.escape(url)+'" alt="" />' :
                        '<a href="'+safe.escape(url)+'">'+safe.escape(decodeURIComponent(url.split('/').pop()))+'</a>');
                });
            }});
    }
    function urlDialog(editor, image) {
        editor.cmd.selection(); const range = editor.cmd.range.cloneRange();
        const {node, body, footer} = dialog(image ? '插入网络图片' : '设置链接');
        const input = document.createElement('input'); input.type = 'url'; input.placeholder = 'https://';
        input.setAttribute('aria-label', image ? '网络图片地址' : '链接地址'); body.append(input);
        const error = document.createElement('p'); error.setAttribute('role','status'); body.append(error);
        footer.append(button('取消', () => node.close()), button('插入', () => {
            const url = input.value.trim();
            if (!safe.validURL(url, image)) { error.textContent = '请输入安全的完整网址或站内绝对路径。'; return; }
            restoreSelection(editor, range);
            if (image) editor.insertHtml('<img src="'+safe.escape(url)+'" alt="" />');
            else editor.exec('createlink', url, '');
            node.close();
        }));
        node.addEventListener('close', () => editor.focus()); input.focus();
    }
    const K = window.KindEditor;
    if (K) {
        // Preloaded plugins may be requested again by native context menus.
        const tablePlugin = K.plugin('table');
        if (tablePlugin) K.plugin('table', function (kind) { if (!this.plugin.table) tablePlugin.call(this, kind); });
        K.lang({cmsnetworkimage:'网络图片', cmshtml:'编辑 HTML / 转存内嵌图片', cmssource:'查看原始 HTML'}, 'zh_CN');
        K.plugin('cmsnetworkimage', function () { this.clickToolbar('cmsnetworkimage', () => urlDialog(this, true)); });
        K.plugin('cmshtml', function () { this.clickToolbar('cmshtml', () => source(fields.get(this.srcElement[0]), true)); });
        K.plugin('cmssource', function () { this.clickToolbar('cmssource', () => source(fields.get(this.srcElement[0]))); });
        // These names also handle editor context menus; no old upload plugin is loaded.
        K.plugin('image', function () {
            if (this.plugin.image) return;
            const editor = this;
            this.plugin.image = {edit:() => richPicker(editor, 'image'), delete:() => {
                const selected = editor.plugin.getSelectedImage();
                if (selected) { editor.addBookmark(false); selected.remove(); editor.addBookmark(false); sync(); }
            }};
            this.clickToolbar('image', this.plugin.image.edit);
        });
        K.plugin('insertfile', function () { this.clickToolbar('insertfile', () => richPicker(this, 'file')); });
        K.plugin('link', function () {
            if (this.plugin.link) return;
            this.plugin.link = {edit:() => urlDialog(this, false), delete:() => this.exec('unlink')};
            this.clickToolbar('link', this.plugin.link.edit);
        });
    }
    function sync() {
        for (const [textarea, state] of fields) {
            const html = state.ready ? state.editor.html() : state.original;
            const unchanged = !state.ready || html === state.baseline;
            // Omission preserves stored legacy bytes on an existing record. New or
            // changed HTML always goes through the normal server save sanitizer.
            textarea.disabled = state.existing && unchanged;
            textarea.value = unchanged ? state.original : html;
        }
    }
    function source(state, editable = false) {
        const {node, body, footer} = dialog(editable ? '编辑 HTML（应用前安全检查）' : '原始 HTML（本次打开时）');
        const input = document.createElement('textarea'); input.readOnly = !editable;
        input.value = editable && state.ready ? state.editor.html() : state.original;
        input.setAttribute('aria-label', editable ? 'HTML 源码' : '原始 HTML'); body.append(input);
        const note = document.createElement('p'); note.setAttribute('role','status'); body.append(note);
        footer.append(button('关闭', () => node.close()));
        if (editable) footer.append(button('检查并预览', () => preview(state, input.value, note, node)));
    }
    async function preview(state, input, note, parent, insertion = null) {
        if (busy()) { note.textContent = '请等待当前处理完成。'; return; }
        const before = state.ready ? state.editor.html() : null;
        const controller = new AbortController(); parent?.addEventListener('close', () => controller.abort(), {once:true});
        pending++;
        try {
            let prepared = {html:input, replacements:new Map()};
            if (safe.hasInlineImages(input)) {
                if (!window.confirm('将内嵌图片转存到现有资源库，保留原排版。取消预览不会删除已上传的图片。继续吗？')) return;
                prepared = await window.FengInlineImages.transfer(input, {upload, signal:controller.signal});
                safe.checkImages(input, prepared.html, prepared.replacements);
            }
            const html = await safe.clean(prepared.html, controller.signal);
            safe.checkImages(prepared.html, html);
            const view = dialog('安全检查结果预览');
            const frame = document.createElement('iframe'); frame.className = 'legacy-preview'; frame.title = '正文预览';
            frame.setAttribute('sandbox',''); frame.referrerPolicy = 'no-referrer';
            frame.srcdoc = '<!doctype html><meta charset="utf-8"><meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src http: https:; style-src \'unsafe-inline\'; base-uri \'none\'; form-action \'none\'">'+html;
            const info = document.createElement('p'); info.textContent = '已完成安全清洗，未进行 Markdown 转换或自动重排；请检查排版后应用。保存文章后才会写入数据库。';
            view.body.append(info, frame);
            view.footer.append(button('取消', () => view.node.close()), button('应用到编辑区', async () => {
                if ((state.ready ? state.editor.html() : null) !== before) { info.textContent = '编辑内容已变化，请关闭预览并重新检查。'; return; }
                try {
                    if (insertion && state.ready) {
                        restoreSelection(state.editor, insertion.range);
                        state.editor.insertHtml(html); safe.checkImages(html, state.editor.html(), new Map(), true);
                    } else if (!state.ready) await createEditor(state, html);
                    else { state.editor.html(html); safe.checkImages(html, state.editor.html()); }
                    // A deliberate application must be saved, even if it matches the safe initial view.
                    state.baseline = null;
                    state.note.textContent = '已应用，请检查后保存。可用“恢复本次打开的原文”撤销。';
                    sync(); view.node.close(); parent?.close();
                } catch (error) {
                    if (before !== null) state.editor.html(before);
                    info.textContent = error.message;
                }
            }));
            note.textContent = '';
        } catch (error) { if (error.name !== 'AbortError') note.textContent = error.message; }
        finally { pending--; }
    }
    function interceptPaste(state, doc) {
        doc.addEventListener('paste', async event => {
            event.preventDefault(); event.stopImmediatePropagation();
            if (busy()) { alert('请等待当前处理完成后粘贴。'); return; }
            const data = event.clipboardData;
            const html = data?.getData('text/html') || safe.escape(data?.getData('text/plain') || '').replace(/\r?\n/g,'<br />');
            if (!html) return;
            if (safe.hasInlineImages(html)) { sourcePaste(state, html); return; }
            const editor = state.editor; editor.cmd.selection(); const range = editor.cmd.range.cloneRange();
            const before = editor.html(); pending++;
            try {
                const cleaned = await safe.clean(html); safe.checkImages(html, cleaned);
                if (editor.html() !== before) throw Error('正文已变化，请重新粘贴。');
                restoreSelection(editor, range); editor.insertHtml(cleaned);
            } catch (error) { alert(error.message); } finally { pending--; }
        }, true);
        // Native HTML/file drops bypass the paste filter; use the modern upload picker.
        doc.addEventListener('drop', event => { event.preventDefault(); event.stopImmediatePropagation(); alert('请使用图片或附件按钮上传，或粘贴文字。'); }, true);
        doc.addEventListener('keydown', event => {
            if (event.key === 'Escape' && state.editor.fullscreenMode) { event.preventDefault(); state.editor.fullscreen(false); }
        });
    }
    function sourcePaste(state, html) {
        state.editor.cmd.selection(); const insertion = {range:state.editor.cmd.range.cloneRange()};
        const view = dialog('粘贴内容含内嵌图片');
        const note = document.createElement('p'); note.textContent = '请先转存图片，再检查预览。确认后插入到当前选区，不会重新排版已有正文。';
        const input = document.createElement('textarea'); input.value = html; input.setAttribute('aria-label','待转存 HTML');
        view.body.append(note, input); view.footer.append(button('取消', () => view.node.close()), button('转存并预览', () => preview(state, input.value, note, view.node, insertion)));
    }
    function createEditor(state, html) {
        return new Promise((resolve, reject) => {
            if (!state.ready && state.editor) { K.remove(state.textarea); state.editor = null; }
            const textarea = state.textarea; textarea.disabled = false; textarea.value = html;
            if (!K) { reject(Error('编辑器组件未加载，原文已保留。')); return; }
            try {
                state.editor = K.create(textarea, {
                    basePath:'/admin/public/kindeditor-4.1.7/', width:'100%', height:380, minWidth:240,
                    items, htmlTags, filterMode:true, wellFormatMode:false, urlType:'',
                    syncType:'none', fullscreenShortcut:false, allowFileManager:false, allowImageUpload:false,
                    allowImageRemote:false, allowFlashUpload:false, allowMediaUpload:false, allowFileUpload:false,
                    afterCreate:function () {
                        state.editor = this;
                        try {
                            if (!state.ready) { safe.checkImages(html, this.html()); state.baseline = this.html(); }
                            state.ready = true; textarea.readOnly = false;
                            this.edit.iframe[0].title = textarea.name || '富文本编辑区';
                            interceptPaste(state, this.edit.doc); updateFrame(); resolve();
                        } catch (error) { state.ready = false; this.readonly(); reject(error); }
                    },
                    afterChange:() => { if (state.ready) sync(); }
                });
            } catch (error) { reject(error); }
        });
    }
    async function mount(textarea) {
        if (fields.has(textarea)) return;
        const form = textarea.form;
        // Legacy table/div markup can place associated controls outside the form subtree.
        const existing = !!form?.elements.namedItem('_settings_version') || /^[1-9]\d*$/.test(form?.elements.namedItem('id')?.value || '');
        const state = {textarea, original:textarea.value, existing, ready:false, baseline:null, editor:null}; fields.set(textarea, state);
        const row = document.createElement('div'); row.className = 'legacy-actions cms-kindeditor-actions';
        const note = document.createElement('p'); note.className = 'legacy-warning'; note.setAttribute('role','status'); state.note = note;
        row.append(button('查看原始 HTML', () => source(state)), button('编辑 HTML / 转存内嵌图片', () => source(state, true)),
            button('插入网络图片', () => { if (state.ready) urlDialog(state.editor, true); }),
            button('恢复本次打开的原文', () => {
                if (busy() || !window.confirm('放弃当前富文本修改，恢复本次打开的原文？')) return;
                if (state.editor) { K.remove(state.textarea); state.editor = null; }
                state.ready = false; state.baseline = null;
                textarea.value = state.original; textarea.disabled = false; load(state); updateFrame();
            }));
        textarea.after(row, note); textarea.readOnly = true;
        await load(state);
    }
    async function load(state) {
        pending++;
        try {
            state.note.textContent = '正在安全载入历史正文…';
            if (safe.hasInlineImages(state.original)) throw Error('原文含内嵌图片。请使用“编辑 HTML / 转存内嵌图片”处理后再编辑正文；只修改其他字段会保留原文。');
            const html = state.original ? await safe.clean(state.original) : '';
            safe.checkImages(state.original, html);
            await createEditor(state, html);
            state.note.textContent = html === state.original ? '' : '编辑区已按安全规则载入；未改正文时保存会保留原文，修改正文后按现有安全规则保存。';
        } catch (error) {
            state.note.textContent = error.message;
            state.textarea.value = state.original; state.textarea.readOnly = true;
        } finally { pending--; sync(); }
    }
    function fieldPicker(trigger, restoreFocus) {
        window.FengResourcePicker.open(trigger, {upload, restoreFocus, onSelect:urls => {
            const field = document.getElementById(trigger.dataset.cmsTarget); if (!field) return;
            const multiple = trigger.hasAttribute('data-cms-multiple');
            const value = multiple ? (urls.length ? urls.join(',')+',' : '') : (urls[0] || '');
            if (value !== field.value) { field.value = value; field.dispatchEvent(new Event('change', {bubbles:true})); }
        }});
    }
    function beforeSave(event) {
        if (busy() || Array.from(fields.values()).some(state => !state.ready && !state.existing)) {
            event.preventDefault(); event.stopImmediatePropagation(); alert('富文本载入或文件处理尚未完成，请检查提示后再保存。'); return;
        }
        sync();
    }
    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-cms-target]');
        if (trigger) { event.preventDefault(); fieldPicker(trigger); return; }
        if (event.target.closest('#save, [type="submit"]')) beforeSave(event);
    }, true);
    document.addEventListener('submit', beforeSave, true);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') for (const state of fields.values()) if (state.editor?.fullscreenMode) state.editor.fullscreen(false);
    });
    function start() { document.querySelectorAll('textarea[data-cms-editor]').forEach(mount); window.FengImageFields?.mount({open:fieldPicker}); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
    window.addEventListener('pageshow', updateFrame);
    window.addEventListener('pagehide', event => {
        try { window.frameElement?.classList.remove('cms-editor-fullscreen-frame'); } catch (_) {}
        if (!event.persisted) { for (const state of fields.values()) if (state.editor) K.remove(state.textarea); fields.clear(); }
    });
    window.FengEditor = {sync, upload};
}());
