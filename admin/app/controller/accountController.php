<?php
include 'admin.php';

// The two roles are fixed. Only a super administrator may manage accounts.
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

    public function add(): void
    {
        $this->display('account/add.html');
    }

    public function create(): void
    {
        $name=$_POST['username']??null;
        $password=$_POST['password']??null;
        $role=$_POST['role']??null;
        if (!is_string($name) || !preg_match('/^[A-Za-z][A-Za-z0-9_.-]{4,63}$/D',$name) ||
            !is_string($password) || !cms_account_password_valid($password) ||
            !in_array($role,['editor','super_admin'],true)) {
            cms_fail(400, '用户名须为 5–64 位英文字母、数字、点、横线或下划线；密码至少 8 个字符且不超过 72 字节，并选择账号类型。');
        }
        $code=$this->newCode();
        $hash=password_hash($password,PASSWORD_DEFAULT);
        $codeHash=password_hash($code,PASSWORD_DEFAULT);
        $db=$this->connection();
        try {
            $stmt=$db->prepare('INSERT INTO '.$this->table()." (admin,password,role,enabled,auth_version,login_code_hash,datetime) VALUES (?,?,?,1,1,?,NOW())");
            $stmt->bind_param('ssss',$name,$hash,$role,$codeHash);
            $stmt->execute();
            $id=$db->insert_id;
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode()===1062) { cms_fail(409, '用户名已存在。'); }
            throw $e;
        } finally { $db->close(); }
        error_log('Admin account created: id='.$id.' role='.$role.' by admin id='.(int)$_SESSION['manage_id']);
        header('Cache-Control: no-store');
        $this->display('account/created.html',['name'=>$name,'role'=>$role,'newCode'=>$code]);
    }

    private function newCode(): string
    {
        $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code='';
            for ($i=0;$i<4;$i++) { $code.=$alphabet[random_int(0,strlen($alphabet)-1)]; }
        } while (!preg_match('/[A-Z]/',$code) || !preg_match('/[2-9]/',$code));
        return $code;
    }

    private function accountId(): int
    {
        $id=$_POST['id']??null;
        if (!is_string($id) || !preg_match('/^[1-9][0-9]{0,9}$/D',$id)) { cms_fail(400,'账号编号不正确。'); }
        return (int)$id;
    }

    public function toggle(): void
    {
        $id=$this->accountId();
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
        $id=$this->accountId();
        $password=$_POST['password']??null;
        if (!is_string($password) || !cms_account_password_valid($password)) {
            cms_fail(400,'新密码至少 8 个字符且不超过 72 字节。');
        }
        $db=$this->connection();
        try {
            $stmt=$db->prepare('SELECT role FROM '.$this->table().' WHERE id=?');
            $stmt->bind_param('i',$id);
            $stmt->execute();
            $target=$stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$target) { cms_fail(404,'账号不存在。'); }
            $role=$target['role'];
            if ($role==='super_admin') {
                if ($id===(int)$_SESSION['manage_id']) { cms_fail(403,'请在个人设置中修改自己的密码。'); }
                $operatorPassword=$_POST['operator_password']??null;
                if (!is_string($operatorPassword) || $operatorPassword==='') { cms_fail(403,'请先核验当前管理员密码。'); }
                $stmt=$db->prepare('SELECT password FROM '.$this->table()." WHERE id=? AND role='super_admin' AND enabled=1");
                $actorId=(int)$_SESSION['manage_id'];
                $stmt->bind_param('i',$actorId);
                $stmt->execute();
                $actor=$stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (!$actor || !cms_password_verify($operatorPassword,$actor['password'])) {
                    cms_fail(403,'当前管理员密码不正确。');
                }
            } elseif ($role!=='editor') { cms_fail(403,'账号类型不正确。'); }
            $code=$this->newCode();
            $hash=password_hash($password,PASSWORD_DEFAULT);
            $codeHash=password_hash($code,PASSWORD_DEFAULT);
            $stmt=$db->prepare('UPDATE '.$this->table().' SET password=?,login_code_hash=?,auth_version=auth_version+1 WHERE id=? AND role=?');
            $stmt->bind_param('ssis',$hash,$codeHash,$id,$role);
            $stmt->execute();
            if ($stmt->affected_rows!==1) { cms_fail(404,'账号不存在。'); }
            $stmt->close();
        } finally { $db->close(); }
        error_log('Admin credentials reset: id='.$id.' role='.$role.' by admin id='.(int)$_SESSION['manage_id']);
        $this->show($code,'账号密码和认证码已重置，旧会话立即失效。请保存并交付新的认证码。');
    }

    public function delete(): void
    {
        $id=$this->accountId();
        $db=$this->connection();
        try {
            $stmt=$db->prepare('SELECT role FROM '.$this->table().' WHERE id=?');
            $stmt->bind_param('i',$id);
            $stmt->execute();
            $target=$stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$target) { cms_fail(404,'账号不存在。'); }
            if ($target['role']!=='editor') { cms_fail(403,'超级管理员账号不能删除。'); }
            $stmt=$db->prepare('DELETE FROM '.$this->table()." WHERE id=? AND role='editor'");
            $stmt->bind_param('i',$id);
            $stmt->execute();
            if ($stmt->affected_rows!==1) { cms_fail(404,'编辑账号不存在。'); }
            $stmt->close();
        } finally { $db->close(); }
        error_log('Editor account deleted: id='.$id.' by admin id='.(int)$_SESSION['manage_id']);
        header('Location: ?controller=account',true,303);
    }
}
