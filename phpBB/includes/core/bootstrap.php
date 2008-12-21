<?php
/**
*
* @package core
* @version $Id$
* @copyright (c) 2008 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
* Within this file only the framework with all components but no phpBB-specific things will be loaded
*/

/**
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

$starttime = explode(' ', microtime());
$starttime = $starttime[1] + $starttime[0];

// Report all errors, except notices
error_reporting(E_ALL | E_STRICT); //  ^ E_NOTICE
date_default_timezone_set('UTC');

// Define missing Framework states...
if (!defined('PHPBB_FRAMEWORK_FULL') && !defined('PHPBB_FRAMEWORK_SAFE'))
{
	define('PHPBB_FRAMEWORK_FULL', true);
	define('PHPBB_FRAMEWORK_SAFE', false);
}
else
{
	(!defined('PHPBB_FRAMEWORK_FULL')) ? define('PHPBB_FRAMEWORK_FULL', false) : define('PHPBB_FRAMEWORK_SAFE', false);
}

// Initialize some standard variables, constants and classes we need
include PHPBB_ROOT_PATH . 'includes/core/core.' . PHP_EXT;
include PHPBB_ROOT_PATH . 'plugins/bootstrap.' . PHP_EXT;

// If we are on PHP >= 6.0.0 we do not need some code
if (version_compare(PHP_VERSION, '6.0.0-dev', '>='))
{
	/**
	* @ignore
	*/
	define('STRIP', false);
}
else
{
	@set_magic_quotes_runtime(0);

	// Be paranoid with passed vars
	if (@ini_get('register_globals') == '1' || strtolower(@ini_get('register_globals')) == 'on' || !function_exists('ini_get'))
	{
		die('phpBB will not work with register globals turned on. Please turn register globals off.');
	}

	define('STRIP', (@get_magic_quotes_gpc()) ? true : false);
}

// In Full Mode, we check for the cron script and include the config file
if (PHPBB_FRAMEWORK_FULL)
{
	if (defined('IN_CRON'))
	{
		@define('PHPBB_ROOT_PATH', dirname(__FILE__) . DIRECTORY_SEPARATOR);
	}

	if (!file_exists(PHPBB_ROOT_PATH . 'config.' . PHP_EXT))
	{
		die('<p>The config.' . PHP_EXT . ' file could not be found.</p><p><a href="' . PHPBB_ROOT_PATH . 'install/index.' . PHP_EXT . '">Click here to install phpBB</a></p>');
	}

	require(PHPBB_ROOT_PATH . 'config.' . PHP_EXT);
}

// In safemode, we need to have some standard values
if (PHPBB_FRAMEWORK_SAFE)
{
	if (!file_exists(PHPBB_ROOT_PATH . 'config.' . PHP_EXT))
	{
		define('CONFIG_ADM_FOLDER', 'adm');
		define('CONFIG_ACM_TYPE', 'file');
	}
	else
	{
		require PHPBB_ROOT_PATH . 'config.' . PHP_EXT;
	}

	if (!defined('PHPBB_INSTALLED'))
	{
		$dbms = $dbhost = $dbport = $dbname = $dbuser = $dbpasswd = '';
		$table_prefix = 'phpbb_';
	}
}

if (defined('DEBUG_EXTRA'))
{
	$base_memory_usage = 0;
	if (function_exists('memory_get_usage'))
	{
		$base_memory_usage = memory_get_usage();
	}
}

// Load Extensions
if (!empty($load_extensions))
{
	$load_extensions = explode(',', $load_extensions);

	foreach ($load_extensions as $extension)
	{
		@dl(trim($extension));
	}
}

// Register autoload function
spl_autoload_register('__phpbb_autoload');

// Set error handler before a real one is there
set_error_handler(array('phpbb', 'error_handler'));

// Add constants
include_once PHPBB_ROOT_PATH . 'includes/constants.' . PHP_EXT;

// Add global functions
require_once PHPBB_ROOT_PATH . 'includes/functions.' . PHP_EXT;
require_once PHPBB_ROOT_PATH . 'includes/functions_content.' . PHP_EXT;
require_once PHPBB_ROOT_PATH . 'includes/utf/utf_tools.' . PHP_EXT;

// Add pre-defined system core files
require_once PHPBB_ROOT_PATH . 'includes/core/request.' . PHP_EXT;

phpbb::register('security', false, 'core/security');
phpbb::register('url', false, 'core/url');
phpbb::register('system', false, 'core/system');

phpbb::register('plugins');

?>