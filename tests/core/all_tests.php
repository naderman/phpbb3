<?php
/**
*
* @package testing
* @version $Id$
* @copyright (c) 2009 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

if (!defined('PHPUnit_MAIN_METHOD'))
{
	define('PHPUnit_MAIN_METHOD', 'phpbb_core_all_tests::main');
	define('PHPBB_ROOT_PATH', '../../phpBB/');
}

require_once 'test_framework/framework.php';
require_once 'PHPUnit/TextUI/TestRunner.php';

require_once 'autoload.php';
require_once 'request.php';

class phpbb_core_all_tests
{
	public static function main()
	{
		PHPUnit_TextUI_TestRunner::run(self::suite());
	}

	public static function suite()
	{
		$suite = new PHPUnit_Framework_TestSuite('phpBB Core');

		$suite->addTestSuite('phpbb_core_autoload_test');
		$suite->addTestSuite('phpbb_core_request_test');

		return $suite;
	}
}

if (PHPUnit_MAIN_METHOD == 'phpbb_core_all_tests::main')
{
	phpbb_core_all_tests::main();
}
