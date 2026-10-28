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

    private function locked(string $file,callable $work): mixed {
        $root=rtrim(getenv('APP_RUNTIME_PATH') ?: ROOT_PATH.'/var/runtime','/').'/template-versions';
        if(!is_dir($root) && !mkdir($root,0750,true) || !is_dir($root)){
            throw new RuntimeException('模板版本目录不可用。');
        }
        $key=hash('sha256',$file);
        $lock=fopen($root.'/'.$key.'.lock','c');
        if(!$lock){throw new RuntimeException('无法锁定模板文件。');}
        try{
            if(!flock($lock,LOCK_EX)){throw new RuntimeException('无法锁定模板文件。');}
            return $work($root.'/'.$key.'.rev');
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }

    private function version(string $file,string $revision): string {
        $content=file_get_contents($file);
        if($content===false){throw new RuntimeException('无法读取模板文件。');}
        $nonce=is_file($revision)?file_get_contents($revision):'';
        if($nonce===false || is_link($revision)){throw new RuntimeException('模板版本记录不正确。');}
        return hash_hmac('sha256',$nonce.'\0'.$content,KEYS);
    }

    private function revise(string $revision): void {
        $temporary=$revision.'.'.bin2hex(random_bytes(8)).'.tmp';
        try{
            if(file_put_contents($temporary,bin2hex(random_bytes(16)),LOCK_EX)!==32 ||
                !chmod($temporary,0600) || !rename($temporary,$revision)){
                throw new RuntimeException('无法发布模板版本记录。');
            }
        }finally{if(is_file($temporary)){unlink($temporary);}}
    }

    public function read($name,$project): array {
        $file=$this->pathset($name,$project);
        return $this->locked($file,function(string $revision) use ($file,$name): array {
            if(is_link($file) || !is_file($file)){cms_fail(404,'模板不存在。');}
            $content=file_get_contents($file);
            if($content===false){throw new RuntimeException('无法读取模板文件。');}
            return ['name'=>$name,'content'=>$content,
                'file_version'=>$this->version($file,$revision)];
        });
    }

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
        $deleted=$this->locked($file,function(string $revision) use ($file): bool {
            if(is_link($file)){cms_fail(400,'不能操作符号链接模板。');}
            if(!is_file($file)){return false;}
            $this->revise($revision);
            return unlink($file);
        });
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
        $baseline=$array['_file_version']??null;
        if($mode==='1' && (!is_string($baseline) || !preg_match('/^[a-f0-9]{64}$/D',$baseline))){
            cms_fail(400,'模板编辑版本不正确，请刷新页面。');
        }
        return $this->locked($file,function(string $revision) use ($file,$mode,$content,$project,$array,$baseline): array {
            if(is_link($file)){cms_fail(400,'不能操作符号链接模板。');}
            if($mode==='0' && file_exists($file)){return ['status'=>'e','project'=>$project];}
            if($mode==='1'){
                if(!is_file($file)){cms_fail(404,'要修改的模板不存在。');}
                if(!hash_equals($this->version($file,$revision),$baseline)){
                    throw new CmsTemplateEditConflict('模板已被其他管理员修改，请复制当前输入后刷新页面。');
                }
            }
            if(!is_writable(dirname($file))){return ['status'=>'n','project'=>$project,'message'=>'模板目录不可写，请检查容器挂载和目录权限。'];}
            $temporary=tempnam(dirname($file),'.fengcms-');
            if($temporary===false){return ['status'=>'n','project'=>$project,'message'=>'无法创建模板临时文件，请检查目录权限。'];}
            try{
                if(file_put_contents($temporary,$content,LOCK_EX)!==strlen($content) || !chmod($temporary,0644)){
                    return ['status'=>'n','project'=>$project,'message'=>'模板写入失败，请稍后重试。'];
                }
                // Advance the generation before publishing. A crash can cause a
                // harmless conflict, but never lets an old form overwrite a new file.
                $this->revise($revision);
                $saved=$mode==='0' ? @link($temporary,$file) : @rename($temporary,$file);
                if(!$saved && $mode==='0' && file_exists($file)){return ['status'=>'e','project'=>$project];}
                if($saved){error_log('Template saved by admin '.(int)($_SESSION['manage_id']??0).': '.$project.'/'.$array['name']);}
                return ['status'=>$saved?'y':'n','project'=>$project,'message'=>$saved?'保存成功。':'模板保存失败，请检查目录权限。'];
            }finally{if(is_file($temporary)){unlink($temporary);}}
        });
    }
}
final class CmsTemplateEditConflict extends RuntimeException {}
