<?php
// Server-side HTML policy. Never rely on an editor's client-side filtering.
function cms_clean_html($html): string
{
    if (!is_string($html)) { cms_fail(400, '富文本内容必须是字符串。'); }
    if (strlen($html) > 2 * 1024 * 1024) { cms_fail(413, '正文不能超过 2MB。'); }
    static $purifier;
    if (!$purifier) {
        require_once dirname(__DIR__).'/vendor/htmlpurifier/library/HTMLPurifier.auto.php';
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('HTML.Allowed', 'p[style],br,h1[style],h2[style],h3[style],h4[style],h5[style],h6[style],strong,b,em,i,u,s,strike,span[style],blockquote,ul,ol[start],li,a[href|title],img[src|alt|width|height|style],table[width|style],thead,tbody,tfoot,tr,th[colspan|rowspan|style],td[colspan|rowspan|style]');
        $config->set('CSS.AllowedProperties', ['color','background-color','font-size','font-weight','font-style','text-decoration','text-align','width','height','border','border-collapse','padding']);
        $config->set('URI.AllowedSchemes', ['http'=>true, 'https'=>true, 'mailto'=>true, 'tel'=>true]);
        $config->set('HTML.SafeIframe', false);
        $config->set('Attr.EnableID', false);
        // No writable vendor tree or serialized definitions are needed.
        $config->set('Cache.DefinitionImpl', null);
        $purifier = new HTMLPurifier($config);
    }
    return $purifier->purify($html);
}

// Writes must fail before purification can discard an unresolved inline image.
// Public rendering keeps its existing sanitization policy.
function cms_clean_html_for_save($html): string
{
    if (!is_string($html)) { cms_fail(400, '富文本内容必须是字符串。'); }
    if (strlen($html) > 2 * 1024 * 1024) { cms_fail(413, '正文不能超过 2MB。'); }
    require_once dirname(__DIR__).'/vendor/htmlpurifier/library/HTMLPurifier.auto.php';
    $config = HTMLPurifier_Config::createDefault();
    $lexer = HTMLPurifier_Lexer::create($config);
    $tokens = $lexer->tokenizeHTML($html, $config, new HTMLPurifier_Context());
    foreach ($tokens as $token) {
        if (($token->name ?? null) === 'img' && preg_match('/^data:/i', trim($token->attr['src'] ?? ''))) {
            cms_fail(400, '正文包含尚未转存的内嵌图片，请先使用“优化历史富文本”完成图片转存后再保存。');
        }
    }
    return cms_clean_html($html);
}

// Legacy/imported copyright is untrusted until it has passed the current
// HTML policy. The content hash invalidates the optional cache on every edit.
function cms_clean_copyright($value): string
{
    $html=(string)($value ?? '');
    if (strlen($html)>2*1024*1024) {
        error_log('Oversized base.copyright omitted from public rendering.');
        return '';
    }
    static $lastInput=null, $lastResult=null;
    if ($lastInput===$html) { return $lastResult; }
    $key='fengcms:copyright:purifier-v1:'.hash('sha256',$html);
    $hit=false;
    $clean=function_exists('apcu_fetch') ? apcu_fetch($key,$hit) : false;
    if (!$hit || !is_string($clean)) {
        $clean=cms_clean_html($html);
        if (function_exists('apcu_store')) { apcu_store($key,$clean,3600); }
    }
    $lastInput=$html;
    return $lastResult=$clean;
}

function cms_richtext_fields(string $project): array
{
    $rows = D('view_field')->field('name,type_quote')->where(['project'=>$project])->getall();
    $names = [];
    foreach ($rows as $row) {
        if (in_array($row['type_quote'], ['content.html','htmledit.html'], true)) { $names[]=$row['name']; }
    }
    return $names;
}

function cms_clean_record(array $record, string $project): array
{
    foreach (cms_richtext_fields($project) as $name) {
        if (array_key_exists($name, $record)) { $record[$name] = cms_clean_html_for_save($record[$name]); }
    }
    return $record;
}
