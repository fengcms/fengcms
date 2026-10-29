'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const context={window:{},File,Uint8Array,TextEncoder,atob,btoa,DOMException};
vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname,'../admin/js/legacy-inline-images.js'),'utf8'),context);
const {decode}=context.window.FengInlineImages;
(async()=>{
 const uri='data:image/png;base64,AQIDBA==';const decoded=decode(uri);
 assert.equal(decoded.file.type,'image/png');assert.equal(decoded.file.name,'legacy-base64.png');
 assert.deepEqual([...new Uint8Array(await decoded.file.arrayBuffer())],[1,2,3,4]);
 assert.equal(decode('DATA:IMAGE/PNG;base64,AQID\nBA==').cacheKey,decoded.cacheKey);
 for(const value of ['',null,'https://example.com/a.png','data:text/html;base64,AQIDBA==','data:image/svg+xml;base64,AQIDBA==','data:image/png,AQIDBA==','data:image/png;base64,','data:image/png;base64,AQ=IDBA=','data:image/png;base64,AQIDBA','data:image/png;base64,AQIDBB==','data:image/png;base64,@@@@'])assert.throws(()=>decode(value),String(value));
 assert.throws(()=>decode('data:image/png;base64,'+'A'.repeat(Math.ceil(10*1024*1024/3)*4+4)));
 console.log('Base64 解码回归通过：字节、MIME 白名单、编码完整性、别名与大小边界。实际图片内容由上传接口校验。');
})().catch(error=>{console.error(error);process.exitCode=1;});
