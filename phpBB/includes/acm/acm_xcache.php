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

/**
* ACM XCache Based Caching
* @package acm
*/
class phpbb_acm_xcache extends phpbb_acm_abstract
{
	public $supported = array('data' => true, 'sql' => true);

	/**
	* Set cache path
	*/
	function __construct($cache_prefix)
	{
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

		return xcache_get($this->cache_prefix . '_' . $var_name);
	}

	/**
	* Put data into cache
	*/
	protected function put_local($var_name, $data, $ttl = 31536000)
	{
		xcache_set($this->cache_prefix . '_' . $var_name, $data, $ttl);
		return $data;
	}

	/**
	* Load global cache
	*/
	public function load()
	{
		// grab the global cache
		if (xcache_isset($this->cache_prefix . '_global'))
		{
			$data = xcache_get($this->cache_prefix . '_global');

			$this->vars = unserialize($data['vars']);
			$this->var_expires = unserialize($data['var_expires']);

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

		$data = array(
			'vars'			=> serialize($this->vars),
			'var_expires'	=> serialize($this->var_expires),
		);

		xcache_set($this->cache_prefix . '_global', $data);

		$this->is_modified = false;
	}

	/**
	* Check if a given cache entry exist
	*/
	public function exists($var_name)
	{
		if ($var_name[0] !== '#')
		{
			return xcache_isset($this->cache_prefix . '_' . $var_name);
		}
	}

	protected function destroy_local($var_name, $additional_data = false)
	{
		// We support removing sql cache sorted by table ;)
		if ($this->cache_prefix == 'sql' && $var_name === 'tables' && !empty($additional_data))
		{
			if (!is_array($additional_data))
			{
				$table = array($additional_data);
			}
			else
			{
				$table = $additional_data;
			}

			$num_entries = xcache_count(XC_TYPE_VAR);

			if (!$num_entries)
			{
				return;
			}

			for ($i = 0; $i < $num_entries; $i++)
			{
				$data = xcache_list(XC_TYPE_VAR, $i);

				if (empty($data['cache_list']))
				{
					continue;
				}

				foreach ($data['cache_list'] as $list)
				{
					if (strpos($list['name'], $this->cache_prefix . '_') === 0)
					{
						continue;
					}

					if (xcache_isset($list['name']))
					{
						$data = xcache_get($list['name']);

						if (empty($data['query']))
						{
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
							xcache_unset($list['name']);
						}
					}
				}
			}

			return;
		}

		if (!$this->exists($var_name))
		{
			return false;
		}

		xcache_unset($this->cache_prefix . '_' . $var_name);
	}

	/**
	* Purge cache
	*/
	protected function _purge()
	{
		$num_entries = xcache_count(XC_TYPE_VAR);

		if (!$num_entries)
		{
			return;
		}

		for ($i = 0; $i < $num_entries; $i++)
		{
			$data = xcache_list(XC_TYPE_VAR, $i);

			if (empty($data['cache_list']))
			{
				continue;
			}

			$found = false;
			foreach ($data['cache_list'] as $list)
			{
				if (strpos($list['name'], $this->cache_prefix . '_') === 0)
				{
					continue;
				}

				$found = true;
			}

			if ($found)
			{
				xcache_clear_cache(XC_TYPE_VAR, $i);
			}
		}

		return;
	}
}

?>