<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
//
defined('TPL_INCLUDE') or die( 'Restricted access'); 

class tplmanageModel extends model{


    private const ROOTS = ['tpl'=>'template','css'=>'css','scrap'=>'template/scrap','inc'=>'template/inc','show'=>'template/show'];

    public function files($type='tpl'){
        if(!is_string($type) || !isset(self::ROOTS[$type])){cms_fail(400,'模板分类不正确。');}
        $root=ROOT_PATH.'/'.self::ROOTS[$type];
        $extension=$type==='css'?'css':'html';
        $files=[];
        foreach(scandir($root,SCANDIR_SORT_NONE) as $file){
            if($file[0]!=='.' && !is_link($root.'/'.$file) && is_file($root.'/'.$file) && pathinfo($file,PATHINFO_EXTENSION)===$extension){$files[]=$file;}
        }
        usort($files,static fn($a,$b)=>strnatcasecmp($a,$b) ?: strcmp($a,$b));
        return $files;
    }

    public function pathset($name, $type){
        if(!is_string($name) || strlen($name)>200 || !preg_match('/^[\p{L}\p{N}_][\p{L}\p{N}_. -]*$/uD',$name) || str_contains($name,'..') || $name!==trim($name) || !is_string($type) || !isset(self::ROOTS[$type])){
            cms_fail(400,'模板名称或分类不正确，请使用中文、英文、数字、下划线或连字符。');
        }
        $root=ROOT_PATH.'/'.self::ROOTS[$type];
        $relative=$name.($type==='css'?'.css':'.html');
        if(is_link($root.'/'.$relative)){cms_fail(400,'不能操作符号链接模板。');}
        return cms_path_within($root,$relative,false);
    }

    public function deletefile($array){
        if(!cms_template_write_enabled()){cms_fail(403,'此站点已关闭后台模板写入。');}
        $file=$this->pathset($array['name']??null,$array['project']??null);
        $deleted=is_file($file) && unlink($file);
        if($deleted){error_log('Template deleted by admin '.(int)($_SESSION['manage_id']??0).': '.$array['project'].'/'.$array['name']);}
        return $deleted;
    }

    public function save($array){
        if(!cms_template_write_enabled()){cms_fail(403,'此站点已关闭后台模板写入。');}
        $project=$array['project']??null;
        $file=$this->pathset($array['name']??null,$project);
        $mode=$array['type']??null;
        $content=$array['content']??null;
        if(!in_array($mode,['0','1'],true) || !is_string($content)){cms_fail(400,'模板保存参数不正确。');}
        if(strlen($content)>2*1024*1024){cms_fail(413,'模板内容不能超过 2MB。');}
        if($mode==='0' && file_exists($file)){return ['status'=>'e','project'=>$project];}
        if($mode==='1' && !is_file($file)){cms_fail(404,'要修改的模板不存在。');}
        if(!is_writable(dirname($file))){return ['status'=>'n','project'=>$project,'message'=>'模板目录不可写，请检查容器挂载和目录权限。'];}
        $temporary=tempnam(dirname($file),'.fengcms-');
        if($temporary===false){return ['status'=>'n','project'=>$project,'message'=>'无法创建模板临时文件，请检查目录权限。'];}
        try{
            if(file_put_contents($temporary,$content,LOCK_EX)!==strlen($content) || !chmod($temporary,0644)){
                return ['status'=>'n','project'=>$project,'message'=>'模板写入失败，请稍后重试。'];
            }
            // Publish a complete file. New templates never overwrite an existing name.
            $saved=$mode==='0' ? @link($temporary,$file) : @rename($temporary,$file);
            if(!$saved && $mode==='0' && file_exists($file)){return ['status'=>'e','project'=>$project];}
            if($saved){error_log('Template saved by admin '.(int)($_SESSION['manage_id']??0).': '.$project.'/'.$array['name']);}
            return ['status'=>$saved?'y':'n','project'=>$project,'message'=>$saved?'保存成功。':'模板保存失败，请检查目录权限。'];
        }finally{
            if(is_file($temporary)){unlink($temporary);}
        }
    }
}
