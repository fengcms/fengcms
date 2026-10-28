const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');

const source = fs.readFileSync('template/message_add.html', 'utf8');
const script = source.match(/<script type="text\/javascript">([\s\S]*?)<\/script>/)?.[1];
assert.ok(script, 'message form inline script exists');
let settings;
let clickHandler;
let confirmed = false;
let requests = 0;
let alerts = [];
let href = '';
const elements = {
    '.form': { Validform(options) {
        settings = options;
        return { ajaxPost() { if (options.beforeSubmit() !== false) requests++; } };
    } },
    '#save': { click(handler) { clickHandler = handler; } },
    '#vcode': { val() { return this; } },
    '#captcha-image': { attr() { return this; } },
};
function $(selector) {
    if (typeof selector === 'function') { selector(); return; }
    return elements[selector];
}
$.Hidemsg = () => {};
const context = {
    $, confirm: () => confirmed,
    alert: (message) => alerts.push(message),
    location: { set href(value) { href = value; } },
    Math, Date,
};
vm.runInNewContext(script.replaceAll("{curl('message')}", '/?controller=message'), context);
assert.equal(typeof settings.beforeSubmit, 'function');
assert.equal(typeof clickHandler, 'function');
clickHandler();
assert.equal(requests, 0, 'canceling confirmation sends no save request');
confirmed = true;
clickHandler();
assert.equal(requests, 1, 'confirming submits once');
settings.callback({ status: 'y' });
assert.equal(href, '/?controller=message');
settings.callback({ status: 'c' });
assert.match(alerts.at(-1), /验证码/);
console.log('Message form confirmation regression checks passed.');
