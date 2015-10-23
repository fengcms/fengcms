<?php
/*******************************************************************
 * @authors FengCms 
 * @web     http://www.fengcms.com
 * @email   web@fengcms.com
 * @date    2013-10-30 16:00:12
 * @version FengCms Beta 1.0
 * @copy    Copyright © 2013-2018 Powered by DiFang Web Studio  
 *******************************************************************/
//	异常处理

defined('TPL_INCLUDE') or die( 'Restricted access'); 


class exceptions extends Exception{

    protected $eval = '';
    protected $code = 0;

    /**
     * 重定义构造器
     *
     * @param string $message
     * @param int $code
     * @return void
     */
	public function __construct($message = null, $code = 0){
        if (is_numeric($data)) {
            $this->code = $data;
        } else {
            $this->eval = $data;
        }
        parent::__construct($message, $this->code);
    }

    /**
     * 异常堆栈
     *
     * @return string
     */
	 final public function getStackTrace() {
        $string = $file = null;
        $traces = $this->getTrace(); unset($traces[0]);
        array_splice($traces, count($traces)-4, -1);
        foreach ($traces as $i => $trace) {
            $file = isset($trace['file']) ? replpath($trace['file']) : $file;
            $line = isset($trace['line']) ? $trace['line'] : null;
            $class = isset($trace['class']) ? $trace['class'] : null;
            $type = isset($trace['type']) ? $trace['type'] : null;
            $args = isset($trace['args']) ? $trace['args'] : null;
            $function = isset($trace['function']) ? $trace['function'] : null;
            $string .= "\t#" . $i . ' [' . date("y-m-d H:i:s") . '] ' . $file . ($line ? '(' . $line . ') ' : ' ');
            $string .= $class . $type . $function . '(';
            if (is_array($args)) {
                $arrs = array();
                foreach ($args as $v) {
                    if (is_object($v)) {
                        $arrs[] = implode(' ', get_object_vars($v));
                    } else {
                        $error_level = error_reporting(0);
                        $vars = print_r($v, true);
                        error_reporting($error_level);
                        while (strpos($vars, chr(32) . chr(32)) !== false) {
                            $vars = str_replace(chr(32) . chr(32), chr(32), $vars);
                        }
                        $arrs[] = $vars;
                    }
                }
                $string .= str_replace("\n", '', implode(', ', $arrs));
            }
            $string .= ")\r\n";
        }
        return $string;
    }

    /**
     * get eval
     *
     * @return string
     */
    final public function getEval() {
        return $this->eval;
    }

    /**
     * 自定义公用错误
     *
     * @return string
     */
	public static function error($message){
		return sprintf('%s: [%d]: %s', __CLASS__, 200, $message);
	}

    /**
     * 自定义输出
     *
     * @return string
     */
    public function __toString() {
        return sprintf('%s: [%d]: %s', __CLASS__, $this->code, $this->message);
    }
}


	/**
	 * 异常信息载入
	 *
     * @param object &$e
     * @return void
	 */
	function systemerror(&$e){
		$path=ERROR_INFO_PATH.$path;

		if(DEBUGS){
			$trace = $e->getTrace(); $error = $trace[0];
			$Message=$e->getMessage();$File=replpath($error['file']);$Line=$error['line'];
			$log = sprintf("[Message]:\r\n\t%s\r\n", $e->getMessage());
			$log.= sprintf("[File]:\r\n\t%s (%d)\r\n", $error['file'], $error['line']);
			$log.= sprintf("[Trace]:\r\n%s\r\n", $e->getStackTrace());
			error_log($log, 3, $path.'logs/'.date('Y-m-d').'_error.log');
			include_once $path. '/error.php';
		}else{
			include_once $path. '/500.html';
		}
	}

	/**
	 * 异常信息抛出
	 *
     * @param string $error
     * @param num $errno
     * @return void
	 */
	function throwexce($error, $errno = 500){
		if (error_reporting() == 0) return ;
		throw new exceptions($error, $errno);
	}

?>