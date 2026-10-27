<?php
/** A per-account upload request can resume after the file move or a lost response. */
final class CmsUploadRequestException extends RuntimeException
{
    public function __construct(public int $httpStatus,string $message){parent::__construct($message);}
}

final class CmsUploadRequest
{
    private static function table(): string
    {
        if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D',DB_PREFIX)){
            throw new LogicException('Invalid database prefix.');
        }
        return '`'.DB_PREFIX.'upload_request`';
    }

    private static function fullPath(string $relative,string $directory): string
    {
        if(!preg_match('~^/upload/'.preg_quote($directory,'~').'/[0-9]{8}/[a-f0-9]{32}\.[a-z0-9]{2,5}$~D',$relative)){
            throw new CmsUploadRequestException(409,'上传恢复路径不正确，请联系管理员。');
        }
        return dirname(__DIR__,2).$relative;
    }

    private static function move(string $temporary,string $relative,string $directory): void
    {
        $target=self::fullPath($relative,$directory);
        $folder=dirname($target);
        if(!is_dir($folder) && !mkdir($folder,0755,true)){
            throw new CmsUploadRequestException(500,'无法创建上传目录。');
        }
        if(is_file($target)){
            throw new CmsUploadRequestException(409,'目标资源已存在，请检查后重试。');
        }
        if(!move_uploaded_file($temporary,$target)){
            throw new CmsUploadRequestException(500,'文件保存失败。');
        }
        chmod($target,0644);
    }

    private static function prune(mysqli $db): void
    {
        if(random_int(1,50)!==1){return;}
        try{
            $db->query('DELETE FROM '.self::table().' WHERE created_at < NOW() - INTERVAL 30 DAY LIMIT 100');
        }catch(Throwable $e){error_log('Upload request cleanup failed: '.$e->getMessage());}
    }

    public static function save(string $temporary,string $originalName,int $size,string $directory,
        string $extension,int $accountId,?string $requestKey): string
    {
        if($requestKey===null){
            $relative='/upload/'.$directory.'/'.date('Ymd').'/'.bin2hex(random_bytes(16)).'.'.$extension;
            self::move($temporary,$relative,$directory);
            return $relative;
        }
        if($accountId<1 || !preg_match('/^[a-f0-9]{32}$/D',$requestKey)){
            throw new CmsUploadRequestException(400,'上传请求标识不正确。');
        }
        $contentHash=hash_file('sha256',$temporary);
        if($contentHash===false){throw new CmsUploadRequestException(500,'无法读取上传文件。');}
        $payloadHash=hash('sha256',$originalName."\0".$size."\0".$contentHash);
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db=new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME);
        $db->set_charset('utf8mb4');
        $lock='fengcms_upload_'.substr(hash('sha256',DB_NAME.':'.$accountId.':'.$requestKey),0,40);
        $locked=false;
        try{
            $locked=(int)$db->execute_query('SELECT GET_LOCK(?,5)',[$lock])->fetch_row()[0]===1;
            if(!$locked){throw new CmsUploadRequestException(409,'同次上传正在处理，请稍后重试。');}
            $row=$db->execute_query('SELECT directory,payload_hash,relative_path,status FROM '.self::table().
                ' WHERE account_id=? AND request_key=?',[$accountId,$requestKey])->fetch_assoc();
            if($row){
                if($row['directory']!==$directory || !hash_equals($row['payload_hash'],$payloadHash)){
                    throw new CmsUploadRequestException(409,'上传请求标识已用于其他文件或目录。');
                }
                $relative=$row['relative_path'];
                if(!in_array($row['status'],['pending','complete'],true)){
                    throw new CmsUploadRequestException(409,'上传请求状态不正确。');
                }
            }else{
                $relative='/upload/'.$directory.'/'.date('Ymd').'/'.bin2hex(random_bytes(16)).'.'.$extension;
                $db->execute_query('INSERT INTO '.self::table().
                    ' (account_id,request_key,directory,payload_hash,relative_path,status) VALUES (?,?,?,?,?,?)',
                    [$accountId,$requestKey,$directory,$payloadHash,$relative,'pending']);
            }
            $target=self::fullPath($relative,$directory);
            if(is_file($target)){
                if(!hash_equals($contentHash,hash_file('sha256',$target)?:'')){
                    throw new CmsUploadRequestException(409,'已保存的资源内容发生变化，请检查后重试。');
                }
            }else{
                self::move($temporary,$relative,$directory);
            }
            $db->execute_query('UPDATE '.self::table().
                ' SET status=?,created_at=NOW() WHERE account_id=? AND request_key=?',
                ['complete',$accountId,$requestKey]);
            self::prune($db);
            return $relative;
        }finally{
            if($locked){try{$db->execute_query('SELECT RELEASE_LOCK(?)',[$lock]);}catch(Throwable $e){error_log('Upload lock release failed: '.$e->getMessage());}}
            $db->close();
        }
    }
}
