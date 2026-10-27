<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('TPL_INCLUDE', true);
class model {}
require dirname(__DIR__).'/admin/app/model/classifyModel.php';

$rows = [
    ['id'=>1,'classid'=>0,'name'=>'root'],
    ['id'=>4,'classid'=>0,'name'=>'sibling'],
    ['id'=>2,'classid'=>1,'name'=>'child'],
    ['id'=>3,'classid'=>2,'name'=>'nested'],
    ['id'=>5,'classid'=>999,'name'=>'orphan'],
    ['id'=>6,'classid'=>7,'name'=>'cycle-a'],
    ['id'=>7,'classid'=>6,'name'=>'cycle-b'],
];
$model = new classifyModel();
$tree = $model->classifytree($rows);
if (count($tree)!==2 || $tree[0]['id']!==1 || $tree[1]['id']!==4 ||
    $tree[0]['classid'][0]['id']!==2 || $tree[0]['classid'][0]['classid'][0]['id']!==3 ||
    $tree[1]['classid']!==[]) {
    throw new RuntimeException('Admin category tree changed ordering or hierarchy.');
}
echo "PASS admin category tree keeps input order and hierarchy\n";

$cycle = $model->classifytree($rows, 7);
if (count($cycle)!==1 || $cycle[0]['id']!==6 || $cycle[0]['classid'][0]['id']!==7 ||
    $cycle[0]['classid'][0]['classid']!==[]) {
    throw new RuntimeException('Admin category tree did not stop at a cycle.');
}
echo "PASS admin category tree terminates on a cycle\n";
