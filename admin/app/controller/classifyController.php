<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
// 栏目管理
include "admin.php";

class classifyController extends Controller{

	private $model = 'classify';

    /**
     * 栏目列表
     * @access public
     * @return template,array
     */
	public function index(){
		$where=($_GET['id'])?'classid="'.$_GET['id'].'"':"classid=0";
		return $this->display('classify/index.html',array("classify"	=>	M($this->model)->findall($where)));
	}

    /**
     * 栏目添加
     * @access public
     * @return template,array
     */
	public function add(){
		return $this->display('classify/add.html');
	}

    /**
     * 栏目添加
     * @access public
     * @return template,array
     */
	public function shift(){
		return $this->display('classify/shift.html');
	}

     /**
     * 栏目排序
     * @access public
     * @return template,array
     */
    public function sorts(){
		$where=$_GET['classid']?'classid="'.$_GET['classid'].'"':'classid="0"';
        return $this->display("classify/sorts.html",array("classify" => M($this->model)->findall($where)));
    }
    /**
     * 栏目排序执行
     * @access public
     * @return template,array
     */
    public function order(){
        echo json_encode(M($this->model)->order($_POST));
    }
    /**
     * 栏目修改
     * @access public
     * @return template,array
     */
	public function update(){
		return $this->display('classify/update.html',M($this->model)->findone('id="'.$_GET['id'].'"'));
	}

    /**
     * 栏目频道
     * @access public
     * @return template,array
     */
	public function classify(){
		$where='type="module"';
		if($_GET['p']){
			if($where)$where.=' and ';
			$where.='project="'.$_GET['p'].'"';
		}

			return $this->display('classify/classify.html',array(
			'classifyselect'	=>	M($this->model)->procoption(M($this->model)->classifytree(M($this->model)->findall($where))),0,false)
		);
	}

    /**
     * 栏目类型
     * @access public
     * @return template,array
     */
	public function type(){
		return $this->display('classify/type_'.$_GET['t'].'.html');
	}

    /**
     * 栏目类型
     * @access public
     * @return template,array
     */
	public function tps(){
		if($_GET['t']=="single"){
			return $this->display('classify/type_single.html');
		}else{
			return $this->display('classify/type_classify.html');
		}		
	}
    /**
     * 栏目添加方法
     * @access public
     * @return template,array
     */
	public function genre(){
		return $this->display('classify/type_module_'.$_GET['f'].'.html');
	}

    /**
     * 单条栏目删除
     *
     * @return array
     */
	public function delete(){
        $arr=M($this->model)->where('id="'.$_GET['id'].'"')->getone();

        if(M($this->model)->where('classid="'.$_GET['id'].'"')->getcount()>0){
            
                $re=$this->traversaldelete(M($this->model)->classifytree(M($this->model)->findall(),$_GET['id']),$arr['project'])&&D($this->model)->where('id="'.$_GET['id'].'"')->delete();

            if($re){
                echo '<script type="text/javascript">alert("删除成功！");</script>';
                echo '<meta http-equiv="refresh" content="0;url=?controller=classify">';
            }

        }else{
            
		  if(D($arr['project'])->where('classid="'.$_GET['id'].'"')->getcount()>0){

			  $re=D($arr['project'])->where('classid="'.$_GET['id'].'"')->delete()&&D($this->model)->where('id="'.$_GET['id'].'"')->delete();

		  }else{

			  $re=D($this->model)->where('id="'.$_GET['id'].'"')->delete();

		  }

            if($re){
                echo '<script type="text/javascript">alert("删除成功！");</script>';
                echo '<meta http-equiv="refresh" content="0;url=?controller=classify">';
            }else{
                echo '<script type="text/javascript">alert("删除失败！");</script>';
                echo '<meta http-equiv="refresh" content="0;url=?controller=classify">';
            }
        }
	}

    /**
     * 清空栏目
     *
     * @return array
     */
	public function emptys(){

        $arr=M($this->model)->where('id="'.$_GET['id'].'"')->getone();
        
        if(M($this->model)->where('classid="'.$_GET['id'].'"')->getcount()>0){
            
            if($this->traversaldelete(M($this->model)->classifytree(M($this->model)->findall(),$_GET['id']),$arr['project'],false)){
                echo '<script type="text/javascript">alert("清空成功！");</script>';
                echo '<meta http-equiv="refresh" content="0;url=?controller=classify">';
            }

        }else{

            if(D($arr['project'])->where('classid="'.$arr['id'].'"')->delete()){
                echo '<script type="text/javascript">alert("清空成功！");</script>';
                echo '<meta http-equiv="refresh" content="0;url=?controller=classify">';
            }else{
                echo '<script type="text/javascript">alert("清空失败！");</script>';
                echo '<meta http-equiv="refresh" content="0;url=?controller=classify">';
            }
        }
	}

    /**
     * 批量删除
     *
     * @return array
     */
	public function batch(){
        if(!$_POST['classify']){
            echo json_encode(array('status' => 'a'));
        }else{
            if($this->batchdelete($_POST['classify'])){
                echo json_encode(array('status' => 'y'));
            }else{
                echo json_encode(array('status' => 'n'));
            }
        }
	}

    /**
     * 批量删除
     *
     * @return array
     */
    public function batchdelete($array){

        foreach($array as $v){

            $arr=M($this->model)->findone('id="'.$v.'"');

            if($arr['type']=="module"){

                if(M($this->model)->where('classid="'.$arr['id'].'"')->getcount()>0){

                    $re=D($this->model)->where('id="'.$arr['id'].'"')->delete()&&$this->traversaldelete(M($this->model)->classifytree(M($this->model)->findall(),$arr['id']),$arr['project']);

                }else{
                    if(D($arr['project'])->where('classid="'.$arr['id'].'"')->getcount()>0){
                     
                        $re=D($arr['project'])->where('classid="'.$arr['id'].'"')->delete()&&D($this->model)->where('id="'.$arr['id'].'"')->delete();

                    }else{
                        $re=D($this->model)->where('id="'.$arr['id'].'"')->delete();
                    }

                }

            }elseif($arr['type']=="url"){

                $re=D($this->model)->where('id="'.$arr['id'].'"')->delete();

            }elseif($arr['type']=="single"){

               
                    if(D($arr['project'])->where('classid="'.$arr['id'].'"')->getcount()>0){
                     
                         $re=D($arr['project'])->where('classid="'.$arr['id'].'"')->delete()&&D($this->model)->where('id="'.$arr['id'].'"')->delete();

                    }else{
                        $re=D($this->model)->where('id="'.$arr['id'].'"')->delete();
                    }
            }
        }
        if($re){

               return true;

            }else{

               return false;
            }
    }


    /**
     * 带有下级栏目删除
     *
     * @return array
     */
    public function traversaldelete($array,$table,$w=true){

        foreach($array as $k => $v){

            if($w){
                
                if($v['type']=='module'){
					if(D($table)->where('classid="'.$v['id'].'"')->getcount()>0){
						D($table)->where('classid="'.$v['id'].'"')->delete();
					}
					D($this->model)->where('id="'.$v['id'].'"')->delete();
                }elseif($v['type']=='url'){
                    
                    if(!D($this->model)->where('id="'.$v['id'].'"')->delete()) return false;                

                }

            }else{

                D($table)->where('classid="'.$v['id'].'"')->delete();

            }

            if($v['classid']){

                $this->traversaldelete($v['classid'],$table,true);

            }
        }
        return true;
    }

    /**
     * 保存信息
     *
     * @return array
     */
	public function save(){
		$genre=$_POST['genre'];
		unset($_POST['genre']);
		if($genre=="one" || $genre==""){
			echo json_encode(M($this->model)->save($_POST));
		}elseif($genre=="batch"){
			echo json_encode(M($this->model)->batchsave($_POST));
		}
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function execution(){
		return $this->display('classify/execution.html');
	}
}






?>