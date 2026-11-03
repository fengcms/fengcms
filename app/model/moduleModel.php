<?php
require_once dirname(__DIR__).'/searchCriteria.php';
require_once dirname(__DIR__).'/listExpression.php';

/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
// 前台模板兼容入口：内容查询、搜索及展示数据组装。

class moduleModel extends model{

	private $d_name		 = "module";
    private const RELATED_CANDIDATE_LIMIT = 500;
    private const RELATED_MAX_TAGS = 8;
    private const RELATED_MAX_TAG_BYTES = 64;

    /** @var array<string, array{revision:int, exists:bool}> */
    private static array $attributeAvailability = [];
	/*
	栏目模块
	*/
	public function module(){
		return D($this->d_name)->where(['type'=>1,'status'=>1])->getall();
	}

	/*
	栏目模块
	*/
	public function l($table,$func=""){
        $op=CmsListExpression::parse($func,fn($classid)=>$this->whereclass($classid));
		$field=$op['field']??'*';
		$where=$op['where']??'id';
		$sortf=$op['sort']['f']??'id';
		$sorts=$op['sort']['s']??'order';
		$sortp=$op['sort']['p']??'desc';
		$limit=$op['limit']??10;

        $rows = $this->navigationRows($table, $field, $where, $sortf, $sorts, $sortp, $limit);
        if ($rows === null) {
            $rows=D($table)->field($field)->where($where)->sort($sortf,$sorts,$sortp)->limit($limit)->getall();
        }
		// Legacy templates print these fields directly in href attributes. Keep the
		// template syntax while neutralizing unsafe URLs from imported/old rows.
		if($table==='classify' || $table==='friend'){
			$key=$table==='classify'?'html':'url';
			foreach($rows as &$row){
				if(isset($row[$key])){$row[$key]=cms_safe_url($row[$key]);}
			}
			unset($row);
		}
		return $rows;
	}

    /** Keep other l() expressions on their original SQL path. */
    private function navigationRows($table, $field, $where, $sortf, $sorts, $sortp, $limit): ?array {
        if ($table !== 'classify' || $sortf !== 'sort' || $sorts !== 'order' || $sortp !== 'asc' ||
            !is_string($where) || !preg_match('/^classid=(0|[1-9][0-9]*)&&nav=1$/D', $where, $match)) {
            return null;
        }
        $parent = filter_var($match[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $max = filter_var((string)$limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($parent === false || $max === false || !is_string($field)) { return null; }
        $fields = array_map('trim', explode(',', $field));
        foreach ($fields as $name) {
            if (!in_array($name, ['id','classid','name','html','nav','url_type','type','sort'], true)) {
                return null;
            }
        }
        return M('classify')->navigationRows($parent, $fields, $max);
    }

	/*
	栏目图片属性
	*/
	public function pic($table,$classid,$num=3,$field=""){
			$where=$this->whereclass($classid);
			return D($table)->field($this->fieldhandle($field)."id,title,img,html")->where('status=1 and img!=""'.$where)->sort("id","order","desc")->limit($num)->getall();
	}
	/*
	栏目最新记录
	*/
	public function n($table,$classid,$num=10,$field=""){
			$where=$this->whereclass($classid);
			return D($table)->field($this->fieldhandle($field)."id,title,html")->where('status="1" '.$where)->sort("id","order","desc")->limit($num)->getall();
	}

	/*
	栏目头条属性
	*/
	public function t($table,$classid,$num=1,$field=""){
		if($this->attrib($table,"attrib")){
			$where=$this->whereclass($classid);
			return D($table)->field($this->fieldhandle($field)."id,title,html")->where('status="1" and attrib_t="1" '.$where)->sort("id","order","desc")->limit($num)->getall();
		}
	}

	/*
	栏目推荐属性
	*/
	public function j($table,$classid,$num=10,$field=""){		
		if($this->attrib($table,"attrib")){
			$where=$this->whereclass($classid);
			return D($table)->field($this->fieldhandle($field)."id,title,html")->where('status="1" and attrib_j="1" '.$where)->sort("id","order","desc")->limit($num)->getall();
		}
	}

	/*
	栏目热门属性
	*/
	public function r($table,$classid,$num=10,$field=""){
		if($this->attrib($table,"attrib")){
			$where=$this->whereclass($classid);
			return D($table)->field($this->fieldhandle($field)."id,title,html")->where('status="1" and attrib_r="1" '.$where)->sort("id","order","desc")->limit($num)->getall();
		}
	}

	/*
	栏目幻灯属性
	*/
	public function h($table,$classid,$num=5,$field=""){
		if($this->attrib($table,"attrib")){
			$where=$this->whereclass($classid);
			return D($table)->field($this->fieldhandle($field)."id,title,img,html")->where('status="1" and attrib_h="1" '.$where)->sort("id","order","desc")->limit($num)->getall();
		}
	}

	/*
	查询关联栏目
	*/
	public function whereclass($classid){
		$classarray=array_filter(explode(",",M("classify")->children($classid)));
		if(!empty($classarray)){
			return ' and ('.implode(" or ",$classarray).')';
		}
	}

	/*
	搜索函数
	*/
    public function search($arrays, $field="", $num=20){
        // Legacy templates call search($_GET, ...). Keep that entry point and
        // capture its request context before entering the explicit query API.
        $criteria = CmsSearchCriteria::fromInput((array)$arrays, $_GET['page'] ?? '1');
        return $this->searchPage($criteria, CmsPageContext::fromGlobals($criteria->page), $field, $num);
    }

    /** Search with validated input and explicit pagination context. */
    public function searchPage(CmsSearchCriteria $criteria, CmsPageContext $pageContext, $field="", $num=20): array {
        $needle = '%'.$criteria->tags.'%';
        $project = $criteria->project;
        $modules = D('module')->where(['type'=>1,'search'=>1,'status'=>1])->getall();
        $queries = []; $params = [];
        foreach ($modules as $module) {
            if ($project !== '' && $project !== $module['project']) { continue; }
            $table = model::identifier(DB_PREFIX.$module['project']);
            $hasTags = $this->attrib($module['project'], 'tags');
            $columns = $project !== '' ? '*' : $this->fieldhandle($field).'id,title,html,time';
            $queries[] = 'SELECT '.$columns.' FROM '.$table.' WHERE status=1 AND (title LIKE ?'.($hasTags ? ' OR tags LIKE ?' : '').')';
            $params[] = $needle;
            if ($hasTags) { $params[] = $needle; }
        }
        if (!$queries) {
            $pager = new page(0, $num, [], $pageContext);
            return ['list'=>[], 'fy'=>$pager->getpage(), 'total'=>0];
        }
        $source = ' FROM ('.implode(' UNION ', $queries).') search_results';
        $repository = D($this->d_name);
        // These generated SELECTs can share the ordinary connection when the
        // template supplied only column names. Keep custom SQL expressions on
        // the native SQL isolation path because they may change session state.
        $trustedRead = $field === '' || preg_match('/^\s*[A-Za-z_][A-Za-z0-9_]*(?:\s*,\s*[A-Za-z_][A-Za-z0-9_]*)*\s*$/D', $field) === 1;
        $count = $repository->excsql('SELECT COUNT(*) AS total'.$source, $params, $trustedRead);
        $total=(int)($count[0]['total'] ?? 0);
        $pager = new page($total, $num, [], $pageContext, 100);
        $info = $pager->getinfo();
        $offset = ($info['curpage'] - 1) * $info['pageper'];
        $pagination=$pager->getpage();
        if ($total>100*$info['pageper']) {
            $pagination=cms_html((string)$pagination.' <span class="search-limit">最多可浏览前 100 页，请缩小关键词范围以查找更多结果。</span>');
        }
        return [
            'list' => $repository->excsql('SELECT *'.$source.' ORDER BY time DESC, id DESC, html ASC LIMIT '.$offset.','.$info['pageper'], $params, $trustedRead),
            'fy' => $pagination,
            'total' => $info['total'],
        ];
    }

	/*
	记录分页
	*/
	public function page($table,$classid,$num=20){
		$where=$this->whereclass($classid);
		return sqlpage($table,$num,'*','status=1 '.$where,'id desc');
	}

	/*
	内容
	*/
	public function content($table,$id){

		$arr=D($table)->where(['id'=>(int)$id,'status'=>1])->getone();
        if (!$arr) { cms_fail(404, '内容不存在。'); }
		
		if($table!="single"){

			if($this->attrib($table,"hits")){

				CmsDatabaseGate::write(fn()=>D($table)->where(['id'=>(int)$id])->update("hits=hits+1"));
			}
			
			$arr['classname']=M("classify")->classname($arr['classid']);

			$arr['origin']=$this->aurl('origin',$arr['origin']);

			$arr['author']=$this->aurl('author',$arr['author']);

			$arr['inputer']=$this->aurl('inputer',$arr['inputer']);

			$arr['tags']=$this->atags($arr['tags']);
            foreach (['origin','author','inputer','tags'] as $field) { $arr[$field]=cms_html($arr[$field]); }
		}
		foreach (cms_richtext_fields($table) as $field) { $arr[$field]=cms_html($arr[$field] ?? ''); }
		return $arr;
		
	}

	private function aurl($table,$string){
		$aurl=D($table)->where(['name'=>$string])->getone();
		if(!empty($aurl['url'])){
			return '<a href="'.cms_escape(cms_safe_url($aurl['url'])).'" target="_blank" rel="noopener">'.cms_escape($aurl['name']).'</a>';
		}else{
			return cms_escape($string);
		}
	}

	private function atags($string){
        // 空标签不产生链接；固定实体表的参数化读取可复用请求连接。
        if ((string)$string==='') { return ''; }
		$names=explode(',',(string)$string);
		$unique=array_values(array_unique($names));
		$found=[];
		if ($unique) {
			$placeholders=implode(',',array_fill(0,count($unique),'?'));
			$rows=D('tags')->excsql('SELECT name FROM '.model::identifier(DB_PREFIX.'tags').' WHERE name IN ('.$placeholders.')', $unique, true);
			foreach ($rows as $row) { $found[$row['name']]=true; }
		}
		$links=[];
		foreach ($names as $name) {
			$links[]=isset($found[$name])
				? '<a href="/?controller=search&amp;tags='.rawurlencode($name).'" target="_blank">'.cms_escape($name).'</a>'
				: cms_escape($name);
		}
		return implode(',',$links);
	}

	/*
	整站幻灯属性
	*/
	public function wh($num=5,$field=""){
		$arr=D($this->d_name)->field("project")->where(['type'=>1,'status'=>1])->getall();
		$array=[];
		foreach($arr as $k => $v){
			if($this->attrib($v['project'],"attrib") && $this->attrib($v['project'],"img")){
				$array[]='select '.$this->fieldhandle($field).'id,title,img,html from `'.DB_PREFIX.$v['project'].'` where img!="" and attrib_h=1 and status=1';
			}
		}
		if($array){
			return D($this->d_name)->excsql("select * from (".implode(" union ",$array).") h order by id desc limit ".$num);
		}
        return [];
	}

	/*
	整站头条
	*/
	public function wt($num=1,$field=""){

		$arr=D($this->d_name)->field("project")->where(['type'=>1,'status'=>1])->getall();

		$array=[];

		foreach($arr as $k => $v){
			if($this->attrib($v['project'],"title") && $this->attrib($v['project'],"attrib")){
				$array[]='select '.$this->fieldhandle($field).'id,title,info,html from `'.DB_PREFIX.$v['project'].'` where attrib_t=1 and status=1';
			}
		}
		if($array){
			return D($this->d_name)->excsql("select  * from (".implode(" union ",$array).") t order by id desc limit ".$num);
		}
        return [];
	}

	/*
	整站最新
	*/
	public function wn($num=15,$field=""){

		$arr=D($this->d_name)->field("project")->where(['type'=>1,'status'=>1])->getall();

		$array=[];

		foreach($arr as $k => $v){
			if($this->attrib($v['project'],"title") && $this->attrib($v['project'],"attrib")){
				$array[]='select '.$this->fieldhandle($field).'id,title,html from `'.DB_PREFIX.$v['project'].'` where status=1';
			}
		}
		if($array){
			return D($this->d_name)->excsql("select  * from (".implode(" union ",$array).") n order by id desc limit ".$num);
		}
        return [];
	}

	/*
	整站最新图片
	*/
	public function wpic($num=3,$field=""){

		$arr=D($this->d_name)->field("project")->where(['type'=>1,'status'=>1])->getall();

		$array=[];

		foreach($arr as $k => $v){

			if($this->attrib($v['project'],"title") && $this->attrib($v['project'],"img")){
				$array[]='select '.$this->fieldhandle($field).'id,title,img,html from `'.DB_PREFIX.$v['project'].'` where img!="" and status=1';
			}
		}
		if($array){
			return D($this->d_name)->excsql("select  * from (".implode(" union ",$array).") n order by id desc limit ".$num);
		}
        return [];
	}

	/*
	相关文章
	*/
    public function related($table, $string, $num=10, $field=""){
        if (!is_string($table) || !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,49}$/D', $table) ||
            !is_string($string) || strlen($string)>1024 || !$this->attrib($table,'title') || !$this->attrib($table,'tags')) { return []; }
        $tags=[];
        foreach (array_slice(array_filter(explode(',', $string)), 0, self::RELATED_MAX_TAGS) as $tag) {
            if ($tag!=='' && strlen($tag)<=self::RELATED_MAX_TAG_BYTES) { $tags[]=$tag; }
        }
        if (!$tags) { return []; }

        // Scan the primary key only, so every supported content table (including
        // imported models without secondary indexes) has a hard candidate bound.
        $excludeId=0;
        $requestProject=$_GET['project']??null;
        $requestId=$_GET['id']??null;
        if ($requestProject===$table && is_string($requestId) && preg_match('/^[1-9][0-9]{0,9}$/D',$requestId) &&
            (int)$requestId<=2147483647) { $excludeId=(int)$requestId; }
        $limit=max(0,min((int)$num,100));
        if ($limit===0) { return []; }
        $candidateQuery=D($table)->field('id');
        $candidateIds=array_map('intval',array_column(
            $candidateQuery->sort('id','order','desc')->limit(self::RELATED_CANDIDATE_LIMIT)->getall(),'id'
        ));
        if (!$candidateIds) { return []; }

        $matches=[]; $params=$candidateIds;
        $currentCondition=$excludeId>0 ? ' AND id<>?' : '';
        if ($excludeId>0) { $params[]=$excludeId; }
        foreach ($tags as $tag) {
            $matches[]='(title LIKE ? OR tags LIKE ?)';
            $params[]='%'.$tag.'%'; $params[]='%'.$tag.'%';
        }
        $idPlaceholders=implode(',',array_fill(0,count($candidateIds),'?'));
        return D($table)->field($this->fieldhandle($field).'id,title,html')
            ->where('status=1 AND id IN ('.$idPlaceholders.')'.$currentCondition.' AND ('.implode(' OR ',$matches).')',$params)
            ->sort('id','order','desc')->limit($limit)->getall();
    }

	/*
	位置
	*/
	public function location($classid,$str=">>"){
		$array=explode(',',M("classify")->wz($classid,$str));
		return array_map('cms_html', array_reverse(array_unique(array_filter($array))));
	}

	/*
	网站标题
	*/
	public function title($classid,$str="-"){
		$array=explode(',',M("classify")->title($classid,$str));
		return array_unique(array_filter($array));
	}

	private function attrib($project,$attrib){
        $key = hash('sha256', serialize([DB_HOST, DB_NAME, DB_PREFIX, $project, $attrib]));
        $revision = model::requestWriteRevision();
        if (!isset(self::$attributeAvailability[$key]) || self::$attributeAvailability[$key]['revision'] !== $revision) {
            self::$attributeAvailability[$key] = [
                'revision' => $revision,
                'exists' => D('view_field')->where(['project'=>$project,'name'=>$attrib])->getcount() > 0,
            ];
        }
        if(self::$attributeAvailability[$key]['exists']){

			switch($attrib){

			case "attrib":

				return "attrib_j,attrib_g,attrib_t,attrib_r,attrib_d,attrib_h";

			  break;

			default:

			  return $attrib;
			}
		}else{

			return false;
		}
	}

	private function fieldhandle($field){
		if($field){
			return implode(",",array_filter(explode(",",$field))).",";
		}
	}
}







?>
