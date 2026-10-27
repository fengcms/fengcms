<?php
defined('TPL_INCLUDE') or die('Restricted access');
require_once __DIR__.'/database_gate.php';

/** Coordinates schema DDL, imports and content writes for one model project. */
final class CmsModelSchema
{
    private static array $held = [];
    private static array $connections = [];

    public static function connection(): mysqli
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db=new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME);
        $db->set_charset('utf8mb4');
        return $db;
    }

    public static function table(string $name): string
    {
        return model::identifier(DB_PREFIX.$name);
    }

    public static function lockName(string $project): string
    {
        if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,29}$/D',$project)){
            cms_fail(400,'模型名称不正确。');
        }
        return 'fengcms_model_'.substr(hash('sha256',DB_NAME.':'.$project),0,40);
    }

    public static function isHeld(string $project): bool
    {
        return isset(self::$held[self::lockName($project)]);
    }

    public static function heldConnection(string $project): mysqli
    {
        $key=self::lockName($project);
        if(!isset(self::$connections[$key])){throw new LogicException('Model lifecycle lock is required.');}
        return self::$connections[$key];
    }

    public static function withLock(string $project,callable $work): mixed
    {
        if(!CmsDatabaseGate::held()){
            return CmsDatabaseGate::write(fn()=>self::withLock($project,$work));
        }
        $key=self::lockName($project);
        if(isset(self::$held[$key])){
            self::$held[$key]++;
            try{return $work(self::$connections[$key]);}
            finally{self::$held[$key]--;}
        }
        $db=self::connection();
        if((int)$db->execute_query('SELECT GET_LOCK(?,5)',[$key])->fetch_row()[0]!==1){
            $db->close();
            cms_fail(409,'模型结构正在由其他请求处理，请稍后重试。');
        }
        self::$held[$key]=1;
        self::$connections[$key]=$db;
        try{return $work($db);}
        finally{
            unset(self::$held[$key],self::$connections[$key]);
            try{$db->execute_query('SELECT RELEASE_LOCK(?)',[$key]);}finally{$db->close();}
        }
    }

    public static function journal(mysqli $db,string $project,string $action,string $name=''): ?array
    {
        return $db->execute_query('SELECT * FROM '.self::table('model_schema_operation').
            ' WHERE project=? AND action=? AND field_name=?',[$project,$action,$name])->fetch_assoc();
    }

    public static function pending(mysqli $db,string $project): bool
    {
        return (bool)$db->execute_query('SELECT 1 FROM '.self::table('model_schema_operation').
            ' WHERE project=? LIMIT 1',[$project])->fetch_row();
    }

    public static function begin(mysqli $db,string $project,int $moduleId,string $action,string $name='',?int $fieldId=null,string $payload=''): void
    {
        $db->execute_query('INSERT INTO '.self::table('model_schema_operation').
            ' (project,module_id,field_id,action,field_name,stage,payload) VALUES (?,?,?,?,?,?,?)',
            [$project,$moduleId,$fieldId,$action,$name,'prepared',$payload]);
    }

    public static function stage(mysqli $db,string $project,string $action,string $name,string $stage): void
    {
        $db->execute_query('UPDATE '.self::table('model_schema_operation').
            ' SET stage=? WHERE project=? AND action=? AND field_name=?',[$stage,$project,$action,$name]);
    }

    public static function clear(mysqli $db,string $project,string $action,string $name=''): void
    {
        $db->execute_query('DELETE FROM '.self::table('model_schema_operation').
            ' WHERE project=? AND action=? AND field_name=?',[$project,$action,$name]);
    }

    public static function tableExists(mysqli $db,string $project): bool
    {
        return (bool)$db->execute_query('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?',
            [DB_NAME,DB_PREFIX.$project])->fetch_row();
    }

    public static function columnExists(mysqli $db,string $project,string $name): bool
    {
        return (bool)$db->execute_query('SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?',
            [DB_NAME,DB_PREFIX.$project,$name])->fetch_row();
    }

    /** Recheck a request's original model after acquiring its lifecycle lock. */
    public static function assertUsable(mysqli $db,string $project,?int $expectedId): void
    {
        $module=$db->execute_query('SELECT id,status FROM '.self::table('module').' WHERE project=?',
            [$project])->fetch_assoc();
        if($expectedId===null || !$module || (int)$module['id']!==$expectedId){
            cms_fail(409,'模型已变更，请刷新页面后重试。');
        }
        if((int)$module['status']<0 || self::pending($db,$project) || !self::tableExists($db,$project)){
            cms_fail(409,'模型结构操作尚未完成，请稍后重试。');
        }
    }

    /** Must be called under the project's lifecycle lock and the category relation lock. */
    public static function deleteModel(mysqli $db,int $id,string $project): void
    {
        $module=$db->execute_query('SELECT id,type FROM '.self::table('module').' WHERE id=? AND project=?',
            [$id,$project])->fetch_assoc();
        $operation=self::journal($db,$project,'model_delete');
        if($operation){
            $current=$db->execute_query('SELECT id FROM '.self::table('module').' WHERE project=?',[$project])->fetch_row();
            if($current && (int)$current[0]!==$id){
                cms_fail(409,'模型代号已由其他模型使用，停止旧删除操作。');
            }
            if((int)$operation['module_id']!==$id || ($module && (int)$module['type']!==1)){
                cms_fail(409,'模型身份与待完成的删除操作不符。');
            }
            if($operation['stage']!=='prepared' && self::tableExists($db,$project)){
                cms_fail(409,'原模型删表已完成，但同名数据表再次出现，需人工核对。');
            }
            if(!$module && self::tableExists($db,$project)){
                cms_fail(409,'原模型记录已不存在，但同名数据表仍存在，需人工核对。');
            }
        }else{
            if(!$module || (int)$module['type']!==1){cms_fail(404,'模型不存在。');}
            if(self::pending($db,$project)){
                cms_fail(409,'模型有待恢复的字段操作，请先完成字段操作。');
            }
            $references=(int)$db->execute_query('SELECT COUNT(*) FROM '.self::table('classify').
                ' WHERE project=?',[$project])->fetch_row()[0];
            if($references>0){
                $rows=$db->execute_query('SELECT id,name FROM '.self::table('classify').
                    ' WHERE project=? ORDER BY id LIMIT 8',[$project])->fetch_all(MYSQLI_ASSOC);
                $labels=array_map(static fn($row)=>'#'.$row['id'].' '.mb_substr((string)$row['name'],0,30),$rows);
                cms_fail(409,'模型仍被 '.$references.' 个栏目引用：'.implode('、',$labels).'。请先处理这些栏目。');
            }
            self::begin($db,$project,$id,'model_delete');
        }
        try{
            // Retried DDL is permitted only by this journal's original model identity.
            $db->execute_query('UPDATE '.self::table('module').' SET status=-2 WHERE id=? AND project=?',[$id,$project]);
            if(self::tableExists($db,$project)){
                $db->query('DROP TABLE '.self::table($project));
            }
            self::stage($db,$project,'model_delete','','table_dropped');
            $db->execute_query('DELETE FROM '.self::table('module_field').' WHERE module_id=?',[$id]);
            $db->execute_query('DELETE FROM '.self::table('content_recycle_state').' WHERE project=?',[$project]);
            $db->execute_query('DELETE FROM '.self::table('content_create_request').' WHERE project=?',[$project]);
            self::stage($db,$project,'model_delete','','references_cleared');
            $db->execute_query('DELETE FROM '.self::table('module').' WHERE id=? AND project=?',[$id,$project]);
            self::clear($db,$project,'model_delete');
        }catch(Throwable $e){
            error_log('Model deletion pending recovery: '.$e);
            $stage=self::tableExists($db,$project)?'数据表仍存在':'数据表已删除';
            cms_fail(409,'模型删除尚未完成（'.$stage.'），请稍后重试同一模型。');
        }
    }
}
