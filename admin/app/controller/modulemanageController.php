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

include "admin.php";
require_once ROOT_PATH.'/system/common/model_package.php';

class modulemanageController extends Controller{

	private $model = 'modulemanage';


    /**
     * 网站基础信息
     * @access public
     * @return template,array
     */
	public function index(){
		return $this->display('modulemanage/index.html',array(
			'module' =>	M($this->model)->findall("type=1")
			));
	}

    /**
     * 栏目添加
     * @access public
     * @return template,array
     */
	public function add(){
		return $this->display('modulemanage/add.html');
	}

    /**
     * 栏目添加
     * @access public
     * @return template,array
     */
	public function update(){
		return $this->display('modulemanage/update.html',M($this->model)->findone(['id'=>(int)$_GET['id']]));
	}

    /**
     * 栏目添加
     * @access public
     * @return template,array
     */
	public function disabled(){
		if (!in_array($_GET['f'] ?? null, ['0','1'], true)) { cms_fail(400, '模型状态不正确。'); }
		if(M($this->model)->setStatus((int)$_GET['f'],(int)$_GET['id'])){
			echo '<meta http-equiv="refresh" content="0;url=?controller=modulemanage">';
		}else{
			echo '<meta http-equiv="refresh" content="0;url=?controller=modulemanage">';
		}
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function delete(){
		$arr=M($this->model)->findone(['id'=>(int)$_GET['id']]);
		if(!$arr){cms_fail(404,'模型不存在。');}

		$deltable=D($arr['project'])->droptable();
		$delmodule=M($this->model)->delete(['id'=>(int)$arr['id']]);
		$delfield=M("fieldmanage")->delete(['module_id'=>(int)$arr['id']]);

		if($deltable&&$delmodule&&$delfield){
			echo '<meta http-equiv="refresh" content="0;url=?controller=modulemanage">';
		}else{
			echo '<meta http-equiv="refresh" content="0;url=?controller=modulemanage">';
		}
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function save(){
		echo json_encode(M($this->model)->save($_POST));
	}

    /**
     * 模块导出
     *
     * @return array
     */
	public function elicit(){
		try{$package=CmsModelPackage::export((int)($_GET['id']??0));}
        catch(InvalidArgumentException $e){cms_fail(400,$e->getMessage());}
        catch(Throwable $e){error_log('Model export failed: '.$e);cms_fail(500,'模型导出失败，请查看私有错误日志。');}
        while(ob_get_level()>0){ob_end_clean();}
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="fengcms-model-'.(int)$_GET['id'].'.fengmodel.json"');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo $package;
	}

    /**
     * 模块导入
     *
     * @return array
     */
	public function load(){
		return $this->display('modulemanage/load.html');
	}

    public function preview(){
        $project=$_POST['project']??null;
        if(!is_string($project)){cms_fail(400,'模型表名不正确。');}
        $json=$_POST['package_json']??'';
        if(isset($_FILES['package_file']) && $_FILES['package_file']['error']!==UPLOAD_ERR_NO_FILE){
            $file=$_FILES['package_file'];
            if($file['error']!==UPLOAD_ERR_OK || $file['size']>1048576 || !is_uploaded_file($file['tmp_name'])){
                cms_fail(400,'模型文件不正确或超过 1 MB。');
            }
            $json=file_get_contents($file['tmp_name']);
        }
        if(!is_string($json)){cms_fail(400,'模型文件不正确。');}
        try{$package=CmsModelPackage::parse($json,$project);}
        catch(InvalidArgumentException $e){cms_fail(400,$e->getMessage());}
        $_SESSION['model_import_preview']=['project'=>$project,'package'=>$package,'created'=>time(),'nonce'=>bin2hex(random_bytes(16))];
        $this->display('modulemanage/preview.html',[
            'project'=>$project,'package'=>$package,'nonce'=>$_SESSION['model_import_preview']['nonce'],
        ]);
    }

    /**
     * 模块导入
     *
     * @return array
     */
	public function loading(){
		$preview=$_SESSION['model_import_preview']??null;
        $nonce=$_POST['nonce']??null;
        if(!is_array($preview) || !is_string($nonce) || !hash_equals($preview['nonce'],$nonce) ||
            time()-(int)$preview['created']>600){cms_fail(400,'导入预览已过期，请重新选择模型。');}
        unset($_SESSION['model_import_preview']);
        try{CmsModelPackage::import($preview['package'],$preview['project']);}
        catch(InvalidArgumentException $e){cms_fail(400,$e->getMessage());}
        catch(Throwable $e){error_log('Model import failed: '.$e);cms_fail(500,'模型导入失败，请查看私有错误日志。');}
        header('Location: ?controller=modulemanage&imported=1',true,303);
	}

    /**
     * 保存信息
     *
     * @return array
     */
	public function execution(){
		return $this->display('modulemanage/execution.html');
	}

    /**
     * 保存信息
     *
     * @return array
     */
}






?>
