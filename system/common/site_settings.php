<?php
/** Coordinates the database settings row with the runtime URL-mode file. */
final class CmsSiteSettings
{
    private static function root(): string
    {
        $root=rtrim(getenv('APP_RUNTIME_PATH') ?: dirname(__DIR__,2).'/var/runtime','/');
        if(!is_dir($root) && !mkdir($root,0750,true) || !is_dir($root)){
            throw new RuntimeException('站点运行目录不可用。');
        }
        return $root;
    }

    private static function locked(callable $work): mixed
    {
        $handle=fopen(self::root().'/.site-settings.lock','c');
        if(!$handle){throw new RuntimeException('无法锁定站点设置。');}
        try{
            if(!flock($handle,LOCK_EX)){throw new RuntimeException('无法锁定站点设置。');}
            return $work();
        }finally{flock($handle,LOCK_UN);fclose($handle);}
    }

    private static function connection(): mysqli
    {
        mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
        $db=new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME);
        $db->set_charset('utf8mb4');
        return $db;
    }

    private static function table(): string
    {
        if(!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,62}$/D',DB_PREFIX)){
            throw new RuntimeException('站点表前缀不正确。');
        }
        return '`'.DB_PREFIX.'base`';
    }

    private static function row(mysqli $db): array
    {
        $rows=$db->query('SELECT * FROM '.self::table().' LIMIT 2')->fetch_all(MYSQLI_ASSOC);
        if(count($rows)!==1){throw new CmsSiteSettingsConflict('网站基本信息必须恰好有一行，保存已取消。');}
        return $rows[0];
    }

    private static function siteBytes(): ?string
    {
        $path=self::root().'/site.json';
        if(is_link($path)){throw new RuntimeException('站点设置文件不能是符号链接。');}
        if(!is_file($path)){return null;}
        $data=file_get_contents($path);
        if($data===false){throw new RuntimeException('无法读取站点设置文件。');}
        return $data;
    }

    private static function version(array $row,?string $file): string
    {
        return hash_hmac('sha256',serialize([$row,$file]),KEYS);
    }

    private static function publish(string $path,string $bytes): void
    {
        $temporary=$path.'.'.bin2hex(random_bytes(8)).'.tmp';
        try{
            if(@file_put_contents($temporary,$bytes,LOCK_EX)!==strlen($bytes) ||
                !@chmod($temporary,0600) || !@rename($temporary,$path)){
                throw new RuntimeException('站点设置文件发布失败。');
            }
        }finally{if(is_file($temporary)){unlink($temporary);}}
    }

    private static function update(mysqli $db,array $row): void
    {
        $sets=[];$values=[];
        foreach($row as $column=>$value){
            if(!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D',$column)){throw new RuntimeException('站点设置列名不正确。');}
            $sets[]='`'.$column.'`=?';$values[]=$value;
        }
        $db->execute_query('UPDATE '.self::table().' SET '.implode(',',$sets).' LIMIT 1',$values);
    }

    private static function recoverLocked(mysqli $db): void
    {
        $path=self::root().'/.site-settings-pending.json';
        if(!file_exists($path) && !is_link($path)){return;}
        if(is_link($path) || !is_file($path)){
            throw new RuntimeException('站点设置恢复记录不正确。');
        }
        $state=json_decode((string)file_get_contents($path),true);
        if(!is_array($state) || ($state['database']??null)!==DB_NAME ||
            !is_array($state['old_row']??null) || !is_array($state['new_row']??null) ||
            !array_key_exists('old_file',$state) || !array_key_exists('new_file',$state)){
            throw new RuntimeException('站点设置恢复记录不正确。');
        }
        $oldFile=$state['old_file']===null?null:base64_decode((string)$state['old_file'],true);
        $newFile=base64_decode((string)$state['new_file'],true);
        if($oldFile===false || $newFile===false){throw new RuntimeException('站点设置恢复记录不正确。');}
        $current=self::row($db);$currentFile=self::siteBytes();
        if(($current!==$state['old_row'] && $current!==$state['new_row']) ||
            ($currentFile!==$oldFile && $currentFile!==$newFile)){
            throw new RuntimeException('站点设置已被其他操作修改，停止自动恢复。');
        }
        if($current!==$state['old_row']){self::update($db,$state['old_row']);}
        if($currentFile!==$oldFile){
            if($oldFile===null){if(!unlink(self::root().'/site.json')){throw new RuntimeException('无法恢复站点设置文件。');}}
            else{self::publish(self::root().'/site.json',$oldFile);}
        }
        if(!unlink($path)){throw new RuntimeException('无法清理站点设置恢复记录。');}
    }

    public static function recoverPending(): void
    {
        $path=self::root().'/.site-settings-pending.json';
        if(!file_exists($path) && !is_link($path)){return;}
        self::locked(function(): void {
            $db=self::connection();
            try{self::recoverLocked($db);}finally{$db->close();}
        });
    }

    public static function snapshot(): array
    {
        return self::locked(function(): array {
            $db=self::connection();
            try{
                self::recoverLocked($db);
                $row=self::row($db);
                $row['_settings_version']=self::version($row,self::siteBytes());
                return $row;
            }finally{$db->close();}
        });
    }

    public static function save(array $values,string $urlType,string $baseline): void
    {
        self::locked(function() use ($values,$urlType,$baseline): void {
            $db=self::connection();
            try{
                self::recoverLocked($db);
                $old=self::row($db);$oldFile=self::siteBytes();
                if(!hash_equals(self::version($old,$oldFile),$baseline)){
                    throw new CmsSiteSettingsConflict('网站设置已被其他管理员修改，请复制当前输入后刷新页面。');
                }
                $new=array_replace($old,$values,['time'=>(string)time()]);
                $newFile=json_encode(['url_type'=>(int)$urlType,'revision'=>bin2hex(random_bytes(16))],JSON_THROW_ON_ERROR);
                $journal=self::root().'/.site-settings-pending.json';
                self::publish($journal,json_encode([
                    'database'=>DB_NAME,'old_row'=>$old,'new_row'=>$new,
                    'old_file'=>$oldFile===null?null:base64_encode($oldFile),
                    'new_file'=>base64_encode($newFile),
                ],JSON_THROW_ON_ERROR));
                try{
                    self::update($db,$new);
                    self::publish(self::root().'/site.json',$newFile);
                    if(!unlink($journal)){throw new RuntimeException('无法完成站点设置发布。');}
                }catch(Throwable $e){
                    try{self::recoverLocked($db);}catch(Throwable $recovery){
                        error_log('Site settings recovery failed: '.$recovery);
                        throw new RuntimeException('站点设置恢复未完成，请暂停修改并检查运行目录。',0,$e);
                    }
                    throw $e;
                }
            }finally{$db->close();}
        });
    }
}

final class CmsSiteSettingsConflict extends RuntimeException {}
