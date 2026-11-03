<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('TPL_INCLUDE',true);
require dirname(__DIR__).'/app/listExpression.php';
set_error_handler(static function($level,$message,$file,$line) { throw new ErrorException($message,0,$level,$file,$line); });
$calls=[];
$category=function($id)use(&$calls){$calls[]=$id;return ' and (classid=7 or classid=8)';};
$cases=[
    ''=>['limit'=>10],
    'w[status=1,7];f[id,title];n[2];s[id,2]'=>['where'=>'status=1 and (classid=7 or classid=8)','field'=>'id,title','limit'=>'2','sort'=>['f'=>'id','s'=>'order','p'=>'asc']],
    'n[2];'=>['limit'=>10],
    's[id]'=>[],
    's[id,1];s[id]'=>['sort'=>['f'=>'id','s'=>'order','p'=>'desc']],
    'w[status=1];w[];f[title];f[]'=>['where'=>'status=1','field'=>'title'],
    'n[0]'=>['limit'=>'0'],
];
foreach($cases as $input=>$expected){
    if(CmsListExpression::parse($input,$category)!==$expected){throw new RuntimeException('Legacy expression changed: '.$input);}
}
if($calls!==['7']){throw new RuntimeException('Unexpected category lookup.');}
echo "PASS legacy list expression defaults, incomplete tokens and category callback remain stable\n";
