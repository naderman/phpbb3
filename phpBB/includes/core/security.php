<?php
/**
*
* @package core
* @version $Id: core.php 9200 2008-12-15 18:06:53Z acydburn $
* @copyright (c) 2008 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

if (!defined('IN_PHPBB'))
{
	exit();
}
/**
* Class for generating random numbers, unique ids, unique keys, seeds, hashes...
* Has phpBB plugin support
*/
class phpbb_security extends phpbb_plugin_support
{
	/**
	 * @var bool Let this class being instantiated within the core
	 */
	public $phpbb_register = true;

	/**
	 * Used Hash type.
	 */
	public $hash_type = '$H$';

	/**
	 * @var bool
	 */
	private $dss_seeded = false;

	public function __construct() {}

	/**
	* Generates an alphanumeric random string of given length
	*/
	public function gen_rand_string($num_chars = 8)
	{
		if ($this->plugin_overload(__FUNCTION__)) return $this->__call(__FUNCTION__, array($num_chars));

		$rand_str = $this->unique_id();
		$rand_str = str_replace('0', 'Z', strtoupper(base_convert($rand_str, 16, 35)));

		$result = substr($rand_str, 0, $num_chars);

		return ($this->plugin_append(__FUNCTION__)) ? $this->plugin_append_call(__FUNCTION__, $result, $num_chars) : $result;
	}

	/**
	* Return unique id
	* @param string $extra additional entropy
	*/
	public function unique_id($extra = 'c')
	{
		if ($this->plugin_overload(__FUNCTION__)) return $this->__call(__FUNCTION__, array($extra));

		if (!isset(phpbb::$config['rand_seed']))
		{
			$val = md5(md5($extra) . microtime());
			$val = md5(md5($extra) . $val . $extra);
			return substr($val, 4, 16);
		}

		$val = phpbb::$config['rand_seed'] . microtime();
		$val = md5($val);
		phpbb::$config['rand_seed'] = md5(phpbb::$config['rand_seed'] . $val . $extra);

		if (!$this->dss_seeded && phpbb::$config['rand_seed_last_update'] < time() - rand(1,10))
		{
			set_config('rand_seed', phpbb::$config['rand_seed'], true);
			set_config('rand_seed_last_update', time(), true);
		}

		$result = substr($val, 4, 16);

		return ($this->plugin_append(__FUNCTION__)) ? $this->plugin_append_call(__FUNCTION__, $result, $extra) : $result;
	}

	/**
	*
	* @version Version 0.1 / $Id: core_security.php 9185 2008-12-11 17:39:51Z acydburn $
	*
	* Portable PHP password hashing framework.
	*
	* Written by Solar Designer <solar at openwall.com> in 2004-2006 and placed in
	* the public domain.
	*
	* There's absolutely no warranty.
	*
	* The homepage URL for this framework is:
	*
	*	http://www.openwall.com/phpass/
	*
	* Please be sure to update the Version line if you edit this file in any way.
	* It is suggested that you leave the main version number intact, but indicate
	* your project name (after the slash) and add your own revision information.
	*
	* Please do not change the "private" password hashing method implemented in
	* here, thereby making your hashes incompatible.  However, if you must, please
	* change the hash type identifier (the "$P$") to something different.
	*
	* Obviously, since this code is in the public domain, the above are not
	* requirements (there can be none), but merely suggestions.
	*
	*
	* Hash the password
	*/
	public function hash_password($password)
	{
		if ($this->plugin_overload(__FUNCTION__)) return $this->__call(__FUNCTION__, array($password));

		$itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

		$random_state = $this->unique_id();
		$random = '';
		$count = 6;

		if (($fh = @fopen('/dev/urandom', 'rb')))
		{
			$random = fread($fh, $count);
			fclose($fh);
		}

		if (strlen($random) < $count)
		{
			$random = '';

			for ($i = 0; $i < $count; $i += 16)
			{
				$random_state = md5($this->unique_id() . $random_state);
				$random .= pack('H*', md5($random_state));
			}
			$random = substr($random, 0, $count);
		}

		$hash = $this->_hash_crypt_private($password, $this->_hash_gensalt_private($random, $itoa64), $itoa64);
		$result = (strlen($hash) == 34) ? $hash : md5($password);

		return ($this->plugin_append(__FUNCTION__)) ? $this->plugin_append_call(__FUNCTION__, $result, $password) : $result;
	}

	/**
	* Check for correct password
	*
	* @param string $password The password in plain text
	* @param string $hash The stored password hash
	*
	* @return bool Returns true if the password is correct, false if not.
	*/
	public function check_password($password, $hash)
	{
		if ($this->plugin_overload(__FUNCTION__)) return $this->__call(__FUNCTION__, array($password, $hash));

		$itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
		if (strlen($hash) == 34)
		{
			$result = ($this->_hash_crypt_private($password, $hash, $itoa64) === $hash) ? true : false;
		}
		else
		{
			$result = (md5($password) === $hash) ? true : false;
		}

		return ($this->plugin_append(__FUNCTION__)) ? $this->plugin_append_call(__FUNCTION__, $result, $password, $hash) : $result;
	}

	/**
	* Add a secret hash   for use in links/GET requests
	* @param string  $link_name The name of the link; has to match the name used in check_link_hash, otherwise no restrictions apply
	* @return string the hash
	*/
	public function hash_link($link_name)
	{
		if ($this->plugin_overload(__FUNCTION__)) return $this->__call(__FUNCTION__, array($link_name));

		if (!isset(phpbb::$user->data["hash_$link_name"]))
		{
			phpbb::$user->data["hash_$link_name"] = substr(sha1(phpbb::$user->data['user_form_salt'] . $link_name), 0, 8);
		}

		$result = phpbb::$user->data["hash_$link_name"];

		return ($this->plugin_append(__FUNCTION__)) ? $this->plugin_append_call(__FUNCTION__, $result, $link_name) : $result;
	}

	/**
	* checks a link hash - for GET requests
	* @param string $token the submitted token
	* @param string $link_name The name of the link
	* @return boolean true if all is fine
	*/
	public function check_link($token, $link_name)
	{
		if ($this->plugin_overload(__FUNCTION__)) return $this->__call(__FUNCTION__, array($token, $link_name));

		$result = $token === $this->generate_link_hash($link_name);

		return ($this->plugin_append(__FUNCTION__)) ? $this->plugin_append_call(__FUNCTION__, $result, $token, $link_name) : $result;
	}

	/**
	* Add a secret token to the form (requires the S_FORM_TOKEN template variable)
	* @param string  $form_name The name of the form; has to match the name used in check_form_key, otherwise no restrictions apply
	*/
	public function add_form_key($form_name)
	{
		if ($this->plugin_overload(__FUNCTION__)) return $this->__call(__FUNCTION__, array($form_name));

		$now = time();
		$token_sid = (phpbb::$user->data['user_id'] == ANONYMOUS && !empty(phpbb::$config['form_token_sid_guests'])) ? phpbb::$user->session_id : '';
		$token = sha1($now . phpbb::$user->data['user_form_salt'] . $form_name . $token_sid);

		$template->assign_vars(array(
			'S_FORM_TOKEN'	=> build_hidden_fields(array('creation_time' => $now, 'form_token' => $token)),
		));

		if ($this->plugin_append(__FUNCTION__)) $this->plugin_append_call(__FUNCTION__, true);
	}

	/**
	* Check the form key. Required for all altering actions not secured by confirm_box
	* @param string  $form_name The name of the form; has to match the name used in add_form_key, otherwise no restrictions apply
	* @param int $timespan The maximum acceptable age for a submitted form in seconds. Defaults to the config setting.
	* @param string $return_page The address for the return link
	* @param bool $trigger If true, the function will triger an error when encountering an invalid form
	*/
	public function check_form_key($form_name, $timespan = false, $return_page = '', $trigger = false)
	{
		if ($this->plugin_overload(__FUNCTION__)) return $this->__call(__FUNCTION__, array($form_name, $timespan, $return_page, $trigger));

		$result = false;

		if ($timespan === false)
		{
			// we enforce a minimum value of half a minute here.
			$timespan = (phpbb::$config['form_token_lifetime'] == -1) ? -1 : max(30, phpbb::$config['form_token_lifetime']);
		}

		if (request::is_set_post('creation_time') && request::is_set_post('form_token'))
		{
			$creation_time	= abs(request_var('creation_time', 0));
			$token = request_var('form_token', '');

			$diff = time() - $creation_time;

			// If creation_time and the time() now is zero we can assume it was not a human doing this (the check for if ($diff)...
			if ($diff && ($diff <= $timespan || $timespan === -1))
			{
				$token_sid = (phpbb::$user->data['user_id'] == ANONYMOUS && !empty(phpbb::$config['form_token_sid_guests'])) ? phpbb::$user->session_id : '';
				$key = sha1($creation_time . phpbb::$user->data['user_form_salt'] . $form_name . $token_sid);

				if ($key === $token)
				{
					$result = true;
				}
			}
		}

		if ($trigger && !$result)
		{
			trigger_error(phpbb::$user->lang['FORM_INVALID'] . $return_page);
		}

		return ($this->plugin_append(__FUNCTION__)) ? $this->plugin_append_call(__FUNCTION__, $result, $form_name, $timespan, $return_page, $trigger) : $result;
	}

	/**
	* Generate salt for hash generation
	*/
	private function _hash_gensalt_private($input, &$itoa64, $iteration_count_log2 = 6)
	{
		if ($iteration_count_log2 < 4 || $iteration_count_log2 > 31)
		{
			$iteration_count_log2 = 8;
		}

		$output = $this->hash_type;
		$output .= $itoa64[min($iteration_count_log2 + 5, 30)];
		$output .= $this->_hash_encode64($input, 6, $itoa64);

		return $output;
	}

	/**
	* Encode hash
	*/
	private function _hash_encode64($input, $count, &$itoa64)
	{
		$output = '';
		$i = 0;

		do
		{
			$value = ord($input[$i++]);
			$output .= $itoa64[$value & 0x3f];

			if ($i < $count)
			{
				$value |= ord($input[$i]) << 8;
			}

			$output .= $itoa64[($value >> 6) & 0x3f];

			if ($i++ >= $count)
			{
				break;
			}

			if ($i < $count)
			{
				$value |= ord($input[$i]) << 16;
			}

			$output .= $itoa64[($value >> 12) & 0x3f];

			if ($i++ >= $count)
			{
				break;
			}

			$output .= $itoa64[($value >> 18) & 0x3f];
		}
		while ($i < $count);

		return $output;
	}

	/**
	* The crypt function/replacement
	*/
	private function _hash_crypt_private($password, $setting, &$itoa64)
	{
		$output = '*';

		// Check for correct hash
		if (substr($setting, 0, 3) != $this->hash_type)
		{
			return $output;
		}

		$count_log2 = strpos($itoa64, $setting[3]);

		if ($count_log2 < 7 || $count_log2 > 30)
		{
			return $output;
		}

		$count = 1 << $count_log2;
		$salt = substr($setting, 4, 8);

		if (strlen($salt) != 8)
		{
			return $output;
		}

		/**
		* We're kind of forced to use MD5 here since it's the only
		* cryptographic primitive available in all versions of PHP
		* currently in use.  To implement our own low-level crypto
		* in PHP would result in much worse performance and
		* consequently in lower iteration counts and hashes that are
		* quicker to crack (by non-PHP code).
		*/
		$hash = md5($salt . $password, true);
		do
		{
			$hash = md5($hash . $password, true);
		}
		while (--$count);

		$output = substr($setting, 0, 12);
		$output .= $this->_hash_encode64($hash, 16, $itoa64);

		return $output;
	}
}

?>