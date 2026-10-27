// Attach the visitor session token to same-origin jQuery requests, including
// Validform's asynchronous captcha validation.
(function () {
    'use strict';
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (!meta || !window.jQuery) return;
    window.jQuery.ajaxPrefilter(function (options, original, xhr) {
        var url = new URL(options.url, window.location.href);
        if (url.origin === window.location.origin) {
            xhr.setRequestHeader('X-CSRF-Token', meta.content);
        }
    });
}());
