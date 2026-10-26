<?php
// Shared by front controllers, login and editor endpoints.
function cms_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) { return; }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.upload_progress.enabled', '0');
    session_name('fengcms_session');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function cms_fail(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['status' => 'n', 'error' => 1, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function cms_csrf_check(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        cms_fail(403, '请求校验失败，请刷新页面后重试。');
    }
}

function cms_post_required(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        cms_fail(405, '此操作需要 POST 请求。');
    }
    cms_csrf_check();
}

function cms_admin_required(): void
{
    if (empty($_SESSION['manage_id']) || empty($_SESSION['manage'])) {
        cms_fail(401, '请先登录后台。');
    }
}

function cms_password_verify(string $password, string $hash): bool
{
    if (preg_match('/^[a-f0-9]{32}$/D', $hash)) {
        if (hash_equals($hash, md5(md5(md5($password))))) { return true; }
        // The old login/password-change handlers entity-encoded special
        // characters before hashing. Preserve those accounts during migration.
        return function_exists('lib_replace_end_tag') &&
            hash_equals($hash, md5(md5(md5(lib_replace_end_tag($password)))));
    }
    return password_verify($password, $hash);
}

function cms_escape($value): string
{
    return is_scalar($value) ? htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';
}

function cms_safe_url($value): string
{
    if (!is_string($value) || preg_match('/[\x00-\x20\x7f]/', $value) || str_contains($value, '\\')) { return ''; }
    if (str_starts_with($value, '//')) { return ''; }
    $scheme = parse_url($value, PHP_URL_SCHEME);
    return $scheme && !in_array(strtolower($scheme), ['http','https','mailto','tel'], true) ? '' : $value;
}

function cms_route_guard(string $controller, string $action, bool $admin): void
{
    foreach ($_GET as $key => $value) {
        if (!is_string($value)) { cms_fail(400, '查询参数不正确。'); }
        if (in_array($key, ['id','classid','page'], true) && !preg_match('/^[0-9]{1,10}$/D', $value)) {
            cms_fail(400, '数字参数不正确。');
        }
        if ($key === 'project' && !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D', $value)) {
            cms_fail(400, '模型名称不正确。');
        }
    }
    foreach (['id','classid','module_id'] as $key) {
        if (isset($_POST[$key]) && (!is_string($_POST[$key]) || !preg_match('/^[0-9]{0,10}$/D', $_POST[$key]))) {
            cms_fail(400, '数字参数不正确。');
        }
    }
    if (isset($_GET['project']) && in_array($controller, ['module','recover','content'], true)) {
        $project = D('module')->where('project=? AND status>=0', [$_GET['project']])->getone();
        if (!$project) { cms_fail(404, '模型不存在。'); }
    }
    $read = $admin ? [
        'home' => 'index main execution', 'base' => 'index execution',
        'manage' => 'password', 'module' => 'index add batchadd update execution',
        'classify' => 'index add shift sorts update classify type tps genre execution',
        'fieldmanage' => 'index system add update execution',
        'modulemanage' => 'index add update load execution elicitcution',
        'message' => 'index reply execution',
        'signup' => 'index signup detail signupinfo execution',
        'recover' => 'index execution', 'tplmanage' => 'index add update execution',
        'dbmanage' => 'index dbbackup dbregain dbmanages', 'safe' => 'index serverversion',
    ] : [
        'home' => 'index vccore validate', 'classify' => 'index', 'content' => 'index',
        'down' => 'index', 'map' => 'index', 'message' => 'index add',
        'search' => 'index', 'single' => 'index', 'tags' => 'index',
    ];
    $write = $admin ? [
        'admin' => 'index', 'manage' => 'save quit', 'base' => 'save',
        'module' => 'deleteone recoverone batch shift save batchsave',
        'classify' => 'order delete emptys batch save',
        'fieldmanage' => 'systemsave save enable sort delete',
        'modulemanage' => 'disabled delete save elicit loading',
        'message' => 'deleteone deletebatch save', 'signup' => 'deleteone deletebatch save',
        'recover' => 'recoverone delete batch', 'tplmanage' => 'delete save',
        'dbmanage' => 'delete save', 'cache' => 'index',
    ] : ['message' => 'save'];
    $readable = in_array($action, explode(' ', $read[$controller] ?? ''), true);
    $writable = in_array($action, explode(' ', $write[$controller] ?? ''), true);
    if (!$readable && !$writable) { cms_fail(404, '页面不存在。'); }
    if ($admin && $controller !== 'admin') {
        if (empty($_SESSION['manage_id']) || empty($_SESSION['manage'])) {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
                header('Location: /admin/login.php', true, 302);
                exit;
            }
            cms_admin_required();
        }
    }
    if ($writable || ($admin && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')) {
        cms_post_required();
    }
    unset($_POST['_csrf']);
}

function cms_path_within(string $root, string $relative, bool $mustExist = true): string
{
    $base = realpath($root);
    if (!$base || str_contains($relative, "\0") || str_contains($relative, '\\') ||
        preg_match('~(?:^|/)\.\.(?:/|$)~', $relative)) {
        cms_fail(400, '文件路径不正确。');
    }
    $candidate = $base.'/'.ltrim($relative, '/');
    $resolved = realpath($candidate);
    if (!$resolved && !$mustExist) {
        $parent = realpath(dirname($candidate));
        $resolved = $parent ? $parent.'/'.basename($candidate) : false;
    }
    if (!$resolved || !str_starts_with($resolved, $base.'/')) {
        cms_fail(404, '文件不存在。');
    }
    return $resolved;
}
