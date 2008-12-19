<?php

class phpbb_core_system implements phpbb_plugin
{
	public $phpbb_plugin = 'system';

	public function register_plugin(phpbb_plugin_support $object)
	{
		// First parameter is the method name to inject or the new name. Second parameter is the method name within this class. The third is always $this. Fourth optional parameter defined the mode
		//		METHOD_ADD: default. Method is an individual method. *Object is passed*
		//		METHOD_OVERRIDE: Hook is called instead of the method. *Object is passed*
		//		METHOD_PREFIX: Hook is called, then the method is called. Parameter are passed by reference. *Object is passed* *Original Parameters are passed by reference*
		//		METHOD_SUFFIX: Method is called, then the hook is called. The result is passed to the hook. *Object is passed* *Result is passed* *Parameters are passed*
		$object->register_method(false, 'get_path', $this);
		$object->register_method('get_page', 'get_page_prefix', $this, phpbb::METHOD_PREFIX);
		$object->register_method('get_page', 'get_page', $this, phpbb::METHOD_SUFFIX);
	}

	/**
	* Get PATH environment variable
	*/
	public function get_path(phpbb_system $object)
	{
		// Return PATH
		return (!empty($_SERVER['PATH'])) ? $_SERVER['PATH'] : getenv('PATH');
	}

	public function get_page_prefix(phpbb_system $object)
	{
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
		$object->register_method('unique_id', 'unique_id', $this, phpbb::METHOD_OVERRIDE);

		$object->register_attribute('x', $this);
		$object->register_attribute('hash_type', $this);

		$object->register_method('gen_rand_string', 'gen_rand_string', $this, phpbb::METHOD_SUFFIX);
	}

	public function test(phpbb_security $object)
	{
		echo 'test';
		print_r($object);
	}

	public function gen_rand_string(phpbb_security $object, $result, $extra = 'c')
	{
		$result = $result . ';test2;';
		return $result;
	}

	public function unique_id(phpbb_security $object)
	{
		return md5('tst');
	}
}

//$plugin = new phpbb_core_security();

// Register a new plugin the mod author created
//phpbb::register('mymod', false, 'core/plugins/core_mymod');

?>