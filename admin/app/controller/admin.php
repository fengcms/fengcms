<?php
defined('TPL_INCLUDE') or die( 'Restricted access'); 
	if($_SESSION['manage']==""){
		echo '<meta http-equiv="refresh" content="0;url=login.php">';
		exit;
	}
?>