<?php
require_once dirname(__DIR__, 4).'/system/common/security.php';
cms_session_start();
cms_admin_required();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$dir = $_GET['dir'] ?? '';
if (!is_string($dir) || !in_array($dir, ['', 'image', 'file', 'media'], true)) {
    cms_fail(400, '目录不正确。');
}
$templates = $dir === '';
$root = dirname(__DIR__, 4).($templates ? '/template' : '/upload/'.$dir);
$path = $_GET['path'] ?? '';
if (!is_string($path)) { cms_fail(400, '路径不正确。'); }
$base = realpath($root);
if (!$base) { echo json_encode(['moveup_dir_path' => '', 'current_dir_path' => '', 'current_url' => '/upload/'.$dir.'/', 'total_count' => 0, 'file_list' => []]); exit; }
$current = $path === '' ? $base : cms_path_within($base, $path);
if (!is_dir($current)) { cms_fail(400, '目录不正确。'); }
$relative = ltrim(substr($current, strlen($base)), '/');
$relative = $relative === '' ? '' : $relative.'/';
$list = [];
foreach (new DirectoryIterator($current) as $file) {
    if ($file->isDot() || $file->isLink() || str_starts_with($file->getFilename(), '.')) { continue; }
    $ext = strtolower($file->getExtension());
    if (!$file->isDir() && !in_array($ext, $templates ? ['html'] : ['jpg','jpeg','png','gif','bmp','pdf','txt','zip','doc','docx','xls','xlsx','ppt','pptx','mp3','mp4','wav'], true)) { continue; }
    $list[] = [
        'is_dir' => $file->isDir(), 'has_file' => $file->isDir(),
        'filesize' => $file->isDir() ? 0 : $file->getSize(),
        'is_photo' => in_array($ext, ['jpg','jpeg','png','gif','bmp'], true),
        'filetype' => $ext, 'filename' => $file->getFilename(),
        'datetime' => date('Y-m-d H:i:s', $file->getMTime()),
    ];
}
$order = $_GET['order'] ?? 'name';
usort($list, static function ($a, $b) use ($order) {
    if ($a['is_dir'] !== $b['is_dir']) { return $a['is_dir'] ? -1 : 1; }
    return match ($order) {
        'size' => $a['filesize'] <=> $b['filesize'],
        'type' => strcmp($a['filetype'], $b['filetype']),
        default => strcmp($a['filename'], $b['filename']),
    };
});
echo json_encode([
    'moveup_dir_path' => $relative === '' || !str_contains(rtrim($relative, '/'), '/') ? '' : dirname(rtrim($relative, '/')).'/',
    'current_dir_path' => $relative,
    'current_url' => ($templates ? '/' : '/upload/'.$dir.'/').$relative,
    'total_count' => count($list), 'file_list' => $list,
], JSON_UNESCAPED_UNICODE);
