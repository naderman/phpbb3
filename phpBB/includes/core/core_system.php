<?php
if (!defined('IN_PHPBB'))
{
	exit();
}

class phpbb_core_system extends phpbb_system
{
	public $_instantiate = true;

	/**
	* Get PATH environment variable
	*/
	protected function get_path()
	{
		// Return PATH
		return (!empty($_SERVER['PATH'])) ? $_SERVER['PATH'] : getenv('PATH');
	}
}

?>