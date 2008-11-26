<?php
/**
*
* @package acm
* @version $Id$
* @copyright (c) 2005 phpBB Group
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

class phpbb_acm_file extends phpbb_acm_abstract
{
	public $cache_dir = '';
	public $supported = array('data' => true, 'sql' => true);

	/**
	* Set cache path
	*/
	function __construct($cache_prefix)
	{
		$this->cache_dir = PHPBB_ROOT_PATH . 'cache/';
		$this->cache_prefix = $cache_prefix;
	}

	/**
	* Get saved cache object
	*/
	protected function get_local($var_name)
	{
		if (!$this->exists($var_name))
		{
			return false;
		}

		@include($this->cache_dir . $this->cache_prefix . '_' . $var_name . '.' . PHP_EXT);

		// If no data there, then the file expired...
		if ($expired)
		{
			// Destroy
			$this->destroy($var_name);
			return false;
		}

		return $data;
	}

	/**
	* Put data into cache
	*/
	protected function put_local($var_name, $data, $ttl = 31536000)
	{
		$filename = $this->cache_dir . $this->cache_prefix . '_' . $var_name . '.' . PHP_EXT;

		if ($fp = @fopen($filename, 'wb'))
		{
			@flock($fp, LOCK_EX);
			fwrite($fp, "<?php\n\$expired = (time() > " . (time() + $ttl) . ") ? true : false;\nif (\$expired) { return; }\n\$data =  " . (sizeof($data) ? "unserialize(" . var_export(serialize($data), true) . ");" : 'array();'));
			@flock($fp, LOCK_UN);
			fclose($fp);

			if (!function_exists('phpbb_chmod'))
			{
				include(PHPBB_ROOT_PATH . 'includes/functions.' . PHP_EXT);
			}

			phpbb_chmod($filename, CHMOD_WRITE);
		}

		return $data;
	}

	/**
	* Load global cache
	*/
	public function load()
	{
		// grab the global cache
		if (file_exists($this->cache_dir . $this->cache_prefix . '_global.' . PHP_EXT))
		{
			@include($this->cache_dir . $this->cache_prefix . '_global.' . PHP_EXT);
			return true;
		}

		return false;
	}

	/**
	* Save global Cache
	*/
	public function save()
	{
		if (!$this->is_modified)
		{
			return;
		}

		$filename = $this->cache_dir . $this->cache_prefix . '_global.' . PHP_EXT;

		if ($fp = @fopen($filename, 'wb'))
		{
			@flock($fp, LOCK_EX);
			fwrite($fp, "<?php\n\$this->vars = unserialize(" . var_export(serialize($this->vars), true) . ");\n\$this->var_expires = unserialize(" . var_export(serialize($this->var_expires), true) . ");");
			@flock($fp, LOCK_UN);
			fclose($fp);

			if (!function_exists('phpbb_chmod'))
			{
				include(PHPBB_ROOT_PATH . 'includes/functions.' . PHP_EXT);
			}

			phpbb_chmod($filename, CHMOD_WRITE);
		}
		else
		{
			// Now, this occurred how often? ... phew, just tell the user then...
			if (!@is_writable($this->cache_dir))
			{
				trigger_error($this->cache_dir . ' is NOT writable.', E_USER_ERROR);
			}

			trigger_error('Not able to open ' . $filename, E_USER_ERROR);
		}

		$this->is_modified = false;
	}

	/**
	* Check if a given cache entry exist
	*/
	public function exists($var_name)
	{
		if ($var_name[0] !== '#')
		{
			return file_exists($this->cache_dir . $this->cache_prefix . '_' . $var_name . '.' . PHP_EXT);
		}
	}

	protected function destroy_local($var_name, $additional_data = false)
	{
		// We support removing sql cache sorted by table ;)
		if ($this->cache_prefix == 'sql' && $var_name === 'tables' && !empty($additional_data))
		{
			$table = (!is_array($additional_data)) ? array($additional_data) : $additional_data;
			$dir = @opendir($this->cache_dir);

			if (!$dir)
			{
				return;
			}

			while (($entry = readdir($dir)) !== false)
			{
				if (strpos($entry, $this->cache_prefix . '_') !== 0)
				{
					continue;
				}

				// The following method is more failproof than simply assuming the query is on line 3 (which it should be)
				@include($this->cache_dir . $entry);

				if (empty($data))
				{
					$this->_remove_file($this->cache_dir . $entry);
					continue;
				}

				// Get the query
				$data = $data['query'];

				$found = false;
				foreach ($table as $check_table)
				{
					// Better catch partial table names than no table names. ;)
					if (strpos($data, $check_table) !== false)
					{
						$found = true;
						break;
					}
				}

				if ($found)
				{
					$this->_remove_file($this->cache_dir . $entry);
				}
			}
			closedir($dir);

			return;
		}

		if (!$this->exists($var_name))
		{
			return false;
		}

		$this->_remove_file($this->cache_dir . $this->cache_prefix . '_' . $var_name . '.' . PHP_EXT, true);
	}

	/**
	* Removes/unlinks file
	*/
	private function _remove_file($filename, $check = false)
	{
		if ($check && !@is_writable($this->cache_dir))
		{
			// E_USER_ERROR - not using language entry - intended.
			trigger_error('Unable to remove files within ' . $this->cache_dir . '. Please check directory permissions.', E_USER_ERROR);
		}

		return @unlink($filename);
	}

	/**
	* Tidy cache
	*/
	protected function _tidy()
	{
		$dir = @opendir($this->cache_dir);

		if (!$dir)
		{
			return;
		}

		while (($entry = readdir($dir)) !== false)
		{
			if (strpos($entry, $this->cache_prefix . '_') !== 0 || strpos($entry, $this->cache_prefix . '_global') === 0)
			{
				continue;
			}

			$expired = true;
			@include($this->cache_dir . $entry);

			if ($expired)
			{
				$this->remove_file($this->cache_dir . $entry);
			}
		}
		closedir($dir);
	}
}

/**
* ACM File Based Caching
* @package acm
*/
class acm
{

	public $sql_rowset = array();

	/**
	* Purge cache data
	*/
	public function purge()
	{
		// Purge all phpbb cache files
		$dir = @opendir($this->cache_dir);

		if (!$dir)
		{
			return;
		}

		while (($entry = readdir($dir)) !== false)
		{
			if (strpos($entry, 'sql_') !== 0 && strpos($entry, 'data_') !== 0 && strpos($entry, 'ctpl_') !== 0 && strpos($entry, 'tpl_') !== 0)
			{
				continue;
			}

			$this->remove_file($this->cache_dir . $entry);
		}
		closedir($dir);

		unset($this->vars);
		unset($this->var_expires);
		unset($this->sql_rowset);

		$this->vars = array();
		$this->var_expires = array();
		$this->sql_rowset = array();

		$this->is_modified = false;
	}


}

?>