<?php
if (PHP_SAPI !== 'cli' || getenv('FENGCMS_DEV_SETUP') !== '1') {
    exit("Development setup must run through ./bin/dev up.\n");
}
require __DIR__.'/migrate.php';
require __DIR__.'/../system/common/install_schema.php';

$db = migration_connection();
$tables = $db->query('SHOW TABLES')->fetch_all();
if ($tables) {
    // Never import the destructive legacy installation dump over an existing site.
    if (!$db->query("SHOW TABLES LIKE 'f_dev_meta'")->num_rows) {
        throw new RuntimeException('Database is not empty and has no development marker. Refusing to initialize it.');
    }
    migrate_security($db, 'f_');
    migrate_roles($db, 'f_');
    migrate_content_write($db, 'f_');
    migrate_model_schema($db, 'f_');
    migrate_license_notice($db, 'f_');
    echo "Existing development database retained; administrator password unchanged.\n";
    exit;
}
$password = getenv('FENGCMS_ADMIN_PASSWORD');
if (!$password || strlen($password) < 12) {
    throw new RuntimeException('A development password of at least 12 characters is required.');
}
cms_install_schema($db, 'f_');
migrate_security($db, 'f_');
migrate_roles($db, 'f_');
migrate_content_write($db, 'f_');
migrate_model_schema($db, 'f_');
cms_install_admin($db, 'f_', 'admin', $password);
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
