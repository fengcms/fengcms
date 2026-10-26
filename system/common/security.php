<?php
require_once __DIR__.'/richtext.php';
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
    if (!cms_admin_valid()) { cms_fail(401, '请先登录后台。'); }
}

function cms_admin_role(): string
{
    return (string)($_SESSION['manage_role'] ?? '');
}

function cms_admin_fingerprint(array $account): string
{
    return hash_hmac('sha256', implode("\n", [
        (string)$account['password'], (string)$account['admin'], (string)$account['role'],
        (string)$account['enabled'], (string)$account['auth_version'],
        (string)($account['login_code_hash'] ?? ''),
    ]), KEYS);
}

function cms_admin_role_required(string $role): void
{
    cms_admin_required();
    if (cms_admin_role() !== $role) { cms_fail(403, '没有权限执行此操作。'); }
}

function cms_admin_clear(): void
{
    foreach (['manage_id','manage','manage_role','auth_at','last_seen','auth_fingerprint'] as $key) { unset($_SESSION[$key]); }
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function cms_admin_valid(): bool
{
    if (empty($_SESSION['manage_id']) || empty($_SESSION['manage'])) { return false; }
    $now=time();
    $login=$_SESSION['auth_at']??null;
    $last=$_SESSION['last_seen']??null;
    if (!is_int($login) || !is_int($last) || $login>$now || $last>$now ||
        $now-$login>43200 || $now-$last>3600 || !is_string($_SESSION['auth_fingerprint']??null)) {
        cms_admin_clear();
        return false;
    }
    try {
        // Standalone editor APIs reach this function without system/app.php.
        if (!defined('DB_HOST')) {
            if (!defined('TPL_INCLUDE')) { define('TPL_INCLUDE', true); }
            if (!defined('ROOT_PATH')) { define('ROOT_PATH', dirname(__DIR__, 2)); }
            if (!defined('APP_PATH')) { define('APP_PATH', ROOT_PATH.'/admin/app'); }
            require_once ROOT_PATH.'/config.php';
        }
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,62}$/D',DB_PREFIX)) { return false; }
        $db=new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME);
        $db->set_charset('utf8mb4');
        $stmt=$db->prepare('SELECT admin,password,role,enabled,auth_version,login_code_hash FROM `'.DB_PREFIX.'manage` WHERE id=? LIMIT 1');
        $id=(int)$_SESSION['manage_id'];
        $stmt->bind_param('i',$id);
        $stmt->execute();
        $row=$stmt->get_result()->fetch_assoc();
        $stmt->close(); $db->close();
        $expected=$row ? cms_admin_fingerprint($row) : null;
        if (!is_string($expected) || !in_array($row['role'], ['super_admin','editor'], true) ||
            (int)$row['enabled']!==1 || !hash_equals($expected,$_SESSION['auth_fingerprint'])) {
            cms_admin_clear();
            return false;
        }
        $_SESSION['manage']=$row['admin'];
        $_SESSION['manage_role']=$row['role'];
    } catch (Throwable $e) {
        error_log('Administrator session verification failed: '.$e);
        return false;
    }
    $_SESSION['last_seen']=$now;
    return true;
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

function cms_account_password_valid(string $password): bool
{
    return mb_check_encoding($password, 'UTF-8') && mb_strlen($password, 'UTF-8') >= 8 && strlen($password) <= 72;
}

// Mark only HTML produced by audited renderers or administrator rich-text fields.
// Ordinary strings (including request input) never acquire this type automatically.
final class CmsHtml implements Stringable
{
    public function __construct(private string $html) {}
    public function __toString(): string { return $this->html; }
}

function cms_html($value): CmsHtml
{
    return $value instanceof CmsHtml ? $value : new CmsHtml((string)($value ?? ''));
}

function cms_template_output($value): string
{
    return $value instanceof CmsHtml ? (string)$value : cms_escape($value);
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

function cms_editor_content_project(string $name): bool
{
    static $allowed=[];
    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D',$name)) { return false; }
    if (array_key_exists($name,$allowed)) { return $allowed[$name]; }
    $module=D('module')->where(['project'=>$name])->getone();
    return $allowed[$name]=$module && (int)$module['type']===1 && (int)$module['status']===1;
}

function cms_editor_category_row(array $row): void
{
    $type=$row['type']??null;
    $project=(string)($row['project']??'');
    if (($type==='module' && cms_editor_content_project($project)) ||
        ($type==='single' && $project==='single') ||
        ($type==='url' && ($project==='' || $project==='single' || cms_editor_content_project($project)))) {
        return;
    }
    cms_fail(403,'没有权限管理此栏目。');
}

function cms_editor_category_tree(int $id): void
{
    static $rows=null, $children=null;
    if ($rows===null) {
        $rows=[]; $children=[];
        foreach (D('classify')->getall() as $row) {
            $rows[(int)$row['id']]=$row;
            $children[(int)$row['classid']][]=(int)$row['id'];
        }
    }
    $pending=[$id]; $seen=[];
    while ($pending) {
        $current=array_pop($pending);
        if (isset($seen[$current]) || count($seen)>10000) { cms_fail(400,'栏目层级不正确。'); }
        $seen[$current]=true;
        $row=$rows[$current]??null;
        if (!$row) { cms_fail(404,'栏目不存在。'); }
        cms_editor_category_row($row);
        foreach ($children[$current]??[] as $child) { $pending[]=$child; }
    }
}

function cms_editor_classify_guard(string $action): void
{
    if (in_array($action,['type','tps'],true) &&
        !in_array($_GET['t']??null,['module','url','single'],true)) { cms_fail(400,'栏目类型不正确。'); }
    if ($action==='genre' && !in_array($_GET['f']??null,['one','batch'],true)) { cms_fail(400,'栏目方式不正确。'); }
    if ($action==='classify' && isset($_GET['p']) && $_GET['p']!=='' && !cms_editor_content_project($_GET['p'])) {
        cms_fail(403,'没有权限读取此模型的栏目。');
    }
    if ($action==='save') {
        $id=$_POST['id']??null;
        if (is_string($id) && $id!=='' && (int)$id>0) {
            cms_editor_category_tree((int)$id);
            if (isset($_POST['type']) || isset($_POST['project']) || isset($_POST['topid'])) {
                cms_fail(403,'不能更改栏目的类型或所属模型。');
            }
        } else {
            $type=$_POST['type']??null;
            $project=$_POST['project']??null;
            if ($type==='module' && (!is_string($project) || !cms_editor_content_project($project))) {
                cms_fail(403,'没有权限创建此模型的栏目。');
            }
            if ($type==='single' && !in_array($project,['single',null],true)) {
                cms_fail(403,'单页栏目参数不正确。');
            }
            if ($type==='url' && (!is_string($project) || !cms_editor_content_project($project))) {
                cms_fail(403,'外链栏目所属模型不正确。');
            }
            if (!in_array($type,['module','single','url'],true)) { cms_fail(400,'栏目类型不正确。'); }
        }
        $parent=$_POST['classid']??'0';
        if (!is_string($parent) || !preg_match('/^[0-9]{1,10}$/D',$parent)) { cms_fail(400,'父栏目不正确。'); }
        if ((int)$parent>0) { cms_editor_category_tree((int)$parent); }
    } elseif (in_array($action,['delete','emptys','update'],true)) {
        $id=$_GET['id']??null;
        if (!is_string($id) || (int)$id<1) { cms_fail(400,'栏目编号不正确。'); }
        cms_editor_category_tree((int)$id);
    } elseif ($action==='batch') {
        $ids=$_POST['classify']??null;
        if (!is_array($ids) || count($ids)>1000) { cms_fail(400,'栏目编号不正确。'); }
        foreach ($ids as $id) {
            if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,9}$/D',$id)) { cms_fail(400,'栏目编号不正确。'); }
            cms_editor_category_tree((int)$id);
        }
    } elseif ($action==='order') {
        $ids=$_POST['id']??null;
        if (!is_array($ids)) { cms_fail(400,'栏目编号不正确。'); }
        foreach ($ids as $id) { cms_editor_category_tree((int)$id); }
    }
}

function cms_route_guard(string $controller, string $action, bool $admin): void
{
    foreach ($_GET as $key => $value) {
        if (!is_string($value)) { cms_fail(400, '查询参数不正确。'); }
        if (in_array($key, ['id','classid','page'], true) && !preg_match('/^[0-9]{1,10}$/D', $value)) {
            cms_fail(400, '数字参数不正确。');
        }
        if ($key === 'project' && !(!$admin && $controller === 'search' && $value === '') &&
            !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D', $value)) {
            cms_fail(400, '模型名称不正确。');
        }
    }
    foreach (['id','classid','module_id'] as $key) {
        if ($key==='id' && $admin && is_array($_POST[$key]??null) &&
            (($controller==='classify' && $action==='order') || ($controller==='fieldmanage' && $action==='sort'))) {
            $ids=$_POST[$key];
            if (!array_is_list($ids) || count($ids)>1000) { cms_fail(400, '排序记录不正确。'); }
            foreach ($ids as $id) {
                if (!is_string($id) || !preg_match('/^[0-9]{1,10}$/D',$id) || (int)$id<1) { cms_fail(400, '排序记录不正确。'); }
            }
            continue;
        }
        if (isset($_POST[$key]) && (!is_string($_POST[$key]) || !preg_match('/^[0-9]{0,10}$/D', $_POST[$key]))) {
            cms_fail(400, '数字参数不正确。');
        }
    }
    $read = $admin ? [
        'home' => 'index main execution', 'base' => 'index execution',
        'manage' => 'password', 'account' => 'index add', 'module' => 'index add batchadd update execution',
        'classify' => 'index add shift sorts update classify type tps genre execution',
        'fieldmanage' => 'index system add update execution',
        'modulemanage' => 'index add update load execution elicit',
        'message' => 'index reply execution',
        'signup' => 'index signup detail signupinfo execution',
        'recover' => 'index execution', 'tplmanage' => 'index add update execution',
			'dbmanage' => 'index dbbackup dbmanages dbimport', 'safe' => 'index',
    ] : [
        'home' => 'index vccore validate', 'classify' => 'index', 'content' => 'index',
        'down' => 'index', 'map' => 'index', 'message' => 'index add',
        'search' => 'index', 'single' => 'index', 'tags' => 'index',
    ];
    $write = $admin ? [
        'admin' => 'index', 'manage' => 'save quit', 'account' => 'create toggle reset delete', 'base' => 'save',
        'module' => 'deleteone recoverone batch shift save batchsave',
        'classify' => 'order delete emptys batch save',
        'fieldmanage' => 'systemsave save enable sort delete',
        'modulemanage' => 'disabled delete save preview loading',
        'message' => 'deleteone deletebatch save', 'signup' => 'deleteone deletebatch save',
        'recover' => 'recoverone delete batch', 'tplmanage' => 'delete save',
		'dbmanage' => 'create download delete previewimport runimport', 'cache' => 'index',
    ] : ['message' => 'save'];
    $readable = in_array($action, explode(' ', $read[$controller] ?? ''), true);
    $writable = in_array($action, explode(' ', $write[$controller] ?? ''), true);
    if (!$readable && !$writable) { cms_fail(404, '页面不存在。'); }
    if ($admin && $controller !== 'admin') {
        if (!cms_admin_valid()) {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
                header('Location: /admin/login.php', true, 302);
                exit;
            }
            cms_fail(401, '请先登录后台。');
        }
        if (cms_admin_role()==='editor') {
            $editorRead = [
                'home' => 'index main execution', 'manage' => 'password',
                'module' => 'index add batchadd update execution',
                'classify' => 'index add shift sorts update classify type tps genre execution',
                'message' => 'index reply execution', 'recover' => 'index execution',
            ];
            $editorWrite = [
                'manage' => 'save quit', 'module' => 'deleteone recoverone batch shift save batchsave',
                'classify' => 'order delete emptys batch save',
                'message' => 'deleteone deletebatch save', 'recover' => 'recoverone delete batch',
            ];
            if (!in_array($action, explode(' ', $editorRead[$controller] ?? ''), true) &&
                !in_array($action, explode(' ', $editorWrite[$controller] ?? ''), true)) {
                cms_fail(403, '没有权限访问此功能。');
            }
            if ($controller==='classify') { cms_editor_classify_guard($action); }
        }
        if ($controller==='module' || ($controller==='recover' && isset($_GET['project']))) {
            $projectName=$_GET['project']??'';
            if ($projectName==='') { cms_fail(400, '模型名称不正确。'); }
            $project=D('module')->where(['project'=>$projectName])->getone();
            if (!$project || (int)$project['status']<0) { cms_fail(404, '模型不存在。'); }
            if (cms_admin_role()==='editor' &&
                ((int)$project['status']!==1 || !in_array((int)$project['type'], [0,1], true) ||
                ($controller==='recover' && ((int)$project['type']!==1 || (int)$project['recover']!==1)))) {
                cms_fail(403, '没有权限访问此模型。');
            }
        }
    }
    if ($writable || ($admin && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST')) {
        cms_post_required();
    }
    if ($admin && (($controller==='module' && $action==='batch') ||
        (($controller==='message' || $controller==='signup') && $action==='deletebatch'))) {
        // Keep this below PHP's usual max_input_vars (1000), so truncated oversized forms fail closed.
        $_POST['record']=cms_id_list($_POST['record']??[], '记录编号', 500);
    }
    if ($admin && $controller==='classify' && $action==='batch') {
        $_POST['classify']=cms_id_list($_POST['classify']??[], '栏目编号', 500);
    }
    unset($_POST['_csrf']);
}

function cms_id_list($value, string $label, int $max=1000): array
{
    if (!is_array($value) || !array_is_list($value) || count($value)>$max) {
        cms_fail(400,$label.'不正确。');
    }
    $ids=[];
    foreach ($value as $item) {
        if (!is_string($item) || !preg_match('/^[1-9][0-9]{0,9}$/D',$item)) {
            cms_fail(400,$label.'不正确。');
        }
        $ids[]=(int)$item;
    }
    return array_values(array_unique($ids));
}

function cms_sort_pairs($ids, $sorts): array
{
    if (!is_array($ids) || !is_array($sorts) || !array_is_list($ids) || !array_is_list($sorts) ||
        count($ids)!==count($sorts) || count($ids)>1000) { cms_fail(400, '排序参数不正确。'); }
    $pairs=[];
    foreach ($ids as $key=>$id) {
        $sort=$sorts[$key];
        if (!is_string($id) || !preg_match('/^[0-9]{1,10}$/D',$id) || (int)$id<1 ||
            !is_string($sort) || !preg_match('/^[0-9]{1,10}$/D',$sort) || (int)$sort>2147483647) {
            cms_fail(400, '排序参数不正确。');
        }
        $pairs[]=[(int)$id,(int)$sort];
    }
    return $pairs;
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
