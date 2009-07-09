<?php
/**
*
* @package testing
* @version $Id$
* @copyright (c) 2009 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

define('IN_PHPBB', true);
define('PHP_EXT', 'php');

include(PHPBB_ROOT_PATH . 'includes/core/bootstrap.' . PHP_EXT);

// require at least PHPUnit 3.3.0
require_once 'PHPUnit/Runner/Version.php';
if (version_compare(PHPUnit_Runner_Version::id(), '3.3.0', '<'))
{
	trigger_error('PHPUnit >= 3.3.0 required');
}

require_once 'PHPUnit/Framework.php';
require_once 'test_framework/phpbb_test_case.php';

