<?php
include 'admin.php';
require_once ROOT_PATH.'/system/common/backup.php';
require_once ROOT_PATH.'/system/common/backup_import.php';

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

    public function dbimport(): void
    {
        CmsBackupImport::pruneUploads();
        $this->display('dbmanage/dbimport.html',['pending'=>CmsBackupImport::pending()]);
    }

    public function recoverimport(): void
    {
        cms_session_release();
        try{
            CmsBackupImport::recover();
            header('Location: ?controller=dbmanage&operate=dbimport&recovered=1',true,303);
        }catch(Throwable $e){
            error_log('Database import recovery failed: '.$e);
            cms_fail(500,$e instanceof RuntimeException?$e->getMessage():'导入恢复失败，请查看私有错误日志。');
        }
    }

    private function clearImportPreview(): void
    {
        $preview = $_SESSION['db_import_preview'] ?? null;
        unset($_SESSION['db_import_preview']);
        if (is_array($preview) && ($preview['kind'] ?? null) === 'upload' &&
            is_string($preview['id'] ?? null) && preg_match('/^[a-f0-9]{32}$/D', $preview['id'])) {
            $path = CmsBackupImport::uploadPath($preview['id']);
            if (is_file($path) || is_link($path)) { @unlink($path); }
        }
    }

    public function previewimport(): void
    {
        $this->clearImportPreview();
        CmsBackupImport::pruneUploads();
        $path=null; $kind=null; $id=null;
        try {
            $backupId=$_POST['backup_id']??null;
            $upload=$_FILES['backup_file']??null;
            if (is_string($backupId) && $backupId !== '' && (!is_array($upload) || $upload['error'] === UPLOAD_ERR_NO_FILE)) {
                [$path] = CmsBackup::file($backupId);
                $kind='stored'; $id=$backupId;
            } elseif ((!isset($backupId) || $backupId === '') && is_array($upload) && $upload['error'] === UPLOAD_ERR_OK &&
                isset($upload['name'],$upload['size'],$upload['tmp_name']) &&
                is_string($upload['name']) && preg_match('/\.sql$/iD', $upload['name']) &&
                $upload['size'] > 0 && $upload['size'] <= CmsBackupImport::MAX_BYTES && is_uploaded_file($upload['tmp_name'])) {
                $id=bin2hex(random_bytes(16)); $kind='upload';
                $path=CmsBackupImport::uploadPath($id);
                if (!move_uploaded_file($upload['tmp_name'], $path) || !chmod($path, 0600)) {
                    throw new RuntimeException('备份上传失败，请检查私有备份目录权限。');
                }
            } else {
                throw new InvalidArgumentException('请选择一份已有备份，或上传不超过 8 MB 的本系统 .sql 备份。');
            }
            $import=CmsBackupImport::parse($path);
            $db=CmsBackupImport::connection();
            try { $summary=CmsBackupImport::verify($db,$import); } finally { $db->close(); }
            $_SESSION['db_import_preview']=[
                'kind'=>$kind,'id'=>$id,'sha256'=>$summary['sha256'],
                'summary'=>$summary,'created'=>time(),'nonce'=>bin2hex(random_bytes(16)),
            ];
            $this->display('dbmanage/dbimport_preview.html', [
                'summary'=>$summary,'nonce'=>$_SESSION['db_import_preview']['nonce'],
                'source_label'=>$kind==='stored' ? '后台备份 '.$id : '上传的 SQL 备份',
            ]);
        } catch (Throwable $e) {
            if ($kind==='upload' && $path && (is_file($path) || is_link($path))) { @unlink($path); }
            if ($e instanceof InvalidArgumentException) { cms_fail(400,$e->getMessage()); }
            error_log('Database import preview failed: '.$e);
            cms_fail(500,$e instanceof RuntimeException ? $e->getMessage() : '备份预览失败，请查看私有错误日志。');
        }
    }

    public function runimport(): void
    {
        $preview=$_SESSION['db_import_preview']??null;
        $nonce=$_POST['nonce']??null;
        if (!is_array($preview) || !is_string($nonce) || !is_string($preview['nonce']??null) ||
            !hash_equals($preview['nonce'],$nonce) || time()-(int)($preview['created']??0)>600 ||
            ($_POST['confirm']??null)!=='1') {
            cms_fail(400,'导入预览已过期或未确认，请重新选择备份。');
        }
        unset($_SESSION['db_import_preview']);
        cms_session_release();
        $kind=$preview['kind']??null; $id=$preview['id']??null;
        $path=null;
        try {
            if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D',$id)) {
                throw new InvalidArgumentException('备份编号不正确。');
            }
            if ($kind==='stored') { [$path]=CmsBackup::file($id); }
            elseif ($kind==='upload') { $path=CmsBackupImport::uploadPath($id); }
            else { throw new InvalidArgumentException('导入来源不正确。'); }
            $import=CmsBackupImport::parse($path);
            if (!is_string($preview['sha256']??null) || !hash_equals($preview['sha256'],$import['sha256'])) {
                throw new InvalidArgumentException('备份文件已变化，请重新预览。');
            }
            $db=CmsBackupImport::connection();
            try {
                // Hold the import lock while creating the pre-import backup and staging rows.
                CmsBackupImport::restore($db,$import, static fn() => CmsBackup::create());
            } finally { $db->close(); }
            if ($kind==='upload' && is_file($path)) { @unlink($path); }
            header('Location: ?controller=dbmanage&operate=dbmanages&imported=1', true, 303);
        } catch (Throwable $e) {
            if ($kind==='upload' && $path && (is_file($path) || is_link($path))) { @unlink($path); }
            error_log('Database import failed: '.$e);
            if ($e instanceof InvalidArgumentException) { cms_fail(400,$e->getMessage()); }
            cms_fail(500,$e instanceof RuntimeException ? $e->getMessage() : '数据库导入失败，请查看私有错误日志。');
        }
    }

    public function create(): void
    {
        cms_session_release();
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
        cms_session_release();
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
