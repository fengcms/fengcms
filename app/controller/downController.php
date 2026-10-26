<?php
class downController extends Controller
{
    public function index()
    {
        $encoded = $_GET['file'] ?? '';
        $relative = is_string($encoded) ? base64_decode($encoded, true) : false;
        if ($relative === false || !str_starts_with($relative, '/upload/')) {
            cms_fail(400, '文件路径不正确。');
        }
        $file = cms_path_within(ROOT_PATH.'/upload', substr($relative, strlen('/upload/')));
        if (!is_file($file) || !in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)),
            ['jpg','jpeg','png','gif','bmp','pdf','txt','zip','doc','docx','xls','xlsx','ppt','pptx','mp3','mp4','wav'], true)) {
            cms_fail(404, '文件不存在。');
        }
        header('Content-Type: application/octet-stream');
        header('X-Content-Type-Options: nosniff');
        header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode(basename($file)));
        header('Content-Length: '.filesize($file));
        readfile($file);
    }
}
