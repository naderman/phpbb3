<?php
/**
*
* @package acm
* @version $Id$
* @copyright (c) 2008 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* ACM call schema for developer
*
* Base Class: phpbb_acm
* Abstract: phpbb_acm_abstract
* Type-specific class: phpbb_acm_[object] extends abstract
*
* Pre-defined cache types:
*		data	=> data files
*		sql		=> sql files
*		tpl		=> template files
*		ctpl	=> custom template files
*
* Main Class used for calls is phpbb_acm
* Methods to be called for cache data handling:
*		get(): Get cached data. For global data, prefix name with #
*		put(): Put data in cache. For global data, prefix name with #
*		tidy(): Tidy complete cache and remove expired entries.
*		purge(): Purge complete cache and remove all entries.
*		unload(): Unload complete cache (called on __destruct)
*		__call(): for calling methods only defined in phpbb_acm_[object]; the cache type must be the first parameter
*			example: [acm]->call_custom_method([type], [additional parameter]);
*
* Methods for handling cache types
*		register(), supported()
*
* If you add a new cache object (shm for example), then these are the phpbb_acm_[object] methods you must (or can) define (based on the abstract):
*	Required: get_local(), put_local(), destroy_local(), load(), save(), exists(), _purge()
*	Optional: get_global(), put_global(), destroy_global(), _tidy()
*
*/


/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* Define missing ACM config variable... if not initialized yet
* @ignore
*/
if (!defined('CONFIG_ACM_TYPE'))
{
	define('CONFIG_ACM_TYPE', 'file');
}

/**
* Base, accessible ACM class. All ACM calls are directed to this class
* @package acm
*/
class phpbb_acm
{
	public $phpbb_required = array();
	public $phpbb_optional = array();

	/**
	* Currently registered core acm types.
	*/
	public $cache_types = array('data' => NULL, 'sql' => NULL, 'tpl' => NULL, 'ctpl' => NULL);

	/**
	* Constructor
	*/
	public function __construct() { }

	/**
	* In case someone wants to access a public function for the acm plugin not defined within the phpbb_acm class,
	* we use this magic method. We expect the cache type for those calls as the *first* argument
	*/
	public function __call($method, $arguments)
	{
		// Special cases for some methods. ;)
		if (strpos($method, 'get_') === 0 || strpos($method, 'put_') === 0)
		{
			$cache_type = substr($method, 4);
			$method = substr($method, 0, 3);
		}
		else
		{
			// Get cache type
			$cache_type = $arguments[0];
			array_shift($arguments);
		}

		// Check if the cache type is initialized and exist
		if (!$this->type_exists($cache_type))
		{
			return false;
		}

		// $this->cache_types[$cache_type]->$method($arguments);
		return call_user_func_array(array($this->cache_types[$cache_type], $method), $arguments);
	}

	/**
	* Get data from cache
	*
	* @param string $var_name The variable name. If prefixed with #, then the global cache will be used.
	* @param string $cache_type The cache type to use. Default is 'data'.
	*
	* @return mixed false if no data was stored within the cache, else the dataset.
	*/
	public function get($var_name, $cache_type = 'data')
	{
		return $this->__call('get', array($cache_type, $var_name));
	}

	/**
	* Store data in cache
	*
	* @param string $var_name The variable name. If prefixed with #, then the global cache will be used.
	* @param mixed $data The data to store, usually an array.
	* @param int $ttl The time the cache should be valid in seconds.
	* @param string $cache_type The cache type to use. Default is 'data'.
	*
	* @return mixed The dataset stored.
	*/
	public function put($var_name, $data, $ttl = 31536000, $cache_type = 'data')
	{
		return $this->__call('put', array($cache_type, $var_name, $data, $ttl));
	}

	/**
	* Tidy cache.
	* This removes all expired cache files
	*/
	public function tidy()
	{
		foreach ($this->cache_types as $cache_type => $object)
		{
			if ($object === NULL)
			{
				continue;
			}

			$this->cache_types[$cache_type]->tidy();
		}
	}

	/**
	* Purge cache.
	* This removes all cache files from currently registered cache objects
	*/
	public function purge()
	{
		foreach ($this->cache_types as $cache_type => $object)
		{
			if ($object === NULL)
			{
				continue;
			}

			$this->cache_types[$cache_type]->purge();
		}
	}

	/**
	* Register a custom cache type/class.
	*
	* @param string $cache_type		The cache type to register/set
	* @param string $cache_append	String to append to the cached data as identifier (if the coder has different types to distinct from)
	* @param string $cache_object	The exact name of the cache class to load.
	*								The filename must be: <code>includes/acm/acm_{$cache_object}.php</code>
	*								The class definition must be: <code>class phpbb_acm_{$cache_object} extends phpbb_acm_abstract
	*
	*/
	public function register($cache_type, $cache_append = false, $cache_object = CONFIG_ACM_TYPE)
	{
		// We need to init every cache type...
		if (!isset($this->cache_types[$cache_type]))
		{
			$this->cache_types[$cache_type] = NULL;
		}

		// Unregister if already registered
		if ($this->cache_types[$cache_type] !== NULL)
		{
			$this->cache_types[$cache_type] = NULL;
		}

		if ($this->cache_types[$cache_type] === NULL)
		{
			$class_name = 'phpbb_acm_' . $cache_object;

			if (!class_exists($class_name))
			{
				if (!file_exists(PHPBB_ROOT_PATH . 'includes/acm/acm_' . $cache_object . '.' . PHP_EXT))
				{
					return false;
				}
				require_once(PHPBB_ROOT_PATH . 'includes/acm/acm_' . $cache_object . '.' . PHP_EXT);
			}

			// Set cache prefix, for example ctpl_prosilver
			$cache_prefix = ($cache_append === false) ? $cache_type : $cache_type . '_' . $cache_append;

			$this->cache_types[$cache_type] = new $class_name($cache_prefix);

			if (!$this->supported($cache_type))
			{
				$this->cache_types[$cache_type] = NULL;
				return false;
			}
		}

		return true;
	}

	/**
	* Unload everything from cache and make sure non-stored cache items are properly saved.
	*/
	public function unload()
	{
		foreach ($this->cache_types as $cache_type => $object)
		{
			if ($object === NULL)
			{
				continue;
			}

			$this->cache_types[$cache_type]->unload();
		}
	}

	/**
	* Check if a specified cache type is supported with the ACM class
	*
	* @param string $cache_type The cache type to check.
	*
	* @return bool True if the type is supported, else false.
	*/
	public function supported($cache_type)
	{
		if (!$this->type_exists($cache_type))
		{
			return false;
		}

		return !empty($this->cache_types[$cache_type]->supported[$cache_type]) || $this->cache_types[$cache_type]->supported === true;
	}

	/**
	* Check if the cache type exists. Sometimes some types do not exist if the relevant files are not there or do not support the given cache type.
	*
	* @param string $cache_type The cache type to check.
	*
	* @return bool True if the type exist, else false.
	* @access private
	*/
	private function type_exists($cache_type)
	{
		if (!isset($this->cache_types[$cache_type]) || $this->cache_types[$cache_type] === NULL)
		{
			$this->register($cache_type);
		}

		return ($this->cache_types[$cache_type] !== NULL);
	}
}

/**
* The abstract class all ACM plugins must extend.
* @package acm
*/
abstract class phpbb_acm_abstract
{
	protected $vars = array();
	protected $var_expires = array();
	protected $is_modified = false;

	public $cache_prefix = '';

	abstract protected function get_local($var_name);
	abstract protected function put_local($var_name, $data, $ttl = 31536000);
	abstract protected function destroy_local($var_name, $additional_data = false);
	abstract public function load();
	abstract public function save();
	abstract public function exists($var_name);

	abstract protected function _purge();

	public function get($var_name)
	{
		// A global variable, use the internal arrays
		if ($var_name[0] === '#')
		{
			$var_name = substr($var_name, 1);
			return $this->get_global($var_name);
		}
		else
		{
			return $this->get_local($var_name);
		}
	}

	public function put($var_name, $data, $ttl = 31536000)
	{
		if ($var_name[0] === '#')
		{
			$var_name = substr($var_name, 1);
			return $this->put_global($var_name, $data, $ttl);
		}
		else
		{
			return $this->put_local($var_name, $data, $ttl);
		}
	}

	public function destroy($var_name, $additional_data = false)
	{
		if ($var_name[0] === '#')
		{
			$var_name = substr($var_name, 1);
			$this->destroy_global($var_name, $additional_data);
		}
		else
		{
			$this->destroy_local($var_name, $additional_data);
		}
	}

	public function unload()
	{
		$this->save();
		unset($this->vars);
		unset($this->var_expires);

		$this->vars = array();
		$this->var_expires = array();
	}

	protected function get_global($var_name)
	{
		// Check if we have all variables
		if (!sizeof($this->vars))
		{
			$this->load();
		}

		if (!isset($this->var_expires[$var_name]))
		{
			return false;
		}

		// If expired... we remove this entry now...
		if (time() > $this->var_expires[$var_name])
		{
			$this->destroy_global($var_name);
			return false;
		}

		if (isset($this->vars[$var_name]))
		{
			return $this->vars[$var_name];
		}

		return false;
	}

	protected function put_global($var_name, $data, $ttl = 31536000)
	{
		$this->vars[$var_name] = $data;
		$this->var_expires[$var_name] = time() + $ttl;
		$this->is_modified = true;

		return $data;
	}

	protected function destroy_global($var_name, $additional_data = false)
	{
		$this->is_modified = true;

		unset($this->vars[$var_name]);
		unset($this->var_expires[$var_name]);

		// We save here to let the following cache hits succeed
		$this->save();
	}

	/**
	* Tidy cache
	*/
	public function tidy()
	{
		// If cache has no auto-gc, we call the _tidy function here
		if (method_exists($this, '_tidy'))
		{
			$this->_tidy();
		}

		// Now tidy global settings
		if (!sizeof($this->vars))
		{
			$this->load();
		}

		foreach ($this->var_expires as $var_name => $expires)
		{
			if (time() > $expires)
			{
				// We only unset, then save later
				unset($this->vars[$var_name]);
				unset($this->var_expires[$var_name]);
			}
		}

		$this->is_modified = true;
		$this->save();

		set_config('cache_last_gc', time(), true);
	}

	/**
	* Purge Cache
	*/
	public function purge()
	{
		$this->_purge();

		// Now purge global settings
		unset($this->vars);
		unset($this->var_expires);

		$this->vars = array();
		$this->var_expires = array();

		$this->is_modified = true;
		$this->save();
	}
}

?>