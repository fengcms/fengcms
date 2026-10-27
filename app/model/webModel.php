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

class webModel extends model{

	private $d_name		 = "base";
	public $information = array();
    private static ?array $requestInformation = null;

	public function __construct(){
		parent::__construct($this->d_name);
        if (self::$requestInformation === null) {
            $information = $this->getone();
            // Imported or older database rows may predate write-time sanitization.
            // Clean only the rendered copy; do not rewrite stored content here.
            $information['copyright'] = cms_html(cms_clean_copyright($information['copyright'] ?? ''));
            self::$requestInformation = $information;
        }
        $this->information = self::$requestInformation;
	}
}







?>
