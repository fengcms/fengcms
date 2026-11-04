/* Safe boundaries for the customer editor: inert input, server policy, no reflow. */
(function () {
    'use strict';
    function fragment(html) {
        const template = document.createElement('template');
        template.innerHTML = html;
        return template;
    }
    function hasInlineImages(html) {
        return Array.from(fragment(html).content.querySelectorAll('img')).some(image => /^data:/i.test((image.getAttribute('src') || '').trim()));
    }
    function checkImages(before, after, replacements = new Map(), allowAdditional = false) {
        function inventory(html, replace) {
            return Array.from(fragment(html).content.querySelectorAll('img')).map(image => {
                const src = image.getAttribute('src') || '';
                const href = image.closest('a')?.getAttribute('href') || '';
                return JSON.stringify([replace.get(src) || src, href]);
            }).sort();
        }
        const source = inventory(before, replacements), result = inventory(after, new Map());
        const unmatched = result.slice();
        const missing = source.some(image => { const index = unmatched.indexOf(image); if (index < 0) return true; unmatched.splice(index, 1); return false; });
        if (missing || (!allowAdditional && unmatched.length)) {
            throw Error('处理前后的图片或图片链接不一致，原文已保留，请检查 HTML。');
        }
    }
    async function clean(html, signal) {
        const response = await fetch('/admin/api/richtext.php', {
            method:'POST', credentials:'same-origin', signal,
            headers:{'X-CSRF-Token':document.querySelector('meta[name="csrf-token"]')?.content || ''},
            body:new URLSearchParams({html})
        });
        const result = await response.json();
        if (!response.ok || result.error !== 0 || typeof result.html !== 'string') throw Error(result.message || '正文安全检查失败，请重试。');
        return result.html;
    }
    function validURL(value, image = false) {
        if (typeof value !== 'string' || /[\\\x00-\x20\x7f]/.test(value) || !value || value.startsWith('//')) return false;
        if (/^\/(?!\/)/.test(value)) return true;
        if (!image && /^(?:mailto:|tel:)/i.test(value)) return true;
        if (!/^https?:\/\//i.test(value)) return false;
        try { const url = new URL(value); return !!url.hostname && !url.username && !url.password; } catch (_) { return false; }
    }
    function escape(value) { return String(value).replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char])); }
    window.FengRichtext = {clean, hasInlineImages, checkImages, validURL, escape};
}());
