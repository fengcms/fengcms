<?php
class messageModel extends model
{
    public function page() { return sqlpage('message',20,'*','status=1','id desc'); }
    public function save($input)
    {
        $code = $input['vcode'] ?? '';
        if (!is_string($code) || empty($_SESSION['authnum']) || !hash_equals(strtolower($_SESSION['authnum']), strtolower($code))) {
            return ['status'=>'c'];
        }
        $data = [];
        foreach (['title'=>255,'name'=>50,'qq'=>20,'tel'=>20,'mail'=>50,'content'=>5000] as $key=>$limit) {
            $value = $input[$key] ?? '';
            if (!is_string($value) || mb_strlen($value, 'UTF-8') > $limit) { return ['status'=>'n']; }
            $data[$key] = $value;
        }
        if (trim($data['title']) === '' || trim($data['content']) === '') { return ['status'=>'n']; }
        // Publication state, reply and timestamps are controlled by the server.
        $data += ['status'=>0,'time'=>time()];
        $id = D('message')->insert($data);
        unset($_SESSION['authnum']);
        return ['status'=>$id ? 'y' : 'n','id'=>$id];
    }
}
