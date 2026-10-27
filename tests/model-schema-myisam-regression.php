<?php
if(PHP_SAPI!=='cli' || !preg_match('/^fengcms_schema_qa_[a-f0-9]{12}$/D',getenv('DB_NAME')?:'')){
    exit("Refusing model schema test outside its isolated QA database.\n");
}
$errorLog='/tmp/'.(getenv('DB_NAME')?:'fengcms-schema').'-errors.log';
ini_set('error_log',$errorLog);
require dirname(__DIR__).'/tools/migrate.php';
require dirname(__DIR__).'/system/common/install_schema.php';
$db=migration_connection();
cms_install_schema($db,'f_');
migrate_model_schema($db,'f_');
$db->query('CREATE TABLE f_dev_meta (id int PRIMARY KEY) ENGINE=InnoDB');
$db->query('INSERT INTO f_dev_meta VALUES (1)');
foreach(['module','module_field','classify'] as $table){
    $db->query('ALTER TABLE `f_'.$table.'` ENGINE=MyISAM');
}
define('TPL_INCLUDE',true);
define('ROOT_PATH',dirname(__DIR__));
define('SYSTEM_PATH',ROOT_PATH.'/system/');
define('MODEL_PATH',ROOT_PATH.'/admin/app/model/');
define('DRIVER_PATH',ROOT_PATH.'/system/driver/');
define('DB_TYPE','mysqli');
define('DB_HOST',getenv('DB_HOST'));
define('DB_USER',getenv('DB_USER'));
define('DB_PASS',getenv('DB_PASS'));
define('DB_NAME',getenv('DB_NAME'));
define('DB_PREFIX','f_');
require ROOT_PATH.'/system/core/database.php';
require ROOT_PATH.'/system/core/model.php';
require ROOT_PATH.'/system/common/function.php';
require ROOT_PATH.'/admin/app/model/contentWrite.php';
final class SchemaTestHttpError extends RuntimeException {
    public function __construct(public int $httpStatus,string $message){parent::__construct($message);}
}
function cms_fail(int $status,string $message): never {throw new SchemaTestHttpError($status,$message);}
function check_schema(bool $condition,string $message): void {
    if(!$condition){throw new RuntimeException('FAIL: '.$message);}
    echo 'PASS '.$message."\n";
}
$project='qa_'.bin2hex(random_bytes(5));
$triggers=[];
try{
    $created=M('modulemanage')->save(['project'=>$project,'name'=>'MyISAM 结构回归']);
    $moduleId=(int)($created['id']??0);
    check_schema(($created['status']??'')==='y' && $moduleId>0,'MyISAM metadata model creation');
    $fieldData=['project'=>$project,'module_id'=>(string)$moduleId,'name'=>'f_fault','type'=>'17','aliases'=>'故障字段'];
    $triggers[]='qa_field_add';
    $db->query("CREATE TRIGGER qa_field_add BEFORE INSERT ON f_module_field FOR EACH ROW
        BEGIN IF NEW.module_id=$moduleId AND NEW.name='f_fault'
            THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='schema QA'; END IF; END");
    $failed=M('fieldmanage')->save($fieldData);
    check_schema(($failed['status']??'')==='p' &&
        $db->query("SHOW COLUMNS FROM `f_$project` LIKE 'f_fault'")->num_rows===1 &&
        (int)$db->execute_query('SELECT COUNT(*) FROM f_model_schema_operation WHERE project=?',[$project])->fetch_row()[0]===1,
        'MyISAM field metadata failure keeps a resumable column journal');
    $db->query('DROP TRIGGER qa_field_add');
    $db->query("ALTER TABLE `f_$project` MODIFY COLUMN f_fault text NULL");
    $changedColumn=false;
    try{M('fieldmanage')->save($fieldData);}
    catch(SchemaTestHttpError $e){$changedColumn=$e->httpStatus===409;}
    check_schema($changedColumn,'MyISAM field retry refuses a changed physical definition');
    $db->query("ALTER TABLE `f_$project` MODIFY COLUMN f_fault varchar(255) NULL");
    $field=M('fieldmanage')->save($fieldData);
    $fieldId=(int)($field['id']??0);
    check_schema(($field['status']??'')==='y' && $fieldId>0 &&
        (int)$db->execute_query('SELECT COUNT(*) FROM f_module_field WHERE id=?',[$fieldId])->fetch_row()[0]===1,
        'MyISAM field creation resumes without duplicate column');
    $db->query("INSERT INTO `f_$project` (title,f_fault) VALUES ('row','original-value')");
    $triggers[]='qa_field_del';
    $db->query("CREATE TRIGGER qa_field_del BEFORE DELETE ON f_module_field FOR EACH ROW
        BEGIN IF OLD.id=$fieldId THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='schema QA'; END IF; END");
    $failed=M('fieldmanage')->deleteField($fieldId,$project);
    check_schema(($failed['status']??'')==='p' &&
        $db->query("SHOW COLUMNS FROM `f_$project` LIKE 'f_fault'")->num_rows===0 &&
        $db->query('SELECT id FROM f_module_field WHERE id='.$fieldId)->num_rows===1,
        'MyISAM field deletion fault reports dropped physical column');
    $db->query('DROP TRIGGER qa_field_del');
    $db->query("ALTER TABLE `f_$project` ADD COLUMN f_fault varchar(255) NULL");
    $db->query("UPDATE `f_$project` SET f_fault='new-value'");
    $recreatedColumn=false;
    try{M('fieldmanage')->deleteField($fieldId,$project);}
    catch(SchemaTestHttpError $e){$recreatedColumn=$e->httpStatus===409;}
    check_schema($recreatedColumn &&
        $db->query("SELECT f_fault FROM `f_$project` LIMIT 1")->fetch_row()[0]==='new-value',
        'MyISAM field deletion retry preserves a recreated column');
    $db->query("ALTER TABLE `f_$project` DROP COLUMN f_fault");
    $deleted=M('fieldmanage')->deleteField($fieldId,$project);
    check_schema(($deleted['status']??'')==='y' &&
        $db->query('SELECT id FROM f_module_field WHERE id='.$fieldId)->num_rows===0,
        'MyISAM field deletion retry clears stale metadata');
    $db->query("ALTER TABLE `f_$project` ADD COLUMN attrib_j varchar(255) NULL");
    $db->query("UPDATE `f_$project` SET attrib_j='legacy-value'");
    $collision=false;
    try{M('fieldmanage')->systemsave(['project'=>$project,'record'=>['2','3']]);}
    catch(SchemaTestHttpError $e){$collision=$e->httpStatus===409;}
    check_schema($collision && $db->query("SHOW COLUMNS FROM `f_$project` LIKE 'classid'")->num_rows===0 &&
        $db->query("SELECT attrib_j FROM `f_$project` LIMIT 1")->fetch_row()[0]==='legacy-value',
        'MyISAM system batch rejects composite collision before partial DDL');
    $db->query("ALTER TABLE `f_$project` DROP COLUMN attrib_j");
    $system=M('fieldmanage')->systemsave(['project'=>$project,'record'=>['2','3']]);
    check_schema(($system['status']??'')==='y' &&
        $db->query("SHOW COLUMNS FROM `f_$project` LIKE 'attrib_h'")->num_rows===1,
        'MyISAM system batch succeeds after conflict removal');
    $db->execute_query("INSERT INTO f_classify (name,type,project,classid) VALUES ('held','module',?,0)",[$project]);
    $blocked=false;
    try{CmsModelSchema::withLock($project,fn(mysqli $lockDb)=>
        ContentWrite::categoryLocked($project,fn()=>CmsModelSchema::deleteModel($lockDb,$moduleId,$project)));}
    catch(SchemaTestHttpError $e){$blocked=$e->httpStatus===409;}
    check_schema($blocked && $db->query("SHOW TABLES LIKE 'f_$project'")->num_rows===1,
        'MyISAM referenced model is rejected before DROP');
    $db->execute_query('DELETE FROM f_classify WHERE project=?',[$project]);
    $db->execute_query('INSERT INTO f_content_recycle_state (project,record_id,previous_status) VALUES (?,?,?)',[$project,1,1]);
    $triggers[]='qa_module_del';
    $db->query("CREATE TRIGGER qa_module_del BEFORE DELETE ON f_module FOR EACH ROW
        BEGIN IF OLD.id=$moduleId THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='schema QA'; END IF; END");
    $partial=false;
    try{CmsModelSchema::withLock($project,fn(mysqli $lockDb)=>
        ContentWrite::categoryLocked($project,fn()=>CmsModelSchema::deleteModel($lockDb,$moduleId,$project)));}
    catch(SchemaTestHttpError $e){$partial=$e->httpStatus===409;}
    check_schema($partial && $db->query("SHOW TABLES LIKE 'f_$project'")->num_rows===0 &&
        $db->query('SELECT id FROM f_module WHERE id='.$moduleId)->num_rows===1,
        'MyISAM model deletion fault retains a resumable identity journal');
    $_GET['project']=$project;
    $blockedSave=$blockedBatch=$blockedDirect=false;
    try{M('module')->save(['title'=>'stale content'],$project);}
    catch(SchemaTestHttpError $e){$blockedSave=$e->httpStatus===409;}
    try{M('module')->batchsave(['batch'=>'stale content|https://example.test/']);}
    catch(SchemaTestHttpError $e){$blockedBatch=$e->httpStatus===409;}
    try{(new ContentWrite($project))->edit(1,[],str_repeat('a',64));}
    catch(SchemaTestHttpError $e){$blockedDirect=$e->httpStatus===409;}
    check_schema($blockedSave && $blockedBatch && $blockedDirect &&
        (int)$db->query('SELECT status FROM f_module WHERE id='.$moduleId)->fetch_row()[0]===-2,
        'MyISAM incomplete deletion blocks content save, batch and direct writer before missing-table SQL');
    $db->query('DROP TRIGGER qa_module_del');
    CmsModelSchema::withLock($project,fn(mysqli $lockDb)=>
        ContentWrite::categoryLocked($project,fn()=>CmsModelSchema::deleteModel($lockDb,$moduleId,$project)));
    check_schema($db->query('SELECT id FROM f_module WHERE id='.$moduleId)->num_rows===0 &&
        (int)$db->execute_query('SELECT COUNT(*) FROM f_content_recycle_state WHERE project=?',[$project])->fetch_row()[0]===0 &&
        (int)$db->execute_query('SELECT COUNT(*) FROM f_model_schema_operation WHERE project=?',[$project])->fetch_row()[0]===0,
        'MyISAM model deletion retry clears module and content state');
}finally{
    foreach($triggers as $trigger){$db->query('DROP TRIGGER IF EXISTS `'.$trigger.'`');}
    $db->close();
    @unlink($errorLog);
}
