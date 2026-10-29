<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
//
defined('TPL_INCLUDE') or die( 'Restricted access'); 

class classifyModel extends model{

	private $d_name='classify';

    /**
     * 获取文章列表
     *
     * @return array
     */
	public function findall($where="",$sort="sort,id",$field="*"){
		return D($this->d_name)->field($field)->where($where)->sort($sort)->getall();
	}

    /**
     * 获取文章列表
     *
     * @return array
     */
	public function findone($where,$field='*'){
		return D($this->d_name)->field($field)->where($where)->getone();
	}

    /**
     * 查询下级栏目
     *
     * @return array
     */
	public function children($id){

		$arr=$this->findall(['classid'=>(int)$id],'id,classid');

			foreach($arr as $k => $v){				

				$array.='classid = "'.(int)$v['id'].'" or ';

				if(D($this->d_name)->where(['classid'=>(int)$v['id']])->getcount()>0){

					$array.=$this->children($v['id']);
				}
			}

		return $array;
	}

	public function descendantIds(int $id): array{
		$children=[];
		foreach(D($this->d_name)->field('id,classid')->getall() as $row){
			$children[(int)$row['classid']][]=(int)$row['id'];
		}
		$seen=[];
		$pending=[$id];
		while($pending){
			$current=array_pop($pending);
			if(isset($seen[$current])){continue;}
			$seen[$current]=true;
			foreach($children[$current]??[] as $child){$pending[]=$child;}
		}
		return array_keys($seen);
	}


    /**
     * 遍历所有栏目
     *
     * @return option
     */
	public function procoption($array,$vle=0,$w=false,$i=0){
		$arr='';
		if(is_array($array)){
			if(count($array)>0){
				foreach($array as $k => $v) {
					if($w){
						($v['id']==$vle)?$option='selected':$option="";
						if(is_array($v['classid']) && count($v['classid'])>0){
							$arr .= '<optgroup label="'.str_repeat('──',$i).cms_escape($v['name']).'"></optgroup>';
							$arr .= $this->procoption($v['classid'],$vle,$w,$i+1);
						}else{
							$arr .= '<option value="'.$v['id'].'" '.$option.'>'.str_repeat('──',$i).cms_escape($v['name']).'</option>';
						}
					}else{
						($v['id']==$vle)?$option='selected':$option="";
						$arr .= '<option value="'.$v['id'].'" '.$option.'>'.str_repeat('──',$i).cms_escape($v['name']).'</option>';
						if(is_array($v['classid']) && count($v['classid'])>0){
							$arr .= $this->procoption($v['classid'],$vle,$w,$i+1);
						}
					}
				}
			}
			return cms_html($arr ?? '');
		}
	}

    /**
     * 遍历所有栏目
     *
     * @return array
     */
	public function classifytree($data,$classid=0){
        $children = [];
        foreach ($data as $row) { $children[(int)$row['classid']][] = $row; }
        $seen = [];
        return $this->buildClassifyTree($children, (int)$classid, $seen);
	}

    private function buildClassifyTree(array &$children, int $parent, array &$seen): array {
        $tree = [];
        foreach ($children[$parent] ?? [] as $row) {
            $id = (int)$row['id'];
            if (isset($seen[$id])) { continue; }
            $seen[$id] = true;
            $row['classid'] = $this->buildClassifyTree($children, $id, $seen);
            $tree[] = $row;
        }
        return $tree;
    }

   /**
     * 排序
     *
     * @return array
     */
    public function order($array){
        $pairs=cms_sort_pairs($array['id']??null,$array['sort']??null);
        if(!$pairs){return ['status'=>'a'];}
        foreach($pairs as [$id,$sort]){
            if(!D($this->d_name)->where(['id'=>$id])->getone()){return ['status'=>'n'];}
        }
        foreach($pairs as [$id,$sort]){
            D($this->d_name)->where(['id'=>$id])->update(['sort'=>$sort,'time'=>time()]);
        }
        return ['status'=>'y'];
    }

    /**
     * 获取文章列表
     *
     * @return array
     */
	public function save($array){
		$rawId=$array['id']??null;
		if($rawId!==null && (!is_string($rawId) || !preg_match('/^[1-9][0-9]{0,9}$/D',$rawId))){cms_fail(400,'栏目 ID 不正确。');}
		$id=$rawId===null?null:(int)$rawId;
		$common=['name','nav','intro','img','key','description','channel_template','classify_template','content_template','url_type'];
		if($id!==null){
			$existing=D($this->d_name)->where(['id'=>$id])->getone();
			if(!$existing){return ['status'=>'n','id'=>$id];}
			// 栏目归属与层级在修改表单中不可编辑，外链地址仅供外链栏目修改。
			$allowed=$common;
			if($existing['type']==='url'){$allowed[]='html';}
		}else{
			$allowed=array_merge($common,['type','project','enname','classid']);
			if(($array['type']??null)==='url'){$allowed[]='html';}
		}
		$array=array_intersect_key($array,array_fill_keys($allowed,true));
		foreach($array as $value){if(!is_string($value) && !is_int($value)){cms_fail(400,'栏目内容不正确。');}}
		if(array_key_exists('intro',$array)){$array['intro']=cms_clean_html_for_save($array['intro']);}
		if($id===null && ($array['type']??null)!=='url' &&
			(!isset($array['enname']) || !preg_match('/^[A-Za-z0-9_-]{1,50}$/D',$array['enname']))){
			cms_fail(400,'栏目英文名称只能包含字母、数字、下划线和连字符，且不能超过 50 字符。');
		}
		if(array_key_exists('html',$array) && $array['html']!=='' && cms_safe_url($array['html'])===''){
			cms_fail(400,'外链地址只允许安全的链接。');
		}
		if(array_key_exists('time',$_POST)){$array['time']=time();}
		if($id!==null){
			$re=$array?D($this->d_name)->where(['id'=>$id])->update($array):false;
		}else{
			if(($array['type']??null)==='single'){$array['project']='single';}
			if(($array['type']??null)==='module'){$this->assertModuleProject($array['project']??null);}
			$array['topid']=$this->topid($array['classid']??0);
			$id=D($this->d_name)->insert($array);
			$re=$id;
			if($id && $array['type']!=='url'){
				$re=D($this->d_name)->where(['id'=>$id])->update(['html'=>'/'.$array['project'].'_'.$array['enname'].'_'.$id.'.html']);
			}
		}

		if($re){

			return array('status' => 'y','id' => $id);

		}else{

			return array('status' => 'n','id' => $id);
		}

	}

	private function assertModuleProject(mixed $project): void{
		if(!is_string($project) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,29}$/D',$project)){
			cms_fail(400,'栏目所属模型不正确。');
		}
		$module=D('module')->where(['project'=>$project,'type'=>1])->getone();
		if(!$module || (int)$module['status']<0){cms_fail(409,'栏目所属模型不存在或尚未完成创建。');}
	}

	private function topid($classid){

		if($classid>0){
			$arr=D($this->d_name)->where(['id'=>(int)$classid])->getone();
			if(!$arr){cms_fail(400,'父栏目不存在。');}
			if($arr['topid']>0){
				return $arr['topid'];
			}else{
				return $arr['id'];
			}
		}else{
				return 0;
		}
	}


    /**
     * 获取文章列表
     *
     * @return array
     */
	public function batchsave($classify){
		if(($classify['type']??null)!=='module' || !is_string($classify['project']??null) ||
			!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D',$classify['project'])){
			cms_fail(400,'批量栏目类型或模型不正确。');
		}
		$this->assertModuleProject($classify['project']);
		foreach(['name','classid','intro','img','key','description','channel_template','classify_template','content_template'] as $field){
			if(isset($classify[$field]) && !is_string($classify[$field])){cms_fail(400,'栏目内容不正确。');}
		}
		if(isset($classify['classid']) && !preg_match('/^[0-9]{1,10}$/D',$classify['classid'])){
			cms_fail(400,'父栏目不正确。');
		}
		$names=$classify['name']??null;
		if(!is_string($names) || $names===''){cms_fail(400,'栏目内容不正确。');}
		$intro=cms_clean_html_for_save($classify['intro']??'');
		$topid=$this->topid($classify['classid']??0);
		$rows=[];
		foreach(explode("\n",$names) as $v){
			if(trim($v)===''){continue;}
			$array=[];

			$c=explode("|",$v);
			if(count($c)!==2 || trim($c[0])===''){cms_fail(400,'批量栏目格式不正确。');}

			$array['name']				=	trim($c[0]);
			$array['enname']			=	trim($c[1]??'');
			if(!preg_match('/^[A-Za-z0-9_-]{1,50}$/D',$array['enname'])){
				cms_fail(400,'栏目英文名称只能包含字母、数字、下划线和连字符，且不能超过 50 字符。');
			}

			$array['topid']				=	$topid;

			$array['classid']			=	$classify['classid']??0;
			$array['type']				=	$classify['type']??'';
			$array['project']			=	$classify['project']??'';
			$array['intro']				=	$intro;
			$array['img']				=	$classify['img']??'';
			$array['key']				=	$classify['key']??'';
			$array['description']		=	$classify['description']??'';
			$array['time']				=	time();
			$array['channel_template']	=	$classify['channel_template']??'';
			$array['classify_template']	=	$classify['classify_template']??'';
			$array['content_template']	=	$classify['content_template']??'';

			$rows[]=$array;
		}
		if(!$rows){cms_fail(400,'栏目内容不正确。');}
		foreach($rows as $array){
			$id=D($this->d_name)->insert($array);

			$re=D($this->d_name)->where(['id'=>(int)$id])->update(array(
				'html'	=>	'/'.$array['project'].'_'.$array['enname'].'_'.$id.'.html'
				));
			if(!$re) return array('status' => 'n','id' => $id);
		}

			return array('status' => 'y','id' => $id);
	}

}







?>
