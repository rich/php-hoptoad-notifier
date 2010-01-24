<?php
class Services_Hoptoad_CodeIgniter extends Services_Hoptoad {
	var $ci;

	function ci() {
		if (isset($this->ci)) return $this->ci;
		$this->ci =& get_instance();
		return $this->ci;
	}

	function action() {
		return $this->ci()->router->method;
	}

	function component() {
		return $this->ci()->router->class;
	}

	function project_path() {
		return APPPATH;
	}
}
