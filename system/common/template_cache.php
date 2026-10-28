<?php
/** Shared render lock; cache removal takes the exclusive side of the same lock. */
final class CmsTemplateCache
{
    public static function lock(bool $exclusive): mixed
    {
        $root=rtrim(getenv('APP_RUNTIME_PATH') ?: dirname(__DIR__,2).'/var/runtime','/');
        if(!is_dir($root) && !mkdir($root,0750,true) || !is_dir($root)){
            throw new RuntimeException('模板缓存锁目录不可用。');
        }
        $handle=fopen($root.'/.template-cache.lock','c');
        if(!$handle){throw new RuntimeException('模板缓存锁不可用。');}
        if(!flock($handle,$exclusive?LOCK_EX:LOCK_SH)){
            fclose($handle);throw new RuntimeException('无法锁定模板缓存。');
        }
        return $handle;
    }

    public static function unlock(mixed $handle): void
    {
        flock($handle,LOCK_UN);fclose($handle);
    }

    private static function removeTree(string $path): void
    {
        if(is_link($path)){throw new RuntimeException('编译缓存目录不能是符号链接。');}
        if(!is_dir($path)){return;}
        $items=new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST);
        foreach($items as $item){
            $name=$item->getPathname();
            if($item->isDir() && !$item->isLink()){$ok=rmdir($name);}
            else{$ok=unlink($name);}
            if(!$ok){throw new RuntimeException('编译缓存清理失败。');}
        }
        if(!rmdir($path)){throw new RuntimeException('编译缓存清理失败。');}
    }

    public static function clear(?string $siteRoot=null): int
    {
        $lock=self::lock(true);
        try{
            $siteRoot=$siteRoot??dirname(__DIR__,2);
            $dirs=[$siteRoot.'/app/cache/compile',
                $siteRoot.'/app/cache/compile_mobile',
                $siteRoot.'/admin/app/cache/compile'];
            $removed=0;
            foreach($dirs as $dir){
                if(is_dir($dir) || is_link($dir)){
                    self::removeTree($dir);$removed++;
                }
            }
            return $removed;
        }finally{self::unlock($lock);}
    }
}
