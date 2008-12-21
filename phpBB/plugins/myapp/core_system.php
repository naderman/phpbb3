<?php

class phpbb_core_system implements phpbb_plugin
{
	public $phpbb_plugin = 'system';

	public function register_plugin(phpbb_plugin_support $object)
	{
		// First parameter is the method name to inject or the new name. Second parameter is the method name within this class. The third is always $this. Fourth optional parameter defined the mode
		//		METHOD_ADD: default. Method is an individual method. *Object is passed*
		//		METHOD_OVERRIDE: Hook is called instead of the method. *Object is passed*
		//		METHOD_PREFIX: Hook is called, then the method is called. Parameter are passed by reference. *Object is passed* *Original Parameters are passed*
		//		METHOD_SUFFIX: Method is called, then the hook is called. The result is passed to the hook. *Object is passed* *Result is passed* *Parameters are passed*
		$object->register_method(false, 'get_path', $this);
		$object->register_method('get_page', 'get_page', $this, phpbb::PLUGIN_INJECT, 'return');
	}

	/**
	* Get PATH environment variable
	*/
	public function get_path(phpbb_system $object)
	{
		// Return PATH
		return (!empty($_SERVER['PATH'])) ? $_SERVER['PATH'] : getenv('PATH');
	}

	public function get_page(phpbb_system $object, $result)
	{
		$result['application_path'] = '/var/www/customer/';
		return $result;
	}
}

class phpbb_core_security implements phpbb_plugin
{
	var $phpbb_plugin = 'security';
	var $x = 1;

	function register_plugin(phpbb_plugin_support $object)
	{
		$object->register_method(false, 'test', $this);
		$object->register_method('unique_id', 'unique_id', $this, phpbb::PLUGIN_OVERRIDE);

		$object->register_attribute('x', $this);
		$object->register_attribute('hash_type', $this);

		$object->register_method('gen_rand_string', 'gen_rand_string', $this, phpbb::PLUGIN_INJECT);
	}

	public function test(phpbb_security $object)
	{
		echo 'test';
		print_r($object);
	}

	public function gen_rand_string(phpbb_security $object, $num_chars)
	{
		$num_chars = 7;
	}

	public function unique_id(phpbb_security $object)
	{
		return md5('tst');
	}
}

?>