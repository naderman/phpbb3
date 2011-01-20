<?php
/**
*
* @package phpbb_text
* @copyright (c) 2011 phpBB Group
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
* @package phpbb_text
*/
interface phpbb_text_formatting_interface
{
	/**
	* Returns an HTML version ready for displaying to a user.
	*
	* @return string Resulting markup
	*/
	public function to_html();

	/**
	* Returns a plaintext representation which can be edited by a user.
	*
	*/
	public function to_text();
}
