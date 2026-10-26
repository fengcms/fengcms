<?php
include 'admin.php';
require_once ROOT_PATH.'/system/common/backup.php';

class dbmanageController extends Controller
{
    public function index(): void
    {
        header('Location: ?controller=dbmanage&operate=dbmanages', true, 302);
    }

    public function dbbackup(): void
    {
        $this->display('dbmanage/dbbackup.html');
    }

    public function dbmanages(): void
    {
        $backups = CmsBackup::all();
        foreach ($backups as &$backup) {
            $backup['created_label'] = date('Y-m-d H:i:s', strtotime($backup['created_at']));
            $backup['size_label'] = $backup['bytes'] < 1024
                ? $backup['bytes'].' B' : ($backup['bytes'] < 1048576
                    ? round($backup['bytes']/1024, 1).' KB'
                    : round($backup['bytes']/1048576, 1).' MB');
            $backup['sha256_short'] = substr($backup['sha256'], 0, 16);
        }
        unset($backup);
        $this->display('dbmanage/dbmanages.html', [
            'backups'=>$backups,
        ]);
    }

    public function create(): void
    {
        try {
            CmsBackup::create();
            header('Location: ?controller=dbmanage&operate=dbmanages&created=1', true, 303);
        } catch (Throwable $e) {
            error_log('Database backup failed: '.$e);
            cms_fail(500, $e instanceof RuntimeException ? $e->getMessage() : '备份失败，请查看私有错误日志。');
        }
    }

    public function download(): void
    {
        $id = $_POST['backup_id'] ?? null;
        if (!is_string($id)) { cms_fail(400, '备份编号不正确。'); }
        try { [$file, $meta] = CmsBackup::file($id); }
        catch (InvalidArgumentException $e) { cms_fail(400, $e->getMessage()); }
        catch (Throwable $e) { cms_fail(404, $e->getMessage()); }
        $handle = fopen($file, 'rb');
        if ($handle === false) { cms_fail(500, '备份文件无法读取。'); }
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="fengcms-'.$id.'.sql"');
        header('Content-Length: '.$meta['bytes']);
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        while (!feof($handle)) { echo fread($handle, 65536); }
        fclose($handle);
    }

    public function delete(): void
    {
        $id = $_POST['backup_id'] ?? null;
        if (!is_string($id)) { cms_fail(400, '备份编号不正确。'); }
        try { CmsBackup::delete($id); }
        catch (InvalidArgumentException $e) { cms_fail(400, $e->getMessage()); }
        catch (Throwable $e) { cms_fail(404, $e->getMessage()); }
        header('Location: ?controller=dbmanage&operate=dbmanages&deleted=1', true, 303);
    }
}
