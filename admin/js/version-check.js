(function () {
    'use strict';

    var api = 'https://api.github.com/repos/fengcms/fengcms/releases/latest';
    var cacheKey = 'fengcms.latestRelease.v1';
    var cacheDuration = 6 * 60 * 60 * 1000;
    var current, latest, status, refresh;
    var pending = false;
    var lastRequest = 0;

    function parts(value) {
        var match = /^v?(\d+)\.(\d+)(?:\.(\d+))?$/.exec(String(value).trim());
        return match ? [Number(match[1]), Number(match[2]), Number(match[3] || 0)] : null;
    }

    function compare(a, b) {
        for (var i = 0; i < 3; i++) {
            if (a[i] !== b[i]) { return a[i] > b[i] ? 1 : -1; }
        }
        return 0;
    }

    function show(tag) {
        var local = parts(current.textContent);
        var remote = parts(tag);
        if (!remote) { throw new Error('Invalid release tag'); }
        latest.textContent = tag;
        if (!local) {
            status.textContent = '当前版本号无法比较，请查看发布记录。';
        } else if (compare(remote, local) > 0) {
            status.textContent = '发现新版本，请查看发布记录并手动升级。';
        } else if (compare(remote, local) === 0) {
            status.textContent = '当前已是最新正式版。';
        } else {
            status.textContent = '当前版本高于 GitHub 最新正式版。';
        }
    }

    function readCache() {
        try {
            var cached = JSON.parse(window.localStorage.getItem(cacheKey));
            if (cached && typeof cached.time === 'number' && Date.now() - cached.time < cacheDuration && parts(cached.tag)) {
                return cached.tag;
            }
        } catch (e) { /* Private browsing can disable storage. */ }
        return null;
    }

    function writeCache(tag) {
        try { window.localStorage.setItem(cacheKey, JSON.stringify({tag: tag, time: Date.now()})); }
        catch (e) { /* The version check still works without storage. */ }
    }

    function finish(message, detail) {
        if (message) { latest.textContent = message; }
        if (detail) { status.textContent = detail; }
        pending = false;
        refresh.disabled = false;
    }

    function check(force) {
        if (pending) { return; }
        if (!force) {
            var cached = readCache();
            if (cached) { show(cached); return; }
        }
        if (force && Date.now() - lastRequest < 60000) {
            status.textContent = '刚刚查询过，请稍后重试。';
            return;
        }
        pending = true;
        lastRequest = Date.now();
        refresh.disabled = true;
        latest.textContent = '正在查询 GitHub…';
        status.textContent = '请稍候';

        var xhr = new XMLHttpRequest();
        xhr.timeout = 8000;
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4 || !pending) { return; }
            if (xhr.status === 404) {
                finish('尚无正式版本', 'GitHub 尚未发布正式版，请查看发布记录。');
                return;
            }
            if (xhr.status !== 200) {
                finish('查询失败', '无法读取 GitHub 版本，请查看发布记录。');
                return;
            }
            try {
                var release = JSON.parse(xhr.responseText);
                if (!release || release.draft || release.prerelease || !parts(release.tag_name)) {
                    throw new Error('Invalid release');
                }
                show(release.tag_name);
                writeCache(release.tag_name);
                finish();
            } catch (e) {
                finish('查询失败', 'GitHub 返回的版本信息无效，请查看发布记录。');
            }
        };
        xhr.onerror = xhr.ontimeout = function () {
            if (pending) { finish('查询失败', '无法连接 GitHub，请检查网络或打开发布记录。'); }
        };
        try {
            xhr.open('GET', api, true);
            xhr.send();
        } catch (e) {
            finish('查询失败', '无法连接 GitHub，请检查网络或打开发布记录。');
        }
    }

    $(function () {
        current = document.getElementById('current-version');
        latest = document.getElementById('version');
        status = document.getElementById('version-status');
        refresh = document.getElementById('version-refresh');
        if (!current || !latest || !status || !refresh) { return; }
        refresh.onclick = function () { check(true); };
        check(false);
    });
}());
