<?php
defined('TPL_INCLUDE') or die('Restricted access');

class messageModel
{
    private const FIELDS=['title'=>255,'name'=>50,'qq'=>20,'tel'=>20,'mail'=>50,'content'=>5000,'reply'=>5000];
    private const ROW_FIELDS=['title','name','qq','tel','mail','content','reply','time','rtime','status','edit_version'];
    private ?float $batchDeadline=null;

    private function id(mixed $value): int
    {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-9][0-9]{0,9}$/D',(string)$value)) {
            cms_fail(400,'留言 ID 不正确。');
        }
        return (int)$value;
    }

    private function locked(int $id,callable $work): mixed
    {
        $connection=D('message');
        $key='fmsg:'.substr(hash('sha256',DB_NAME.':'.DB_PREFIX.':'.$id),0,58);
        $deadline=$this->batchDeadline??microtime(true)+5.0;
        try {
            do {
                $remaining=max(0.0,$deadline-microtime(true));
                $result=$connection->excsql('SELECT GET_LOCK(?,?) AS acquired',[$key,min(1.0,$remaining)]);
                if ((int)($result[0]['acquired']??0)===1) { return $work(); }
            } while (microtime(true)<$deadline);
            throw new RuntimeException('留言锁等待超时。');
        } finally {
            try { $connection->excsql('SELECT RELEASE_LOCK(?)',[$key]); }
            catch (Throwable) { /* Closing the private connection releases its lock. */ }
        }
    }

    private function version(array $row): string
    {
        $values=[];
        foreach (self::ROW_FIELDS as $field) { $values[$field]=$row[$field]??null; }
        return hash_hmac('sha256',json_encode($values,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),KEYS);
    }

    public function reply($value): array
    {
        $id=$this->id($value);
        return $this->locked($id,function() use ($id): array {
            $row=D('message')->where(['id'=>$id])->getone();
            if (!$row) { cms_fail(404,'留言不存在。'); }
            $row['_message_version']=$this->version($row);
            return $row;
        });
    }

    public function delete($value): int
    {
        $id=$this->id($value);
        return $this->locked($id,function() use ($id): int {
            $record=D('message');
            $record->query('SET SESSION innodb_lock_wait_timeout=2');
            $record->query('SET SESSION lock_wait_timeout=2');
            $count=$record->where(['id'=>$id])->delete();
            return $count;
        });
    }

    public function batch($ids): array
    {
        if (!is_array($ids) || !$ids) { return ['status'=>'e']; }
        if (count($ids)>500) { cms_fail(400,'一次最多处理 500 条留言。'); }
        $ids=array_values(array_unique(array_map(fn($value)=>$this->id($value),$ids)));
        $result=['changed'=>[],'already'=>[],'failed'=>[],'unprocessed'=>[]];
        $deadline=microtime(true)+12.0;
        $this->batchDeadline=$deadline;
        try {
            foreach ($ids as $index=>$value) {
                if (microtime(true)>=$deadline) { $result['unprocessed']=array_slice($ids,$index);break; }
                $id=$value;
                try { $result[$this->delete($id)>0?'changed':'already'][]=$id; }
                catch (Throwable $error) { error_log('Message batch delete failed: '.$error);$result['failed'][]=$id; }
            }
        } finally { $this->batchDeadline=null; }
        $result['status']=$result['failed'] || $result['unprocessed']?'p':'y';
        return $result;
    }

    public function save($input): array
    {
        $id=$this->id($input['id']??null);
        $baseline=$input['_message_version']??null;
        if (!is_string($baseline) || !preg_match('/^[a-f0-9]{64}$/D',$baseline)) {
            cms_fail(400,'留言编辑版本不正确，请刷新页面。');
        }
        $values=[];
        foreach (self::FIELDS as $field=>$limit) {
            $value=$input[$field]??'';
            if (!is_string($value) || mb_strlen($value,'UTF-8')>$limit) { cms_fail(400,'留言字段不正确：'.$field); }
            $values[$field]=$value;
        }
        if (trim($values['title'])==='' || trim($values['content'])==='') { cms_fail(400,'标题和留言内容不能为空。'); }
        $status=$input['status']??null;
        if (!is_string($status) || !in_array($status,['0','1'],true)) { cms_fail(400,'留言状态不正确。'); }
        $values['status']=(int)$status;
        $values['rtime']=$values['reply']!==''?time():0;
        return $this->locked($id,function() use ($id,$baseline,$values): array {
            $row=D('message')->where(['id'=>$id])->getone();
            if (!$row) { cms_fail(404,'留言不存在。'); }
            if (!hash_equals($this->version($row),$baseline)) { cms_fail(409,'留言已被修改，请保留输入并刷新页面。'); }
            $values['edit_version']=bin2hex(random_bytes(16));
            $where='id=?';$params=[$id];
            foreach (self::ROW_FIELDS as $field) { $where.=' AND `'.$field.'` <=> ?';$params[]=$row[$field]??null; }
            $affected=D('message')->where($where,$params)->update($values);
            if ($affected===0) {
                $current=D('message')->where(['id'=>$id])->getone();
                if (!$current) { cms_fail(404,'留言不存在。'); }
                $expected=array_replace($row,$values);
                foreach (self::ROW_FIELDS as $field) {
                    if (($current[$field]===null)!==($expected[$field]===null) ||
                        (string)$current[$field]!==(string)$expected[$field]) {
                        cms_fail(409,'留言已被修改，请保留输入并刷新页面。');
                    }
                }
            }
            return ['status'=>'y','id'=>$id];
        });
    }
}
