<?php
/**
*
* @package phpBB3
* @version $Id$
* @copyright (c) 2005 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
* Minimum Requirement: PHP 5.2.0+
*
* Within this file the framework with all components as well as all phpBB-specific things will be loaded
*/

/**
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

// Init Framework
require_once PHPBB_ROOT_PATH . 'includes/core/bootstrap.' . PHP_EXT;

// Run through remaining Framework states
if (PHPBB_FRAMEWORK_FULL)
{
	if (!defined('PHPBB_INSTALLED'))
	{
		// Redirect the user to the installer
		// We have to generate a full HTTP/1.1 header here since we can't guarantee to have any of the information
		// available as used by the redirect function
		$server_name = (!empty($_SERVER['HTTP_HOST'])) ? strtolower($_SERVER['HTTP_HOST']) : ((!empty($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : getenv('SERVER_NAME'));
		$server_port = (!empty($_SERVER['SERVER_PORT'])) ? (int) $_SERVER['SERVER_PORT'] : (int) getenv('SERVER_PORT');
		$secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 1 : 0;

		$script_name = (!empty($_SERVER['PHP_SELF'])) ? $_SERVER['PHP_SELF'] : getenv('PHP_SELF');
		if (!$script_name)
		{
			$script_name = (!empty($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : getenv('REQUEST_URI');
		}

		// Replace any number of consecutive backslashes and/or slashes with a single slash
		// (could happen on some proxy setups and/or Windows servers)
		$script_path = trim(dirname($script_name)) . '/install/index.' . PHP_EXT;
		$script_path = preg_replace('#[\\\\/]{2,}#', '/', $script_path);

		$url = (($secure) ? 'https://' : 'http://') . $server_name;

		if ($server_port && (($secure && $server_port <> 443) || (!$secure && $server_port <> 80)))
		{
			// HTTP HOST can carry a port number...
			if (strpos($server_name, ':') === false)
			{
				$url .= ':' . $server_port;
			}
		}

		$url .= $script_path;
		header('Location: ' . $url);
		exit;
	}
}

// Set PHP error handler to ours
set_error_handler(defined('PHPBB_MSG_HANDLER') ? PHPBB_MSG_HANDLER : 'msg_handler');

// enforce the use of the request class
request::disable_super_globals();

// Register database - DBMS is from the config
if (!empty($dbms))
{
	// Register DB object. Custom class is dbal_$dbms, custom files are includes/db/dbal.php and includes/db/$dbms.php
	phpbb::register('db', 'phpbb_dbal_' . basename($dbms), array('db/dbal', 'db/' . $dbms));

	// Connect to DB
	phpbb::$db->sql_connect($dbhost, $dbuser, $dbpasswd, $dbname, $dbport, false, defined('PHPBB_DB_NEW_LINK') ? PHPBB_DB_NEW_LINK : false);
}

// We do not need the db password any longer, unset for safety purposes
if (!empty($dbpasswd))
{
	unset($dbpasswd);
}

// Register Cache Manager (SPL autoload test) - we define no classes
phpbb::register('acm');

// Grab global variables
phpbb_cache::obtain_config();

/*
// For example, data handling with xcache and sql handling within the cache-directory
phpbb::$acm->register('sql');
//phpbb::$acm->register('data', false, 'xcache');

// Grab global variables
phpbb_cache::obtain_config();

print_r(phpbb::$config);

// Some obscure playing around... ;)

// Register DBAL class
phpbb::register('db', 'phpbb_dbal_' . basename($dbms), array('db/dbal', 'db/' . $dbms));

// DBAL class usage example 1
echo ";".phpbb::$db->sql_layer . ";";

// DBAL class usage example 2
$db = phpbb::get_instance('db');
echo ";".$db->sql_layer.";";

// Constructor parameter call example
// Register new class (here, a second db class) and call the constructor with two parameter (string -> 'test' and string -> 'test2')
phpbb::register('target_db', 'phpbb_dbal_' . basename($dbms), false, 'test', 'test2');

// Second DB class not having a property, therefore in instances...
echo ";".phpbb::$instances['target_db']->sql_layer.";";

// The same call as above - but a little bit easier through array access (we could also do a shortcut, for example i instead of call)
echo ";".phpbb::get('target_db')->sql_layer.";";

exit;
*/

// Register Template
phpbb::register('template');

// Register permission class
phpbb::register('acl');

// Register user object
phpbb::register('user', false, false, phpbb::$config['auth_method']);

// Within the user-facing files we call: phpbb::$user->start();

// Register API
phpbb::register('api');

// Register Plugins
phpbb::$plugins->init(PHPBB_ROOT_PATH . 'plugins/');
phpbb::$plugins->setup();

?>