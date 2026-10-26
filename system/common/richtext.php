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
        if (array_key_exists($name, $record)) { $record[$name] = cms_clean_html($record[$name]); }
    }
    return $record;
}
