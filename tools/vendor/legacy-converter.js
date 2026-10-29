import TurndownService from 'turndown';
import {gfm} from 'turndown-plugin-gfm';
import MarkdownIt from 'markdown-it';
const turndown=new TurndownService({headingStyle:'atx',codeBlockStyle:'fenced',bulletListMarker:'-'});
turndown.use(gfm);
// Explicit whitespace boundaries keep adjacent CJK emphasis runs valid CommonMark.
turndown.addRule('emphasisBoundaries',{filter:['strong','b','em','i'],replacement:(content,node)=>{
 const marker=['STRONG','B'].includes(node.nodeName)?'**':'*';
 return content.trim()?' '+marker+content.trim()+marker+' ':'';
}});
const markdown=new MarkdownIt({html:false,linkify:false,typographer:false});
function parse(html){const t=document.createElement('template');t.innerHTML=html;return t.content;}
function inspect(html){
 const root=parse(html);
 if(root.querySelector('td[colspan],td[rowspan],th[colspan],th[rowspan],table table'))throw Error('正文包含合并单元格或嵌套表格，请先人工处理这些表格。');
 if(root.querySelector('iframe,video,audio,object,embed,svg'))throw Error('正文包含视频、嵌入对象或 SVG，暂不支持自动优化，请先人工处理。');
 const tables=Array.from(root.querySelectorAll('table'));
 if(tables.some(t=>!t.querySelector('tr') || !t.querySelector('tr').querySelector('th')))throw Error('正文包含没有表头的表格，暂不支持自动优化，请先补充表头。');
}
function resources(html,selector,attr){return Array.from(parse(html).querySelectorAll(selector),n=>n.getAttribute(attr));}
function hasInlineImages(html){return resources(html,'img','src').some(src=>/^data:/i.test((src||'').trim()));}
function checkImages(before,after,replacements=new Map()){
 if(JSON.stringify(resources(before,'img','src').map(src=>replacements.has(src)?replacements.get(src):src))!==JSON.stringify(resources(after,'img','src')))throw Error('图片在转换中发生变化，已停止处理，请人工检查原始 HTML。');
}
function checkResources(before,after){
 checkImages(before,after);
 // wangEditor serializes linked images as img[data-href], rather than a > img.
 const links=html=>Array.from(parse(html).querySelectorAll('a[href],img[data-href]')).filter(node=>
  node.tagName==='A' ? node.textContent.trim() || node.querySelector('img') : !node.closest('a[href]')
 ).map(node=>node.getAttribute(node.tagName==='A'?'href':'data-href')).filter(value=>value&&value.trim());
 if(JSON.stringify(links(before))!==JSON.stringify(links(after)))throw Error('链接在转换中发生变化，已停止处理，请人工检查原始 HTML。');
}
function checkContent(before,after){checkResources(before,after);const text=html=>(parse(html).textContent||'').replace(/\s/g,'');if(text(before)!==text(after))throw Error('正文文字在转换中发生变化，已停止处理，请人工检查。');}
function convert(html){inspect(html);const result=markdown.render(turndown.turndown(html));checkContent(html,result);return result;}
// Map generated, sanitized image hyperlinks to/from wangEditor's documented HTML shape.
function forEditor(html){const root=parse(html);root.querySelectorAll('img[data-href]').forEach(image=>image.setAttribute('data-href',encodeURIComponent(image.getAttribute('data-href')||'')));root.querySelectorAll('a[href]').forEach(link=>{
 const children=Array.from(link.childNodes),image=link.querySelector('img');
 if(image&&children.every(n=>n===image||(n.nodeType===3&&!n.textContent.trim()))){image.setAttribute('data-href',encodeURIComponent(link.getAttribute('href')));link.replaceWith(image);}
});const t=document.createElement('template');t.content.append(root);return t.innerHTML;}
function forStorage(html){const root=parse(html);root.querySelectorAll('img[data-href]').forEach(image=>{
 const href=image.getAttribute('data-href');image.removeAttribute('data-href');
 if(href&&!image.closest('a[href]')){const link=document.createElement('a');link.setAttribute('href',href);image.replaceWith(link);link.append(image);}
});const t=document.createElement('template');t.content.append(root);return t.innerHTML;}
window.FengLegacyConverter={inspect,convert,checkImages,hasInlineImages,checkResources,checkContent,forEditor,forStorage};
