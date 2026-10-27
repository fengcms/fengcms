<?php
require_once __DIR__.'/database_gate.php';
// Portable model definitions. Package strings never become SQL statements.
require_once __DIR__.'/model_schema.php';
final class CmsModelPackage
{
    private const MAX_BYTES = 1048576;
    private const MODULE_KEYS = ['name','item','unit','description','recover','search','status'];
    private const FIELD_KEYS = ['sort','name','aliases','type','defaults','must','length','enable','search','nullmsg','errormsg','status'];

    private static function db(): mysqli
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $db->set_charset('utf8mb4');
        return $db;
    }

    private static function table(string $project): string
    {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,29}$/D', $project)) {
            throw new InvalidArgumentException('模型表名须以字母开头，最多 30 位，只含字母、数字和下划线。');
        }
        $name = DB_PREFIX.$project;
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/D', $name)) {
            throw new InvalidArgumentException('模型表名不正确。');
        }
        return '`'.$name.'`';
    }

    private static function quote(string $name): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,49}$/D', $name)) {
            throw new InvalidArgumentException('字段名称不正确。');
        }
        return '`'.$name.'`';
    }

    private static function exactKeys(array $value, array $keys): bool
    {
        $actual = array_keys($value); sort($actual); sort($keys);
        return $actual === $keys;
    }

    private static function sqlType(string $type): string
    {
        $type = strtolower(trim($type));
        if (preg_match('/^(?:tinyint|smallint|mediumint|int|bigint)(?:\([1-9][0-9]{0,2}\))?(?: unsigned)?$/D', $type) ||
            preg_match('/^(?:varchar|char)\(([1-9][0-9]{0,3})\)$/D', $type, $match) && (int)$match[1] <= 1000 ||
            preg_match('/^(?:text|mediumtext|longtext|date|datetime|timestamp)$/D', $type) ||
            preg_match('/^decimal\(([1-9][0-9]?),([0-9]{1,2})\)$/D', $type)) {
            return $type;
        }
        throw new InvalidArgumentException('模型包含尚未支持的数据库字段类型。');
    }

    public static function export(int $id): string
    {
        $db = self::db();
        try {
            $module = $db->execute_query('SELECT * FROM `'.DB_PREFIX.'module` WHERE id=? AND type=1', [$id])->fetch_assoc();
            if (!$module) { throw new InvalidArgumentException('模型不存在。'); }
            if(!CmsModelSchema::isHeld($module['project'])){
                $project=$module['project'];
                return CmsModelSchema::withLock($project,fn()=> self::export($id));
            }
            if((int)$module['status']<0 || CmsModelSchema::pending($db,$module['project'])){
                throw new RuntimeException('模型结构操作尚未完成，暂不能导出。');
            }
            $table = self::table($module['project']);
            $columns = [];
            $result = $db->query('SHOW FULL COLUMNS FROM '.$table);
            while ($row = $result->fetch_assoc()) {
                $columns[] = [
                    'name'=>$row['Field'], 'type'=>self::sqlType($row['Type']),
                    'nullable'=>$row['Null']==='YES', 'default'=>$row['Default'],
                ];
            }
            $indexes = $db->query('SHOW INDEX FROM '.$table)->fetch_all(MYSQLI_ASSOC);
            if (count($indexes)!==1 || $indexes[0]['Key_name']!=='PRIMARY' || $indexes[0]['Column_name']!=='id') {
                throw new RuntimeException('该模型含自定义索引，当前格式尚不能无损导出。');
            }
            $fields = $db->execute_query('SELECT * FROM `'.DB_PREFIX.'module_field` WHERE module_id=? ORDER BY sort,id', [$id])->fetch_all(MYSQLI_ASSOC);
            if (count($fields)>100 || count($columns)>100) { throw new RuntimeException('模型字段超过导出上限。'); }
            $usedTypes = array_values(array_unique(array_map(static fn($field)=>(int)$field['type'],$fields)));
            $fieldTypes = [];
            foreach ($usedTypes as $typeId) {
                $typeRow = $db->execute_query('SELECT id,f,class,`quote` FROM `'.DB_PREFIX.'module_field_type` WHERE id=?',[$typeId])->fetch_assoc();
                if (!$typeRow) { throw new RuntimeException('源模型引用了不存在的字段类型。'); }
                $fieldTypes[] = $typeRow;
            }
            $data = [
                'format'=>'fengcms-model-v1', 'source_project'=>$module['project'],
                'module'=>array_intersect_key($module, array_flip(self::MODULE_KEYS)),
                'columns'=>$columns, 'field_types'=>$fieldTypes,
                'fields'=>array_map(static fn($field) => array_intersect_key($field, array_flip(self::FIELD_KEYS)), $fields),
            ];
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            if (strlen($json)>self::MAX_BYTES) { throw new RuntimeException('模型导出超过 1 MB 上限。'); }
            return $json;
        } finally { $db->close(); }
    }

    public static function parse(string $json, string $project): array
    {
        self::table($project);
        if (strlen($json)>self::MAX_BYTES || $json==='') { throw new InvalidArgumentException('模型文件为空或超过 1 MB。'); }
        try { $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR); }
        catch (JsonException) { throw new InvalidArgumentException('模型文件不是有效 JSON。'); }
        if (!is_array($data) || !self::exactKeys($data,['format','source_project','module','columns','fields','field_types']) ||
            $data['format']!=='fengcms-model-v1' || !is_string($data['source_project']) ||
            !is_array($data['module']) || !self::exactKeys($data['module'], self::MODULE_KEYS) ||
            !is_array($data['columns']) || !array_is_list($data['columns']) || count($data['columns'])<2 || count($data['columns'])>100 ||
            !is_array($data['fields']) || !array_is_list($data['fields']) || count($data['fields'])>100 ||
            !is_array($data['field_types']) || !array_is_list($data['field_types']) || count($data['field_types'])>100) {
            throw new InvalidArgumentException('模型格式或版本不正确。');
        }
        foreach (['name'=>50,'item'=>50,'unit'=>10,'description'=>255] as $key=>$limit) {
            if (!is_string($data['module'][$key]) && $data['module'][$key]!==null ||
                is_string($data['module'][$key]) && mb_strlen($data['module'][$key])>$limit) {
                throw new InvalidArgumentException('模型属性不正确。');
            }
        }
        foreach (['recover','search','status'] as $key) {
            if (!in_array((string)$data['module'][$key], ['0','1'], true)) { throw new InvalidArgumentException('模型开关参数不正确。'); }
        }
        $names = [];
        foreach ($data['columns'] as $column) {
            if (!is_array($column) || !self::exactKeys($column,['name','type','nullable','default']) ||
                !is_string($column['name']) || !is_string($column['type']) || !is_bool($column['nullable']) ||
                !is_null($column['default']) && !is_string($column['default']) ||
                is_string($column['default']) && strlen($column['default'])>255) {
                throw new InvalidArgumentException('模型数据库字段不正确。');
            }
            self::quote($column['name']); self::sqlType($column['type']);
            if (isset($names[$column['name']])) { throw new InvalidArgumentException('模型字段名称重复。'); }
            $names[$column['name']] = true;
        }
        if ($data['columns'][0]['name']!=='id' || !str_starts_with(strtolower($data['columns'][0]['type']),'int') ||
            $data['columns'][0]['nullable'] || !isset($names['title'],$names['info'],$names['content'],$names['img'],$names['status'],$names['time'],$names['html'])) {
            throw new InvalidArgumentException('模型缺少必要的系统字段。');
        }
        $typeMap=[];
        foreach ($data['field_types'] as $type) {
            if (!is_array($type) || !self::exactKeys($type,['id','f','class','quote']) ||
                !is_numeric($type['id']) || !is_numeric($type['f']) ||
                !is_string($type['class']) || !is_string($type['quote']) && $type['quote']!==null ||
                strlen($type['class'])>50 || strlen((string)$type['quote'])>255) {
                throw new InvalidArgumentException('模型字段类型描述不正确。');
            }
            if (isset($typeMap[(int)$type['id']])) { throw new InvalidArgumentException('模型字段类型重复。'); }
            $typeMap[(int)$type['id']]=$type;
        }
        $fieldNames = [];
        foreach ($data['fields'] as $field) {
            if (!is_array($field) || !self::exactKeys($field,self::FIELD_KEYS) || !is_string($field['name']) ||
                !is_string($field['aliases']) && $field['aliases']!==null ||
                !is_string($field['type']) && !is_int($field['type']) ||
                !preg_match('/^[0-9]{1,3}$/D',(string)$field['type'])) {
                throw new InvalidArgumentException('模型字段配置不正确。');
            }
            self::quote($field['name']);
            if (!isset($typeMap[(int)$field['type']]) ||
                !isset($names[$field['name']]) && !($field['name']==='attrib' && isset($names['attrib_j'],$names['attrib_g'],$names['attrib_t'],$names['attrib_r'],$names['attrib_d'],$names['attrib_h']))) {
                throw new InvalidArgumentException('模型字段与数据表或类型不匹配。');
            }
            if (isset($fieldNames[$field['name']])) { throw new InvalidArgumentException('模型字段配置重复。'); }
            $fieldNames[$field['name']] = true;
            foreach (['defaults','length','nullmsg','errormsg'] as $key) {
                if (!is_null($field[$key]) && !is_scalar($field[$key]) || is_string($field[$key]) && strlen($field[$key])>255) {
                    throw new InvalidArgumentException('模型字段配置过长或类型错误。');
                }
            }
            foreach (['sort','must','enable','search','status'] as $key) {
                if (!is_numeric($field[$key]) || (int)$field[$key]<0 || (int)$field[$key]>100000) {
                    throw new InvalidArgumentException('模型字段开关或排序不正确。');
                }
            }
        }
        return $data;
    }

    public static function import(array $data, string $project): int
    {
        return CmsDatabaseGate::write(fn()=>self::importLocked($data,$project));
    }

    private static function importLocked(array $data,string $project): int
    {
        // Validate again even when the preview was stored in a server session.
        $data = self::parse(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $project);
        $db = self::db();
        $table = self::table($project);
        $lock = 'fengcms_model_'.substr(hash('sha256', DB_NAME.':'.$project),0,40);
        $created = false; $moduleId = null;
        try {
            if ((int)$db->execute_query('SELECT GET_LOCK(?,10)', [$lock])->fetch_row()[0]!==1) {
                throw new RuntimeException('另一个模型操作正在进行，请稍后重试。');
            }
            $existing = $db->execute_query('SELECT id FROM `'.DB_PREFIX.'module` WHERE project=?', [$project])->fetch_row();
            $tableExists = $db->execute_query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [DB_PREFIX.$project])->fetch_row()[0];
            if ($existing || (int)$tableExists>0 || CmsModelSchema::pending($db,$project)) {
                throw new InvalidArgumentException('模型名称或数据表已存在，或有待恢复的结构操作。');
            }
            foreach ($data['field_types'] as $type) {
                $targetType=$db->execute_query('SELECT id,f,class,`quote` FROM `'.DB_PREFIX.'module_field_type` WHERE id=?',[(int)$type['id']])->fetch_assoc();
                if (!$targetType || (int)$targetType['f']!==(int)$type['f'] ||
                    $targetType['class']!==$type['class'] || $targetType['quote']!==$type['quote']) {
                    throw new InvalidArgumentException('目标站缺少相同定义的字段类型。');
                }
            }
            $definitions = [];
            foreach ($data['columns'] as $column) {
                $name = self::quote($column['name']); $type = self::sqlType($column['type']);
                $part = $name.' '.$type.($column['nullable'] ? ' NULL' : ' NOT NULL');
                if ($column['name']==='id') { $part .= ' AUTO_INCREMENT'; }
                elseif ($column['default'] !== null) {
                    if (preg_match('/^(?:text|mediumtext|longtext)$/D',$type)) { throw new InvalidArgumentException('文本字段默认值不受支持。'); }
                    $part .= " DEFAULT '".$db->real_escape_string($column['default'])."'";
                } elseif ($column['nullable']) { $part .= ' DEFAULT NULL'; }
                $definitions[] = $part;
            }
            $definitions[] = 'PRIMARY KEY (`id`)';
            $db->query("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'");
            $db->query('CREATE TABLE '.$table.' ('.implode(',', $definitions).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            $created = true;
            $m = $data['module'];
            $db->execute_query('INSERT INTO `'.DB_PREFIX.'module` (name,project,type,item,unit,description,recover,search,status,time) VALUES (?,?,1,?,?,?,?,?,?,?)',
                [$m['name'],$project,$m['item'],$m['unit'],$m['description'],(int)$m['recover'],(int)$m['search'],-2,time()]);
            $moduleId = $db->insert_id;
            $keys = self::FIELD_KEYS;
            $sql = 'INSERT INTO `'.DB_PREFIX.'module_field` (module_id,'.implode(',',array_map(self::quote(...),$keys)).') VALUES (?,'.implode(',',array_fill(0,count($keys),'?')).')';
            foreach ($data['fields'] as $field) {
                $values = array_map(static fn($key) => $field[$key], $keys);
                $db->execute_query($sql, array_merge([$moduleId], $values));
            }
            $db->execute_query('UPDATE `'.DB_PREFIX.'module` SET status=? WHERE id=?',[(int)$m['status'],$moduleId]);
            return $moduleId;
        } catch (Throwable $e) {
            if ($moduleId) {
                $db->execute_query('DELETE FROM `'.DB_PREFIX.'module_field` WHERE module_id=?', [$moduleId]);
                $db->execute_query('DELETE FROM `'.DB_PREFIX.'module` WHERE id=?', [$moduleId]);
            }
            if ($created) { $db->query('DROP TABLE '.$table); }
            throw $e;
        } finally {
            try { $db->execute_query('SELECT RELEASE_LOCK(?)', [$lock]); } catch (Throwable) {}
            $db->close();
        }
    }
}
