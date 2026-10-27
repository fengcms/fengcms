<?php
if(PHP_SAPI!=='cli' || !preg_match('/^fengcms_backup_qa_[a-f0-9]{12}$/D',getenv('DB_NAME')?:'')){
    exit(2);
}
foreach(['HOST','USER','PASS','NAME'] as $part){define('DB_'.$part,getenv('DB_'.$part));}
define('DB_PREFIX','f_');
define('DBBACKUP_PATH',rtrim(getenv('FENGCMS_BACKUP_PATH'),'/').'/');
require dirname(__DIR__).'/system/common/backup.php';
require dirname(__DIR__).'/system/common/backup_import.php';
try{
    $op=$argv[1]??'';
    if($op==='backup'){
        $meta=CmsBackup::create();echo json_encode(['ok'=>true,'id'=>$meta['id']]);
    }elseif($op==='write' || $op==='write_b'){
        CmsDatabaseGate::write(function() use ($op){
            $db=CmsBackupImport::connection();
            try{$db->query("UPDATE ".($op==='write_b'?'f_b':'f_a')." SET value='late-write' WHERE id=1");}
            finally{$db->close();}
        });
        echo json_encode(['ok'=>true]);
    }elseif($op==='schema'){
        CmsDatabaseGate::write(function(){
            $db=CmsBackupImport::connection();
            try{$db->query('ALTER TABLE f_a ADD COLUMN late_schema varchar(20) NULL');}
            finally{$db->close();}
        });
        echo json_encode(['ok'=>true]);
    }elseif($op==='restore'){
        $id=$argv[2]??'';$signal=$argv[3]??'';
        if(!preg_match('/^[a-f0-9]{32}$/D',$id) || !preg_match('~^/tmp/fengcms-backup-signal-[a-f0-9]{16}$~D',$signal)){
            throw new RuntimeException('Worker arguments invalid');
        }
        [$path]=CmsBackup::file($id);$import=CmsBackupImport::parse($path);
        $db=CmsBackupImport::connection();
        try{
            CmsBackupImport::restore($db,$import,function() use ($db,$signal){
                $meta=CmsBackup::create();
                file_put_contents($signal.'.ready',json_encode(['connection'=>$db->thread_id,'auto'=>$meta['id']]));
                $deadline=microtime(true)+20;
                while(!is_file($signal.'.go') && microtime(true)<$deadline){usleep(20000);}
                if(!is_file($signal.'.go')){throw new RuntimeException('Worker release timed out');}
            });
        }finally{$db->close();}
        echo json_encode(['ok'=>true]);
    }else{throw new RuntimeException('Unknown worker operation');}
}catch(Throwable $e){echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);}
