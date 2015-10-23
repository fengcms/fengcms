<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
// 首页模型

class classifyModel extends model{

	private $d_name		 = "classify";

	public function __construct(){
		unset($this->options);
		parent::__construct($this->d_name);
	}

	public function classify($classid=0,$type=""){
		if($type)$where=' and type="'.$type.'"';
		return D($this->d_name)->where('classid="'.$classid.'"'.$where)->getall();
	}

	public function classside($classid){
		$array=D($this->d_name)->where('id="'.$classid.'"')->getone();
		return D($this->d_name)->where('classid="'.$array['classid'].'"')->getall();
	}


	public function classname($classid){
		$array=D($this->d_name)->where('id="'.$classid.'"')->getone();
		return $array['name'];
	}

    /**
     * 查询下级栏目
     *
     * @return array
     */
	public function children($id){

		$arr=D($this->d_name)->field('id,classid')->where('classid = "'.$id.'"')->getall();

			foreach($arr as $k => $v){				

				$array.='classid = "'.$v['id'].'",';

				if(D($this->d_name)->field('id')->where('classid="'.$v['id'].'"')->getcount()>0){

					$array.=$this->children($v['id']).',';
				}
			}
			$array.='classid = "'.$id.'",';

		return $array;
	}

    /**
     * 所在位置导航
     *
     * @return array
     */
	public function wz($id,$str){

		$arr=D($this->d_name)->field('id,classid,name,html')->where('id = "'.$id.'"')->getone();

		$array.=' '.$str.' <a href="'.url($arr['html']).'">'.$arr['name'].'</a>,';

		if($arr['classid']>0){

			$array.=$this->wz($arr['classid'],$str).',';
		}
		return $array;
	}

    /**
     * 网站标题
     *
     * @return array
     */
	public function title($id,$str){

		$arr=D($this->d_name)->field('id,classid,name,html')->where('id = "'.$id.'"')->getone();

		$array.=$arr['name'].' '.$str.' ,';

		if($arr['classid']>0){

			$array.=$this->title($arr['classid'],$str).',';
		}
		return $array;
	}

    /**
     * 网站地图
     *
     * @return array
     */
	public function map($classid=0){

		$arr=D($this->d_name)->field('id,classid,name,html')->where('classid = "'.$classid.'"')->sort("sort")->getall();

		foreach($arr as $v){

		$array.='<li><a href="'.url($v['html']).'">'.$v['name'].'</a>';

			if(D($this->d_name)->field('id')->where('classid="'.$v['id'].'"')->getcount()>0){
				$array.="<ul>".$this->map($v['id'])."</ul></li>";
			}else{
				$array.="</li>";
			}
		}
		return $array;
	}
}







?>