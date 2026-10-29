import TurndownService from 'turndown';
import {gfm} from 'turndown-plugin-gfm';
import MarkdownIt from 'markdown-it';
const turndown=new TurndownService({headingStyle:'atx',codeBlockStyle:'fenced',bulletListMarker:'-'});
turndown.use(gfm);
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
function checkResources(before,after){
 for(const [selector,attr,name] of [['img','src','图片'],['a[href]','href','链接']]){
  if(JSON.stringify(resources(before,selector,attr))!==JSON.stringify(resources(after,selector,attr)))throw Error(name+'在转换中发生变化，已停止处理，请人工检查原始 HTML。');
 }
}
function checkContent(before,after){checkResources(before,after);const text=html=>(parse(html).textContent||'').replace(/\s/g,'');if(text(before)!==text(after))throw Error('正文文字在转换中发生变化，已停止处理，请人工检查。');}
function convert(html){inspect(html);const result=markdown.render(turndown.turndown(html));checkContent(html,result);return result;}
window.FengLegacyConverter={inspect,convert,checkResources,checkContent};
