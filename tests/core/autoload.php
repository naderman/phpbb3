<?php
/**
*
* @package testing
* @version $Id$
* @copyright (c) 2009 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

require_once 'test_framework/framework.php';

class phpbb_core_autoload_test extends phpbb_test_case
{
	private $autoload_functions;

	public function setUp()
	{
		$this->autoload_functions = spl_autoload_functions();

		if ($this->autoload_functions !== false)
		{
			foreach ($this->autoload_functions as $function)
			{
				spl_autoload_unregister($function);
			}
		}
	}

	public function test_core_file()
	{
		$this->assertFalse(class_exists('phpbb_request'), 'phpbb_request is already loaded.');
		phpbb_autoload::load('phpbb_request');
		$this->assertTrue(class_exists('phpbb_request'), 'phpbb_request was not loaded.');
	}

	public function tearDown()
	{
		if ($this->autoload_functions !== false)
		{
			foreach ($this->autoload_functions as $function)
			{
				spl_autoload_register($function);
			}
		}
	}
}