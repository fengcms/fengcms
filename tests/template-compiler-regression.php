<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

define('TPL_INCLUDE', true);
define('ROOT_PATH', dirname(__DIR__));
define('ABS_PATH', ROOT_PATH);
define('CACHE_PATH', sys_get_temp_dir());
function is_mobile_request(): bool { return false; }
function cms_template_output($value): string { return (string)($value ?? ''); }

require ROOT_PATH.'/system/core/template.php';

$calls = [];
function review_items(string $key): array
{
    global $calls;
    $calls[$key] = ($calls[$key] ?? 0) + 1;
    return match ($key) {
        'empty' => [],
        'nested' => [['a', 'b'], ['c']],
        default => ['a' => 1, 'b' => 2],
    };
}

function render_probe(string $source): string
{
    $file = tempnam(sys_get_temp_dir(), 'fengcms-tpl-');
    if ($file === false) { throw new RuntimeException('Cannot create template probe.'); }
    try {
        file_put_contents($file, $source);
        $compiled = (new ReflectionMethod(template::class, 'parse'))->invoke(null, $file);
        ob_start();
        try { eval('?>'.$compiled); return ob_get_clean(); }
        catch (Throwable $error) { ob_end_clean(); throw $error; }
    } finally { unlink($file); }
}

function check_probe(bool $condition, string $message): void
{
    if (!$condition) { throw new RuntimeException('FAIL '.$message); }
    echo 'PASS '.$message.PHP_EOL;
}

check_probe(render_probe('{loop review_items("plain") $item}{$item}{/loop}') === '12'
    && ($calls['plain'] ?? 0) === 1, 'single-value loop evaluates its source once');
check_probe(render_probe('{loop review_items("keys") $key $item}{$key}{$item}{/loop}') === 'a1b2'
    && ($calls['keys'] ?? 0) === 1, 'key-value loop evaluates its source once');
check_probe(render_probe('before{loop review_items("empty") $item}bad{/loop}after') === 'beforeafter'
    && ($calls['empty'] ?? 0) === 1, 'empty loop evaluates its source once');
check_probe(render_probe('{loop review_items("nested") $row}{loop $row $item}{$item}{/loop}{/loop}') === 'abc'
    && ($calls['nested'] ?? 0) === 1, 'nested loops preserve output and source calls');
check_probe(render_probe('before{loop $missing $item}bad{/loop}after') === 'beforeafter',
    'missing source still skips the loop');
echo "Template compiler regression checks passed.\n";
