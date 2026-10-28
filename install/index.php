<?php
// One-time, shared-host web installer. Development containers disable this endpoint.
$root = dirname(__DIR__);
require_once $root.'/system/common/config_loader.php';
require_once $root.'/system/common/runtime_requirements.php';
$installedConfig=is_file($root.'/config.local.php') || is_link($root.'/config.local.php') ||
    cms_config_is_private_file($root.'/config.php');
if (getenv('FENGCMS_DISABLE_INSTALL') === '1' || $installedConfig) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

function installer_escape($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function installer_page($title, $body)
{
    echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>'.installer_escape($title).' · FengCMS 安装</title>';
    echo '<style>body{margin:0;background:#f4f6fa;color:#243044;font:16px/1.6 system-ui,sans-serif}.wrap{max-width:700px;margin:6vh auto;padding:30px;background:#fff;border-radius:12px;box-shadow:0 8px 30px #18263d12}h1{margin:0 0 8px;font-size:28px}h2{font-size:20px}p{margin:12px 0}.note{background:#edf4ff;padding:14px;border-radius:8px}.error{background:#fff0ef;color:#9b2727;padding:14px;border-radius:8px}label{display:block;margin:16px 0 5px;font-weight:600}input{box-sizing:border-box;width:100%;padding:10px;border:1px solid #b9c5d4;border-radius:6px;font:inherit}button{margin-top:20px;background:#265b9d;color:#fff;border:0;border-radius:6px;padding:10px 22px;font:inherit;cursor:pointer}button:disabled{opacity:.5}code{background:#f1f3f6;padding:2px 4px;overflow-wrap:anywhere}.small{color:#53657a;font-size:14px}li{margin:5px 0}a{color:#265b9d}</style></head><body><main class="wrap">';
    echo '<h1>FengCMS 首次安装</h1><p class="small">仅用于新站点。已有站点请按迁移文档升级。</p>';
    echo '<h2>'.installer_escape($title).'</h2>'.$body.'</main></body></html>';
}

function installer_post_value($name, $trim = true)
{
    $value = $_POST[$name] ?? '';
    return is_string($value) ? ($trim ? trim($value) : $value) : '';
}

function installer_requirements($root, $https)
{
    $problems = [];
    if (!$https) { $problems[] = '请先为站点启用 HTTPS，再提交任何密码。'; }
    if (!cms_php_version_supported(PHP_VERSION_ID)) {
        $problems[] = '当前 PHP 版本为 '.PHP_VERSION.'；本版安装器要求 '.cms_php_version_requirement_label().'。';
    }
    foreach (['mysqli', 'mbstring', 'gd', 'fileinfo', 'json'] as $extension) {
        if (!extension_loaded($extension)) { $problems[] = '缺少 PHP 扩展：'.$extension; }
    }
    if (!is_writable($root)) { $problems[] = '站点根目录不可写，无法创建私有配置文件；请在主机面板调整属主或最小必要权限。'; }
    foreach (['upload', 'app/cache', 'admin/app/cache', 'admin/app/dbbackup', 'var/runtime'] as $relative) {
        $path = $root.'/'.$relative;
        $parent = dirname($path);
        while (!is_dir($parent) && $parent !== dirname($parent)) { $parent = dirname($parent); }
        if (is_dir($path) ? !is_writable($path) : !is_writable($parent)) {
            $problems[] = $relative.' 不可写或无法创建；不要使用 777 权限。';
        }
    }
    return $problems;
}

function installer_make_directories($root)
{
    foreach (['upload', 'app/cache', 'admin/app/cache', 'admin/app/dbbackup', 'var/runtime'] as $relative) {
        $path = $root.'/'.$relative;
        if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create a required directory.');
        }
        if (!is_writable($path)) { throw new RuntimeException('A required directory is not writable.'); }
    }
}

function installer_prepare_config($root, $values)
{
    $temporary = $root.'/.config.php.'.bin2hex(random_bytes(8)).'.tmp';
    $contents = "<?php\n// FengCMS private configuration data v1.\nreturn ".var_export($values, true).";\n";
    $previousUmask = umask(0077);
    $handle = fopen($temporary, 'x');
    umask($previousUmask);
    if ($handle === false) { throw new RuntimeException('Unable to create private configuration.'); }
    if (!chmod($temporary, 0600)) {
        fclose($handle);
        unlink($temporary);
        throw new RuntimeException('Unable to protect private configuration.');
    }
    try {
        $length = strlen($contents);
        for ($offset = 0; $offset < $length;) {
            $written = fwrite($handle, substr($contents, $offset));
            if ($written === false || $written === 0) { throw new RuntimeException('Unable to write private configuration.'); }
            $offset += $written;
        }
        fflush($handle);
    } catch (Throwable $error) {
        fclose($handle);
        unlink($temporary);
        throw $error;
    }
    fclose($handle);
    return $temporary;
}

$transportSecure = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
$https = $transportSecure;
if (getenv('FENGCMS_INSTALL_ALLOW_HTTP') === '1') { $https = true; } // Isolated test environments only.
$secretFile = __DIR__.'/authorize.php';
$secret = is_file($secretFile) ? require $secretFile : null;
$secretReady = is_string($secret) && preg_match('/^[A-Za-z0-9_-]{32,128}$/D', $secret) && strpos($secret, 'REPLACE_WITH_') !== 0;
if (!$secretReady) {
    http_response_code(503);
    installer_page('等待站长授权', '<p>请先通过主机文件管理器或 SFTP，将 <code>install/authorize.example.php</code> 复制为 <code>install/authorize.php</code>，把占位符改成你自己生成的至少 32 位随机密钥，再刷新此页。安装完成后删除该文件。</p><p class="note">此操作确认你拥有站点文件的写入权限，防止访客抢先安装。</p>');
    exit;
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('fengcms_install');
session_set_cookie_params(['lifetime' => 0, 'path' => '/install/', 'secure' => $transportSecure, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
$csrf = installer_escape($_SESSION['csrf']);
$error = '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET' && $method !== 'POST') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

if ($method === 'POST') {
    $submitted = installer_post_value('_csrf', false);
    if (!hash_equals($_SESSION['csrf'], $submitted)) {
        http_response_code(403);
        installer_page('请求被拒绝', '<p class="error">请求校验失败，请刷新安装页面后重试。</p>');
        exit;
    }
    if (installer_post_value('action') === 'unlock' && $https) {
        $attempts = (int)($_SESSION['attempts'] ?? 0);
        $blockedUntil = (int)($_SESSION['blocked_until'] ?? 0);
        if ($blockedUntil > time()) {
            $error = '密钥尝试次数过多，请稍后再试。';
        } elseif (hash_equals($secret, installer_post_value('install_key', false))) {
            session_regenerate_id(true);
            $_SESSION['authorized'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            $_SESSION['attempts'] = 0;
            header('Location: index.php', true, 303);
            exit;
        } else {
            $_SESSION['attempts'] = $attempts + 1;
            if ($_SESSION['attempts'] >= 5) { $_SESSION['blocked_until'] = time() + 900; }
            $error = '安装密钥不正确。';
        }
    }
}

if (empty($_SESSION['authorized'])) {
    if (!$https) {
        http_response_code(403);
        installer_page('需要 HTTPS', '<p class="error">安装器只接受 HTTPS 连接。请在主机面板为站点启用 HTTPS 后重新访问。</p>');
        exit;
    }
    $message = $error ? '<p class="error">'.installer_escape($error).'</p>' : '';
    installer_page('验证站长身份', $message.'<p>输入你刚在 <code>install/authorize.php</code> 中设置的一次性安装密钥。</p><form method="post" action="index.php"><input type="hidden" name="_csrf" value="'.$csrf.'"><input type="hidden" name="action" value="unlock"><label for="install_key">安装密钥</label><input id="install_key" name="install_key" type="password" required autocomplete="off" maxlength="128"><button type="submit">继续</button></form>');
    exit;
}

$problems = installer_requirements($root, $https);
$action = installer_post_value('action');
if ($method === 'POST' && ($action === 'review' || $action === 'install')) {
    $form = $action === 'review' ? [
        'db_host' => installer_post_value('db_host'), 'db_user' => installer_post_value('db_user'),
        'db_pass' => installer_post_value('db_pass', false), 'db_name' => installer_post_value('db_name'),
        'db_prefix' => installer_post_value('db_prefix'), 'admin_user' => installer_post_value('admin_user'),
        'admin_pass' => installer_post_value('admin_pass', false), 'admin_confirm' => installer_post_value('admin_confirm', false),
    ] : ($_SESSION['install_form'] ?? []);
    $host = $form['db_host'] ?? '';
    $user = $form['db_user'] ?? '';
    $password = $form['db_pass'] ?? '';
    $database = $form['db_name'] ?? '';
    $prefix = $form['db_prefix'] ?? '';
    $admin = $form['admin_user'] ?? '';
    $adminPassword = $form['admin_pass'] ?? '';
    $confirm = $form['admin_confirm'] ?? '';
    if ($problems) {
        $error = '安装环境不满足要求，请先处理下列问题。';
    } elseif (!preg_match('/^[A-Za-z0-9._:-]{1,200}$/D', $host) || strlen($user) < 1 || strlen($user) > 128 || preg_match('/[\x00-\x1f\x7f]/', $user) ||
        strlen($password) > 512 || !preg_match('/^[A-Za-z0-9_]{1,64}$/D', $database) ||
        !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,29}$/D', $prefix) ||
        !preg_match('/^[A-Za-z][A-Za-z0-9_.-]{2,63}$/D', $admin) ||
        strlen($adminPassword) < 12 || strlen($adminPassword) > 72 || $adminPassword !== $confirm) {
        $error = '输入不符合要求：请检查数据库信息、表前缀及管理员密码（12 至 72 字节，两次一致）。请先填写信息并核对目标。';
    } elseif ($action === 'review') {
        $schema = file_get_contents(__DIR__.'/install.sql');
        if ($schema === false || !preg_match_all('/^CREATE\s+(TABLE|VIEW)\s+`#prefix_([A-Za-z0-9_]+)`/mi', $schema, $objects) ||
            preg_match_all('/\bCREATE\s+(?:TABLE|VIEW)\b/i', $schema) !== count($objects[0])) {
            $error = '无法读取安装对象清单，请检查安装包是否完整。';
        } else {
            $_SESSION['install_form'] = $form;
            $list = '';
            foreach ($objects[2] as $index => $suffix) {
                $list .= '<li>'.installer_escape(strtoupper($objects[1][$index])).' <code>'.installer_escape($prefix.$suffix).'</code></li>';
            }
            $summary = '<p class="note">请确认将向数据库 <strong>'.installer_escape($database).'</strong> 写入下列对象，表前缀为 <code>'.installer_escape($prefix).'</code>，管理员用户名为 <strong>'.installer_escape($admin).'</strong>。此时尚未写入数据库。</p>';
            $summary .= '<ul>'.$list.'</ul><p>继续前请再次核对数据库和表前缀；已有对象会被拒绝，执行中失败可能留下部分新对象。</p>';
            $summary .= '<form method="post" action="index.php"><input type="hidden" name="_csrf" value="'.installer_escape($_SESSION['csrf']).'"><input type="hidden" name="action" value="install"><button type="submit">确认并开始安装</button></form><p><a href="index.php">返回修改</a></p>';
            installer_page('确认安装目标', $summary);
            exit;
        }
    } else {
        unset($_SESSION['install_form']);
        $db = null;
        $lockFile = null;
        $databaseLock = null;
        $temporary = null;
        $schemaStarted = false;
        try {
            installer_make_directories($root);
            $lockFile = fopen($root.'/var/runtime/install.lock', 'c');
            if (!$lockFile || !flock($lockFile, LOCK_EX | LOCK_NB)) { throw new RuntimeException('Another installation is running.'); }
            if (!chmod($root.'/var/runtime/install.lock', 0600)) { throw new RuntimeException('Unable to protect installation lock.'); }
            if (is_file($root.'/config.local.php') || is_link($root.'/config.local.php') ||
                cms_config_is_private_file($root.'/config.php')) {
                throw new RuntimeException('Site is already installed.');
            }
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $db = new mysqli($host, $user, $password, $database);
            $db->set_charset('utf8mb4');
            define('FENGCMS_INSTALLER', true);
            require_once $root.'/system/common/install_schema.php';
            $databaseLock = cms_install_lock($db, $prefix);
            if (!cms_install_prefix_available($db, $prefix)) { throw new RuntimeException('The selected table prefix is already in use.'); }
            $adminCode = sprintf('%04d', random_int(0, 9999));
            $config = ['db_host' => $host, 'db_user' => $user, 'db_pass' => $password, 'db_name' => $database,
                'db_prefix' => $prefix, 'admin_code' => $adminCode, 'app_key' => bin2hex(random_bytes(32)), 'url_type' => 0];
            $temporary = installer_prepare_config($root, $config);
            $schemaStarted = true;
            cms_install_schema($db, $prefix);
            cms_install_admin($db, $prefix, $admin, $adminPassword);
            if (is_file($root.'/config.local.php') || is_link($root.'/config.local.php') ||
                cms_config_is_private_file($root.'/config.php') ||
                is_link($root.'/config.php') || !rename($temporary, $root.'/config.php')) {
                throw new RuntimeException('Unable to publish private configuration.');
            }
            $temporary = null;
            @unlink($secretFile);
            unset($_SESSION['authorized']);
            installer_page('安装完成', '<p class="note">站点已完成首次安装。管理员用户名：<strong>'.installer_escape($admin).'</strong>；后台认证码：<strong>'.installer_escape($adminCode).'</strong>。请妥善保存认证码，勿公开管理员密码。</p><p>请确认 <code>install/authorize.php</code> 已删除，并通过 <a href="/admin/login.php">后台登录页</a>登录。后续版本更新请使用 GitHub Releases 的手动升级说明。</p>');
            exit;
        } catch (Throwable $failure) {
            error_log('FengCMS installation failed: '.get_class($failure));
            $error = $schemaStarted
                ? '安装未完成。目标表前缀可能已有部分对象；请先检查本次创建的对象并恢复备份，不要直接重试或覆盖现有数据。'
                : '安装未开始写入数据库。请核对连接信息、目标前缀和目录权限后重试。';
        } finally {
            if ($temporary && is_file($temporary)) { @unlink($temporary); }
            if ($databaseLock && $db instanceof mysqli) { try { cms_install_unlock($db, $databaseLock); } catch (Throwable $ignored) {} }
            if ($db instanceof mysqli) { $db->close(); }
            if (is_resource($lockFile)) { flock($lockFile, LOCK_UN); fclose($lockFile); }
        }
    }
}

$body = $error ? '<p class="error">'.installer_escape($error).'</p>' : '';
if ($problems) {
    $body .= '<p class="error">环境检查未通过：</p><ul>';
    foreach ($problems as $problem) { $body .= '<li>'.installer_escape($problem).'</li>'; }
    $body .= '</ul>';
} else {
    $body .= '<p class="note">环境检查通过。数据库必须由主机面板预先创建；目标表前缀不能已有表或视图。安装器不会删除现有数据。</p>';
}
$body .= '<form method="post" action="index.php"><input type="hidden" name="_csrf" value="'.installer_escape($_SESSION['csrf']).'"><input type="hidden" name="action" value="review">';
$fields = [
    ['db_host', '数据库主机', 'text', 'localhost'],
    ['db_user', '数据库用户名', 'text', ''],
    ['db_pass', '数据库密码', 'password', ''],
    ['db_name', '数据库名称', 'text', ''],
    ['db_prefix', '数据表前缀', 'text', 'f_'],
    ['admin_user', '管理员用户名', 'text', 'admin'],
    ['admin_pass', '管理员密码（12 至 72 字节）', 'password', ''],
    ['admin_confirm', '再次输入管理员密码', 'password', ''],
];
foreach ($fields as $field) {
    [$name, $label, $type, $placeholder] = $field;
    $body .= '<label for="'.$name.'">'.installer_escape($label).'</label><input id="'.$name.'" name="'.$name.'" type="'.$type.'" placeholder="'.installer_escape($placeholder).'" required autocomplete="off" maxlength="512">';
}
$body .= '<button type="submit"'.($problems ? ' disabled' : '').'>核对安装目标</button></form><p class="small">确认页会列出将创建的表和视图。安装失败时不会自动删除数据库中的部分对象。请勿对现有站点使用本向导。</p>';
installer_page('环境检查与安装信息', $body);
