<?php
if (!defined('IN_PHPBB'))
{
	exit();
}

// This defines our main API

class phpbb_api
{
	public $user = NULL;
	public $include_path = false;

	public function __construct($custom_api_path = false)
	{
		$this->include_path = ($custom_api_path) ? $custom_api_path : PHPBB_ROOT_PATH . 'includes/api/';

		// Include provided API files
		$this->user = new phpbb_api_user();

		// Include custom API files
		if ($dh = @opendir($this->include_path))
		{
			while (($file = readdir($dh)) !== false)
			{
				if (strpos($file, 'api_') === 0 && substr($file, -(strlen(PHP_EXT) + 1)) === '.' . PHP_EXT)
				{
					$name = substr($file, 4, -strlen(PHP_EXT) - 1);
					$class_name = 'phpbb__api_' . $name;

					require_once $this->include_path . $file;

					if (class_exists($class_name, false))
					{
						if (property_exists($class_name, '_instantiate'))
						{
							$this->$name = new $class_name();
						}
					}
				}
			}
			closedir($dh);
		}
	}
}

?>