<?php
// Read-only inventory by default; --apply is restricted to an explicitly named copy database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$configRoot=dirname(__DIR__);
require_once $configRoot.'/system/common/config_loader.php';
$settings=cms_config_values($configRoot);
$host=$settings['DB_HOST']; $user=$settings['DB_USER'];
$password=$settings['DB_PASS']; $database=$settings['DB_NAME'];
$prefix=$settings['DB_PREFIX'];
if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,29}$/D',$prefix)) { throw new RuntimeException('表前缀不正确。'); }
$options=[];
foreach (array_slice($argv,1) as $argument) {
    if (!preg_match('/^--([a-z-]+)(?:=(.*))?$/D',$argument,$match)) { throw new RuntimeException('参数不正确。'); }
    if (isset($options[$match[1]])) { throw new RuntimeException('重复参数。'); }
    $options[$match[1]]=$match[2]??true;
}
if (array_diff(array_keys($options),['apply','table','confirm-db','plan'])) { throw new RuntimeException('未知参数。'); }
if (isset($options['apply']) && $options['apply']!==true) { throw new RuntimeException('--apply 不带值。'); }
$db=new mysqli($host,$user,$password,$database);
$db->set_charset('utf8mb4');
$objects=$db->query('SELECT TABLE_NAME,TABLE_TYPE,ENGINE,TABLE_COLLATION,TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME')->fetch_all(MYSQLI_ASSOC);
$tables=[]; $views=[];
foreach ($objects as $object) {
    if (!str_starts_with($object['TABLE_NAME'],$prefix)) { continue; }
    if ($object['TABLE_TYPE']==='BASE TABLE') { $tables[$object['TABLE_NAME']]=$object; }
    else { $views[]=$object['TABLE_NAME']; }
}
if (!$tables) { throw new RuntimeException('当前数据库没有匹配此前缀的实体表。'); }
$columns=$db->query('SELECT TABLE_NAME,COLUMN_NAME,DATA_TYPE,CHARACTER_MAXIMUM_LENGTH,CHARACTER_SET_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()')->fetch_all(MYSQLI_ASSOC);
$columnMap=[];
foreach ($columns as $column) { $columnMap[$column['TABLE_NAME']][$column['COLUMN_NAME']]=$column; }
$columnIssues=[];
foreach ($columns as $column) {
    if (isset($tables[$column['TABLE_NAME']]) && $column['CHARACTER_SET_NAME']!==null &&
        $column['CHARACTER_SET_NAME']!=='utf8mb4') {
        $columnIssues[$column['TABLE_NAME']][]=$column['COLUMN_NAME'];
    }
}
$indexes=$db->query('SELECT TABLE_NAME,INDEX_NAME,COLUMN_NAME,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX')->fetch_all(MYSQLI_ASSOC);
$indexBytes=[];
foreach ($indexes as $index) {
    $column=$columnMap[$index['TABLE_NAME']][$index['COLUMN_NAME']]??null;
    if (!$column || !isset($tables[$index['TABLE_NAME']])) { continue; }
    $length=(int)($index['SUB_PART']??$column['CHARACTER_MAXIMUM_LENGTH']??8);
    $table=$index['TABLE_NAME']; $name=$index['INDEX_NAME'];
    $indexBytes[$table][$name]=($indexBytes[$table][$name]??0)+($column['CHARACTER_SET_NAME'] ? $length*4 : min($length,8));
}
$unsafe=[];
foreach ($indexBytes as $table=>$list) {
    foreach ($list as $name=>$bytes) {
        if ($bytes>3072) { $unsafe[]=$table.'.'.$name.' 的 utf8mb4 索引估计 '.$bytes.' 字节，超过 3072 字节'; }
    }
}
$foreignKeys=$db->query('SELECT TABLE_NAME,REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL')->fetch_all(MYSQLI_ASSOC);
$dependent=[];
foreach ($foreignKeys as $key) { $dependent[$key['TABLE_NAME']]=true; $dependent[$key['REFERENCED_TABLE_NAME']]=true; }
$targets=[];
foreach ($tables as $name=>$table) {
    $needs=$table['ENGINE']!=='InnoDB' || $table['TABLE_COLLATION']!=='utf8mb4_unicode_ci' || isset($columnIssues[$name]);
    printf("%s  引擎=%s  排序规则=%s  估计行数=%s%s\n",$name,$table['ENGINE'],$table['TABLE_COLLATION'],$table['TABLE_ROWS'],$needs?'  待迁移':'');
    if (isset($columnIssues[$name])) { echo '  非 utf8mb4 字段：'.implode(', ',$columnIssues[$name])."\n"; }
    if (isset($dependent[$name])) { echo "  关联外键：需要人工确认迁移顺序\n"; }
    if ($needs) { $targets[]=$name; }
}
if ($views) { echo '视图：'.implode(', ',$views)."（保留原定义，需在副本核对）\n"; }
foreach ($unsafe as $issue) { echo '阻断：'.$issue."\n"; }
echo '待迁移实体表：'.count($targets)."；工具不会自动迁移。\n";
if (isset($options['plan'])) {
    foreach ($targets as $name) { echo 'ALTER TABLE `'.$name.'` ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;' . "\n"; }
}
if (!isset($options['apply'])) { exit($unsafe ? 2 : 0); }
if (isset($options['plan'])) { throw new RuntimeException('--plan 与 --apply 不能同时使用。'); }
if ($unsafe) { throw new RuntimeException('索引预检未通过，拒绝转换。'); }
if (getenv('FENGCMS_MIGRATION_COPY')!=='1' || !str_starts_with($database,'fengcms_migration_') ||
    ($options['confirm-db']??null)!==$database) {
    throw new RuntimeException('只允许对名称以 fengcms_migration_ 开头且明确确认的数据库副本执行。');
}
$tableName=$options['table']??null;
if (!is_string($tableName) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/D',$tableName) ||
    !str_starts_with($tableName,$prefix) || !isset($tables[$tableName])) {
    throw new RuntimeException('请用 --table 指定当前前缀内的一个实体表。');
}
if (!in_array($tableName,$targets,true)) { echo "表已符合目标格式，无需转换。\n"; exit(0); }
if (isset($dependent[$tableName])) { throw new RuntimeException('该表有关联外键，须人工核对顺序后迁移。'); }
$lock='fengcms_schema_'.substr(hash('sha256',$database.':'.$tableName),0,40);
$stmt=$db->prepare('SELECT GET_LOCK(?,0)'); $stmt->bind_param('s',$lock); $stmt->execute();
if ((int)$stmt->get_result()->fetch_row()[0]!==1) { throw new RuntimeException('该表已有迁移任务。'); }
$stmt->close();
try {
    $quoted='`'.$tableName.'`';
    $before=(int)$db->query('SELECT COUNT(*) FROM '.$quoted)->fetch_row()[0];
    $db->query('SET SESSION lock_wait_timeout=5');
    $db->query('ALTER TABLE '.$quoted.' ENGINE=InnoDB, CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $after=(int)$db->query('SELECT COUNT(*) FROM '.$quoted)->fetch_row()[0];
    if ($before!==$after) { throw new RuntimeException('转换后行数不一致，请停止并从完整副本检查。'); }
    echo $tableName.' 转换完成；行数='.$after."。请继续校验内容、索引和业务页面。\n";
} finally {
    $stmt=$db->prepare('SELECT RELEASE_LOCK(?)'); $stmt->bind_param('s',$lock); $stmt->execute(); $stmt->close();
}
