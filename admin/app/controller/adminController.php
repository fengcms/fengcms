<?php
defined('TPL_INCLUDE') or die('Restricted access');

class adminController extends Controller
{
    public function index()
    {
        $user = $_POST['user'] ?? '';
        $password = $_POST['password'] ?? '';
        $code = $_POST['admincode'] ?? '';
        if (!is_string($user) || !is_string($password) || !is_string($code) ||
            strlen($user) > 255 || strlen($password) > 1024 || strlen($code) > 128) {
            cms_fail(400, '登录参数不正确。');
        }
        $db = new db_mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $attempts = model::identifier(DB_PREFIX.'login_attempts');
        $now = time();
        $expires = $now + 900;
        $buckets = [
            hash_hmac('sha256', 'ip:'.($_SERVER['REMOTE_ADDR'] ?? ''), KEYS) => 60,
            hash_hmac('sha256', 'user:'.mb_strtolower($user, 'UTF-8'), KEYS) => 5,
        ];
        foreach ($buckets as $bucket => $limit) {
            $db->query("INSERT INTO $attempts (bucket, attempts, expires_at) VALUES (?,1,?)
                ON DUPLICATE KEY UPDATE attempts=IF(expires_at<=?,1,attempts+1),
                expires_at=IF(expires_at<=?,VALUES(expires_at),expires_at)", [$bucket, $expires, $now, $now]);
            $row = $db->assoc($db->query("SELECT attempts FROM $attempts WHERE bucket=?", [$bucket]));
            if ($row['attempts'] > $limit) {
                header('Retry-After: 900');
                cms_fail(429, '登录尝试过多，请稍后重试。');
            }
        }
        $db->query("DELETE FROM $attempts WHERE expires_at<?", [$now]);
        $table = model::identifier(DB_PREFIX.'manage');
        $account = $db->assoc($db->query("SELECT id,admin,password,role,enabled,auth_version,login_code_hash FROM $table WHERE admin=? LIMIT 1", [$user]));
        $valid = cms_password_verify($password, $account['password'] ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        $codeValid = $account && !empty($account['login_code_hash'])
            ? password_verify(strlen($code)===4 ? strtoupper($code) : $code, $account['login_code_hash'])
            : ($account && $account['role']==='super_admin' && hash_equals((string)ADMIN_CODE, $code));
        if (!$account || !$valid || !$codeValid || (int)$account['enabled']!==1 ||
            !in_array($account['role'], ['super_admin','editor'], true)) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['status' => 'n']);
            return;
        }
        if (password_needs_rehash($account['password'], PASSWORD_DEFAULT)) {
            $account['password']=password_hash($password, PASSWORD_DEFAULT);
            $db->query("UPDATE $table SET password=? WHERE id=?", [$account['password'], (int)$account['id']]);
        }
        foreach ($buckets as $bucket => $limit) {
            $db->query("DELETE FROM $attempts WHERE bucket=?", [$bucket]);
        }
        session_regenerate_id(true);
        $_SESSION['manage'] = $account['admin'];
        $_SESSION['manage_role'] = $account['role'];
        $_SESSION['manage_id'] = (int)$account['id'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['auth_at'] = time();
        $_SESSION['last_seen'] = time();
        $_SESSION['auth_fingerprint'] = cms_admin_fingerprint($account);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode(['status' => 'y']);
    }
}
