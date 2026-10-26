(function () {
    'use strict';
    function ready() {
        var body = document.body;
        var absolute = Number(body.getAttribute('data-session-expires')) * 1000;
        var idle = Number(body.getAttribute('data-session-idle')) * 1000;
        if (!absolute || !idle) return;
        var notice = document.createElement('div');
        notice.setAttribute('role', 'status');
        notice.style.cssText = 'display:none;position:fixed;z-index:9999;top:6px;left:50%;transform:translateX(-50%);padding:10px 16px;background:#fff3cd;border:1px solid #e2bd68;border-radius:5px;color:#593d00;box-shadow:0 2px 8px #0003;font-size:14px';
        notice.textContent = '后台登录即将过期，请先保存正在编辑的内容，再重新登录。';
        body.appendChild(notice);
        var frame = document.getElementById('ifmain');
        if (frame) frame.addEventListener('load', function () {
            // Loading an authenticated page refreshes the server-side idle timestamp.
            idle = Date.now() + 3600000;
        });
        function check() {
            var now = Date.now();
            notice.style.display = Math.min(absolute - now, idle - now) <= 300000 ? 'block' : 'none';
        }
        check();
        window.setInterval(check, 30000);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready);
    else ready();
}());
