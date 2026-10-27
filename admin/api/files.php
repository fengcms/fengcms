<?php
require_once dirname(__DIR__, 2).'/system/common/security.php';
cms_session_start();
cms_admin_required();
cms_session_release();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
$dir = $_GET['dir'] ?? '';
$path = $_GET['path'] ?? '';
$search = $_GET['q'] ?? '';
$order = $_GET['order'] ?? 'time';
$direction = $_GET['direction'] ?? 'desc';
$page = $_GET['page'] ?? '1';
$limit = $_GET['limit'] ?? '24';
if (!is_string($dir) || !in_array($dir, ['', 'image', 'file', 'media'], true)) { cms_fail(400, '目录不正确。'); }
if (!is_string($path) || !is_string($search) || strlen($search)>200 || !mb_check_encoding($search, 'UTF-8')) { cms_fail(400, '路径或搜索条件不正确。'); }
if (!is_string($order) || !in_array($order, ['name','time','size','type'], true) || !is_string($direction) || !in_array($direction,['asc','desc'],true)) { cms_fail(400, '排序条件不正确。'); }
if (!is_string($page) || !ctype_digit($page) || (int)$page<1 || (int)$page>100000 || !is_string($limit) || !ctype_digit($limit) || (int)$limit<1 || (int)$limit>60) { cms_fail(400, '分页条件不正确。'); }
$page=(int)$page; $limit=(int)$limit;
$templates = $dir === '';
$root = dirname(__DIR__, 2).($templates ? '/template' : '/upload/'.$dir);
$base = realpath($root);
if (!$base) {
    if ($path!=='') { cms_fail(400, '目录不存在。'); }
    echo json_encode(['moveup_dir_path'=>'','current_dir_path'=>'','current_url'=>'/upload/'.$dir.'/','total_count'=>0,'page'=>1,'page_count'=>1,'limit'=>$limit,'file_list'=>[]]); exit;
}
$current = $path === '' ? $base : cms_path_within($base, $path);
if (!is_dir($current)) { cms_fail(400, '目录不正确。'); }
$relative = ltrim(substr($current, strlen($base)), '/');
$relative = $relative === '' ? '' : $relative.'/';
$list = []; $scanned=0;
$allowed = $templates ? ['html'] : ($dir==='image' ? ['jpg','jpeg','png','gif','bmp'] : ['jpg','jpeg','png','gif','bmp','pdf','txt','zip','doc','docx','xls','xlsx','ppt','pptx','mp3','mp4','wav']);
foreach (new DirectoryIterator($current) as $file) {
    if (++$scanned > 10000) { cms_fail(413, '此目录文件过多，请先按子目录整理。'); }
    if ($file->isDot() || $file->isLink() || str_starts_with($file->getFilename(), '.')) { continue; }
    $ext = strtolower($file->getExtension());
    if (!$file->isDir() && !in_array($ext, $allowed, true)) { continue; }
    if ($search!=='' && mb_stripos($file->getFilename(),$search,0,'UTF-8')===false) { continue; }
    $list[] = [
        'is_dir'=>$file->isDir(), 'has_file'=>$file->isDir(),
        'filesize'=>$file->isDir()?0:$file->getSize(),
        'is_photo'=>!$file->isDir() && in_array($ext,['jpg','jpeg','png','gif','bmp'],true),
        'filetype'=>$ext, 'filename'=>$file->getFilename(),
        'mtime'=>$file->getMTime(), 'datetime'=>date('Y-m-d H:i:s',$file->getMTime()),
    ];
}
usort($list, static function ($a,$b) use ($order,$direction) {
    if ($a['is_dir']!==$b['is_dir']) { return $a['is_dir']?-1:1; }
    $result=match($order) {'size'=>$a['filesize']<=>$b['filesize'], 'time'=>$a['mtime']<=>$b['mtime'], 'type'=>strcmp($a['filetype'],$b['filetype']), default=>strnatcasecmp($a['filename'],$b['filename'])};
    if (!$result) { $result=strcmp($a['filename'],$b['filename']); }
    return $direction==='desc' ? -$result : $result;
});
$total=count($list); $pages=max(1,(int)ceil($total/$limit)); $page=min($page,$pages);
$list=array_slice($list,($page-1)*$limit,$limit);
foreach ($list as &$item) {
    $item['width']=null; $item['height']=null;
    if ($item['is_photo'] && $item['filesize']<=10*1024*1024) {
        $size=@getimagesize($current.'/'.$item['filename']);
        if ($size) { $item['width']=$size[0]; $item['height']=$size[1]; }
    }
    // Template paths are filesystem-relative; resource URLs encode each segment.
    $item['url']=$templates ? '/'.$relative.$item['filename'] : '/upload/'.$dir.'/'.implode('/',array_map('rawurlencode',explode('/',$relative.$item['filename'])));
    $item['thumbnail_url']=$dir==='image' && $item['is_photo'] ? '/admin/api/thumbnail.php?path='.rawurlencode($relative.$item['filename']) : null;
}
unset($item);
echo json_encode([
    'moveup_dir_path'=>$relative==='' || !str_contains(rtrim($relative,'/'),'/') ? '' : dirname(rtrim($relative,'/')).'/',
    'current_dir_path'=>$relative, 'current_url'=>($templates?'/':'/upload/'.$dir.'/').$relative,
    'total_count'=>$total, 'page'=>$page, 'page_count'=>$pages, 'limit'=>$limit, 'file_list'=>$list,
], JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
