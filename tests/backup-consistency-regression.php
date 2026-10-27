<?php
if(PHP_SAPI!=='cli' || !preg_match('/^fengcms_backup_qa_[a-f0-9]{12}$/D',getenv('DB_NAME')?:'')){
    exit("Refusing backup consistency test outside its isolated QA database.\n");
}
foreach(['HOST','USER','PASS','NAME'] as $part){define('DB_'.$part,getenv('DB_'.$part));}
define('DB_PREFIX','f_');
define('DBBACKUP_PATH',rtrim(getenv('FENGCMS_BACKUP_PATH'),'/').'/');
require dirname(__DIR__).'/system/common/backup.php';
require dirname(__DIR__).'/system/common/backup_import.php';
function check_backup(bool $ok,string $name): void {
    if(!$ok){throw new RuntimeException('FAIL '.$name);}
    echo 'PASS '.$name."\n";flush();
}
function wait_backup(callable $probe,int $milliseconds=12000): mixed {
    $deadline=microtime(true)+$milliseconds/1000;
    do{$result=$probe();if($result){return $result;}usleep(20000);}while(microtime(true)<$deadline);
    throw new RuntimeException('Timed out waiting for isolated backup worker');
}
function start_backup(string $action,?string $id=null,?string $signal=null): array {
    $tag=bin2hex(random_bytes(8));$file='/tmp/fengcms-backup-signal-'.$tag;
    $command=[PHP_BINARY,__DIR__.'/backup-consistency-worker.php',$action];
    if($id!==null){$command[]=$id;$command[]=$signal??$file;}
    $proc=proc_open($command,[0=>['pipe','r'],1=>['file',$file.'.out','w'],2=>['file',$file.'.err','w']],$pipes);
    if(!is_resource($proc)){throw new RuntimeException('Cannot start backup worker');}
    fclose($pipes[0]);return ['proc'=>$proc,'file'=>$file];
}
function finish_backup(array $job,int $milliseconds=12000): array {
    wait_backup(fn()=>!proc_get_status($job['proc'])['running'],$milliseconds);
    $result=json_decode(file_get_contents($job['file'].'.out'),true);
    $error=file_get_contents($job['file'].'.err');
    proc_close($job['proc']);
    foreach(['.out','.err','.ready','.go'] as $extension){@unlink($job['file'].$extension);}
    if(!is_array($result)){throw new RuntimeException('Worker returned invalid JSON: '.$error);}
    return $result;
}
function waiting_backup(mysqli $db,string $starts): ?array {
    foreach($db->query('SHOW FULL PROCESSLIST')->fetch_all(MYSQLI_ASSOC) as $row){
        if(($row['db']??'')===DB_NAME && str_starts_with($row['Info']??'',$starts)){return $row;}
    }
    return null;
}
$db=CmsBackupImport::connection();$hold=CmsBackupImport::connection();
try{
    $db->query('CREATE TABLE f_dev_meta (id int PRIMARY KEY) ENGINE=InnoDB');
    $db->query('INSERT INTO f_dev_meta VALUES (1)');
    $db->query('CREATE TABLE f_a (id int PRIMARY KEY,value varchar(50)) ENGINE=MyISAM');
    $db->query('CREATE TABLE f_b (id int PRIMARY KEY,value varchar(50)) ENGINE=InnoDB');
    $db->query('CREATE VIEW f_v AS SELECT id,value FROM f_a');
    $db->query("INSERT INTO f_a VALUES (1,'original')");
    $db->query("INSERT INTO f_b VALUES (1,'original')");

    // DB04: the SQL lock wait itself must end within the configured budget.
    $hold->query('LOCK TABLES f_a WRITE');$job=start_backup('backup');
    wait_backup(fn()=>waiting_backup($db,'LOCK TABLES'));
    $started=microtime(true);$blocked=finish_backup($job,10000);
    check_backup(!$blocked['ok'] && microtime(true)-$started<8 &&
        str_contains($blocked['error']??'','读锁'),'blocked backup releases its lock within database wait budget');
    $hold->query('UNLOCK TABLES');
    $retry=CmsBackup::create();CmsBackup::delete($retry['id']);
    check_backup(!CmsBackupImport::pending(),'backup can start after lock wait failure');

    // DB01: direct DBA DDL may bypass the CMS gate, so compare definitions after locking.
    foreach(['f_a'=>'MyISAM','f_b'=>'InnoDB'] as $table=>$engine){
        $hold->query('LOCK TABLES '.$table.' WRITE');$job=start_backup('backup');
        wait_backup(fn()=>waiting_backup($db,'LOCK TABLES'));
        $hold->query('ALTER TABLE '.$table.' ADD COLUMN added varchar(30) NULL');
        $hold->query("UPDATE $table SET added='changed' WHERE id=1");
        $hold->query('UNLOCK TABLES');
        $changed=finish_backup($job);
        check_backup(!$changed['ok'] && str_contains($changed['error']??'','结构'),
            'schema change before read lock cannot publish a mixed '.$engine.' backup');
        $db->query('ALTER TABLE '.$table.' DROP COLUMN added');
    }
    foreach(['table','view'] as $object){
        $hold->query('LOCK TABLES f_a WRITE');$job=start_backup('backup');
        wait_backup(fn()=>waiting_backup($db,'LOCK TABLES'));
        if($object==='table'){$db->query('CREATE TABLE f_added (id int PRIMARY KEY) ENGINE=InnoDB');}
        else{$db->query('CREATE VIEW f_added AS SELECT 1 AS id');}
        $hold->query('UNLOCK TABLES');
        $changed=finish_backup($job);
        check_backup(!$changed['ok'],'new '.$object.' before read lock cannot enter an incomplete backup');
        $db->query('DROP '.strtoupper($object).' f_added');
    }

    // DB02: after the protective backup, both ordinary writes and DDL must fail before swap.
    $source=CmsBackup::create();$sourceId=$source['id'];
    $job=start_backup('restore',$sourceId);
    $ready=wait_backup(fn()=>is_file($job['file'].'.ready')?json_decode(file_get_contents($job['file'].'.ready'),true):null);
    $writer=start_backup('write');$writerResult=finish_backup($writer,9000);
    $writerB=start_backup('write_b');$writerBResult=finish_backup($writerB,9000);
    $schema=start_backup('schema');$schemaResult=finish_backup($schema,9000);
    check_backup(!$writerResult['ok'] && !$writerBResult['ok'] && !$schemaResult['ok'] &&
        $db->query('SHOW COLUMNS FROM f_a LIKE "late_schema"')->num_rows===0,
        'recovery barrier rejects writes and schema changes after protective backup');
    file_put_contents($job['file'].'.go','1');
    $restored=finish_backup($job,20000);
    check_backup($restored['ok'] && $db->query('SELECT value FROM f_a WHERE id=1')->fetch_row()[0]==='original',
        'recovery finishes without acknowledging a lost write');

    // DB03: kill the import connection while its atomic rename waits on metadata.
    $job=start_backup('restore',$sourceId);
    $ready=wait_backup(fn()=>is_file($job['file'].'.ready')?json_decode(file_get_contents($job['file'].'.ready'),true):null);
    $hold->begin_transaction();$hold->query('SELECT id FROM f_b WHERE id=1');
    file_put_contents($job['file'].'.go','1');
    wait_backup(fn()=>waiting_backup($db,'RENAME TABLE'),12000);
    $db->query('KILL CONNECTION '.(int)$ready['connection']);
    $failed=finish_backup($job,12000);$hold->rollback();
    check_backup(!$failed['ok'] && CmsBackupImport::pending() &&
        $db->query('SELECT value FROM f_a WHERE id=1')->fetch_row()[0]==='original',
        'lost import connection preserves live rows and a recovery record');
    $backupRefused=false;$previewRefused=false;
    try{CmsBackup::create();}catch(RuntimeException){$backupRefused=true;}
    [$sourcePath]=CmsBackup::file($sourceId);$import=CmsBackupImport::parse($sourcePath);
    try{CmsBackupImport::verify($db,$import);}catch(RuntimeException){$previewRefused=true;}
    check_backup($backupRefused && $previewRefused,'backup and preview reject a pending recovery');
    CmsBackupImport::recover();
    check_backup(!CmsBackupImport::pending() && CmsBackupImport::verify($db,$import)['row_count']===3,
        'explicit recovery clears only journal-owned temporary tables');
    CmsBackupImport::restore($db,$import);
    check_backup($db->query('SELECT value FROM f_b WHERE id=1')->fetch_row()[0]==='original',
        'original backup can be retried after recovery');
    echo "Backup consistency regressions passed.\n";
}finally{
    try{$hold->rollback();}catch(Throwable){}
    try{$hold->query('UNLOCK TABLES');}catch(Throwable){}
    $hold->close();$db->close();
    foreach(CmsBackup::all() as $meta){try{CmsBackup::delete($meta['id']);}catch(Throwable){}}
    @unlink(DBBACKUP_PATH.'.backup.lock');
    @rmdir(DBBACKUP_PATH);
}
