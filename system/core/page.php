<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
//	分页类

defined('TPL_INCLUDE') or die( 'Restricted access'); 
 
 class page {

    //当前URL
    var $url;
 
    //记录总数
    var $total;
 
    //每页记录数
    var $pageper;
 
    //当前页
    var $curpage;
 
    //总页数
    var $pagenum;
 
    //分页参数名
    var $pagetag = 'page'; 
 
    //显示数字分页数目
    var $showNum = 10;

    var $pagestyle = array (    
        'first'     =>  '首页', //设为null则不显示此项
        'prev'      =>  '上一页',
        'next'      =>  '下一页',
        'last'      =>  '末页',
        'select'    =>  '第%p%页', //选择分页
        'submit'    =>  '跳转',  //跳转显示字符
        //分页显示规则
        'pagerules' => '<em>共%itemtotal%条记录， %curpage%/%pagenum%页</em>  %first%  %prev%  %number%  %next%  %last%',//  %pageJumper%', 
        'pagetype'  => 'web', //分页类型 两种:web wap
        'emptyshow' => true,  //当分页数小于等于1时是否显示分页代码
    );    
 
 
    //静态地址规则
    private $rewriteRules = array('/(.*?_)([0-9]*)(\..*)/ies', '/(.*?)(\..*)/ies');
 
    /**
     * 构造函数
     * @access public
     * @param string $itemtotal 记录总数
     * @param string $pageper 每页记录数
     * @param string $pagetag 分页参数名
     * @param string $pagestyle 分页样式
     * return string
     */
    function __construct($itemtotal, $pageper = 10,  $pagestyle=array()) { 
        $this->curpage = @sprintf("%d", $_GET[$this->pagetag]); //获取当前页数
        $this->total = $itemtotal;  //记录总数
        $this->pageper = ($pageper>0) ? $pageper : 10; //每页记录数
        $this->pagetag = !empty($this->pagetag) ? $this->pagetag : 'page';   //分页参数名称                
        $this->pagenum = ceil($this->total / $pageper); //计算总页数        
        //校验当前页
        $this->curpage = ($this->curpage<=0) ? 1 : (($this->curpage > $this->pagenum) ? $this->pagenum : $this->curpage);
        $this->url = $this->geturl();  //得到分页URL
        //设置分页样式
        if (!empty($pagestyle) && is_array($pagestyle)){
            $this->pagestyle = array_merge($this->pagestyle,$pagestyle);
        }
 
    }
 
	
    /**
     * 获取总记录数
     * @access public
     * return string
     */
	function gettotal(){
		return $this -> total;		
	}



    /**
     * 获取分页
     * @access public
     * return string
     */
    public function getpage(){      
 
        if($this->pagenum<1){
        	return;
        }
        if(!$this->pagestyle['emptyshow']){
        	if($this->pagenum==1){
        		return;
        	}
        }
        $pageBar = $this->pagestyle['pagerules'];        
        //分页统计信息
        $pageBar = str_replace(array('%itemtotal%','%curpage%','%pagenum%','%pageper%'),array($this->total,$this->curpage,$this->pagenum,$this->pageper),$pageBar);
        //翻页代码
        $first = $this->getfirststr();        
        $prev  = $this->getprevstr();
        $next  = $this->getnextstr();
        $last  = $this->getlaststr();        
        $pageBar = str_replace('%first%',$first,$pageBar);
        $pageBar = str_replace('%prev%',$prev,$pageBar);
        $pageBar = str_replace('%next%',$next,$pageBar);
        $pageBar = str_replace('%last%',$last,$pageBar);
        //数字翻页
        $number = $this -> getnumberstr($this->showNum);
        $pageBar = str_replace('%number%',$number,$pageBar);
        if($this->pagestyle['pagetype'] == 'wap'){
            $pageBar = strip_tags($pageBar, "<a>,<br/>");
        }
        //分页跳转
        $jump = $this->getjumpstr('input');
        $pageBar = str_replace('%pageJumper%',$jump,$pageBar);
 
        if($this->pagestyle['pagetype'] =='web'){
            $jump_url = $this->geturl(null,false);
//            $pageBar = "<form name=\"page_jump\" action=\"$jump_url\" method=\"get\" style=\"width:auto;padding:0;margin:0;\">".$pageBar."<form>";
        }
 
        return $pageBar;
    }
 
 
    /**
     * 获取分页信息
     * @access public
     * return string
     */
    public function getinfo(){
        return array(
            'total'=>$this->total,
            'pageper'=>$this->pageper,
            'curpage'=>$this->curpage,
            'pagenum'=>$this->pagenum,
        );
    }
 
    /**
     * 数组分页
     * @param array $arr
     * @return null
     */
    public function handlearray(&$arr){
        if(empty($arr) || !is_array($arr)){
            return $arr;
        }
 
        $start = ($this->curpage-1) * $this->pageper;
        return array_slice($arr, $start, $this->pageper);
    }
 
    /**
     * sql分页
     * @param string $sql
     * @return string
     */
    public function handlesql(&$sql, $type='mysql'){
        if($type == 'mysql'){
            if(strstr(strtolower($sql), ' limit ') === false){
                $start = ($this->curpage-1)* $this->pageper;
                $len = $this->pageper;
                $sql = "$sql LIMIT $start, $len";
            }
        }
        //暂时不支持其他的ＤＢ库类型
        return $sql;
    }
 
    /**
     * 获取数字分页条
     * @access public     
     * return string
     */
    private function getnumberstr(){
        $numberStr = '';
        if ($this->showNum >= $this->pagenum) {            
            for ($i = 1; $i <= $this->pagenum; $i++) {
                $numberStr .= $this->makenumberpage($i);
            }
        } else {
            $center_page = ceil($this->showNum/2); 
            if ($this->curpage <= $center_page) {                
                for ($i = 1; $i <= $this->showNum; $i++) {
                    $numberStr .= $this->makenumberpage($i);
                }
            } else {
                if ($this->curpage+$center_page <= $this->pagenum) {
                    for($i=$this->curpage-$center_page+1;$i<=$this->curpage+$center_page;$i++){
                        $numberStr .= $this->makenumberpage($i);
                    }
                } else {
                    for ($i = $this->pagenum-$this->showNum+1; $i <= $this->pagenum; $i++) {
                        $numberStr .= $this->makenumberpage($i);
                    }
                }
            }
        }
        return $numberStr;        
    }
 
 
    /**
     * 获取返回首页字符串
     * return string
     */     
    private function getfirststr() {
        if(isset($this->pagestyle['first'])&&!empty($this->pagestyle['first'])){
            $pageFirstStr = ($this->curpage!=1) ? " <dd><a href=\"".$this->geturl(1)."\" hidefocus='true'>".trim($this->pagestyle['first'])."</a></dd> " : " <dd><u>".$this->pagestyle['first']."</u></dd> ";
        }
        return $pageFirstStr;
    }
 
    /**
     * 获取上一页字符串
     * return string
     */     
    private function getprevstr() {
        if(isset($this->pagestyle['prev'])&&!empty($this->pagestyle['prev'])){
            $pagePrevStr = ($this->curpage>1) ? " <dd><a href=\"".$this->geturl($this->curpage-1)."\" hidefocus='true'>".trim($this->pagestyle['prev'])."</a></dd> " : " <dd><u>".$this->pagestyle['prev']."</u></dd> ";
        }
        return $pagePrevStr;
    }
    /**
     * 获取下一页字符串
     * return string
     */     
    private function getnextstr() {
        if(isset($this->pagestyle['next'])&&!empty($this->pagestyle['next'])){
            $pageNextStr = ($this->curpage<$this->pagenum) ? " <dd><a href=\"".$this->geturl($this->curpage+1)."\" hidefocus='true'>".trim($this->pagestyle['next'])."</a></dd> " : " <dd><u>".$this->pagestyle['next']."</u></dd> ";
        }
        return $pageNextStr;
    }
    /**
     * 获取末页字符串
     * return string
     */     
    private function getlaststr() {
        if(isset($this->pagestyle['last'])&&!empty($this->pagestyle['last'])){
            $pageLastStr = ($this->curpage!=$this->pagenum) ? " <dd><a href=\"".$this->geturl($this->pagenum)."\" hidefocus='true'>".trim($this->pagestyle['last'])."</a></dd> " : " <dd><u>".$this->pagestyle['last']. "</u></dd> " ;
        }    
        return $pageLastStr;
    }
 
 
 
    /**
     * 获取跳转代码
     * @access public
     * @param string $mode 模式 select(选择框) input(文本框)
     * return string
     */
 
    private function getjumpstr($mode = 'select') {    
        //跳转URL
        if($this->pagestyle['pagetype'] =='wap'){
            $tmpurl = $_SERVER["PHP_SELF"];
            $tmparr = explode('/',$tmpurl);
            $jump_url = $tmparr[count($tmparr)-1];
            $gets = $_GET;
        }else{
            $jump_url = $this->geturl(null,false);
        }
        if($mode == 'select'){
            if($this->pagestyle['pagetype']=='wap'){            	
                $jumpStr = "<select name=\"page\">";
                for($i=1; $i<=$this->pagenum; $i++){                    
                    $tmp_str = str_replace('%p%', $i, $this->pagestyle['select']);
                    $jumpStr .= "<option value=\"".$i."\">".$tmp_str."</option>";
                }
                $jumpStr .= '</select>';
                $jumpStr .= "<anchor title=\"".$this->pagestyle['submit']."\">".$this->pagestyle['submit']."<go method=\"get\" href=\"$jump_url\"><postfield name=\"page\" value=\"$(page)\"/>";
                if(!empty($gets)){
                    unset($gets[$this->pagetag]);
                    foreach($gets as $key=>$val){
                        $jumpStr .= "<postfield name=\"$key\" value=\"$val\"/>";
                    }
                }
                $jumpStr .= "</go></anchor>";
            }else{
                $jumpStr = '<select size="1" class="page_select" onchange="location.href=this.value">';
                for($i=1; $i<=$this->pagenum; $i++){
                    if($this->curpage != $i){
                        $tmp_str = str_replace('%p%', $i, $this->pagestyle['select']);
                        $jumpStr .= '<option value="'.$this->geturl($i).'">'.$tmp_str.'</option>';
                    } else{
                        $tmp_str = str_replace('%p%', $i, $this->pagestyle['select']);
                        $jumpStr .= '<option selected value="'.$this->geturl($i).'">'.$tmp_str.'</option>';
                    }
                }
                $jumpStr .= '</select>';
            }
        }elseif($mode == 'input'){    
            if($this->pagestyle['pagetype']=='wap'){
                $jumpStr = "<input type=\"text\" name=\"page\" format=\"4n\" size=\"2\" value=\"".$this->curpage."\"/><anchor title=\"".$this->pagestyle['submit']."\">".$this->pagestyle['submit']."<go method=\"get\" href=\"$jump_url\"><postfield name=\"page\" value=\"$(page)\"/>";
                if(!empty($gets)){
                    unset($gets[$this->pagetag]);
                    foreach($gets as $key=>$val){
                        $jumpStr .= "<postfield name=\"$key\" value=\"$val\"/>";
                    }
                }
                $jumpStr .= "</go></anchor>";
 
            }else{    
                $jumpStr = "<input name=\"page\" class=\"page_input\" type=\"text\" size=\"2\" value=\"".$this->curpage."\" /><input type=\"submit\" value=\"".$this->pagestyle['submit']."\" class=\"page_submit\" />";
            }
        }        
        return $jumpStr;
    }
 
    /**
     * 生成数字分页方法
     * @access public
     * @param string $page 分页
     * return string
     */
    private function makenumberpage($page) {
        if ($page == $this->curpage)
            return " <dd><u class='fenye_on'>" . $page . "</u></dd> ";
        else
            return " <dd><a href=\"" . $this->geturl($page) . "\" hidefocus='true'>" . $page . "</a></dd> ";
    }
 
 
    /**
     * 获取静态URL变量
     * 需要换换的话在这里改写 preg_replace模块即可
     */
    private function getstaticurl($page, $rewriteRules=null){
        $rewriteRules = !empty($rewriteRules) ? $rewriteRules : $this->rewrite_rules;
 
        $uris = explode('/',$_SERVER['REQUEST_URI']);
        $script_file = array_pop($uris);
 
        if(preg_match($rewriteRules[0], $script_file, $var)){
            $script_file = preg_replace($rewriteRules[0], "'\\1'.curpage.'\\3'", $script_file);
            $script_file = str_replace('curpage', $page, $script_file);
        }
 
        else if(preg_match($rewriteRules[1], $script_file, $var)){
            $script_file = preg_replace($rewriteRules[1], "'\\1'.page_index.'\\2'", $script_file);
            $script_file = str_replace('curpage','_'.$page, $script_file);
        }
 
        $url .= '/';
        if(!empty($uris)){
            foreach($uris as $key=>$val){
                if(!empty($val)){
                    $url .= $val.'/';
                }
            }
        }

        return $url.$script_file;
    }
 
    /**
     * 获得当前URL的方法
     * @access public
     * @param string page 页数
     * @param string model 1(地址中带page=**参数) 0(地址中不带page=**参数)
     * return string
     */
    public function geturl($page=null ,$mode = 1) {
        $page = !isset($page) ? $this->curpage : $page;
        if((!empty($_SERVER['REQUEST_URI']) || !empty($_SERVER['REDIRECT_STATUS'])) 
            && !empty($this->rewrite_rules) 
            && strpos($_SERVER['REQUEST_URI'], '.html') !== true){
                return $this->getstaticurl($page);
        } else {
            $url = !empty($_SERVER['REDIRECT_URL']) ? $_SERVER['REDIRECT_URL'] : $_SERVER['PHP_SELF'];    
        }

        $gets = $_GET;    
        if($mode){
            $gets[$this->pagetag] = (isset($page)&&intval($page)>0)?intval($page):1;
        }else{
            unset($gets[$this->pagetag]);
        }

		$gets = $_GET;
		if($mode){
			$gets[$this->pagetag] = (isset($page)&&intval($page)>0)?intval($page):1;
		}else{
			unset($gets[$this->pagetag]);
		}
		$server=explode("/",$_SERVER['SERVER_SOFTWARE']);
		if($server[0]=="Apache"){
			$r=explode(".html",$_SERVER['REQUEST_URI']);
		}elseif($server[0]=="Microsoft-IIS"){
			$r=explode(".html",$_SERVER['HTTP_X_REWRITE_URL']);
		}
		//$uls=(URL_TYPE==0)?'1':'0';
        if(count($r)>1){
			if(!$_GET['page']){
				$url=$r[0]."_".$page.".html";
			}else{
				$p=explode("_",$r[0]);
				unset($p[(count($p)-1)]);
				foreach($p as $v){
					$l.=$v.'_';
				}
				$url=$l.$page.".html";

			}
		}else{
			if(!empty($gets)){
					$url .= '?';
					$comma = '';
					foreach($gets as $key=>$get){
						$url .= $comma.$key.'='.$get;
						$comma = '&amp;';
					}
			}
			
		}
	
        return $url;
    }
}


/**
 * 数组分页
 * @program $arr array
 * @program $page_per int
 * @return string
 */
function arraypage($arr,$page_per){
	$page = new page(count($arr),$page_per);
	$arr['list'] = $page->handlearray($arr);
	$arr['fy'] = $page->getpage();
    $arr['total']   =   $page ->gettotal();
	return $arr;
}

/**
 * 数组分页
 * @program $arr array
 * @program $page_per int
 * @return string
 */
function sqlpage($table,$page_per=20,$find='*',$where="",$order="id desc"){
	$item_count = D($table)->field("id")->where($where)->getcount(); //总记录数
	$sql=D($table)->field($find)->where($where)->sort($order)->buildselectsql();
	$page = new page($item_count,$page_per); 
	$sql = $page->handlesql($sql);

	$arr['list']	=	D($table)->excsql($sql);
	$arr['fy']		=	$page->getpage();
	$arr['total']	=	$page ->gettotal();
	return $arr;
}

?>