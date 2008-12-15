<?php

if (!defined('IN_PHPBB'))
{
	exit();
}

class phpbb_core_security_my_implementation implements phpbb_plugin
{
	var $phpbb_plugin = 'security';
	var $y = 2;

	function register_plugin(phpbb_plugin_support $object)
	{
		$object->register_attribute('y', $this);
	}
}

$plugin = new phpbb_core_security_my_implementation();

?>