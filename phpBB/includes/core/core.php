<?php
/**
*
* @package core
* @version $Id$
* @copyright (c) 2008 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

if (!defined('IN_PHPBB'))
{
	exit();
}

/**
* static phpBB class
* @package core
* @author acydburn
*/
class phpbb
{
	/**#@+
	* Our own static variables
	*/
	public static $template = NULL;
	public static $user = NULL;
	public static $db = NULL;
	public static $acm = NULL;
	public static $acl = NULL;

	public static $url = NULL;
	public static $security = NULL;

	public static $api = NULL;

	public static $config = array();
	/**#@-*/

	/**
	* A static array holding custom objects
	*/
	public static $instances = NULL;

	/**
	* We do not want this object instantiable
	*/
	private function ___construct() { }

	/**
	* A failover error handler to handle errors before we assigned our own error handler
	*/
	public static function error_handler($errno, $errstr, $errfile, $errline)
	{
		throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
	}

	/**
	* Get instance of static property
	*
	* @param string $variable The name of the object to retrieve.
	* @return mixed The object registered with this name
	*/
	public static function get_instance($variable)
	{
		if (!self::registered($variable))
		{
			return self::register($variable);
		}

		// Please do not try to change it to (expr) ? (true) : (false) - it will not work. ;)
		if (property_exists('phpbb', $variable))
		{
			return self::$$variable;
		}
		else
		{
			return self::$instances[$variable];
		}
	}

	/**
	* Check if the variable is already assigned (preserve singleton)
	*
	* @param string $variable The name of the object to check.
	* @return bool True, if the object is registered, false if not.
	*/
	public static function registered($variable)
	{
		if (property_exists('phpbb', $variable))
		{
			return (self::$$variable !== NULL) ? true : false;
		}

		return (isset(self::$instances[$variable]) && self::$instances[$variable] !== NULL) ? true : false;
	}

	/**
	* Simpler method for calling assigned instances.
	* Overloading is not possible here due to the object being static. ;)
	*
	* @param string $variable The objects name which got self-registered.
	*/
	public static function get($variable)
	{
		// No error checking done here... returned right away
		return self::$instances[$variable];
	}

	/**
	* Register new class/object.
	* Any additional parameter will be forwarded to the class instantiation. Assigned classes need to be instantiated.
	*
	* @param string $variable The objects name. If a property exist, it will be assigned. Else, it will be put in the $instances array
	* @param string $class Define a custom class name for the object, if it does not abide to the rules (phpbb_{$class}).
	* @param mixed $includes Define the additional files/includes required for this class to be correctly set up. It will be included from the includes/ folder.
	*
	* @return mixed The instance of the created object.
	*/
	public static function register($variable, $class = false, $includes = false)
	{
		if (self::registered($variable))
		{
			return self::get_instance($variable);
		}

		$args = (func_num_args() > 3) ? array_slice(func_get_args(), 3) : array();
		$class = ($class === false) ? 'phpbb_' . $variable : $class;

		if ($includes !== false)
		{
			if (!is_array($includes))
			{
				$includes = array($includes);
			}

			foreach ($includes as $file)
			{
				require_once PHPBB_ROOT_PATH . 'includes/' . $file . '.' . PHP_EXT;
			}
		}

		$reflection = new ReflectionClass($class);

		if (!$reflection->isInstantiable())
		{
			throw new Exception('Assigned classes need to be instantiated.');
		}

		if (!property_exists('phpbb', $variable))
		{
			self::$instances[$variable] = (sizeof($args)) ? call_user_func_array(array($reflection, 'newInstance'), $args) : $reflection->newInstance();
		}
		else
		{
			self::$$variable = (sizeof($args)) ? call_user_func_array(array($reflection, 'newInstance'), $args) : $reflection->newInstance();
		}

		return self::get_instance($variable);
	}

	/**
	* Unset/unregister a specific object
	*
	* @param string $variable The objects name to unset.
	*/
	public static function unregister($variable)
	{
		if (!property_exists('phpbb', $variable))
		{
			unset(self::$instances[$variable]);
		}
		else
		{
			self::$$variable = NULL;
		}
	}
}

/**
* phpBB SPL Autoload Function. A phpbb_ prefix will be stripped from the class name.
*
* The files this function tries to include are:
*	includes/{$class_name}/index.php
*	includes/classes/{$class_name}.php
*	includes/{$class_name}.php
*/
function __phpbb_autoload($class_name)
{
	if (strpos($class_name, 'phpbb_') === 0)
	{
		$class_name = substr($class_name, 6);
	}

	$class_name = basename($class_name);

	$filenames = array(
		'includes/' . $class_name . '/index',
		'includes/classes/' . $class_name,
		'includes/' . $class_name,
	);

	if (strpos($class_name, '_') !== false)
	{
		$class_name = str_replace('_', '/', $class_name);

		$filenames = array_merge($filenames, array(
			'includes/' . $class_name,
			'includes/classes/' . $class_name,
		));
	}

	foreach ($filenames as $filename)
	{
		if (file_exists(PHPBB_ROOT_PATH . $filename . '.' . PHP_EXT))
		{
			require_once PHPBB_ROOT_PATH . $filename . '.' . PHP_EXT;
			return;
		}
	}
}

/*
class phpbb_exception extends Exception
{
}
*/
?>