<?php
session_start();
define('ROOT_PATH',dirname(__FILE__));
require ROOT_PATH.'/system/core/validatecode.php';  //�Ȱ������������ʵ��·������ʵ����������޸ġ�
$_vc = new validatecode();		//ʵ����һ������
$_vc->doimg();		
$_SESSION['authnum'] = $_vc->getcode();//��֤�뱣�浽SESSION��
?>