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

class kindeditor{

	public $root_path;
	public $root_url	= '/';
	public $pic_ext		= array('gif', 'jpg', 'jpeg', 'png', 'bmp');
	public $dir_name	= "";

	public $current_path;
	public $current_url;
	public $current_dir_path;
	public $moveup_dir_path;

    /**
     * 初始化
     *
     * @return void
     */
    public function __construct(){

		$this->php_path = ABS_PATH;
		$this->dir_name = empty($_GET['dir']) ? '' : trim($_GET['dir']);
		if (!in_array($this->dir_name, array('', 'image', 'flash', 'media', 'file'))){
			echo "Invalid Directory name.";
			exit;
		}
	}

	public function mdirs(){
		$this->root_path .= $this->dir_name . "/";
		$this->root_url .= $this->dir_name . "/";
		if (!file_exists($this->root_path)) {
			mkdir($this->root_path);
		}
	}

	public function setpath(){
		if (empty($_GET['path'])) {
			$this->current_path = realpath($this->root_path) . '/';
			$this->current_url = $this->root_url;
			$this->current_dir_path = '';
			$this->moveup_dir_path = '';
		} else {
			$this->current_path = realpath($this->root_path) . '/' . $_GET['path'];
			$this->current_url = $this->root_url . $_GET['path'];
			$this->current_dir_path = $_GET['path'];
			$this->moveup_dir_path = preg_replace('/(.*?)[^\/]+\/$/', '$1', $this->current_dir_path);
		}
		echo realpath($this->root_path);
	}

	public function order(){
		$order = empty($_GET['order']) ? 'name' : strtolower($_GET['order']);

		//不允许使用..移动到上一级目录
		if (preg_match('/\.\./', $this->current_path)) {
			echo $this->alert('Access is not allowed.');
			exit;
		}
		//最后一个字符不是/
		if (!preg_match('/\/$/', $this->current_path)) {
			echo $this->alert('Parameter is not valid.');
			exit;
		}
		//目录不存在或不是目录
		if (!file_exists($this->current_path) || !is_dir($this->current_path)) {
			echo $this->alert('Directory does not exist.');
			exit;
		}
	}

	public function file_list(){
		$file_list = array();
		if ($handle = opendir($this->current_path)) {
			$i = 0;
			while (false !== ($filename = readdir($handle))) {
				if ($filename{0} == '.') continue;
				$file = $this->current_path . $filename;
				if (is_dir($file)) {
					$file_list[$i]['is_dir'] = true; //是否文件夹
					$file_list[$i]['has_file'] = (count(scandir($file)) > 2); //文件夹是否包含文件
					$file_list[$i]['filesize'] = 0; //文件大小
					$file_list[$i]['is_photo'] = false; //是否图片
					$file_list[$i]['filetype'] = ''; //文件类别，用扩展名判断
				} else {
					$file_list[$i]['is_dir'] = false;
					$file_list[$i]['has_file'] = false;
					$file_list[$i]['filesize'] = filesize($file);
					$file_list[$i]['dir_path'] = '';
					$file_ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
					$file_list[$i]['is_photo'] = in_array($file_ext, $ext_arr);
					$file_list[$i]['filetype'] = $file_ext;
				}
				$file_list[$i]['filename'] = $filename; //文件名，包含扩展名
				$file_list[$i]['datetime'] = date('Y-m-d H:i:s', filemtime($file)); //文件最后修改时间
				$i++;
			}
			closedir($handle);
		}
		return $file_ext;
	}

	public function usorts(){

		usort($this->file_list(), 'cmp_func');

		$result = array();
		//相对于根目录的上一级目录
		$result['moveup_dir_path'] = $moveup_dir_path;
		//相对于根目录的当前目录
		$result['current_dir_path'] = $current_dir_path;
		//当前目录的URL
		$result['current_url'] = $current_url;
		//文件数
		$result['total_count'] = count($file_list);
		//文件列表数组
		$result['file_list'] = $file_list;

		//输出JSON字符串
		header('Content-type: application/json; charset=UTF-8');
		$json = new Services_JSON();
		return $json->encode($result);
	}

	public function alert($string){
		return $string;
	}

}

function cmp_func($a, $b) {
		global $order;
		if ($a['is_dir'] && !$b['is_dir']) {
			return -1;
		} else if (!$a['is_dir'] && $b['is_dir']) {
			return 1;
		} else {
			if ($order == 'size') {
				if ($a['filesize'] > $b['filesize']) {
					return 1;
				} else if ($a['filesize'] < $b['filesize']) {
					return -1;
				} else {
					return 0;
				}
			} else if ($order == 'type') {
				return strcmp($a['filetype'], $b['filetype']);
			} else {
				return strcmp($a['filename'], $b['filename']);
			}
		}
	}

?>