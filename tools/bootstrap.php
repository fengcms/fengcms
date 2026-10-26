<?php
if (PHP_SAPI !== 'cli' || getenv('FENGCMS_DEV_SETUP') !== '1') {
    exit("Development setup must run through ./bin/dev up.\n");
}
require __DIR__.'/migrate.php';

$db = migration_connection();
$tables = $db->query('SHOW TABLES')->fetch_all();
if ($tables) {
    // Never import the destructive legacy installation dump over an existing site.
    if (!$db->query("SHOW TABLES LIKE 'f_dev_meta'")->num_rows) {
        throw new RuntimeException('Database is not empty and has no development marker. Refusing to initialize it.');
    }
    migrate_security($db, 'f_');
    echo "Existing development database retained; administrator password unchanged.\n";
    exit;
}
$password = getenv('FENGCMS_ADMIN_PASSWORD');
if (!$password || strlen($password) < 12) {
    throw new RuntimeException('A development password of at least 12 characters is required.');
}
$sql = ltrim(file_get_contents(__DIR__.'/../install/install.sql'), "\xEF\xBB\xBF");
$sql = str_replace(['#prefix_', ' ROW_FORMAT=COMPACT'], ['f_', ''], $sql);
$db->multi_query($sql);
do {
    if ($result = $db->store_result()) {
        $result->free();
    }
} while ($db->more_results() && $db->next_result());
migrate_security($db, 'f_');
$stmt = $db->prepare('UPDATE f_manage SET password=? WHERE id=1');
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt->bind_param('s', $hash);
$stmt->execute();
$db->query('CREATE TABLE f_dev_meta (id int PRIMARY KEY) ENGINE=InnoDB');
$db->query('INSERT INTO f_dev_meta VALUES (1)');

foreach (['app/cache/data', 'admin/app/cache/data', 'admin/app/dbbackup', 'upload'] as $dir) {
    $path = __DIR__.'/../'.$dir;
    if (!is_dir($path)) {
        mkdir($path, 0770, true);
    }
    // FPM runs as www-data; named volumes initially belong to root.
    chown($path, 'www-data');
    chgrp($path, 'www-data');
    if (str_ends_with($dir, '/data')) {
        chown(dirname($path), 'www-data');
        chgrp(dirname($path), 'www-data');
    }
}
echo "Development database initialized from bundled sample data.\n";
