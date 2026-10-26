<?php
if (PHP_SAPI !== 'cli' || !preg_match('/^fengcms_import_qa_[a-f0-9]{12}$/D',getenv('DB_NAME') ?: '')) {
    exit("Refusing import test outside its isolated QA database.\n");
}
require dirname(__DIR__).'/system/common/backup.php';
require dirname(__DIR__).'/system/common/backup_import.php';
define('DB_HOST',getenv('DB_HOST'));
define('DB_USER',getenv('DB_USER'));
define('DB_PASS',getenv('DB_PASS'));
define('DB_NAME',getenv('DB_NAME'));
define('DBBACKUP_PATH',rtrim(getenv('FENGCMS_BACKUP_PATH'),'/').'/');
$db=CmsBackupImport::connection();
$backupId=null;
try {
    $db->query('CREATE TABLE f_a (id int PRIMARY KEY, name varchar(50), payload blob) ENGINE=MyISAM');
    $db->query('CREATE TABLE f_b (id int PRIMARY KEY, note text NULL) ENGINE=InnoDB');
    $db->query('CREATE VIEW f_v AS SELECT id,name FROM f_a');
    $binary="\x00\xff\x0a<?php";
    $db->execute_query('INSERT INTO f_a VALUES (?,?,?)',[1,'中文😀',$binary]);
    $db->execute_query('INSERT INTO f_b VALUES (?,?)',[1,null]);
    $backup=CmsBackup::create(); $backupId=$backup['id'];
    $path=DBBACKUP_PATH.$backupId.'.sql';
    $import=CmsBackupImport::parse($path);
    CmsBackupImport::verify($db,$import);
    $db->execute_query('UPDATE f_a SET name=? WHERE id=1',['changed']);
    $restored=CmsBackupImport::restore($db,$import);
    $row=$db->query('SELECT name,payload FROM f_a WHERE id=1')->fetch_assoc();
    if ($restored['tables']!==2 || $restored['rows']!==2 || $row['name']!=='中文😀' || $row['payload']!==$binary ||
        $db->query('SELECT name FROM f_v WHERE id=1')->fetch_row()[0]!=='中文😀' ||
        $db->query('SELECT note FROM f_b WHERE id=1')->fetch_row()[0]!==null) {
        throw new RuntimeException('Backup rows, view, NULL, Unicode or binary data were not restored.');
    }
    $broken=$import;
    $broken['tables']['f_b']['rows'][]=$broken['tables']['f_b']['rows'][0];
    $broken['rows']++;
    try { CmsBackupImport::restore($db,$broken); throw new RuntimeException('Duplicate row was accepted.'); }
    catch (mysqli_sql_exception) {}
    $db->query("SET SESSION sql_mode='STRICT_TRANS_TABLES'");
    $overlong=$import;
    $overlong['tables']['f_a']['rows'][0]['values'][1]=str_repeat('x',51);
    try { CmsBackupImport::restore($db,$overlong); throw new RuntimeException('Overlong value was silently truncated.'); }
    catch (mysqli_sql_exception) {}
    if ($db->query('SELECT name FROM f_a WHERE id=1')->fetch_row()[0]!=='中文😀' ||
        (int)$db->query('SELECT COUNT(*) FROM f_b')->fetch_row()[0]!==1) {
        throw new RuntimeException('Failed import changed live rows.');
    }
    $objects=$db->query('SHOW FULL TABLES');
    while ($object=$objects->fetch_row()) {
        if (str_starts_with($object[0],'cmsimp_') || str_starts_with($object[0],'cmsold_')) {
            throw new RuntimeException('Import left a staging table.');
        }
    }
    $malicious=tempnam('/tmp','fengcms-import-');
    try {
        file_put_contents($malicious,file_get_contents($path)."DROP TABLE `f_a`;\n");
        try { CmsBackupImport::parse($malicious); throw new RuntimeException('Trailing SQL was accepted.'); }
        catch (InvalidArgumentException) {}
    } finally { unlink($malicious); }
    echo "PASS isolated database import: two engines, view, NULL, binary, Unicode, failed swap, strict mode and injected SQL\n";
} finally {
    $db->close();
    if ($backupId !== null) { CmsBackup::delete($backupId); }
    $guard=DBBACKUP_PATH.'.backup.lock';
    if (is_file($guard)) { unlink($guard); }
    @rmdir(DBBACKUP_PATH);
}
