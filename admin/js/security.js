(function () {
    'use strict';
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (!meta) return;
    var token = meta.content;
    var writes = {
        manage: ['save', 'quit'], base: ['save'], cache: ['index'],
        module: ['deleteone', 'recoverone', 'batch', 'shift', 'save', 'batchsave'],
        classify: ['order', 'delete', 'emptys', 'batch', 'save'],
        fieldmanage: ['systemsave', 'save', 'enable', 'sort', 'delete'],
        modulemanage: ['disabled', 'delete', 'save', 'elicit', 'loading'],
        message: ['deleteone', 'deletebatch', 'save'], signup: ['deleteone', 'deletebatch', 'save'],
        recover: ['recoverone', 'delete', 'batch'], tplmanage: ['delete', 'save'], dbmanage: ['delete', 'save']
    };
    function isWrite(url) {
        var actions = writes[url.searchParams.get('controller')];
        return actions && actions.indexOf(url.searchParams.get('operate') || 'index') !== -1;
    }
    if (window.jQuery) {
        window.jQuery.ajaxPrefilter(function (options, original, xhr) {
            var url = new URL(options.url, window.location.href);
            if (url.origin !== window.location.origin) return;
            xhr.setRequestHeader('X-CSRF-Token', token);
            if (url.pathname === '/admin/index.php' && isWrite(url)) {
                options.type = 'POST';
            }
        });
    }
    if (window.KindEditor) {
        window.KindEditor.options.extraFileUploadParams = { _csrf: token };
    }
    // Existing delete/logout links submit POST while retaining query parameters
    // used by legacy controllers. Inline confirmation handlers run first.
    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented) return;
        var url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin || url.pathname !== '/admin/index.php' || !isWrite(url)) return;
        event.preventDefault();
        var form = document.createElement('form');
        form.method = 'post';
        form.action = url.href;
        var base = document.querySelector('base[target]');
        form.target = link.target || (base ? base.target : '');
        var input = document.createElement('input');
        input.type = 'hidden'; input.name = '_csrf'; input.value = token;
        form.appendChild(input); document.body.appendChild(form); form.submit();
    });
}());
