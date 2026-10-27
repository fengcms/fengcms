<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('TPL_INCLUDE', true);
require dirname(__DIR__).'/system/common/security.php';
class template {
    public static function tpl($path): string { return $GLOBALS['probeTemplate']; }
}
require dirname(__DIR__).'/system/core/controller.php';

$directory = sys_get_temp_dir().'/fengcms-session-'.bin2hex(random_bytes(8));
mkdir($directory, 0700);
$GLOBALS['probeTemplate'] = $directory.'/render.php';
file_put_contents($GLOBALS['probeTemplate'], '<?php echo session_status() === PHP_SESSION_NONE ? "released" : "active"; echo ":".$_SESSION["csrf_token"]; ?>');
session_save_path($directory);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/';

try {
    cms_session_start();
    $token = $_SESSION['csrf_token'];
    $_SESSION['probe'] = 'persisted';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start();
    (new controller())->display('probe');
    $rendered = ob_get_clean();
    if ($rendered !== 'released:'.$token || session_status() !== PHP_SESSION_NONE ||
        $_SESSION['probe'] !== 'persisted') {
        throw new RuntimeException('GET rendering retained the session lock or lost its token.');
    }
    cms_session_start();
    if ($_SESSION['probe'] !== 'persisted' || $_SESSION['csrf_token'] !== $token) {
        throw new RuntimeException('Session state was not saved before rendering.');
    }
    echo "PASS GET rendering releases the session after saving state\n";

    $_SERVER['REQUEST_METHOD'] = 'POST';
    ob_start();
    (new controller())->display('probe');
    $rendered = ob_get_clean();
    if ($rendered !== 'active:'.$token || session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException('POST rendering released a writable session unexpectedly.');
    }
    echo "PASS POST rendering keeps its writable session\n";
} finally {
    cms_session_release();
    foreach (glob($directory.'/*') ?: [] as $file) { unlink($file); }
    rmdir($directory);
}
