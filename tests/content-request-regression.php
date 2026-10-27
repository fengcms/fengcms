<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

define('TPL_INCLUDE', true);
require dirname(__DIR__).'/app/contentRequest.php';

$request=CmsContentRequest::fromInput(['project'=>'article','id'=>'0007']);
if ($request->project!=='article' || $request->id!==7) {
    throw new RuntimeException('Valid content request changed.');
}
foreach ([
    ['project'=>'','id'=>'1'],
    ['project'=>'../article','id'=>'1'],
    ['project'=>str_repeat('a',31),'id'=>'1'],
    ['project'=>['article'],'id'=>'1'],
    ['project'=>'article','id'=>['1']],
    ['project'=>'article','id'=>'0'],
    ['project'=>'article','id'=>'-1'],
    ['project'=>'article','id'=>'1abc'],
    ['project'=>'article','id'=>'2147483648'],
] as $input) {
    try {
        CmsContentRequest::fromInput($input);
    } catch (InvalidArgumentException) {
        continue;
    }
    throw new RuntimeException('Malformed content request was accepted.');
}
echo "PASS content route parameters are explicit and bounded\n";

class Controller
{
    public static bool $throwOnDisplay=false;
    public static mixed $seenClassid=null;

    public function display($path,$data='')
    {
        self::$seenClassid=$_GET['classid']??null;
        if (self::$throwOnDisplay) { throw new RuntimeException('Template failed.'); }
        return $path;
    }
}
require dirname(__DIR__).'/app/controller/contentController.php';
$controller=(new ReflectionClass(contentController::class))->newInstanceWithoutConstructor();
(new ReflectionProperty(contentController::class,'content'))->setValue($controller,
    ['classid'=>'42','template'=>'article_content.html']);

$_GET=['project'=>'article','id'=>'7'];
if ($controller->index()!=='article_content.html' || Controller::$seenClassid!=='42' ||
    array_key_exists('classid',$_GET)) {
    throw new RuntimeException('Content rendering leaked a new classid.');
}
$_GET['classid']='99';
if ($controller->index()!=='article_content.html' || Controller::$seenClassid!=='42' ||
    $_GET['classid']!=='99') {
    throw new RuntimeException('Content rendering changed an existing classid.');
}
Controller::$throwOnDisplay=true;
try {
    $controller->index();
    throw new RuntimeException('Expected template failure.');
} catch (RuntimeException $error) {
    if ($error->getMessage()!=='Template failed.') { throw $error; }
}
if ($_GET['classid']!=='99') {
    throw new RuntimeException('Template failure leaked the content classid.');
}
echo "PASS content classid is scoped to rendering, including template failure\n";
