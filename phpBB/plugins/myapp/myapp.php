<?php

if (!defined('IN_PHPBB'))
{
	exit();
}

class phpbb_myapp_info implements phpbb_plugin_info
{
	function setup_plugin(phpbb_plugin_setup $object)
	{
		// Pass on some general information
		$object->add_plugin(array(
			'application'		=> 'myapp',
			'author'			=> 'Meik Sievertsen',
			'version'			=> '1.0.0',
		));

		// Define common includes which are needed by default
		$object->register_includes('functions', 'core_system', 'core_url');

		// Define the files phpBB should call within the setup process
		$object->register_plugins('phpbb_core_system', 'phpbb_core_url', 'phpbb_core_security');

		// Add one simple hook...
		// First parameter is the function to hook into, second the own function called then, third a constant defining the hook
		// Third parameter can be:
		//		FUNCTION_OVERRIDE: Hook is called instead of the function.
		//		FUNCTION_PREFIX: Hook is called, then the function is called. *Parameters are passed by reference*
		//		FUNCTION_SUFFIX: Function is called, then the hook is called. *The result is passed to the hook* *Parameters are passed*
		$object->register_function('exit_handler', 'my_exit_handler', phpbb::FUNCTION_SUFFIX);
	}

	function install()
	{
	}

	function uninstall()
	{
	}
}

?>