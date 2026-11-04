/* Shared authenticated upload transport for editors and resource fields. */
(function () {
    'use strict';
    let pending = 0;
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const uploadKey = () => Array.from(crypto.getRandomValues(new Uint8Array(16)),
        byte=>byte.toString(16).padStart(2,'0')).join('');
    async function upload(file, dir = 'image', onProgress = () => {}, signal, requestKey = uploadKey()) {
        if (file.size <= 0 || file.size > 10 * 1024 * 1024) throw new Error('文件必须大于 0 且不能超过 10MB');
        const data = new FormData();
        data.append('imgFile', file); data.append('_csrf', csrf());data.append('_upload_key',requestKey);
        pending++;
        try {
            return await new Promise((resolve,reject) => {
                const xhr = new XMLHttpRequest();
                const abort=()=>xhr.abort();
                const cleanup=()=>signal?.removeEventListener('abort',abort);
                xhr.open('POST','/admin/api/upload.php?dir='+encodeURIComponent(dir));
                xhr.timeout=120000;
                xhr.upload.onprogress=event=>{if(event.lengthComputable)onProgress(Math.round(event.loaded/event.total*100));};
                xhr.onload=()=>{cleanup();try{const result=JSON.parse(xhr.responseText);
                    if(xhr.status<200 || xhr.status>=300 || result.error!==0)throw new Error(result.message || '上传失败');
                    resolve(result.url);
                }catch(error){reject(error instanceof SyntaxError?new Error('服务器返回异常，请重试'):error);}};
                xhr.onerror=()=>{cleanup();reject(new Error('网络连接失败，请重试'));};
                xhr.ontimeout=()=>{cleanup();reject(new Error('上传超时，请重试'));};
                xhr.onabort=()=>{cleanup();reject(new DOMException('上传已取消','AbortError'));};
                if(signal?.aborted){reject(new DOMException('上传已取消','AbortError'));return;}
                signal?.addEventListener('abort',abort,{once:true});xhr.send(data);
            });
        } finally { pending--; }
    }
    window.FengUpload = {upload, get pending() { return pending; }};
}());
