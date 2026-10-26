'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../admin/js/version-check.js'), 'utf8');

function page(currentVersion, response, stored) {
    const elements = Object.fromEntries(['current-version', 'version', 'version-status', 'version-refresh'].map(id => [id, {textContent: '', disabled: false}]));
    elements['current-version'].textContent = currentVersion;
    const storage = new Map(stored || []);
    const requests = [];
    function XMLHttpRequest() { requests.push(this); }
    XMLHttpRequest.prototype.open = function (method, url) {
        assert.equal(method, 'GET');
        assert.equal(url, 'https://api.github.com/repos/fengcms/fengcms/releases/latest');
    };
    XMLHttpRequest.prototype.send = function () {
        this.status = response.status;
        this.responseText = JSON.stringify(response.body);
        this.readyState = 4;
        this.onreadystatechange();
    };
    const context = {
        XMLHttpRequest,
        Date: {now: () => 1000000},
        document: {getElementById: id => elements[id]},
        window: {localStorage: {
            getItem: key => storage.get(key) || null,
            setItem: (key, value) => storage.set(key, value)
        }},
        $: callback => callback()
    };
    vm.runInNewContext(source, context);
    return {elements, requests, storage};
}

let result = page('v1.35', {status: 200, body: {tag_name: 'v1.36', draft: false, prerelease: false}});
assert.equal(result.elements.version.textContent, 'v1.36');
assert.match(result.elements['version-status'].textContent, /发现新版本/);
assert.equal(result.elements['version-refresh'].disabled, false);
assert.equal(result.requests.length, 1);
assert.equal(JSON.parse(result.storage.get('fengcms.latestRelease.v1')).tag, 'v1.36');

result = page('v1.35', {status: 200, body: {tag_name: 'v1.35', draft: false, prerelease: false}});
assert.match(result.elements['version-status'].textContent, /最新正式版/);

result = page('v1.35', {status: 404});
assert.equal(result.elements.version.textContent, '尚无正式版本');

result = page('v1.35', {status: 200, body: {tag_name: 'v1.36<script>', draft: false, prerelease: false}});
assert.equal(result.elements.version.textContent, '查询失败');

result = page('v1.35', {status: 200, body: {tag_name: 'v1.36', draft: false, prerelease: true}});
assert.equal(result.elements.version.textContent, '查询失败');

result = page('v1.35', {status: 500});
assert.equal(result.elements.version.textContent, '查询失败');

result = page('v1.35', {status: 500}, [['fengcms.latestRelease.v1', JSON.stringify({tag: 'v1.36', time: 999999})]]);
assert.equal(result.elements.version.textContent, 'v1.36');
assert.equal(result.requests.length, 0);

console.log('GitHub 版本查询检查通过：新版本、同版本、无发布、异常内容、预发布、网络错误及缓存。');
