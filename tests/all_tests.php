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
	define('PHPUnit_MAIN_METHOD', 'phpbb_all_tests::main');
}
define('PHPBB_ROOT_PATH', '../phpBB/');

require_once 'test_framework/framework.php';
require_once 'PHPUnit/TextUI/TestRunner.php';

require_once 'core/all_tests.php';

// exclude the test directory from code coverage reports
PHPUnit_Util_Filter::addDirectoryToFilter('./');

class phpbb_all_tests
{
	public static function main()
	{
		PHPUnit_TextUI_TestRunner::run(self::suite());
	}

	public static function suite()
	{
		$suite = new PHPUnit_Framework_TestSuite('phpBB');

		$suite->addTest(phpbb_core_all_tests::suite());

		return $suite;
	}
}

if (PHPUnit_MAIN_METHOD == 'phpbb_all_tests::main')
{
	phpbb_all_tests::main();
}

?>