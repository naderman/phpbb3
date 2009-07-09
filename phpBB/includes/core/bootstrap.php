<?php
/**
*
* @package core
* @version $Id$
* @copyright (c) 2009 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

$starttime = explode(' ', microtime());
$starttime = $starttime[1] + $starttime[0];

// Report all errors
error_reporting(E_ALL | E_STRICT); // ^ E_NOTICE
date_default_timezone_set('UTC');

// PHP6 got rid of these options
if (version_compare(PHP_VERSION, '6.0.0-dev', '<'))
{
	@set_magic_quotes_runtime(0);

	// We do not allow register globals set
	if (@ini_get('register_globals') == '1' || strtolower(@ini_get('register_globals')) == 'on' || !function_exists('ini_get'))
	{
		die('phpBB will not work with register globals turned on. Please turn register globals off.');
	}
}

require PHPBB_ROOT_PATH . 'includes/core/autoload.' . PHP_EXT;
