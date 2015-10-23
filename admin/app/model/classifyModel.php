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

		$arr=$this->findall('classid = "'.$id.'"','id,classid');

			foreach($arr as $k => $v){				

				$array.='classid = "'.$v['id'].'" or ';

				if(D($this->d_name)->where('classid="'.$v['id'].'"')->getcount()>0){

					$array.=$this->children($v['id']);
				}
			}

		return $array;
	}


    /**
     * 遍历所有栏目
     *
     * @return option
     */
	public function procoption($array,$vle=0,$w=false,$i=0){
		if(is_array($array)){
			if(count($array)>0){
				foreach($array as $k => $v) {
					if($w){
						($v['id']==$vle)?$option='selected':$option="";
						if(count($v['classid'])>0){
							$arr .= '<optgroup label="'.str_repeat('──',$i).$v['name'].'"></optgroup>';
							$arr .= $this->procoption($v['classid'],$vle,$w,$i+1);
						}else{
							$arr .= '<option value="'.$v['id'].'" '.$option.'>'.str_repeat('──',$i).$v['name'].'</option>';
						}
					}else{
						($v['id']==$vle)?$option='selected':$option="";
						$arr .= '<option value="'.$v['id'].'" '.$option.'>'.str_repeat('──',$i).$v['name'].'</option>';
						if(count($v['classid'])>0){
							$arr .= $this->procoption($v['classid'],$vle,$w,$i+1);
						}
					}
				}
			}
			return $arr;
		}
	}

    /**
     * 遍历所有栏目
     *
     * @return array
     */
	public function classifytree($data,$classid=0){
	$tree = array();
	foreach($data as $k => $v){
	   if($v['classid'] == $classid){
		$v['classid'] = $this->classifytree($data, $v['id']);
		$tree[] = $v;
	   }
	}
	return $tree;
	}

   /**
     * 排序
     *
     * @return array
     */
	public function order($array){
		foreach($array['id'] as $k => $v){
			if(!D("classify")->where('id="'.$v.'"')->update(array(
				'sort'	=>  $array['sort'][$k],
				'time'	=>  time()
			)))return array('status' => 'n');
		}
		return array('status' => 'y');
	}

    /**
     * 获取文章列表
     *
     * @return array
     */
	public function save($array){

		//$array=array_filter($array);

		if($array['id']){

			$id=$array['id'];unset($array['id']);

			$re=D('classify')->where('id="'.$id.'"')->update($array);

		}else{

			if($array['type']=='single'){

				$array['project']="single";
					
			}

			$array['topid'] = $this->topid($array['classid']);
			
			$id=D($this->d_name)->insert($array);
			
			if($array['type']=="url"){

				$re=$id;

			}else{

				$re=D($this->d_name)->where('id="'.$id.'"')->update(array(
					'html'	=>	'/'.$array['project'].'_'.$array['enname'].'_'.$id.'.html'
					));
			}
		}

		if($re){

			return array('status' => 'y','id' => $id);

		}else{

			return array('status' => 'n','id' => $id);
		}

	}

	private function topid($classid){

		if($classid>0){
			$arr=D($this->d_name)->where('id="'.$classid.'"')->getone();
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

		foreach(explode("\n",$classify['name']) as $v){

			$c=explode("|",$v);

			$array['name']				=	$c[0];
			$array['enname']			=	trim($c[1]);

			$array['topid']				=	$this->topid($classify['classid']);

			$array['classid']			=	$classify['classid'];
			$array['type']				=	$classify['type'];			
			$array['project']			=	$classify['project'];
			$array['intro']				=	$classify['intro'];
			$array['img']				=	$classify['img'];
			$array['key']				=	$classify['key'];
			$array['description']		=	$classify['description'];
			$array['time']				=	time();
			$array['channel_template']	=	$classify['channel_template'];
			$array['classify_template']	=	$classify['classify_template'];
			$array['content_template']	=	$classify['content_template'];

			$id=D($this->d_name)->insert($array);

			$re=D($this->d_name)->where('id="'.$id.'"')->update(array(
				'html'	=>	'/'.$classify['project'].'_'.$array['enname'].'_'.$id.'.html'
				));
			if(!$re) return array('status' => 'n','id' => $id);
		}

			return array('status' => 'y','id' => $id);
	}

}







?>