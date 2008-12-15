<?php
if (!defined('IN_PHPBB'))
{
	exit();
}

class phpbb_core_system implements phpbb_plugin
{
	public $phpbb_plugin = 'system';

	public function register_plugin(phpbb_plugin_support $object)
	{
		$object->register_method('get_path', $this);
		$object->register_append('get_page', $this);
	}

	/**
	* Get PATH environment variable
	*/
	public function get_path()
	{
		// Return PATH
		return (!empty($_SERVER['PATH'])) ? $_SERVER['PATH'] : getenv('PATH');
	}

	public function get_page(phpbb_system $object, $page_array)
	{
		$page_array['application_path'] = '/var/www/customer/';
		return $page_array;
	}
}

$plugin = new phpbb_core_system();

?>