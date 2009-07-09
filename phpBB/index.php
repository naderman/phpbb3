<?php
/**
*
* @package phpBB
* @version $Id$
* @copyright (c) 2009 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

define('IN_PHPBB', true);

if (!defined('PHPBB_ROOT_PATH'))
{
	define('PHPBB_ROOT_PATH', './');
}

if (!defined('PHP_EXT'))
{
	define('PHP_EXT', substr(strrchr(__FILE__, '.'), 1));
}

include(PHPBB_ROOT_PATH . 'core/bootstrap' . PHP_EXT);

