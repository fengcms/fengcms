<?php
defined('TPL_INCLUDE') or die('Restricted access');
require_once ROOT_PATH.'/system/common/model_schema.php';

final class ContentWriteLockTimeout extends RuntimeException {}

/** Serializes content writes across administrator sessions, including legacy MyISAM tables. */
final class ContentWrite
{
    private const BATCH_SECONDS = 12.0;
    private mysqli $db;
    private string $project;
    private string $table;
    private string $recycleTable;
    private string $requestTable;
    private ?int $moduleId;
    private ?float $batchDeadline = null;

    public function __construct(string $project)
    {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,29}$/D', $project)) { cms_fail(400, '模型名称不正确。'); }
        $this->project=$project;
        $this->table=self::tableName($project);
        $this->recycleTable=self::tableName('content_recycle_state');
        $this->requestTable=self::tableName('content_create_request');
        $this->db=new mysqli(DB_HOST,DB_USER,DB_PASS,DB_NAME);
        $this->db->set_charset('utf8mb4');
        $module=$this->db->execute_query('SELECT id FROM '.self::tableName('module').' WHERE project=?',[$project])->fetch_row();
        $this->moduleId=isset($module[0])?(int)$module[0]:null;
    }

    private static function tableName(string $name): string
    {
        $full=DB_PREFIX.$name;
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/D',$full)) { cms_fail(400,'数据表名不正确。'); }
        return '`'.$full.'`';
    }

    private function locked(string $item, callable $work): mixed
    {
        if($item!=='category:relations' && !CmsModelSchema::isHeld($this->project)){
            return CmsModelSchema::withLock($this->project,fn()=> $this->locked($item,$work));
        }
        if($item!=='category:relations'){
            CmsModelSchema::assertUsable(CmsModelSchema::heldConnection($this->project),
                $this->project,$this->moduleId);
        }
        $scope=$item==='category:relations'?'all-categories':$this->project;
        $key='fcw:'.substr(hash('sha256',DB_NAME.':'.DB_PREFIX.':'.$scope.':'.$item),0,58);
        $remaining=$this->batchDeadline===null?5.0:max(0.0,$this->batchDeadline-microtime(true));
        if ($remaining<=0) { throw new ContentWriteLockTimeout('批量操作等待时间已用尽。'); }
        $result=$this->db->execute_query('SELECT GET_LOCK(?,?)',[$key,min(5.0,$remaining)])->fetch_row();
        if ((int)($result[0]??0)!==1) { throw new ContentWriteLockTimeout('内容正在由其他请求处理，请稍后重试。'); }
        try { return $work(); }
        finally { $this->db->execute_query('SELECT RELEASE_LOCK(?)',[$key]); }
    }

    /** Single-record routes retain their HTTP 409 contract; batches catch the exception per ID. */
    public static function single(callable $work): mixed
    {
        try { return $work(); }
        catch (ContentWriteLockTimeout $e) { cms_fail(409,$e->getMessage()); }
    }

    /** Every category mutation and content association shares one lock across models. */
    public static function categoryLocked(string $project,callable $work): mixed
    {
        return (new self($project))->locked('category:relations',$work);
    }

    private function assertCategory(mixed $classid): void
    {
        if ($classid===null || (string)$classid==='0') { return; }
        $category=$this->db->execute_query('SELECT id FROM '.self::tableName('classify').
            ' WHERE id=? AND project=? AND type=?',[(int)$classid,$this->project,
                $this->project==='single'?'single':'module'])->fetch_row();
        if (!$category) { cms_fail(400,'栏目不属于当前模型。'); }
    }

    public function row(int $id): ?array
    {
        return $this->db->execute_query('SELECT * FROM '.$this->table.' WHERE id=?',[$id])->fetch_assoc();
    }

    /** Excludes the public view counter, which changes without editorial writes. */
    public static function editToken(array $row): string
    {
        unset($row['hits']);
        return hash_hmac('sha256',json_encode($row,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),
            (string)($_SESSION['csrf_token']??''));
    }

    private function updateRow(int $id,array $values): int
    {
        if (!$values) { return 0; }
        $parts=[];
        foreach ($values as $column=>$_) {
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D',$column)) { cms_fail(400,'字段名称不正确。'); }
            $parts[]='`'.$column.'`=?';
        }
        $this->db->execute_query('UPDATE '.$this->table.' SET '.implode(',',$parts).' WHERE id=?',
            [...array_values($values),$id]);
        return $this->db->affected_rows;
    }

    public function edit(int $id,array $values,string $token): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$token)) { cms_fail(409,'编辑页面已过期，请重新打开后保存。'); }
        return $this->locked('row:'.$id,fn()=> $this->locked('category:relations',function() use ($id,$values,$token) {
            $row=$this->row($id);
            if (!$row || (isset($row['status']) && (int)$row['status']===-1) ||
                !hash_equals(self::editToken($row),$token)) {
                cms_fail(409,'内容已被修改或移入回收站，请重新打开后核对。');
            }
            if (array_key_exists('classid',$values)) { $this->assertCategory($values['classid']); }
            $this->updateRow($id,$values);
            return ['status'=>'y','id'=>$id];
        }));
    }

    /** Returns changed, already or missing; callers can report each selected ID. */
    public function change(int $id,string $action,?string $attribute=null): string
    {
        return $this->locked('row:'.$id,fn()=> $this->locked('category:relations',function() use ($id,$action,$attribute) {
            $row=$this->row($id);
            if (!$row) { return 'missing'; }
            $status=(int)($row['status']??1);
            if ($action==='recycle') {
                if ($status===-1) { return 'already'; }
                $previous=$status===1?1:0;
                // Durable first: a crash before the MyISAM status write leaves an innocuous hint.
                $this->db->execute_query('INSERT INTO '.$this->recycleTable.' (project,record_id,previous_status) VALUES (?,?,?) '
                    .'ON DUPLICATE KEY UPDATE previous_status=VALUES(previous_status)',[$this->project,$id,$previous]);
                $this->updateRow($id,['status'=>-1]);
                return 'changed';
            }
            if ($action==='restore') {
                if ($status!==-1) { return 'already'; }
                $prior=$this->db->execute_query('SELECT previous_status FROM '.$this->recycleTable.' WHERE project=? AND record_id=?',
                    [$this->project,$id])->fetch_row();
                // Historical rows have no original status. Restore as a private draft.
                $this->updateRow($id,['status'=>isset($prior[0]) && (int)$prior[0]===1?1:0]);
                $this->db->execute_query('DELETE FROM '.$this->recycleTable.' WHERE project=? AND record_id=?',[$this->project,$id]);
                return 'changed';
            }
            if ($action==='delete' || $action==='delete_recycled') {
                if ($action==='delete_recycled' && $status!==-1) { return 'already'; }
                $this->db->execute_query('DELETE FROM '.$this->table.' WHERE id=?',[$id]);
                $this->db->execute_query('DELETE FROM '.$this->recycleTable.' WHERE project=? AND record_id=?',[$this->project,$id]);
                return 'changed';
            }
            if ($action==='attrib' || $action==='attribc') {
                if ($status===-1) { return 'already'; }
                if (!in_array($attribute,['attrib_j','attrib_g','attrib_t','attrib_r','attrib_d','attrib_h'],true)) {
                    cms_fail(400,'文章属性不正确。');
                }
                $target=$action==='attrib'?1:0;
                if ((int)($row[$attribute]??0)===$target) { return 'already'; }
                $this->updateRow($id,[$attribute=>$target,'time'=>time()]);
                return 'changed';
            }
            cms_fail(400,'内容操作不正确。');
        }));
    }

    public function batch(array $ids,string $action,?string $attribute=null): array
    {
        if(!CmsModelSchema::isHeld($this->project)){
            return CmsModelSchema::withLock($this->project,fn()=> $this->batch($ids,$action,$attribute));
        }
        $result=['changed'=>[],'already'=>[],'missing'=>[],'failed'=>[],'unprocessed'=>[]];
        $this->batchDeadline=microtime(true)+self::BATCH_SECONDS;
        try {
            foreach ($ids as $index=>$id) {
                if (microtime(true)>=$this->batchDeadline) {
                    $result['unprocessed']=array_map('intval',array_slice($ids,$index));
                    break;
                }
                try { $result[$this->change((int)$id,$action,$attribute)][]=(int)$id; }
                catch (ContentWriteLockTimeout $e) { $result['failed'][]=(int)$id; }
                catch (Throwable $e) { error_log('Content batch write failed: '.$e); $result['failed'][]=(int)$id; }
            }
        } finally {
            $this->batchDeadline=null;
        }
        $result['status']=$result['failed'] || $result['missing'] || $result['unprocessed'] ? 'p' : 'y';
        return $result;
    }

    /** A single SQL update keeps a valid shift all-or-nothing, while row locks coordinate with editors. */
    public function shift(array $ids,int $classid): bool
    {
        $ids=array_values(array_unique(array_map('intval',$ids)));
        sort($ids,SORT_NUMERIC);
        if (!$ids) { return false; }
        $work=function(int $index) use (&$work,$ids,$classid): bool {
            if ($index<count($ids)) {
                return $this->locked('row:'.$ids[$index],fn()=> $work($index+1));
            }
            return $this->locked('category:relations',function() use ($ids,$classid): bool {
            $slots=implode(',',array_fill(0,count($ids),'?'));
            $total=(int)$this->db->execute_query('SELECT COUNT(*) FROM '.$this->table.
                ' WHERE id IN ('.$slots.') AND status>=0',$ids)->fetch_row()[0];
            if ($total!==count($ids)) { cms_fail(409,'部分内容已删除或回收，请刷新列表后重试。'); }
            $where='id IN ('.$slots.')'; $params=[$classid,...$ids];
            if ($classid!==0) {
                $type=$this->project==='single'?'single':'module';
                $category=self::tableName('classify');
                $where.=' AND EXISTS (SELECT 1 FROM '.$category.' WHERE id=? AND project=? AND type=?)';
                array_push($params,$classid,$this->project,$type);
            }
            $this->db->execute_query('UPDATE '.$this->table.' SET classid=? WHERE '.$where,$params);
            if ($this->db->affected_rows>0) { return true; }
            if ($classid!==0) {
                $exists=$this->db->execute_query('SELECT id FROM '.self::tableName('classify').
                    ' WHERE id=? AND project=? AND type=?',[$classid,$this->project,$type])->fetch_row();
                if (!$exists) { cms_fail(400,'栏目不属于当前模型。'); }
            }
            $already=(int)$this->db->execute_query('SELECT COUNT(*) FROM '.$this->table.
                ' WHERE id IN ('.$slots.') AND classid=?',[...$ids,$classid])->fetch_row()[0];
            return $already===count($ids);
            });
        };
        return $work(0);
    }

    private function allTransactional(array $tables): bool
    {
        foreach ($tables as $table) {
            $row=$this->db->execute_query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?',
                [DB_NAME,DB_PREFIX.$table])->fetch_row();
            if (!isset($row[0]) || strcasecmp($row[0],'InnoDB')!==0) { return false; }
        }
        return true;
    }

    public function create(array $values,string $key,int $account,string $hash): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D',$key) || $account<1) {
            cms_fail(400,'新增请求标识不正确，请重新打开新增页面。');
        }
        ksort($values);
        return $this->locked('create:'.$account.':'.$key,fn()=> $this->locked('category:relations',function() use ($values,$key,$account,$hash) {
            if (array_key_exists('classid',$values)) { $this->assertCategory($values['classid']); }
            $lookup=[$account,$this->project,$key];
            $request=$this->db->execute_query('SELECT payload_hash,state,record_id FROM '.$this->requestTable.
                ' WHERE account_id=? AND project=? AND request_key=?',$lookup)->fetch_assoc();
            if ($request) {
                if (!hash_equals($request['payload_hash'],$hash)) { cms_fail(409,'同一次新增请求的内容已改变，请重新打开新增页面。'); }
                if ($request['state']==='complete' && (int)$request['record_id']>0) {
                    if (!$this->row((int)$request['record_id'])) {
                        cms_fail(409,'原新增记录已删除，请重新打开新增页面。');
                    }
                    return ['status'=>'y','id'=>(int)$request['record_id'],'replayed'=>true];
                }
                if ($request['state']!=='failed') { cms_fail(409,'新增请求尚未完成，请检查记录后处理。'); }
                $this->db->execute_query('UPDATE '.$this->requestTable.' SET state=?,record_id=NULL WHERE account_id=? AND project=? AND request_key=?',
                    ['processing',...$lookup]);
            } else {
                $this->db->execute_query('INSERT INTO '.$this->requestTable.
                    ' (account_id,project,request_key,payload_hash,state) VALUES (?,?,?,?,?)',
                    [$account,$this->project,$key,$hash,'processing']);
            }
            $counters=[];
            foreach (['tags','author','inputer','origin'] as $counter) {
                if (!empty($values[$counter])) {
                    foreach (explode(',',$values[$counter]) as $name) {
                        $name=trim($name);
                        if ($name!=='') { $counters[]=[$counter,$name]; }
                    }
                }
            }
            $transactional=false; $commitAttempted=false; $inserted=null; $incremented=[];
            try {
                $transactional=$this->allTransactional(array_unique([$this->project,...array_column($counters,0)]));
                if ($transactional) { $this->db->begin_transaction(); }
                $columns=array_keys($values);
                $sql='INSERT INTO '.$this->table.' ('.implode(',',array_map(
                    static fn($column)=>'`'.$column.'`',$columns)).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')';
                $this->db->execute_query($sql,array_values($values));
                $inserted=$this->db->insert_id;
                if (!$inserted) { throw new RuntimeException('Content insert returned no ID.'); }
                $this->db->execute_query('UPDATE '.$this->requestTable.' SET record_id=? WHERE account_id=? AND project=? AND request_key=?',
                    [$inserted,...$lookup]);
                $columnsInfo=$this->db->execute_query('SELECT COLUMN_NAME FROM information_schema.COLUMNS '
                    .'WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?',[DB_NAME,DB_PREFIX.$this->project,'html'])->fetch_row();
                if ($columnsInfo) { $this->updateRow($inserted,['html'=>'/content_'.$this->project.'_'.$inserted.'.html']); }
                foreach ($counters as [$counter,$name]) {
                    $this->db->execute_query('UPDATE '.self::tableName($counter).' SET hits=hits+1 WHERE name=?',[$name]);
                    if ($this->db->affected_rows>0) { $incremented[]=[$counter,$name]; }
                }
                $this->db->execute_query('UPDATE '.$this->requestTable.' SET state=? WHERE account_id=? AND project=? AND request_key=?',
                    ['complete',...$lookup]);
                if ($transactional) { $commitAttempted=true; $this->db->commit(); }
                return ['status'=>'y','id'=>$inserted];
            } catch (Throwable $e) {
                if ($commitAttempted) {
                    // A lost COMMIT response has an unknown outcome. Keep the durable
                    // request state for inspection; never advertise a safe retry.
                    error_log('Content creation commit outcome unknown: '.$e);
                    cms_fail(500,'新增提交状态需要管理员核查，请勿重复提交。');
                }
                $clean=true;
                if ($transactional) { try { $this->db->rollback(); } catch (Throwable $ignored) { $clean=false; } }
                else {
                    if ($inserted) {
                        try { $this->db->execute_query('DELETE FROM '.$this->table.' WHERE id=?',[$inserted]); }
                        catch (Throwable $ignored) { $clean=false; }
                    }
                    foreach (array_reverse($incremented) as [$counter,$name]) {
                        try { $this->db->execute_query('UPDATE '.self::tableName($counter).' SET hits=GREATEST(hits-1,0) WHERE name=?',[$name]); }
                        catch (Throwable $ignored) { $clean=false; }
                    }
                }
                if ($clean) {
                    try { $this->db->execute_query('UPDATE '.$this->requestTable.' SET state=?,record_id=NULL '
                        .'WHERE account_id=? AND project=? AND request_key=?',['failed',...$lookup]); }
                    catch (Throwable $ignored) { $clean=false; }
                }
                error_log('Content creation failed; cleanup='.($clean?'complete':'manual-review').': '.$e);
                cms_fail(500,$clean?'新增失败，未保存内容，请稍后重试。':'新增状态需要管理员核查，请勿重复提交。');
            }
        }));
    }
}
