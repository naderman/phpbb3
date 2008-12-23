<?php
/**
*
* @package plugins
* @version $Id$
* @copyright (c) 2008 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

if (!defined('IN_PHPBB'))
{
	exit();
}

class phpbb_myapp_info implements phpbb_plugin_info
{
	public $name = 'My Application';
	public $description = 'Description';
	public $author = 'Meik Sievertsen';
	public $version = '1.0.0';

	function setup_plugin(phpbb_plugins $object)
	{
		// Define common files included by default. They are only included once... please do not add any procedural code to them.
		$object->register_includes('functions', 'core_system', 'core_url');

		// Define the plugins/classes registered within the setup process
		$object->register_plugins('phpbb_myapp_system', 'phpbb_myapp_security');

		// Add one simple hook...
		// First parameter is the function to hook into, second the own function called then, third a constant defining the hook
		// Third parameter can be:
		//		FUNCTION_OVERRIDE: Hook is called instead of the function.
		//		FUNCTION_PREFIX: Hook is called, then the function is called. *Parameters are passed by reference*
		//		FUNCTION_SUFFIX: Function is called, then the hook is called. *The result is passed to the hook* *Parameters are passed*
		$object->register_function('page_header', 'my_page_header_prefix', phpbb::FUNCTION_INJECT, 'default');
		$object->register_function('page_header', 'my_page_header_login', phpbb::FUNCTION_INJECT, 'login_logout');
	}

	function init()
	{
		// Extend acm
		phpbb::$acm->cache_types['myext'] = NULL;
		phpbb::$acm->register('myext');

		phpbb::$acm->put_myext('sometest', array('here' => 1));
	}
}

?>