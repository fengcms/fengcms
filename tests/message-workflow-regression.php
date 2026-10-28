<?php
if(PHP_SAPI!=='cli' || !preg_match('/^fengcms_message_qa_[a-f0-9]{12}$/D',getenv('DB_NAME')?:'')){
    exit("Refusing message workflow test outside its isolated QA database.\n");
}
require dirname(__DIR__).'/tools/migrate.php';
$db=migration_connection();
$db->query("CREATE TABLE f_message (
    id int NOT NULL AUTO_INCREMENT PRIMARY KEY,title varchar(255) DEFAULT NULL,name varchar(50) DEFAULT NULL,
    qq varchar(20) DEFAULT NULL,tel varchar(20) DEFAULT NULL,mail varchar(50) DEFAULT NULL,
    content text,reply text,time int DEFAULT NULL,rtime int DEFAULT NULL,status int DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$db->query('CREATE TABLE f_dev_meta (id int PRIMARY KEY) ENGINE=InnoDB');
$db->query('INSERT INTO f_dev_meta VALUES (1)');
migrate_message_workflow($db,'f_');
define('TPL_INCLUDE',true);define('ROOT_PATH',dirname(__DIR__));define('SYSTEM_PATH',ROOT_PATH.'/system/');
define('DRIVER_PATH',SYSTEM_PATH.'/driver/');define('DB_TYPE','mysqli');define('DB_PREFIX','f_');
define('DB_HOST',getenv('DB_HOST'));define('DB_USER',getenv('DB_USER'));define('DB_PASS',getenv('DB_PASS'));
define('DB_NAME',getenv('DB_NAME'));define('KEYS',getenv('APP_KEY'));
require SYSTEM_PATH.'/core/database.php';require SYSTEM_PATH.'/core/model.php';
function D($name,$prefix=''){return new model($name,$prefix);}
final class MessageTestHttpError extends RuntimeException {public function __construct(public int $status,string $message){parent::__construct($message);}}
function cms_fail(int $status,string $message): never {throw new MessageTestHttpError($status,$message);}
require ROOT_PATH.'/admin/app/model/messageModel.php';
function message_check(bool $ok,string $message): void {if(!$ok){throw new RuntimeException('FAIL: '.$message);}echo "PASS $message\n";}
$triggers=[];
try{
    foreach(['InnoDB','MyISAM'] as $engine){
        $db->query('ALTER TABLE f_message ENGINE='.$engine);
        $db->query('DELETE FROM f_message');
        $model=new messageModel();
        $db->query("INSERT INTO f_message(title,name,qq,tel,mail,content,reply,time,rtime,status)
            VALUES('title','','0','','','original','',1,0,0)");
        $id=(int)$db->insert_id;
        $first=$model->reply((string)$id);
        $second=$first;
        $save=['id'=>(string)$id,'_message_version'=>$first['_message_version'],'title'=>$first['title'],
            'name'=>'','qq'=>'0','tel'=>'','mail'=>'','content'=>'original','reply'=>'FIRST', 'status'=>'1'];
        $saved=$model->save($save);
        $secondSave=$save;$secondSave['_message_version']=$second['_message_version'];$secondSave['reply']='STALE';
        $conflict=0;try{$model->save($secondSave);}catch(MessageTestHttpError $error){$conflict=$error->status;}
        message_check(($saved['status']??'')==='y' && $conflict===409 &&
            $db->query('SELECT reply FROM f_message WHERE id='.$id)->fetch_row()[0]==='FIRST',
            $engine.' stale reply form cannot overwrite the saved response');
        $fresh=$model->reply((string)$id);
        $same=['id'=>(string)$id,'_message_version'=>$fresh['_message_version'],'title'=>$fresh['title'],
            'name'=>'','qq'=>'0','tel'=>'','mail'=>'','content'=>'original','reply'=>'FIRST','status'=>'1'];
        message_check(($model->save($same)['status']??'')==='y',$engine.' same-value reply save succeeds');

        $batchIds=[];
        foreach(['a','b','c'] as $suffix){
            $db->query("INSERT INTO f_message(title,content,status) VALUES('batch_{$engine}_{$suffix}','body',0)");
            $batchIds[]=(int)$db->insert_id;
        }
        $trigger='message_fault_'.strtolower($engine);
        $db->query("CREATE TRIGGER `{$trigger}` BEFORE DELETE ON f_message FOR EACH ROW
            BEGIN IF OLD.id={$batchIds[1]} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='message QA fault'; END IF; END");
        $triggers[]=$trigger;
        $partial=$model->batch($batchIds);
        $db->query("DROP TRIGGER `{$trigger}`");array_pop($triggers);
        message_check(($partial['status']??'')==='p' && ($partial['changed']??[])===[$batchIds[0],$batchIds[2]] &&
            ($partial['failed']??[])===[$batchIds[1]],$engine.' batch reports partial progress by ID');
        $retry=$model->batch($batchIds);
        message_check(($retry['status']??'')==='y' && ($retry['already']??[])===[$batchIds[0],$batchIds[2]] &&
            ($retry['changed']??[])===[$batchIds[1]],$engine.' batch retry safely finishes remaining IDs');

        $deleted=$model->reply((string)$id);
        $model->delete($id);
        $deletedSave=0;try{$model->save(['id'=>(string)$id,'_message_version'=>$deleted['_message_version'],
            'title'=>'title','name'=>'','qq'=>'0','tel'=>'','mail'=>'','content'=>'original','reply'=>'late','status'=>'1']);}
        catch(MessageTestHttpError $error){$deletedSave=$error->status;}
        message_check($deletedSave===404,$engine.' save after deletion reports missing record');
    }
    echo "Message workflow isolated InnoDB/MyISAM regressions passed.\n";
}finally{
    foreach($triggers as $trigger){try{$db->query("DROP TRIGGER IF EXISTS `{$trigger}`");}catch(Throwable){}}
}
