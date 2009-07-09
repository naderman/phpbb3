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
* phpBB auto loading mechanism
*/
class phpbb_autoload
{
	/**
	* Loads a particular class and may also load class dependencies
	*
	* @param	string	$class_name	The class name
	*/
	public static function load($class_name)
	{
		/**
		* @todo Cache this so we can just use a lookup
		*/

		// only handle phpBB classes
		if (strpos($class_name, 'phpbb_') !== 0)
		{
			return;
		}

		// name should be well formed
		if (!preg_match('#[a-z0-9_]#i', $class_name))
		{
			return;
		}

		$class_name = substr($class_name, 6);
		$class_name = basename($class_name);

		$filenames = array(
			'includes/core/' . $class_name,
		);

		if (strpos($class_name, '_') !== false)
		{
			$class_name = str_replace('_', '/', $class_name);

			$filenames = array_merge($filenames, array(
				'includes/' . $class_name,
			));
		}

		foreach ($filenames as $filename)
		{
			if (file_exists(PHPBB_ROOT_PATH . $filename . '.' . PHP_EXT))
			{
				include PHPBB_ROOT_PATH . $filename . '.' . PHP_EXT;
				// store info in cache here
				return;
			}
		}
	}
}

// Register autoload function
spl_autoload_register('phpbb_autoload::load');