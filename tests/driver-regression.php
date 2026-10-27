<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/tools/migrate.php';
$guard=migration_connection();
if(!$guard->query("SHOW TABLES LIKE 'f_dev_meta'")->num_rows){exit("Refusing mutation tests outside the disposable development database.\n");}
define('TPL_INCLUDE',1);
require dirname(__DIR__).'/system/core/database.php';
require dirname(__DIR__).'/system/driver/db_mysqli.php';
$driver=new db_mysqli(getenv('DB_HOST'),getenv('DB_USER'),getenv('DB_PASS'),getenv('DB_NAME'));
$table='qa_driver_'.bin2hex(random_bytes(6));
$passed=0;
function check_affected(int $expected,string $message): void{
    global $driver,$passed;
    if($driver->affected_rows()!==$expected){throw new RuntimeException('FAIL '.$message.'; got '.$driver->affected_rows());}
    $passed++;echo 'PASS '.$message.PHP_EOL;
}
try{
    $driver->query("CREATE TEMPORARY TABLE `$table` (id int PRIMARY KEY, value int) ENGINE=InnoDB");
    $driver->query("INSERT INTO `$table` (id,value) VALUES (?,?)",[1,10]);check_affected(1,'prepared INSERT reports one affected row');
    $driver->query("UPDATE `$table` SET value=? WHERE id=?",[20,1]);check_affected(1,'prepared UPDATE reports changed row');
    $driver->query("UPDATE `$table` SET value=? WHERE id=?",[20,1]);check_affected(0,'prepared UPDATE reports zero unchanged rows');
    $driver->query("UPDATE `$table` SET value=? WHERE id=?",[20,999]);check_affected(0,'prepared UPDATE reports zero unmatched rows');
    $driver->query("DELETE FROM `$table` WHERE id=?",[1]);check_affected(1,'prepared DELETE reports deleted row');
    $driver->query("DELETE FROM `$table` WHERE id=?",[1]);check_affected(0,'prepared DELETE reports zero unmatched rows');
    $driver->query("INSERT INTO `$table` (id,value) VALUES (2,30)");check_affected(1,'plain INSERT reports affected row');
    $driver->query("UPDATE `$table` SET value=30 WHERE id=2");check_affected(0,'plain UPDATE reports zero unchanged rows');
    $driver->query("DELETE FROM `$table` WHERE id=2");check_affected(1,'plain DELETE reports deleted row');
}finally{$driver->query("DROP TEMPORARY TABLE IF EXISTS `$table`");$driver->close();}

$sharedA=new db_mysqli(getenv('DB_HOST'),getenv('DB_USER'),getenv('DB_PASS'),getenv('DB_NAME'),true);
$sharedB=new db_mysqli(getenv('DB_HOST'),getenv('DB_USER'),getenv('DB_PASS'),getenv('DB_NAME'),true);
$isolated=new db_mysqli(getenv('DB_HOST'),getenv('DB_USER'),getenv('DB_PASS'),getenv('DB_NAME'));
$sharedTable='qa_shared_'.bin2hex(random_bytes(6));
try{
    $idA=(int)$sharedA->assoc($sharedA->query('SELECT CONNECTION_ID() AS id'))['id'];
    $idB=(int)$sharedB->assoc($sharedB->query('SELECT CONNECTION_ID() AS id'))['id'];
    $idIsolated=(int)$isolated->assoc($isolated->query('SELECT CONNECTION_ID() AS id'))['id'];
    if($idA!==$idB || $idA===$idIsolated){throw new RuntimeException('Model connections are not request-local and isolated as expected.');}
    $passed++;
    echo "PASS ordinary model drivers share a connection while direct drivers stay isolated\n";

    $sharedA->query("CREATE TEMPORARY TABLE `$sharedTable` (id INT AUTO_INCREMENT PRIMARY KEY, value INT) ENGINE=InnoDB");
    $sharedA->query("INSERT INTO `$sharedTable` (value) VALUES (?)",[7]);
    $insertId=$sharedA->insert_id();
    $affected=$sharedA->affected_rows();
    $row=$sharedB->assoc($sharedB->query("SELECT value FROM `$sharedTable` WHERE id=?",[$insertId]));
    if($insertId!==1 || $affected!==1 || (int)$row['value']!==7 ||
        $sharedA->insert_id()!==$insertId || $sharedA->affected_rows()!==$affected){
        throw new RuntimeException('Shared drivers leaked per-query result state.');
    }
    $passed++;
    echo "PASS shared drivers keep query results and insert state independent\n";

    $sharedA->close();
    $sharedC=new db_mysqli(getenv('DB_HOST'),getenv('DB_USER'),getenv('DB_PASS'),getenv('DB_NAME'),true);
    $idC=(int)$sharedC->assoc($sharedC->query('SELECT CONNECTION_ID() AS id'))['id'];
    if($idC!==$idA || (int)$sharedB->assoc($sharedB->query("SELECT value FROM `$sharedTable`"))['value']!==7){
        throw new RuntimeException('Closing one model detached the pooled connection.');
    }
    $passed++;
    echo "PASS closing one model leaves other models connected\n";
}finally{
    $sharedB->query("DROP TEMPORARY TABLE IF EXISTS `$sharedTable`");
    $sharedA->close();$sharedB->close();$isolated->close();
    if(isset($sharedC)){$sharedC->close();}
}
echo "All $passed driver regression checks passed.\n";
