<?php
include 'admin.php';

// The two roles are fixed. Only a super administrator may manage editor accounts.
class accountController extends Controller
{
    private function connection(): mysqli
    {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $db->set_charset('utf8mb4');
        return $db;
    }

    private function table(): string
    {
        return model::identifier(DB_PREFIX.'manage');
    }

    private function show(?string $newCode = null, ?string $message = null): void
    {
        header('Cache-Control: no-store');
        $db=$this->connection();
        $accounts=$db->query('SELECT id,admin,role,enabled,datetime FROM '.$this->table().' ORDER BY id')->fetch_all(MYSQLI_ASSOC);
        $db->close();
        $this->display('account/index.html', ['accounts'=>$accounts,'newCode'=>$newCode,'message'=>$message]);
    }

    public function index(): void
    {
        $this->show();
    }

    public function create(): void
    {
        $name=$_POST['username']??null;
        $password=$_POST['password']??null;
        if (!is_string($name) || !preg_match('/^[A-Za-z][A-Za-z0-9_.-]{4,63}$/D',$name) ||
            !is_string($password) || strlen($password)<12 || strlen($password)>72) {
            cms_fail(400, '用户名须为 5–64 位英文字母、数字、点、横线或下划线；密码须为 12–72 字节。');
        }
        $code=bin2hex(random_bytes(16));
        $hash=password_hash($password,PASSWORD_DEFAULT);
        $codeHash=password_hash($code,PASSWORD_DEFAULT);
        $db=$this->connection();
        try {
            $stmt=$db->prepare('INSERT INTO '.$this->table()." (admin,password,role,enabled,auth_version,login_code_hash,datetime) VALUES (?,?,'editor',1,1,?,NOW())");
            $stmt->bind_param('sss',$name,$hash,$codeHash);
            $stmt->execute();
            $id=$db->insert_id;
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode()===1062) { cms_fail(409, '用户名已存在。'); }
            throw $e;
        } finally { $db->close(); }
        error_log('Editor account created: id='.$id.' by admin id='.(int)$_SESSION['manage_id']);
        $this->show($code,'编辑账号已创建。请立即保存并单独交付下方认证码；页面关闭后无法再次查看。');
    }

    private function editorId(): int
    {
        $id=$_POST['id']??null;
        if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,9}$/D',$id)) { cms_fail(400,'账号编号不正确。'); }
        return (int)$id;
    }

    public function toggle(): void
    {
        $id=$this->editorId();
        $enabled=$_POST['enabled']??null;
        if (!in_array($enabled,['0','1'],true)) { cms_fail(400,'账号状态不正确。'); }
        $db=$this->connection();
        try {
            $stmt=$db->prepare('UPDATE '.$this->table()." SET enabled=?,auth_version=auth_version+1 WHERE id=? AND role='editor'");
            $value=(int)$enabled;
            $stmt->bind_param('ii',$value,$id);
            $stmt->execute();
            if ($stmt->affected_rows!==1) { cms_fail(404,'编辑账号不存在。'); }
            $stmt->close();
        } finally { $db->close(); }
        error_log('Editor account status changed: id='.$id.' enabled='.$enabled.' by admin id='.(int)$_SESSION['manage_id']);
        header('Location: ?controller=account',true,303);
    }

    public function reset(): void
    {
        $id=$this->editorId();
        $password=$_POST['password']??null;
        if (!is_string($password) || strlen($password)<12 || strlen($password)>72) {
            cms_fail(400,'新密码须为 12–72 字节。');
        }
        $code=bin2hex(random_bytes(16));
        $hash=password_hash($password,PASSWORD_DEFAULT);
        $codeHash=password_hash($code,PASSWORD_DEFAULT);
        $db=$this->connection();
        try {
            $stmt=$db->prepare('UPDATE '.$this->table()." SET password=?,login_code_hash=?,auth_version=auth_version+1 WHERE id=? AND role='editor'");
            $stmt->bind_param('ssi',$hash,$codeHash,$id);
            $stmt->execute();
            if ($stmt->affected_rows!==1) { cms_fail(404,'编辑账号不存在。'); }
            $stmt->close();
        } finally { $db->close(); }
        error_log('Editor credentials reset: id='.$id.' by admin id='.(int)$_SESSION['manage_id']);
        $this->show($code,'编辑账号密码和认证码已重置，旧会话立即失效。请保存并交付新的认证码。');
    }
}
