<?php
class messageModel extends model
{
    public function page() { return sqlpage('message',20,'*','status=1','id desc'); }
    public function formKey(): string
    {
        $forms=$_SESSION['message_forms']??[];
        if (!is_array($forms)) { $forms=[]; }
        $forms=array_filter($forms,static fn($issued)=>is_int($issued) && $issued>=time()-1800);
        if (count($forms)>=20) { asort($forms);$forms=array_slice($forms,-19,null,true); }
        $key=bin2hex(random_bytes(16));
        $forms[$key]=time();
        $_SESSION['message_forms']=$forms;
        return $key;
    }
    public function save($input)
    {
        $requestKey=$input['_message_key']??null;
        $issued=is_string($requestKey) && preg_match('/^[a-f0-9]{32}$/D',$requestKey)
            ? ($_SESSION['message_forms'][$requestKey]??null) : null;
        if (!is_int($issued) || $issued<time()-1800 || $issued>time()) {
            return ['status'=>'n','message'=>'提交页面已过期，请重新打开留言表单。'];
        }
        $data = [];
        foreach (['title'=>255,'name'=>50,'qq'=>20,'tel'=>20,'mail'=>50,'content'=>5000] as $field=>$limit) {
            $value = $input[$field] ?? '';
            if (!is_string($value) || mb_strlen($value, 'UTF-8') > $limit) { return ['status'=>'n']; }
            $data[$field] = $value;
        }
        if (trim($data['title']) === '' || trim($data['content']) === '') { return ['status'=>'n']; }
        $existing=D('message')->where(['submission_key'=>$requestKey])->getone();
        if ($existing) {
            foreach ($data as $field=>$value) {
                if ($existing[$field]!==$value) { return ['status'=>'n','message'=>'该次提交内容已变化，请重新打开留言表单。']; }
            }
            unset($_SESSION['authnum'],$_SESSION['authnum_at']);
            return ['status'=>'y','id'=>(int)$existing['id'],'recovered'=>true];
        }
        if (!cms_captcha_verify($input['vcode'] ?? null)) { return ['status'=>'c']; }
        // Publication state, reply and timestamps are controlled by the server.
        $data += ['status'=>0,'time'=>time(),'submission_key'=>$requestKey];
        try { $id = D('message')->insert($data); }
        catch (mysqli_sql_exception $error) {
            if ($error->getCode()!==1062) { throw $error; }
            $existing=D('message')->where(['submission_key'=>$requestKey])->getone();
            if (!$existing) { throw $error; }
            foreach (['title','name','qq','tel','mail','content'] as $field) {
                if ($existing[$field]!==$data[$field]) { return ['status'=>'n','message'=>'该次提交内容已变化，请重新打开留言表单。']; }
            }
            $id=(int)$existing['id'];
        }
        unset($_SESSION['authnum'],$_SESSION['authnum_at']);
        return ['status'=>$id ? 'y' : 'n','id'=>$id];
    }
}
