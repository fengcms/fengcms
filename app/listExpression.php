<?php
defined('TPL_INCLUDE') or die('Restricted access');

/**
 * 解析受信任模板的 l() 表达式；不读取请求、不访问数据库、不保存跨调用状态。
 * 这里只解释旧语法，不是 SQL 清洗器；w/f/s/n 不得直接接受外部请求值。
 */
final class CmsListExpression
{
    /**
     * @param string $expression 原 w[]/f[]/s[]/n[] 分号分隔语法
     * @param callable(string):?string $categoryCondition 栏目子树条件的兼容适配
     * @return array{where?:string,field?:string,limit?:string|int,sort?:array{f:string,s:string,p:string}}
     */
    public static function parse(string $expression,callable $categoryCondition): array
    {
        $options=[];
        foreach (explode(';',$expression) as $token) {
            $value=substr(substr($token,2),0,-1);
            switch (substr($token,0,1)) {
                case 'w':
                    $parts=explode(',',$value);
                    if ($parts[1]??'') { $options['where']=$parts[0].$categoryCondition($parts[1]); }
                    elseif ($value) { $options['where']=$value; }
                    break;
                case 'n':
                    $options['limit']=$value;
                    break;
                case 'f':
                    if ($value) { $options['field']=$value; }
                    break;
                case 's':
                    $parts=explode(',',$value);
                    if (($parts[1]??null)==1 || ($parts[1]??null)==2) {
                        $options['sort']=['f'=>$parts[0],'s'=>'order','p'=>$parts[1]==1?'desc':'asc'];
                    }
                    break;
                default:
                    // 保留旧语法的空 token 行为（例如末尾分号会恢复默认数量）。
                    $options['limit']=10;
            }
        }
        return $options;
    }
}
