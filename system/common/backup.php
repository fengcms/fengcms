<?php
// A logical backup for shared hosts without a shell or a database client binary.
final class CmsBackup
{
    private const MAX_BYTES = 536870912;
    private const MAX_SECONDS = 120;

    private static function root(): string
    {
        $root = rtrim(getenv('FENGCMS_BACKUP_PATH') ?: DBBACKUP_PATH, '/');
        if (!is_dir($root) && !mkdir($root, 0700, true)) {
            throw new RuntimeException('备份目录不可写。');
        }
        if (is_link($root) || !is_writable($root)) {
            throw new RuntimeException('备份目录不可写。');
        }
        return $root;
    }

    private static function path(string $id, string $extension): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) {
            throw new InvalidArgumentException('备份编号不正确。');
        }
        return self::root().'/'.$id.'.'.$extension;
    }

    private static function quoted(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private static function portableView(string $ddl): string
    {
        // The restoring account becomes the definer. An old site's account may
        // not exist or may lack privileges on the destination database.
        $portable = preg_replace('/\bDEFINER\s*=\s*`(?:``|[^`])+`@`(?:``|[^`])+`\s*/i', '', $ddl, 1, $count);
        if ($portable === null || (stripos($ddl, 'DEFINER') !== false && $count !== 1)) {
            throw new RuntimeException('视图定义无法安全导出，请使用主机备份工具。');
        }
        return $portable;
    }

    private static function write($handle, string $text, int &$bytes): void
    {
        $bytes += strlen($text);
        if ($bytes > self::MAX_BYTES) { throw new RuntimeException('数据库超过后台备份容量上限，请使用主机备份工具。'); }
        for ($offset = 0, $length = strlen($text); $offset < $length; $offset += $written) {
            $written = fwrite($handle, substr($text, $offset));
            if ($written === false || $written === 0) { throw new RuntimeException('备份文件写入失败。'); }
        }
    }

    private static function inspect(mysqli $db): array
    {
        foreach (['TRIGGERS'=>'TRIGGER_SCHEMA', 'ROUTINES'=>'ROUTINE_SCHEMA', 'EVENTS'=>'EVENT_SCHEMA'] as $object=>$column) {
            $count = $db->query("SELECT COUNT(*) AS n FROM information_schema.$object WHERE $column=DATABASE()")->fetch_assoc()['n'];
            if ((int)$count > 0) { throw new RuntimeException('数据库包含尚未支持的触发器、例程或事件，请使用主机备份工具。'); }
        }
        $objects = [];
        $result = $db->query('SHOW FULL TABLES');
        while ($row = $result->fetch_row()) {
            [$name, $type] = $row;
            if (!in_array($type, ['BASE TABLE', 'VIEW'], true)) {
                throw new RuntimeException('数据库包含尚未支持的对象类型。');
            }
            $create = $db->query('SHOW CREATE '.($type === 'VIEW' ? 'VIEW ' : 'TABLE ').self::quoted($name))->fetch_row();
            $ddl = $create[1] ?? null;
            if (!is_string($ddl) || $ddl === '') { throw new RuntimeException('无法读取数据库对象定义。'); }
            if ($type === 'VIEW') { $ddl = self::portableView($ddl); }
            $columns = [];
            if ($type === 'BASE TABLE') {
                $details = $db->query('SHOW FULL COLUMNS FROM '.self::quoted($name));
                while ($column = $details->fetch_assoc()) {
                    $columns[$column['Field']] = preg_match('/^(?:tinyblob|blob|mediumblob|longblob|binary|varbinary|bit)(?:\b|\()/i', $column['Type']) === 1;
                }
            }
            $objects[] = ['name'=>$name, 'type'=>$type, 'ddl'=>$ddl, 'columns'=>$columns];
        }
        if (!$objects) { throw new RuntimeException('数据库没有可备份的表。'); }
        // SHOW FULL TABLES is alphabetic, not dependency order. A second view
        // might reference the first; publishing either order as restorable is
        // unsafe until view dependencies can be resolved on both DB engines.
        if (count(array_filter($objects, static fn($o) => $o['type'] === 'VIEW')) > 1) {
            throw new RuntimeException('数据库包含多个视图，当前无法保证恢复顺序；请使用主机备份工具。');
        }
        return $objects;
    }

    public static function create(): array
    {
        // A shared host may still enforce a lower hard request limit. Such a
        // failure leaves no published backup and releases database locks.
        @set_time_limit(self::MAX_SECONDS + 10);
        $root = self::root();
        $guard = fopen($root.'/.backup.lock', 'c');
        if (!$guard || !flock($guard, LOCK_EX | LOCK_NB)) { throw new RuntimeException('已有备份任务正在运行。'); }
        $id = bin2hex(random_bytes(16));
        $temporary = $root.'/.'.$id.'.tmp';
        $sql = self::path($id, 'sql');
        $manifest = self::path($id, 'json');
        $db = null; $handle = null; $locked = false; $autocommitOff = false;
        $publishedSql = false;
        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $db->set_charset('utf8mb4');
            $objects = self::inspect($db);
            $tables = array_values(array_filter($objects, static fn($o) => $o['type'] === 'BASE TABLE'));
            $views = array_values(array_filter($objects, static fn($o) => $o['type'] === 'VIEW'));
            if (!$tables) { throw new RuntimeException('数据库没有可备份的表。'); }
            $handle = fopen($temporary, 'xb');
            if (!$handle) { throw new RuntimeException('无法创建备份临时文件。'); }
            chmod($temporary, 0600);
            $bytes = 0; $started = microtime(true);
            self::write($handle, "-- FengCMS logical backup; restore into an empty database only.\nSET NAMES utf8mb4;\nSET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO';\nSET FOREIGN_KEY_CHECKS=0;\n", $bytes);
            $db->query('SET autocommit=0'); $autocommitOff = true;
            try {
                $locks = implode(', ', array_map(static fn($table) => self::quoted($table['name']).' READ', $tables));
                $db->query('LOCK TABLES '.$locks);
            } catch (mysqli_sql_exception $e) {
                throw new RuntimeException('无法取得数据库读锁，请检查 LOCK TABLES 权限并使用主机备份工具。', 0, $e);
            }
            $locked = true;
            $summary = [];
            foreach ($tables as $table) {
                if (microtime(true) - $started > self::MAX_SECONDS) { throw new RuntimeException('备份超时，请使用主机备份工具。'); }
                $name = self::quoted($table['name']);
                self::write($handle, "\n-- Table: ".str_replace(["\r", "\n"], ' ', $table['name'])."\n".$table['ddl'].";\n", $bytes);
                $rows = 0;
                $result = $db->query('SELECT * FROM '.$name, MYSQLI_USE_RESULT);
                try {
                    while ($row = $result->fetch_assoc()) {
                        if (($rows % 100) === 0 && microtime(true) - $started > self::MAX_SECONDS) {
                            throw new RuntimeException('备份超时，请使用主机备份工具。');
                        }
                        $values = [];
                        foreach ($row as $column => $value) {
                            if ($value === null) { $values[] = 'NULL'; }
                            elseif ($table['columns'][$column] ?? false) { $values[] = '0x'.bin2hex($value); }
                            else { $values[] = "'".$db->real_escape_string($value)."'"; }
                        }
                        $fields = implode(',', array_map(self::quoted(...), array_keys($row)));
                        self::write($handle, 'INSERT INTO '.$name.' ('.$fields.') VALUES ('.implode(',', $values).');' . "\n", $bytes);
                        $rows++;
                    }
                } finally { $result->free(); }
                $summary[] = ['name'=>$table['name'], 'type'=>'table', 'rows'=>$rows];
            }
            foreach ($views as $view) {
                self::write($handle, "\n-- View: ".str_replace(["\r", "\n"], ' ', $view['name'])."\n".$view['ddl'].";\n", $bytes);
                $summary[] = ['name'=>$view['name'], 'type'=>'view'];
            }
            self::write($handle, "SET FOREIGN_KEY_CHECKS=1;\n", $bytes);
            fflush($handle);
            fclose($handle); $handle = null;
            $db->query('COMMIT');
            $db->query('UNLOCK TABLES'); $locked = false;
            $db->query('SET autocommit=1'); $autocommitOff = false;
            $meta = [
                'format'=>1, 'id'=>$id, 'created_at'=>date(DATE_ATOM), 'database'=>DB_NAME,
                'bytes'=>filesize($temporary), 'sha256'=>hash_file('sha256', $temporary), 'objects'=>$summary,
            ];
            if (!rename($temporary, $sql)) { throw new RuntimeException('无法发布备份文件。'); }
            $publishedSql = true;
            $metadata = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (file_put_contents($manifest.'.tmp', $metadata, LOCK_EX) !== strlen($metadata) ||
                !chmod($manifest.'.tmp', 0600) || !rename($manifest.'.tmp', $manifest)) {
                throw new RuntimeException('无法发布备份清单。');
            }
            return $meta;
        } finally {
            if ($handle) { fclose($handle); }
            if ($db) {
                if ($locked) { try { $db->query('UNLOCK TABLES'); } catch (Throwable) {} }
                if ($autocommitOff) { try { $db->query('SET autocommit=1'); } catch (Throwable) {} }
                $db->close();
            }
            if (is_file($temporary)) { unlink($temporary); }
            if (is_file($manifest.'.tmp')) { unlink($manifest.'.tmp'); }
            if ($publishedSql && !is_file($manifest) && is_file($sql)) { unlink($sql); }
            flock($guard, LOCK_UN); fclose($guard);
        }
    }

    public static function details(string $id): array
    {
        $path = self::path($id, 'json');
        if (is_link($path) || !is_file($path) || filesize($path) > 131072) { throw new RuntimeException('备份不存在。'); }
        $meta = json_decode(file_get_contents($path), true);
        if (!is_array($meta) || ($meta['format'] ?? null) !== 1 || ($meta['id'] ?? null) !== $id ||
            !is_string($meta['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $meta['sha256']) ||
            !is_int($meta['bytes'] ?? null)) {
            throw new RuntimeException('备份清单不正确。');
        }
        return $meta;
    }

    public static function all(): array
    {
        $items = [];
        foreach (glob(self::root().'/*.json') ?: [] as $path) {
            if (preg_match('/^([a-f0-9]{32})\.json$/D', basename($path), $match)) {
                try { $items[] = self::details($match[1]); } catch (Throwable) {}
            }
        }
        usort($items, static fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return $items;
    }

    public static function file(string $id): array
    {
        $meta = self::details($id);
        $path = self::path($id, 'sql');
        if (is_link($path) || !is_file($path) || filesize($path) !== $meta['bytes'] ||
            !hash_equals($meta['sha256'], hash_file('sha256', $path))) {
            throw new RuntimeException('备份文件校验失败。');
        }
        return [$path, $meta];
    }

    public static function delete(string $id): void
    {
        self::details($id);
        $file = self::path($id, 'sql');
        if (is_link($file) || !is_file($file)) { throw new RuntimeException('备份文件不存在。'); }
        if (!unlink($file) || !unlink(self::path($id, 'json'))) {
            throw new RuntimeException('删除备份失败。');
        }
    }
}
