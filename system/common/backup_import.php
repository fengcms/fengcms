<?php
// Import rows from the CMS logical backup format into a matching schema.
// The uploaded SQL is parsed as data; none of its statements are executed.
require_once __DIR__.'/database_gate.php';
final class CmsBackupImport
{
    public const MAX_BYTES = 8388608;
    private const MAX_ROWS = 10000;
    private const MAX_TABLES = 100;
    private const MAX_SECONDS = 120;

    private static function root(): string
    {
        $root=rtrim(getenv('FENGCMS_BACKUP_PATH') ?: DBBACKUP_PATH, '/');
        if (!is_dir($root) && !mkdir($root, 0700, true) || is_link($root) || !is_writable($root)) {
            throw new RuntimeException('私有备份目录不可写。');
        }
        return $root;
    }

    private static function journalPath(): string {return self::root().'/.import-operation.json';}

    public static function pending(): bool
    {
        $path=self::journalPath();
        return is_file($path) || is_link($path);
    }

    private static function journal(array $state): void
    {
        $path=self::journalPath();
        $temporary=self::root().'/.import-operation-'.$state['id'].'.tmp';
        $json=json_encode($state,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $handle=fopen($temporary,'xb');
        if(!$handle){throw new RuntimeException('无法保存导入恢复记录。');}
        try{
            chmod($temporary,0600);
            if(fwrite($handle,$json)!==strlen($json) || !fflush($handle) ||
                (function_exists('fsync') && !fsync($handle))){
                throw new RuntimeException('无法保存导入恢复记录。');
            }
        }finally{fclose($handle);}
        if(!rename($temporary,$path)){throw new RuntimeException('无法发布导入恢复记录。');}
    }

    private static function clearJournal(): void
    {
        $path=self::journalPath();
        if(is_file($path) && !unlink($path)){throw new RuntimeException('无法清理导入恢复记录。');}
    }

    private static function readJournal(): ?array
    {
        $path=self::journalPath();
        if(!file_exists($path) && !is_link($path)){return null;}
        if(is_link($path) || !is_file($path) || filesize($path)>65536){
            throw new RuntimeException('导入恢复记录不正确，停止自动清理。');
        }
        $state=json_decode(file_get_contents($path),true);
        if(!is_array($state) || ($state['database']??null)!==DB_NAME ||
            !preg_match('/^[a-f0-9]{16}$/D',(string)($state['id']??'')) ||
            !in_array($state['phase']??null,['staging','pre_swap','committed'],true) ||
            !is_array($state['tables']??null) || !$state['tables'] || count($state['tables'])>self::MAX_TABLES){
            throw new RuntimeException('导入恢复记录不正确，停止自动清理。');
        }
        foreach($state['tables'] as $index=>$entry){
            if(!is_int($index) || !is_array($entry) || !is_string($entry['live']??null) ||
                !preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/D',$entry['live']) ||
                ($entry['stage']??null)!=='cmsimp_'.$state['id'].'_'.$index ||
                ($entry['old']??null)!=='cmsold_'.$state['id'].'_'.$index){
                throw new RuntimeException('导入恢复表映射不正确，停止自动清理。');
            }
        }
        return $state;
    }

    private static function objectExists(mysqli $db,string $name): bool
    {
        $row=$db->execute_query('SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?',
            [DB_NAME,$name])->fetch_row();
        if($row && $row[0]!=='BASE TABLE'){throw new RuntimeException('导入恢复对象类型不正确，停止自动清理。');}
        return $row!==null;
    }

    /** Explicit recovery never guesses ownership from a table-name prefix. */
    public static function recover(): string
    {
        return CmsDatabaseGate::barrier(function(): string {
            $db=self::connection();
            try{
                $lock=self::importLock($db);
                if((int)$db->execute_query('SELECT GET_LOCK(?,5)',[$lock])->fetch_row()[0]!==1){
                    throw new RuntimeException('另一个数据库导入正在运行。');
                }
                try{return self::recoverLocked($db);}
                finally{try{$db->execute_query('SELECT RELEASE_LOCK(?)',[$lock]);}catch(Throwable){}}
            }finally{$db->close();}
        });
    }

    private static function recoverLocked(mysqli $db): string
    {
        $state=self::readJournal();
        if(!$state){return '没有待恢复的数据库导入。';}
        $oldCount=0;$stageCount=0;
        foreach($state['tables'] as $entry){
            if(!self::objectExists($db,$entry['live'])){throw new RuntimeException('正式数据表缺失，停止自动清理。');}
            $oldCount+=(int)self::objectExists($db,$entry['old']);
            $stageCount+=(int)self::objectExists($db,$entry['stage']);
        }
        $total=count($state['tables']);
        if($state['phase']==='committed'){
            if($stageCount!==0){throw new RuntimeException('已完成导入仍存在暂存表，需人工核对。');}
            foreach($state['tables'] as $entry){
                if(self::objectExists($db,$entry['old'])){$db->query('DROP TABLE '.self::name($entry['old']));}
            }
            self::clearJournal();
            return '已核对正式表并清理完成导入留下的旧表。';
        }
        if($oldCount===$total && $state['phase']==='pre_swap' && $stageCount===0){
            $rename=[];
            foreach($state['tables'] as $entry){
                $rename[]=self::name($entry['live']).' TO '.self::name($entry['stage']);
                $rename[]=self::name($entry['old']).' TO '.self::name($entry['live']);
            }
            $db->query('RENAME TABLE '.implode(',',$rename));
        }elseif($oldCount!==0){
            throw new RuntimeException('中断导入的旧表状态不完整，停止自动清理。');
        }
        foreach($state['tables'] as $entry){
            if(self::objectExists($db,$entry['stage'])){$db->query('DROP TABLE '.self::name($entry['stage']));}
        }
        self::clearJournal();
        return '已核对并清理中断导入，原正式数据表可重新预览。';
    }

    public static function uploadPath(string $id): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/D',$id)) { throw new InvalidArgumentException('导入文件编号不正确。'); }
        return self::root().'/.import-'.$id.'.sql';
    }

    public static function pruneUploads(): void
    {
        foreach (glob(self::root().'/.import-*.sql') ?: [] as $path) {
            if (preg_match('/^\.import-[a-f0-9]{32}\.sql$/D', basename($path)) &&
                (filemtime($path) ?: 0) < time()-3600) {
                @unlink($path);
            }
        }
    }

    private static function name(string $value): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/D', $value)) {
            throw new InvalidArgumentException('备份中的表名或列名不正确。');
        }
        return '`'.$value.'`';
    }

    private static function decodeLiteral(string $value): ?string
    {
        $value = trim($value);
        if ($value === 'NULL') { return null; }
        if (preg_match('/^0x(?:[0-9a-fA-F]{2})*$/D', $value)) {
            return hex2bin(substr($value, 2));
        }
        if (strlen($value) < 2 || $value[0] !== "'" || substr($value, -1) !== "'") {
            throw new InvalidArgumentException('备份包含不支持的数据表达式。');
        }
        $decoded = '';
        $escapes = ['0'=>"\0",'n'=>"\n",'r'=>"\r",'b'=>"\x08",'t'=>"\t",'Z'=>"\x1a",'\\'=>'\\',"'"=>"'",'"'=>'"'];
        for ($i = 1, $end = strlen($value) - 1; $i < $end; $i++) {
            $char = $value[$i];
            if ($char === '\\') {
                if (++$i >= $end || !array_key_exists($value[$i], $escapes)) {
                    throw new InvalidArgumentException('备份字符串转义不正确。');
                }
                $char = $escapes[$value[$i]];
            } elseif ($char === "'") {
                throw new InvalidArgumentException('备份字符串引号不正确。');
            }
            $decoded .= $char;
        }
        return $decoded;
    }

    private static function values(string $list): array
    {
        $parts = []; $start = 0; $quoted = false; $escaped = false;
        for ($i = 0, $n = strlen($list); $i < $n; $i++) {
            $char = $list[$i];
            if ($escaped) { $escaped = false; }
            elseif ($quoted && $char === '\\') { $escaped = true; }
            elseif ($char === "'") { $quoted = !$quoted; }
            elseif ($char === ',' && !$quoted) {
                $parts[] = self::decodeLiteral(substr($list, $start, $i-$start));
                $start = $i+1;
            }
        }
        if ($quoted || $escaped) { throw new InvalidArgumentException('备份数据引号不完整。'); }
        $parts[] = self::decodeLiteral(substr($list, $start));
        return $parts;
    }

    private static function columns(string $ddl, string $table): array
    {
        if (!preg_match('/^CREATE TABLE `'.preg_quote($table, '/').'`\s*\(.*\)\s+ENGINE\s*=.*;$/siD', $ddl)) {
            throw new InvalidArgumentException('备份中的建表定义不符合本系统格式。');
        }
        preg_match_all('/^\s*`([A-Za-z_][A-Za-z0-9_]{0,63})`\s+([A-Za-z]+(?:\([^\n)]*\))?(?: unsigned)?)/mi', $ddl, $matches, PREG_SET_ORDER);
        $columns = [];
        foreach ($matches as $match) {
            if (isset($columns[$match[1]])) { throw new InvalidArgumentException('备份包含重复列名。'); }
            $columns[$match[1]] = self::normalType($match[2]);
        }
        if (!$columns || count($columns) > 100) { throw new InvalidArgumentException('备份数据库列数量不正确。'); }
        return $columns;
    }

    private static function normalType(string $type): string
    {
        $type = strtolower(preg_replace('/\s+/', ' ', $type));
        return preg_replace('/\b(tinyint|smallint|mediumint|int|bigint)\([0-9]+\)/', '$1', $type);
    }

    public static function parse(string $path): array
    {
        if (is_link($path) || !is_file($path) || ($bytes = filesize($path)) === false ||
            $bytes < 100 || $bytes > self::MAX_BYTES) {
            throw new InvalidArgumentException('请选择不超过 8 MB 的本系统 SQL 备份。');
        }
        $data = file_get_contents($path);
        if ($data === false || strlen($data) !== $bytes || str_contains($data, "\0")) {
            throw new InvalidArgumentException('备份文件读取失败或格式不正确。');
        }
        $lines = explode("\n", str_replace("\r\n", "\n", $data));
        $header = [
            '-- FengCMS logical backup; restore into an empty database only.',
            'SET NAMES utf8mb4;', "SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO';",
            'SET FOREIGN_KEY_CHECKS=0;',
        ];
        if (array_splice($lines, 0, 4) !== $header) {
            throw new InvalidArgumentException('只支持本系统生成的 SQL 备份。');
        }
        $tables=[]; $views=[]; $current=null; $kind=null; $ddl=[]; $rows=[]; $total=0; $finished=false;
        $finish = static function () use (&$tables, &$views, &$current, &$kind, &$ddl, &$rows): void {
            if ($current === null) { return; }
            $definition = trim(implode("\n", $ddl));
            if ($kind === 'table') {
                $columns = self::columns($definition, $current);
                $tables[$current] = ['columns'=>$columns, 'rows'=>$rows];
            } else {
                if (!preg_match('/\bVIEW\s+`'.preg_quote($current, '/').'`\s+AS\s+/i', $definition) ||
                    !str_ends_with($definition, ';')) {
                    throw new InvalidArgumentException('备份视图定义不正确。');
                }
                $views[] = $current;
            }
            $current=null; $kind=null; $ddl=[]; $rows=[];
        };
        foreach ($lines as $line) {
            $line = rtrim($line, "\r");
            if ($finished) {
                if (trim($line) !== '') { throw new InvalidArgumentException('备份文件末尾包含额外内容。'); }
                continue;
            }
            if ($line === 'SET FOREIGN_KEY_CHECKS=1;') { $finish(); $finished=true; continue; }
            if (preg_match('/^-- (Table|View): ([A-Za-z_][A-Za-z0-9_]{0,63})$/D', $line, $mark)) {
                $finish();
                $current=$mark[2]; $kind=$mark[1] === 'Table' ? 'table' : 'view';
                if ($kind === 'table') {
                    if ($views || isset($tables[$current]) || count($tables) >= self::MAX_TABLES) {
                        throw new InvalidArgumentException('备份对象顺序或数量不正确。');
                    }
                } elseif (in_array($current, $views, true) || count($views) >= 1) {
                    throw new InvalidArgumentException('备份视图数量不正确。');
                }
                continue;
            }
            if ($current === null) {
                if (trim($line) !== '') { throw new InvalidArgumentException('备份包含不允许的 SQL。'); }
                continue;
            }
            if ($kind === 'table' && str_starts_with($line, 'INSERT INTO ')) {
                if (!preg_match('/^INSERT INTO `'.preg_quote($current, '/').'` \(([^)]+)\) VALUES \((.*)\);$/sD', $line, $insert)) {
                    throw new InvalidArgumentException('备份数据语句不符合本系统格式。');
                }
                $names = explode(',', $insert[1]);
                $names = array_map(static function (string $name): string {
                    if (!preg_match('/^`([A-Za-z_][A-Za-z0-9_]{0,63})`$/D', $name, $match)) {
                        throw new InvalidArgumentException('备份列名不正确。');
                    }
                    return $match[1];
                }, $names);
                $values = self::values($insert[2]);
                if (count($names) !== count($values) || $total++ >= self::MAX_ROWS) {
                    throw new InvalidArgumentException('备份行数或列数超过上限。');
                }
                $rows[] = ['columns'=>$names, 'values'=>$values];
            } elseif ($rows) {
                if (trim($line) !== '') { throw new InvalidArgumentException('备份数据后包含额外 SQL。'); }
            } else {
                $ddl[]=$line;
            }
        }
        if (!$finished || !$tables) { throw new InvalidArgumentException('备份文件不完整。'); }
        return ['tables'=>$tables, 'views'=>$views, 'rows'=>$total, 'bytes'=>$bytes,
            'sha256'=>hash('sha256', $data)];
    }

    public static function connection(): mysqli
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $db->set_charset('utf8mb4');
        return $db;
    }

    public static function verify(mysqli $db, array $import): array
    {
        if(self::pending()){throw new RuntimeException('有待恢复的数据库导入，请先处理后再预览备份。');}
        foreach (['TRIGGERS'=>'TRIGGER_SCHEMA', 'REFERENTIAL_CONSTRAINTS'=>'CONSTRAINT_SCHEMA'] as $object=>$column) {
            if ((int)$db->query("SELECT COUNT(*) FROM information_schema.$object WHERE $column=DATABASE()")->fetch_row()[0] > 0) {
                throw new RuntimeException('当前数据库包含触发器或外键，后台导入暂不支持。');
            }
        }
        $actualTables=[]; $actualViews=[];
        $objects=$db->query('SHOW FULL TABLES');
        while ($row=$objects->fetch_row()) {
            if ($row[1] === 'BASE TABLE') { $actualTables[]=$row[0]; }
            elseif ($row[1] === 'VIEW') { $actualViews[]=$row[0]; }
        }
        $sourceTables=array_keys($import['tables']); $sourceViews=$import['views'];
        sort($actualTables); sort($actualViews); sort($sourceTables); sort($sourceViews);
        if ($actualTables !== $sourceTables || $actualViews !== $sourceViews) {
            throw new InvalidArgumentException('备份与当前数据库的表或视图不一致，请先使用相同版本和模型结构。');
        }
        foreach ($import['tables'] as $name=>$table) {
            $actual=[];
            foreach ($db->query('SHOW FULL COLUMNS FROM '.self::name($name)) as $column) {
                if (preg_match('/\b(?:GENERATED|INVISIBLE)\b/i', $column['Extra']) ||
                    preg_match('/^bit(?:\(|$)/i', $column['Type'])) {
                    throw new InvalidArgumentException('表 '.$name.' 包含后台导入尚不支持的生成列、隐藏列或 BIT 字段。');
                }
                $actual[$column['Field']] = self::normalType($column['Type']);
            }
            if ($actual !== $table['columns']) {
                throw new InvalidArgumentException('表 '.$name.' 的列结构与备份不一致。');
            }
            foreach ($table['rows'] as $row) {
                if (array_keys($actual) !== $row['columns']) {
                    throw new InvalidArgumentException('表 '.$name.' 的备份行列不完整。');
                }
            }
        }
        return ['table_count'=>count($import['tables']), 'view_count'=>count($import['views']),
            'row_count'=>$import['rows'], 'bytes'=>$import['bytes'], 'sha256'=>$import['sha256']];
    }

    public static function restore(mysqli $db, array $import, ?callable $beforeStage = null): array
    {
        return CmsDatabaseGate::barrier(fn()=>self::restoreLocked($db,$import,$beforeStage));
    }

    private static function importLock(mysqli $db): string
    {
        return 'fengcms_import_'.substr(hash('sha256',(string)$db->query('SELECT DATABASE()')->fetch_row()[0]),0,30);
    }

    private static function restoreLocked(mysqli $db,array $import,?callable $beforeStage): array
    {
        @set_time_limit(self::MAX_SECONDS + 10);
        $lock=self::importLock($db);
        if ((int)$db->execute_query('SELECT GET_LOCK(?,5)', [$lock])->fetch_row()[0] !== 1) {
            throw new RuntimeException('另一个数据库导入正在进行。');
        }
        $stage=[]; $old=[]; $swapped=false; $started=microtime(true);
        $journaled=false;$committed=false;$clean=true;$safeToCleanup=true;$state=null;
        try {
            self::verify($db, $import);
            if ($beforeStage !== null) { $beforeStage(); }
            $started=microtime(true);
            $db->query('SET SESSION lock_wait_timeout=5');
            $mode=(string)$db->query('SELECT @@SESSION.sql_mode')->fetch_row()[0];
            if (!in_array('NO_AUTO_VALUE_ON_ZERO',explode(',',$mode),true)) {
                $mode.=($mode==='' ? '' : ',').'NO_AUTO_VALUE_ON_ZERO';
            }
            $db->execute_query('SET SESSION sql_mode=?',[$mode]);
            $tag=bin2hex(random_bytes(8));
            $state=['id'=>$tag,'database'=>DB_NAME,'source_sha256'=>$import['sha256']??'',
                'phase'=>'staging','tables'=>[]];
            foreach(array_keys($import['tables']) as $index=>$name){
                $stage[$name]='cmsimp_'.$tag.'_'.$index;
                $old[$name]='cmsold_'.$tag.'_'.$index;
                if(self::objectExists($db,$stage[$name]) || self::objectExists($db,$old[$name])){
                    throw new RuntimeException('临时表名称冲突，请重试导入。');
                }
                $state['tables'][]=['live'=>$name,'stage'=>$stage[$name],'old'=>$old[$name]];
            }
            self::journal($state);$journaled=true;
            foreach ($import['tables'] as $name=>$table) {
                if (microtime(true)-$started > self::MAX_SECONDS) { throw new RuntimeException('后台导入超时，请使用主机数据库工具。'); }
                $stageName=$stage[$name];
                $db->query('CREATE TABLE '.self::name($stageName).' LIKE '.self::name($name));
                $columns=array_keys($table['columns']);
                $statement='INSERT INTO '.self::name($stageName).' ('.implode(',',array_map(self::name(...),$columns)).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')';
                $rowNumber=0;
                foreach ($table['rows'] as $row) {
                    if (($rowNumber++ % 100) === 0 && microtime(true)-$started > self::MAX_SECONDS) {
                        throw new RuntimeException('后台导入超时，请使用主机数据库工具。');
                    }
                    $db->execute_query($statement, $row['values']);
                }
                if ((int)$db->query('SELECT COUNT(*) FROM '.self::name($stageName))->fetch_row()[0] !== count($table['rows'])) {
                    throw new RuntimeException('导入记录数核对失败。');
                }
            }
            $rename=[];
            foreach ($stage as $name=>$stageName) {
                $rename[]=self::name($name).' TO '.self::name($old[$name]);
                $rename[]=self::name($stageName).' TO '.self::name($name);
            }
            $state['phase']='pre_swap';$safeToCleanup=false;self::journal($state);
            $db->query('RENAME TABLE '.implode(',', $rename));
            $swapped=true;
            foreach ($import['views'] as $view) {
                $db->query('SELECT * FROM '.self::name($view).' LIMIT 0');
            }
            foreach ($stage as $name=>$stageName) {
                if ((int)$db->query('SELECT COUNT(*) FROM '.self::name($name))->fetch_row()[0] !== count($import['tables'][$name]['rows'])) {
                    throw new RuntimeException('替换后的记录数核对失败。');
                }
            }
            $state['phase']='committed';self::journal($state);$committed=true;
            foreach ($old as $oldName) {
                $db->query('DROP TABLE '.self::name($oldName));
            }
            self::clearJournal();$journaled=false;$swapped=false;
            return ['tables'=>count($stage), 'rows'=>$import['rows'], 'leftover'=>[]];
        } catch (Throwable $e) {
            if ($swapped && !$committed) {
                $rollback=[];
                foreach ($stage as $name=>$stageName) {
                    $rollback[]=self::name($name).' TO '.self::name($stageName);
                    $rollback[]=self::name($old[$name]).' TO '.self::name($name);
                }
                try { $db->query('RENAME TABLE '.implode(',', $rollback)); $swapped=false;$safeToCleanup=true; }
                catch (Throwable $rollbackError) {
                    error_log('Database import rollback failed: '.$rollbackError);
                    throw new RuntimeException('导入后回退失败，请立即暂停写入并使用导入前自动备份恢复。', 0, $e);
                }
            }
            throw $e;
        } finally {
            if ($journaled && $safeToCleanup && !$swapped && !$committed) {
                foreach ($stage as $stageName) {
                    try { $db->query('DROP TABLE IF EXISTS '.self::name($stageName)); }
                    catch (Throwable $e) { $clean=false;error_log('Database import stage cleanup failed: '.$e); }
                }
                if($clean && $journaled){
                    try{self::clearJournal();}catch(Throwable $e){error_log('Database import journal cleanup failed: '.$e);}
                }
            }
            try { $db->execute_query('SELECT RELEASE_LOCK(?)', [$lock]); } catch (Throwable) {}
        }
    }
}
