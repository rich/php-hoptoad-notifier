<?php
class Services_Hoptoad_CodeIgniter extends Services_Hoptoad {
	var $ci;

	function __construct() {
		$this->ci =& get_instance();
	}

	function action() {
		return $this->ci->router->method;
	}

	function component() {
		return $this->ci->router->class;
	}

	function project_path() {
		return APPPATH;
	}
}
