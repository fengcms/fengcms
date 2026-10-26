<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$failed = [];
$count = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    $path = $file->getPathname();
    if ($file->getExtension() !== 'php' || str_contains($path, '/.git/') || str_contains($path, '/cache/') || str_contains($path, '/upload/')) { continue; }
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path).' 2>&1', $output, $status);
    $count++;
    if ($status !== 0) { $failed[] = implode("\n", $output); }
    $output = [];
}
echo "PHP ".PHP_VERSION." syntax: $count files, ".count($failed)." failures.\n";
foreach ($failed as $error) { fwrite(STDERR, $error."\n"); }
foreach (['mysqli', 'mbstring', 'gd', 'fileinfo'] as $ext) {
    if (!extension_loaded($ext)) { $failed[] = $ext; fwrite(STDERR, "Missing extension: $ext\n"); }
}
exit($failed ? 1 : 0);
