<?php
require_once __DIR__.'/system/common/security.php';
cms_session_start();
define('ROOT_PATH',dirname(__FILE__));
require ROOT_PATH.'/system/core/validatecode.php';  //�Ȱ������������ʵ��·������ʵ����������޸ġ�
$_vc = new validatecode();		//ʵ����һ������
$_vc->doimg();		
$_SESSION['authnum'] = $_vc->getcode();//��֤�뱣�浽SESSION��
$_SESSION['authnum_at'] = time();
?>
