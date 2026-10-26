<?php
defined('TPL_INCLUDE') or die('Restricted access');

class manageModel extends model
{
    public function findcount($array)
    {
        if (!isset($array['password']) || !is_string($array['password'])) { return 0; }
        $account = D('manage')->where(['id' => (int)$_SESSION['manage_id']])->getone();
        return $account && cms_password_verify($array['password'], $account['password']) ? 1 : 0;
    }

    public function save($array)
    {
        $user = $array['user'] ?? '';
        $password = $array['newpassword'] ?? '';
        if (!is_string($user) || !is_string($password) || strlen($user) < 3 || strlen($user) > 255 ||
            !cms_account_password_valid($password) || $password !== ($array['newpasswords'] ?? null)) {
            return ['status' => 'n', 'info' => '用户名至少3个字符，新密码须至少8个字符且不超过72字节，两次输入须一致。'];
        }
        if (D('manage')->where(['admin' => $user])->getone() && $user !== $_SESSION['manage']) {
            return ['status' => 'n', 'info' => '用户名已存在。'];
        }
        $saved = D('manage')->where(['id' => (int)$_SESSION['manage_id']])->update([
            'admin' => $user, 'password' => password_hash($password, PASSWORD_DEFAULT),
            'datetime' => date('Y-m-d H:i:s'),
        ]);
        if ($saved) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return ['status' => $saved ? 'y' : 'n'];
    }
}
