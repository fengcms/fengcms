<?php
// Shared by the one-time web installer and the disposable development setup.
if (!defined('FENGCMS_INSTALLER') && PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function cms_install_valid_prefix(string $prefix): bool
{
    return (bool)preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,29}$/D', $prefix);
}

function cms_install_prefix_available(mysqli $db, string $prefix): bool
{
    if (!cms_install_valid_prefix($prefix)) { return false; }
    $result = $db->query('SHOW FULL TABLES');
    while ($row = $result->fetch_row()) {
        if (strncasecmp($row[0], $prefix, strlen($prefix)) === 0) {
            $result->free();
            return false;
        }
    }
    $result->free();
    return true;
}

function cms_install_lock(mysqli $db, string $prefix): string
{
    $database = (string)$db->query('SELECT DATABASE()')->fetch_row()[0];
    $lock = 'fengcms_install_'.substr(hash('sha256', $database.':'.$prefix), 0, 40);
    $stmt = $db->prepare('SELECT GET_LOCK(?, 0)');
    $stmt->bind_param('s', $lock);
    $stmt->execute();
    $acquired = (int)$stmt->get_result()->fetch_row()[0];
    $stmt->close();
    if ($acquired !== 1) { throw new RuntimeException('Another installation is running.'); }
    return $lock;
}

function cms_install_unlock(mysqli $db, string $lock): void
{
    $stmt = $db->prepare('SELECT RELEASE_LOCK(?)');
    $stmt->bind_param('s', $lock);
    $stmt->execute();
    $stmt->close();
}

function cms_install_schema(mysqli $db, string $prefix): void
{
    if (!cms_install_prefix_available($db, $prefix)) {
        throw new RuntimeException('The selected table prefix is already in use.');
    }
    $sql = file_get_contents(dirname(__DIR__, 2).'/install/install.sql');
    if ($sql === false) { throw new RuntimeException('The installation schema is missing.'); }
    $sql = ltrim($sql, "\xEF\xBB\xBF");
    if (preg_match('/\bDROP\s+(?:TABLE|DATABASE|VIEW)\b/i', $sql)) {
        throw new RuntimeException('Destructive SQL is forbidden in the installation schema.');
    }
    $sql = str_replace(['#prefix_', ' ROW_FORMAT=COMPACT'], [$prefix, ''], $sql);
    $db->multi_query($sql);
    do {
        $result = $db->store_result();
        if ($result instanceof mysqli_result) { $result->free(); }
    } while ($db->more_results() && $db->next_result());
}

function cms_install_admin(mysqli $db, string $prefix, string $username, string $password): void
{
    if (!cms_install_valid_prefix($prefix)) { throw new InvalidArgumentException('Invalid table prefix.'); }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('UPDATE `'.$prefix.'manage` SET admin=?, password=?, datetime=NOW() WHERE id=1');
    $stmt->bind_param('ss', $username, $hash);
    $stmt->execute();
    if ($stmt->affected_rows !== 1) { throw new RuntimeException('Administrator initialization failed.'); }
    $stmt->close();
}
