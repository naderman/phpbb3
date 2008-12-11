<?php
/**
*
* @package phpBB3
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

class phpbb_server
{
	/**
	* Get valid hostname/port. HTTP_HOST is used, SERVER_NAME if HTTP_HOST not present.
	*/
	public static function get_host()
	{
		// Get hostname
		$host = (!empty($_SERVER['HTTP_HOST'])) ? $_SERVER['HTTP_HOST'] : ((!empty($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : getenv('SERVER_NAME'));

		// Should be a string and lowered
		$host = (string) strtolower($host);

		// If host is equal the cookie domain or the server name (if config is set), then we assume it is valid
		if ((isset(phpbb::$config['cookie_domain']) && $host === phpbb::$config['cookie_domain']) || (isset(phpbb::$config['server_name']) && $host === phpbb::$config['server_name']))
		{
			return $host;
		}

		// Is the host actually a IP? If so, we use the IP... (IPv4)
		if (long2ip(ip2long($host)) === $host)
		{
			return $host;
		}

		// Now return the hostname (this also removes any port definition). The http:// is prepended to construct a valid URL, hosts never have a scheme assigned
		$host = @parse_url('http://' . $host, PHP_URL_HOST);

		// Remove any portions not removed by parse_url (#)
		$host = str_replace('#', '', $host);

		// If, by any means, the host is now empty, we will use a "best approach" way to guess one
		if (empty($host))
		{
			if (!empty(phpbb::$config['server_name']))
			{
				$host = phpbb::$config['server_name'];
			}
			else if (!empty(phpbb::$config['cookie_domain']))
			{
				$host = (strpos(phpbb::$config['cookie_domain'], '.') === 0) ? substr(phpbb::$config['cookie_domain'], 1) : phpbb::$config['cookie_domain'];
			}
			else
			{
				// Set to OS hostname or localhost
				$host = (function_exists('php_uname')) ? php_uname('n') : 'localhost';
			}
		}

		// It may be still no valid host, but for sure only a hostname (we may further expand on the cookie domain... if set)
		return $host;
	}

	/**
	* Extract current session page, relative from current root path (PHPBB_ROOT_PATH)
	*/
	public static function get_page()
	{
		$page_array = array();

		// First of all, get the request uri...
		$script_name = (!empty($_SERVER['PHP_SELF'])) ? $_SERVER['PHP_SELF'] : getenv('PHP_SELF');
		$args = (!empty($_SERVER['QUERY_STRING'])) ? explode('&', $_SERVER['QUERY_STRING']) : explode('&', getenv('QUERY_STRING'));

		// If we are unable to get the script name we use REQUEST_URI as a failover and note it within the page array for easier support...
		if (!$script_name)
		{
			$script_name = (!empty($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : getenv('REQUEST_URI');
			$script_name = (($pos = strpos($script_name, '?')) !== false) ? substr($script_name, 0, $pos) : $script_name;
			$page_array['failover'] = 1;
		}

		// Replace backslashes and doubled slashes (could happen on some proxy setups)
		$script_name = str_replace(array('\\', '//'), '/', $script_name);

		// Now, remove the sid and let us get a clean query string...
		$use_args = array();

		// Since some browser do not encode correctly we need to do this with some "special" characters...
		// " -> %22, ' => %27, < -> %3C, > -> %3E
		$find = array('"', "'", '<', '>');
		$replace = array('%22', '%27', '%3C', '%3E');

		foreach ($args as $argument)
		{
			if (strpos($argument, 'sid=') === 0)
			{
				continue;
			}

			$use_args[] = str_replace($find, $replace, $argument);
		}
		unset($args);

		// The following examples given are for an request uri of {path to the phpbb directory}/adm/index.php?i=10&b=2

		// The current query string
		$query_string = trim(implode('&', $use_args));

		// basenamed page name (for example: index.php)
		$page_name = basename($script_name);
		$page_name = urlencode(htmlspecialchars($page_name));

		// current directory within the phpBB root (for example: adm)
		$root_dirs = explode('/', str_replace('\\', '/', phpbb::$url->realpath(PHPBB_ROOT_PATH)));
		$page_dirs = explode('/', str_replace('\\', '/', phpbb::$url->realpath('./')));
		$intersection = array_intersect_assoc($root_dirs, $page_dirs);

		$root_dirs = array_diff_assoc($root_dirs, $intersection);
		$page_dirs = array_diff_assoc($page_dirs, $intersection);

		$page_dir = str_repeat('../', sizeof($root_dirs)) . implode('/', $page_dirs);

		if ($page_dir && substr($page_dir, -1, 1) == '/')
		{
			$page_dir = substr($page_dir, 0, -1);
		}

		// Current page from phpBB root (for example: adm/index.php?i=10&b=2)
		$page = (($page_dir) ? $page_dir . '/' : '') . $page_name . (($query_string) ? "?$query_string" : '');

		// The script path from the webroot to the current directory (for example: /phpBB3/adm/) : always prefixed with / and ends in /
		$script_path = trim(str_replace('\\', '/', dirname($script_name)));

		// The script path from the webroot to the phpBB root (for example: /phpBB3/)
		$script_dirs = explode('/', $script_path);
		array_splice($script_dirs, -sizeof($page_dirs));
		$root_script_path = implode('/', $script_dirs) . (sizeof($root_dirs) ? '/' . implode('/', $root_dirs) : '');

		// We are on the base level (phpBB root == webroot), lets adjust the variables a bit...
		if (!$root_script_path)
		{
			$root_script_path = ($page_dir) ? str_replace($page_dir, '', $script_path) : $script_path;
		}

		$script_path .= (substr($script_path, -1, 1) == '/') ? '' : '/';
		$root_script_path .= (substr($root_script_path, -1, 1) == '/') ? '' : '/';

		$page_array += array(
			'page_name'			=> $page_name,
			'page_dir'			=> $page_dir,

			'query_string'		=> $query_string,
			'script_path'		=> str_replace(' ', '%20', htmlspecialchars($script_path)),
			'root_script_path'	=> str_replace(' ', '%20', htmlspecialchars($root_script_path)),

			'page'				=> $page,
			'forum'				=> request_var('f', 0),
		);

		return $page_array;
	}

	public static function get_browser()
	{
		return (!empty($_SERVER['HTTP_USER_AGENT'])) ? htmlspecialchars((string) $_SERVER['HTTP_USER_AGENT']) : '';
	}

	public static function get_referer()
	{
		return (!empty($_SERVER['HTTP_REFERER'])) ? htmlspecialchars((string) $_SERVER['HTTP_REFERER']) : '';
	}

	public static function get_port()
	{
		return (!empty($_SERVER['SERVER_PORT'])) ? (int) $_SERVER['SERVER_PORT'] : (int) getenv('SERVER_PORT');
	}

	public static function get_forwarded_for()
	{
		$forwarded_for = (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) ? (string) $_SERVER['HTTP_X_FORWARDED_FOR'] : '';

		// if the forwarded for header shall be checked we have to validate its contents
		if (phpbb::$config['forwarded_for_check'])
		{
			$forwarded_for = preg_replace('#, +#', ', ', $forwarded_for);

			// split the list of IPs
			$ips = explode(', ', $forwarded_for);
			foreach ($ips as $ip)
			{
				// check IPv4 first, the IPv6 is hopefully only going to be used very seldomly
				if (!empty($ip) && !preg_match(get_preg_expression('ipv4'), $ip) && !preg_match(get_preg_expression('ipv6'), $ip))
				{
					// contains invalid data, don't use the forwarded for header
					return '';
				}
			}
		}
		else
		{
			return '';
		}
	}

	public static function get_ip()
	{
		// Why no forwarded_for et al? Well, too easily spoofed. With the results of my recent requests
		// it's pretty clear that in the majority of cases you'll at least be left with a proxy/cache ip.
		return (!empty($_SERVER['REMOTE_ADDR'])) ? htmlspecialchars($_SERVER['REMOTE_ADDR']) : '';
	}

	public static function get_load()
	{
		$load = false;

		// Load limit check (if applicable)
		if (phpbb::$config['limit_load'] || phpbb::$config['limit_search_load'])
		{
			if ((function_exists('sys_getloadavg') && $load = sys_getloadavg()) || ($load = explode(' ', @file_get_contents('/proc/loadavg'))))
			{
				$load = array_slice($load, 0, 1);
				$load = floatval($load[0]);
			}
			else
			{
				set_config('limit_load', '0');
				set_config('limit_search_load', '0');
			}
		}

		return $load;
	}

	public static function get_request_method()
	{
		return (isset($_SERVER['REQUEST_METHOD'])) ? strtolower(htmlspecialchars((string) $_SERVER['REQUEST_METHOD'])) : '';
	}
}

/**
* Session class
* @package phpBB3
*/
class phpbb_session
{
	private $cookie_data = array();

	public $data = array();
	public $server = array();
	public $session_id = '';
	public $time_now = 0;
	public $update_session_page = true;

	public $auth = NULL;

	public $is_registered = false;
	public $is_bot = false;

	public function __construct()
	{
		// Reset data array ;)
		$this->data = array();

		// Init auth object
		$method = basename(trim(phpbb::$config['auth_method']));
		$class = 'phpbb_auth_' . $method;
		$this->auth = new $class();
	}

	/**
	* Start session management
	*
	* This is where all session activity begins. We gather various pieces of
	* information from the client and server. We test to see if a session already
	* exists. If it does, fine and dandy. If it doesn't we'll go on to create a
	* new one ... pretty logical heh? We also examine the system load (if we're
	* running on a system which makes such information readily available) and
	* halt if it's above an admin definable limit.
	*
	* @param bool $update_session_page if true the session page gets updated.
	*			This can be set to circumvent certain scripts to update the users last visited page.
	*/
	public function session_begin($update_session_page = true)
	{
		global $SID, $_SID, $_EXTRA_URL;

		// Give us some basic information
		$this->time_now				= time();
		$this->cookie_data			= array('u' => 0, 'k' => '');
		$this->update_session_page	= $update_session_page;

		// Some server variables, directly generated by phpbb_server methods
		$this->server = array();

		foreach (get_class_methods('phpbb_server') as $method)
		{
			if (strpos($method, 'get_') !== 0)
			{
				continue;
			}

			$this->server[substr($method, 4)] = phpbb_server::$method();
		}

		if (request::is_set(phpbb::$config['cookie_name'] . '_sid', request::COOKIE) || request::is_set(phpbb::$config['cookie_name'] . '_u', request::COOKIE))
		{
			$this->cookie_data['u'] = request_var(phpbb::$config['cookie_name'] . '_u', 0, false, true);
			$this->cookie_data['k'] = request_var(phpbb::$config['cookie_name'] . '_k', '', false, true);
			$this->session_id 		= request_var(phpbb::$config['cookie_name'] . '_sid', '', false, true);

			$SID = (defined('NEED_SID')) ? '?sid=' . $this->session_id : '?sid=';
			$_SID = (defined('NEED_SID')) ? $this->session_id : '';

			if (empty($this->session_id))
			{
				$this->session_id = $_SID = request_var('sid', '');
				$SID = '?sid=' . $this->session_id;
				$this->cookie_data = array('u' => 0, 'k' => '');
			}
		}
		else
		{
			$this->session_id = $_SID = request_var('sid', '');
			$SID = '?sid=' . $this->session_id;
		}

		$_EXTRA_URL = array();

		// Now check for an existing session
		if ($this->session_exist())
		{
			return true;
		}

		// If we reach here then no (valid) session exists. So we'll create a new one
		return $this->session_create();
	}

	/**
	* Create a new session
	*
	* If upon trying to start a session we discover there is nothing existing we
	* jump here. Additionally this method is called directly during login to regenerate
	* the session for the specific user. In this method we carry out a number of tasks;
	* garbage collection, (search)bot checking, banned user comparison. Basically
	* though this method will result in a new session for a specific user.
	*/
	public function session_create($user_id = false, $set_admin = false, $persist_login = false, $viewonline = true)
	{
		global $SID, $_SID;

		// There is one case where we need to add a "failsafe" user... when we are not able to query the database
		if (!phpbb::registered('db'))
		{
			$this->data = $this->default_data();
			return true;
		}

		// If the data array is filled, chances are high that there was a different session active
		if (sizeof($this->data))
		{
			// Kill the session and do not create a new one
			$this->session_kill(false);
		}

		$this->data = array();

		/* Garbage collection ... remove old sessions updating user information
		// if necessary. It means (potentially) 11 queries but only infrequently
		if ($this->time_now > phpbb::$config['session_last_gc'] + phpbb::$config['session_gc'])
		{
			$this->session_gc();
		}*/

		// Do we allow autologin on this board? No? Then override anything
		// that may be requested here
		if (!phpbb::$config['allow_autologin'])
		{
			$this->cookie_data['k'] = $persist_login = false;
		}

		// Check for autologin key. ;)
		if (method_exists($this->auth, 'autologin'))
		{
			$this->data = $this->auth->autologin();

			if (sizeof($this->data))
			{
				$this->cookie_data['k'] = '';
				$this->cookie_data['u'] = $this->data['user_id'];
			}
		}

		// NULL indicates we need to check for a bot later. Sometimes it is apparant that it is not a bot. ;) No need to always check this.
		$bot = NULL;

		// If we're presented with an autologin key we'll join against it.
		// Else if we've been passed a user_id we'll grab data based on that
		if (isset($this->cookie_data['k']) && $this->cookie_data['k'] && $this->cookie_data['u'] && !sizeof($this->data))
		{
			$sql = 'SELECT u.*
				FROM ' . USERS_TABLE . ' u, ' . SESSIONS_KEYS_TABLE . ' k
				WHERE u.user_id = ' . (int) $this->cookie_data['u'] . '
					AND u.user_type IN (' . USER_NORMAL . ', ' . USER_FOUNDER . ")
					AND k.user_id = u.user_id
					AND k.key_id = '" . phpbb::$db->sql_escape(md5($this->cookie_data['k'])) . "'";
			$result = phpbb::$db->sql_query($sql);
			$this->data = phpbb::$db->sql_fetchrow($result);
			phpbb::$db->sql_freeresult($result);

			$bot = false;
		}
		else if ($user_id !== false && !sizeof($this->data))
		{
			$this->cookie_data['k'] = '';
			$this->cookie_data['u'] = $user_id;

			$sql = 'SELECT *
				FROM ' . USERS_TABLE . '
				WHERE user_id = ' . (int) $this->cookie_data['u'] . '
					AND user_type IN (' . USER_NORMAL . ', ' . USER_FOUNDER . ')';
			$result = phpbb::$db->sql_query($sql);
			$this->data = phpbb::$db->sql_fetchrow($result);
			phpbb::$db->sql_freeresult($result);

			$bot = false;
		}

		/**
		* Here we do a bot check, oh er saucy! No, not that kind of bot
		* check. We loop through the list of bots defined by the admin and
		* see if we have any useragent and/or IP matches. If we do, this is a
		* bot, act accordingly
		*/
		if ($bot === NULL)
		{
			$bot = $this->check_bot();
		}

		// If no data was returned one or more of the following occurred:
		// Key didn't match one in the DB
		// User does not exist
		// User is inactive
		// User is bot
		if (!sizeof($this->data) || !is_array($this->data))
		{
			$this->cookie_data['k'] = '';
			$this->cookie_data['u'] = ($bot) ? $bot : ANONYMOUS;

			if (!$bot)
			{
				$sql = 'SELECT *
					FROM ' . USERS_TABLE . '
					WHERE user_id = ' . (int) $this->cookie_data['u'];
			}
			else
			{
				// We give bots always the same session if it is not yet expired.
				$sql = 'SELECT u.*, s.*
					FROM ' . USERS_TABLE . ' u
					LEFT JOIN ' . SESSIONS_TABLE . ' s ON (s.session_user_id = u.user_id)
					WHERE u.user_id = ' . (int) $bot;
			}

			$result = phpbb::$db->sql_query($sql);
			$this->data = phpbb::$db->sql_fetchrow($result);
			phpbb::$db->sql_freeresult($result);

			$this->is_registered = false;
		}
		else
		{
			$this->is_registered = true;
		}

		// Force user id to be integer...
		$this->data['user_id'] = (int) $this->data['user_id'];

		// Code for ANONYMOUS user, INACTIVE user and BOTS
		if (!$this->is_registered)
		{
			// Set last visit date to 'now'
			$this->data['session_last_visit'] = $this->time_now;
			$this->is_bot = ($bot) ? true : false;

			// If our friend is a bot, we re-assign a previously assigned session
			if ($this->is_bot && $bot == $this->data['user_id'] && $this->data['session_id'])
			{
				if ($this->session_valid(false))
				{
					$this->session_id = $this->data['session_id'];

					// Only update session DB a minute or so after last update or if page changes
					if ($this->time_now - $this->data['session_time'] > 60 || ($this->update_session_page && $this->data['session_page'] != $this->server['page']['page']))
					{
						$this->data['session_time'] = $this->data['session_last_visit'] = $this->time_now;
						$sql_ary = array('session_time' => $this->time_now, 'session_last_visit' => $this->time_now, 'session_admin' => 0);

						if ($this->update_session_page)
						{
							$sql_ary['session_page'] = substr($this->server['page']['page'], 0, 199);
						}

						$sql = 'UPDATE ' . SESSIONS_TABLE . ' SET ' . phpbb::$db->sql_build_array('UPDATE', $sql_ary) . "
							WHERE session_id = '" . phpbb::$db->sql_escape($this->session_id) . "'";
						phpbb::$db->sql_query($sql);

						// Update the last visit time
						$sql = 'UPDATE ' . USERS_TABLE . '
							SET user_lastvisit = ' . (int) $this->data['session_time'] . '
							WHERE user_id = ' . (int) $this->data['user_id'];
						phpbb::$db->sql_query($sql);
					}

					$SID = '?sid=';
					$_SID = '';

					return true;
				}
				else
				{
					// If the ip and browser does not match make sure we only have one bot assigned to one session
					phpbb::$db->sql_query('DELETE FROM ' . SESSIONS_TABLE . ' WHERE session_user_id = ' . $this->data['user_id']);
				}
			}
		}
		else
		{
			// Code for registered users
			$this->data['session_last_visit'] = (!empty($this->data['session_time'])) ? $this->data['session_time'] : (($this->data['user_lastvisit']) ? $this->data['user_lastvisit'] : $this->time_now);
			$this->is_bot = false;
		}

		// At this stage we should have a filled data array, defined cookie u and k data.
		// data array should contain recent session info if we're a real user and a recent
		// session exists in which case session_id will also be set

		// Is user banned? Are they excluded? Won't return on ban, exists within method
		if ($this->data['user_type'] != USER_FOUNDER)
		{
			if (!phpbb::$config['forwarded_for_check'])
			{
				$this->check_ban($this->data['user_id'], $this->server['ip']);
			}
			else
			{
				$ips = explode(', ', $this->forwarded_for);
				$ips[] = $this->server['ip'];
				$this->check_ban($this->data['user_id'], $ips);
			}
		}

		$session_autologin = (($this->cookie_data['k'] || $persist_login) && $this->is_registered) ? true : false;
		$set_admin = ($set_admin && $this->is_registered) ? true : false;

		// Create or update the session
		$sql_ary = array(
			'session_user_id'		=> (int) $this->data['user_id'],
			'session_start'			=> (int) $this->time_now,
			'session_last_visit'	=> (int) $this->data['session_last_visit'],
			'session_time'			=> (int) $this->time_now,
			'session_browser'		=> (string) trim(substr($this->server['browser'], 0, 149)),
			'session_forwarded_for'	=> (string) $this->server['forwarded_for'],
			'session_ip'			=> (string) $this->server['ip'],
			'session_autologin'		=> ($session_autologin) ? 1 : 0,
			'session_admin'			=> ($set_admin) ? 1 : 0,
			'session_viewonline'	=> ($viewonline) ? 1 : 0,
		);

		if ($this->update_session_page)
		{
			$sql_ary['session_page'] = (string) substr($this->server['page']['page'], 0, 199);
		}

		phpbb::$db->sql_return_on_error(true);

		// Delete old session, if user id now different from anonymous
		if (!defined('IN_ERROR_HANDLER'))
		{
			// We do not care about the user id, because we assign a new session later
			$sql = 'DELETE
				FROM ' . SESSIONS_TABLE . "
				WHERE session_id = '" . phpbb::$db->sql_escape($this->session_id) . "'";
			$result = phpbb::$db->sql_query($sql);

			// If there were no sessions or the session id empty (then the affected rows will be empty too), then we have a brand new session and can check the active sessions limit
			if ((!$result || !phpbb::$db->sql_affectedrows()) && (empty($this->data['session_time']) && phpbb::$config['active_sessions']))
			{
				$sql = 'SELECT COUNT(session_id) AS sessions
					FROM ' . SESSIONS_TABLE . '
					WHERE session_time >= ' . ($this->time_now - 60);
				$result = phpbb::$db->sql_query($sql);
				$row = phpbb::$db->sql_fetchrow($result);
				phpbb::$db->sql_freeresult($result);

				if ((int) $row['sessions'] > (int) phpbb::$config['active_sessions'])
				{
					header('HTTP/1.1 503 Service Unavailable');
					trigger_error('BOARD_UNAVAILABLE');
				}
			}
		}

		$this->session_id = $this->data['session_id'] = md5(phpbb::$security->unique_id());

		$sql_ary['session_id'] = (string) $this->session_id;

		$sql = 'INSERT INTO ' . SESSIONS_TABLE . ' ' . phpbb::$db->sql_build_array('INSERT', $sql_ary);
		phpbb::$db->sql_query($sql);

		phpbb::$db->sql_return_on_error(false);

		// Regenerate autologin/persistent login key
		if ($session_autologin)
		{
			$this->set_login_key();
		}

		// refresh data
		$SID = '?sid=' . $this->session_id;
		$_SID = $this->session_id;
		$this->data = array_merge($this->data, $sql_ary);

		if (!$bot)
		{
			$cookie_expire = $this->time_now + ((phpbb::$config['max_autologin_time']) ? 86400 * (int) phpbb::$config['max_autologin_time'] : 31536000);

			$this->set_cookie('u', $this->cookie_data['u'], $cookie_expire);
			$this->set_cookie('k', $this->cookie_data['k'], $cookie_expire);
			$this->set_cookie('sid', $this->session_id, $cookie_expire);

			unset($cookie_expire);

			// Only one session entry present...
			$sql = 'SELECT COUNT(session_id) AS sessions
					FROM ' . SESSIONS_TABLE . '
					WHERE session_user_id = ' . (int) $this->data['user_id'] . '
					AND session_time >= ' . (int) ($this->time_now - (max(phpbb::$config['session_length'], phpbb::$config['form_token_lifetime'])));
			$result = phpbb::$db->sql_query($sql);
			$row = phpbb::$db->sql_fetchrow($result);
			phpbb::$db->sql_freeresult($result);

			if ((int) $row['sessions'] <= 1 || empty($this->data['user_form_salt']))
			{
				$this->data['user_form_salt'] = phpbb::$security->unique_id();

				// Update the form key
				$sql = 'UPDATE ' . USERS_TABLE . '
					SET user_form_salt = \'' . phpbb::$db->sql_escape($this->data['user_form_salt']) . '\'
					WHERE user_id = ' . (int) $this->data['user_id'];
				phpbb::$db->sql_query($sql);
			}
		}
		else
		{
			$this->data['session_time'] = $this->data['session_last_visit'] = $this->time_now;

			// Update the last visit time
			$sql = 'UPDATE ' . USERS_TABLE . '
				SET user_lastvisit = ' . (int) $this->data['session_time'] . '
				WHERE user_id = ' . (int) $this->data['user_id'];
			phpbb::$db->sql_query($sql);

			$SID = '?sid=';
			$_SID = '';
		}

		return true;
	}

	/**
	* Kills a session
	*
	* This method does what it says on the tin. It will delete a pre-existing session.
	* It resets cookie information (destroying any autologin key within that cookie data)
	* and update the users information from the relevant session data. It will then
	* grab guest user information.
	*/
	public function session_kill($new_session = true)
	{
		global $SID, $_SID;

		$sql = 'DELETE FROM ' . SESSIONS_TABLE . "
			WHERE session_id = '" . phpbb::$db->sql_escape($this->session_id) . "'
				AND session_user_id = " . (int) $this->data['user_id'];
		phpbb::$db->sql_query($sql);

		// Allow connecting logout with external auth method logout
		if (method_exists($this->auth, 'logout'))
		{
			$this->auth->logout($this, $new_session);
		}

		if ($this->data['user_id'] != ANONYMOUS)
		{
			// Delete existing session, update last visit info first!
			if (!isset($this->data['session_time']))
			{
				$this->data['session_time'] = time();
			}

			$sql = 'UPDATE ' . USERS_TABLE . '
				SET user_lastvisit = ' . (int) $this->data['session_time'] . '
				WHERE user_id = ' . (int) $this->data['user_id'];
			phpbb::$db->sql_query($sql);

			if ($this->cookie_data['k'])
			{
				$sql = 'DELETE FROM ' . SESSIONS_KEYS_TABLE . '
					WHERE user_id = ' . (int) $this->data['user_id'] . "
						AND key_id = '" . phpbb::$db->sql_escape(md5($this->cookie_data['k'])) . "'";
				phpbb::$db->sql_query($sql);
			}

			// Reset the data array
			$this->data = array();

			$sql = 'SELECT *
				FROM ' . USERS_TABLE . '
				WHERE user_id = ' . ANONYMOUS;
			$result = phpbb::$db->sql_query($sql);
			$this->data = phpbb::$db->sql_fetchrow($result);
			phpbb::$db->sql_freeresult($result);
		}

		$cookie_expire = $this->time_now - 31536000;
		$this->set_cookie('u', '', $cookie_expire);
		$this->set_cookie('k', '', $cookie_expire);
		$this->set_cookie('sid', '', $cookie_expire);
		unset($cookie_expire);

		$SID = '?sid=';
		$this->session_id = $_SID = '';

		// To make sure a valid session is created we create one for the anonymous user
		if ($new_session)
		{
			$this->session_create(ANONYMOUS);
		}

		return true;
	}

	/**
	* Session garbage collection
	*
	* This looks a lot more complex than it really is. Effectively we are
	* deleting any sessions older than an admin definable limit. Due to the
	* way in which we maintain session data we have to ensure we update user
	* data before those sessions are destroyed. In addition this method
	* removes autologin key information that is older than an admin defined
	* limit.
	*/
	public function session_gc()
	{
		$batch_size = 10;

		if (!$this->time_now)
		{
			$this->time_now = time();
		}

		// Firstly, delete guest sessions
		$sql = 'DELETE FROM ' . SESSIONS_TABLE . '
			WHERE session_user_id = ' . ANONYMOUS . '
				AND session_time < ' . (int) ($this->time_now - phpbb::$config['session_length']);
		phpbb::$db->sql_query($sql);

		// Get expired sessions, only most recent for each user
		$sql = 'SELECT session_user_id, session_page, MAX(session_time) AS recent_time
			FROM ' . SESSIONS_TABLE . '
			WHERE session_time < ' . ($this->time_now - phpbb::$config['session_length']) . '
			GROUP BY session_user_id, session_page';
		$result = phpbb::$db->sql_query_limit($sql, $batch_size);

		$del_user_id = array();
		$del_sessions = 0;

		while ($row = phpbb::$db->sql_fetchrow($result))
		{
			$sql = 'UPDATE ' . USERS_TABLE . '
				SET user_lastvisit = ' . (int) $row['recent_time'] . ", user_lastpage = '" . phpbb::$db->sql_escape($row['session_page']) . "'
				WHERE user_id = " . (int) $row['session_user_id'];
			phpbb::$db->sql_query($sql);

			$del_user_id[] = (int) $row['session_user_id'];
			$del_sessions++;
		}
		phpbb::$db->sql_freeresult($result);

		if (sizeof($del_user_id))
		{
			// Delete expired sessions
			$sql = 'DELETE FROM ' . SESSIONS_TABLE . '
				WHERE ' . phpbb::$db->sql_in_set('session_user_id', $del_user_id) . '
					AND session_time < ' . ($this->time_now - phpbb::$config['session_length']);
			phpbb::$db->sql_query($sql);
		}

		if ($del_sessions < $batch_size)
		{
			// Less than 10 users, update gc timer ... else we want gc
			// called again to delete other sessions
			set_config('session_last_gc', $this->time_now, true);

			if (phpbb::$config['max_autologin_time'])
			{
				$sql = 'DELETE FROM ' . SESSIONS_KEYS_TABLE . '
					WHERE last_login < ' . (time() - (86400 * (int) phpbb::$config['max_autologin_time']));
				phpbb::$db->sql_query($sql);
			}

			// only called from CRON; should be a safe workaround until the infrastructure gets going
			if (!class_exists('captcha_factory'))
			{
				include(PHPBB_ROOT_PATH . "includes/captcha/captcha_factory." . PHP_EXT);
			}
			captcha_factory::garbage_collect(phpbb::$config['captcha_plugin']);
		}

		return;
	}


	/**
	* Sets a cookie
	*
	* Sets a cookie of the given name with the specified data for the given length of time. If no time is specified, a session cookie will be set.
	*
	* @param string $name		Name of the cookie, will be automatically prefixed with the phpBB cookie name. track becomes [cookie_name]_track then.
	* @param string $cookiedata	The data to hold within the cookie
	* @param int $cookietime	The expiration time as UNIX timestamp. If 0 is provided, a session cookie is set.
	*/
	public function set_cookie($name, $cookiedata, $cookietime)
	{
		$name_data = rawurlencode(phpbb::$config['cookie_name'] . '_' . $name) . '=' . rawurlencode($cookiedata);
		$expire = gmdate('D, d-M-Y H:i:s \\G\\M\\T', $cookietime);
		$domain = (!phpbb::$config['cookie_domain'] || phpbb::$config['cookie_domain'] == 'localhost' || phpbb::$config['cookie_domain'] == '127.0.0.1') ? '' : '; domain=' . phpbb::$config['cookie_domain'];

		header('Set-Cookie: ' . $name_data . (($cookietime) ? '; expires=' . $expire : '') . '; path=' . phpbb::$config['cookie_path'] . $domain . ((!phpbb::$config['cookie_secure']) ? '' : '; secure') . '; HttpOnly', false);
	}

	/**
	* Check for banned user
	*
	* Checks whether the supplied user is banned by id, ip or email. If no parameters
	* are passed to the method pre-existing session data is used. If $return is false
	* this routine does not return on finding a banned user, it outputs a relevant
	* message and stops execution.
	*
	* @param string|array	$user_ips	Can contain a string with one IP or an array of multiple IPs
	*/
	public function check_ban($user_id = false, $user_ips = false, $user_email = false, $return = false)
	{
		if (defined('IN_CHECK_BAN'))
		{
			return;
		}

		$banned = false;
		$cache_ttl = 3600;
		$where_sql = array();

		$sql = 'SELECT ban_ip, ban_userid, ban_email, ban_exclude, ban_give_reason, ban_end
			FROM ' . BANLIST_TABLE . '
			WHERE ';

		// Determine which entries to check, only return those
		if ($user_email === false)
		{
			$where_sql[] = "ban_email = ''";
		}

		if ($user_ips === false)
		{
			$where_sql[] = "(ban_ip = '' OR ban_exclude = 1)";
		}

		if ($user_id === false)
		{
			$where_sql[] = '(ban_userid = 0 OR ban_exclude = 1)';
		}
		else
		{
			$cache_ttl = ($user_id == ANONYMOUS) ? 3600 : 0;
			$_sql = '(ban_userid = ' . $user_id;

			if ($user_email !== false)
			{
				$_sql .= " OR ban_email <> ''";
			}

			if ($user_ips !== false)
			{
				$_sql .= " OR ban_ip <> ''";
			}

			$_sql .= ')';

			$where_sql[] = $_sql;
		}

		$sql .= (sizeof($where_sql)) ? implode(' AND ', $where_sql) : '';
		$result = phpbb::$db->sql_query($sql, $cache_ttl);

		$ban_triggered_by = 'user';
		while ($row = phpbb::$db->sql_fetchrow($result))
		{
			if ($row['ban_end'] && $row['ban_end'] < time())
			{
				continue;
			}

			$ip_banned = false;
			if (!empty($row['ban_ip']))
			{
				if (!is_array($user_ips))
				{
					$ip_banned = preg_match('#^' . str_replace('\*', '.*?', preg_quote($row['ban_ip'], '#')) . '$#i', $user_ips);
				}
				else
				{
					foreach ($user_ips as $user_ip)
					{
						if (preg_match('#^' . str_replace('\*', '.*?', preg_quote($row['ban_ip'], '#')) . '$#i', $user_ip))
						{
							$ip_banned = true;
							break;
						}
					}
				}
			}

			if ((!empty($row['ban_userid']) && intval($row['ban_userid']) == $user_id) ||
				$ip_banned ||
				(!empty($row['ban_email']) && preg_match('#^' . str_replace('\*', '.*?', preg_quote($row['ban_email'], '#')) . '$#i', $user_email)))
			{
				if (!empty($row['ban_exclude']))
				{
					$banned = false;
					break;
				}
				else
				{
					$banned = true;
					$ban_row = $row;

					if (!empty($row['ban_userid']) && intval($row['ban_userid']) == $user_id)
					{
						$ban_triggered_by = 'user';
					}
					else if ($ip_banned)
					{
						$ban_triggered_by = 'ip';
					}
					else
					{
						$ban_triggered_by = 'email';
					}

					// Don't break. Check if there is an exclude rule for this user
				}
			}
		}
		phpbb::$db->sql_freeresult($result);

		if ($banned && !$return)
		{
			// If the session is empty we need to create a valid one...
			if (empty($this->session_id))
			{
				// This seems to be no longer needed? - #14971
//				$this->session_create(ANONYMOUS);
			}

			// Initiate environment ... since it won't be set at this stage
			$this->setup();

			// Logout the user, banned users are unable to use the normal 'logout' link
			if ($this->data['user_id'] != ANONYMOUS)
			{
				$this->session_kill();
			}

			// We show a login box here to allow founders accessing the board if banned by IP
			if (defined('IN_LOGIN') && $this->data['user_id'] == ANONYMOUS)
			{
				$this->setup('ucp');
				$this->is_registered = $this->is_bot = false;

				// Set as a precaution to allow login_box() handling this case correctly as well as this function not being executed again.
				define('IN_CHECK_BAN', 1);

				login_box('index.' . PHP_EXT);

				// The false here is needed, else the user is able to circumvent the ban.
				$this->session_kill(false);
			}

			// Ok, we catch the case of an empty session id for the anonymous user...
			// This can happen if the user is logging in, banned by username and the login_box() being called "again".
			if (empty($this->session_id) && defined('IN_CHECK_BAN'))
			{
				$this->session_create(ANONYMOUS);
			}


			// Determine which message to output
			$till_date = ($ban_row['ban_end']) ? $this->format_date($ban_row['ban_end']) : '';
			$message = ($ban_row['ban_end']) ? 'BOARD_BAN_TIME' : 'BOARD_BAN_PERM';

			$message = sprintf($this->lang[$message], $till_date, '<a href="mailto:' . phpbb::$config['board_contact'] . '">', '</a>');
			$message .= ($ban_row['ban_give_reason']) ? '<br /><br />' . sprintf($this->lang['BOARD_BAN_REASON'], $ban_row['ban_give_reason']) : '';
			$message .= '<br /><br /><em>' . $this->lang['BAN_TRIGGERED_BY_' . strtoupper($ban_triggered_by)] . '</em>';

			// To circumvent session_begin returning a valid value and the check_ban() not called on second page view, we kill the session again
			$this->session_kill(false);

			// A very special case... we are within the cron script which is not supposed to print out the ban message... show blank page
			if (defined('IN_CRON'))
			{
				garbage_collection();
				exit_handler();
				exit;
			}

			trigger_error($message);
		}

		return ($banned && $ban_row['ban_give_reason']) ? $ban_row['ban_give_reason'] : $banned;
	}

	/**
	* Check if ip is blacklisted
	* This should be called only where absolutly necessary
	*
	* Only IPv4 (rbldns does not support AAAA records/IPv6 lookups)
	*
	* @author satmd (from the php manual)
	* @param string $mode register/post - spamcop for example is ommitted for posting
	* @return false if ip is not blacklisted, else an array([checked server], [lookup])
	*/
	public function check_dnsbl($mode, $ip = false)
	{
		if ($ip === false)
		{
			$ip = $this->server['ip'];
		}

		$dnsbl_check = array(
			'sbl-xbl.spamhaus.org'	=> 'http://www.spamhaus.org/query/bl?ip=',
		);

		if ($mode == 'register')
		{
			$dnsbl_check['bl.spamcop.net'] = 'http://spamcop.net/bl.shtml?';
		}

		if ($ip)
		{
			$quads = explode('.', $ip);
			$reverse_ip = $quads[3] . '.' . $quads[2] . '.' . $quads[1] . '.' . $quads[0];

			// Need to be listed on all servers...
			$listed = true;
			$info = array();

			foreach ($dnsbl_check as $dnsbl => $lookup)
			{
				if (phpbb_checkdnsrr($reverse_ip . '.' . $dnsbl . '.', 'A') === true)
				{
					$info = array($dnsbl, $lookup . $ip);
				}
				else
				{
					$listed = false;
				}
			}

			if ($listed)
			{
				return $info;
			}
		}

		return false;
	}

	/**
	* Set/Update a persistent login key
	*
	* This method creates or updates a persistent session key. When a user makes
	* use of persistent (formerly auto-) logins a key is generated and stored in the
	* DB. When they revisit with the same key it's automatically updated in both the
	* DB and cookie. Multiple keys may exist for each user representing different
	* browsers or locations. As with _any_ non-secure-socket no passphrase login this
	* remains vulnerable to exploit.
	*/
	public function set_login_key($user_id = false, $key = false, $user_ip = false)
	{
		$user_id = ($user_id === false) ? $this->data['user_id'] : $user_id;
		$user_ip = ($user_ip === false) ? $this->server['ip'] : $user_ip;
		$key = ($key === false) ? (($this->cookie_data['k']) ? $this->cookie_data['k'] : false) : $key;

		$key_id = phpbb::$security->unique_id(hexdec(substr($this->session_id, 0, 8)));

		$sql_ary = array(
			'key_id'		=> (string) md5($key_id),
			'last_ip'		=> (string) $this->server['ip'],
			'last_login'	=> (int) $this->time_now,
		);

		if (!$key)
		{
			$sql_ary += array(
				'user_id'	=> (int) $user_id
			);
		}

		if ($key)
		{
			$sql = 'UPDATE ' . SESSIONS_KEYS_TABLE . '
				SET ' . phpbb::$db->sql_build_array('UPDATE', $sql_ary) . '
				WHERE user_id = ' . (int) $user_id . "
					AND key_id = '" . phpbb::$db->sql_escape(md5($key)) . "'";
		}
		else
		{
			$sql = 'INSERT INTO ' . SESSIONS_KEYS_TABLE . ' ' . phpbb::$db->sql_build_array('INSERT', $sql_ary);
		}
		phpbb::$db->sql_query($sql);

		$this->cookie_data['k'] = $key_id;

		return false;
	}

	/**
	* Reset all login keys for the specified user
	*
	* This method removes all current login keys for a specified (or the current)
	* user. It will be called on password change to render old keys unusable
	*/
	public function reset_login_keys($user_id = false)
	{
		$user_id = ($user_id === false) ? $this->data['user_id'] : $user_id;

		$sql = 'DELETE FROM ' . SESSIONS_KEYS_TABLE . '
			WHERE user_id = ' . (int) $user_id;
		phpbb::$db->sql_query($sql);

		// Let's also clear any current sessions for the specified user_id
		// If it's the current user then we'll leave this session intact
		$sql_where = 'session_user_id = ' . (int) $user_id;
		$sql_where .= ($user_id === $this->data['user_id']) ? " AND session_id <> '" . phpbb::$db->sql_escape($this->session_id) . "'" : '';

		$sql = 'DELETE FROM ' . SESSIONS_TABLE . "
			WHERE $sql_where";
		phpbb::$db->sql_query($sql);

		// We're changing the password of the current user and they have a key
		// Lets regenerate it to be safe
		if ($user_id === $this->data['user_id'] && $this->cookie_data['k'])
		{
			$this->set_login_key($user_id);
		}
	}

	/**
	* Reset all admin sessions
	*/
	function unset_admin()
	{
		$sql = 'UPDATE ' . SESSIONS_TABLE . '
			SET session_admin = 0
			WHERE session_id = \'' . phpbb::$db->sql_escape($this->session_id) . '\'';
		phpbb::$db->sql_query($sql);
	}

	/**
	* Check if a valid, non-expired session exist. Also make sure it errors out correctly if we do not have a db-setup yet. ;)
	*/
	private function session_exist()
	{
		// If session is empty or does not match the session within the URL (if required - set by NEED_SID), then we need a new session
		if (empty($this->session_id) || (defined('NEED_SID') && $this->session_id !== request::variable('sid', '', false, request::GET)))
		{
			return false;
		}

		// If the db is not initialized/registered, then we also need a new session (we are not able to go forward then...
		if (!phpbb::registered('db'))
		{
			return false;
		}

		// Now finally check the db for our provided session
		$sql = 'SELECT u.*, s.*
			FROM ' . SESSIONS_TABLE . ' s, ' . USERS_TABLE . " u
			WHERE s.session_id = '" . phpbb::$db->sql_escape($this->session_id) . "'
				AND u.user_id = s.session_user_id";
		$result = phpbb::$db->sql_query($sql);
		$row = phpbb::$db->sql_fetchrow($result);
		phpbb::$db->sql_freeresult($result);

		// Also new session if it has not been found. ;)
		if (!$row)
		{
			return false;
		}

		$this->data = $row;

		// Now check the ip, the browser, forwarded for and referer
		if (!$this->session_valid())
		{
			return false;
		}

		// Ok, we are not finished yet. We need to know if the session is expired

		// Check whether the session is still valid if we have one
		if (method_exists($this->auth, 'validate_session'))
		{
			if (!$this->auth->validate_session($this))
			{
				return false;
			}
		}

		// Check the session length timeframe if autologin is not enabled.
		// Else check the autologin length... and also removing those having autologin enabled but no longer allowed board-wide.
		if (!$this->data['session_autologin'])
		{
			if ($this->data['session_time'] < $this->time_now - (phpbb::$config['session_length'] + 60))
			{
				return false;
			}
		}
		else if (!phpbb::$config['allow_autologin'] || (phpbb::$config['max_autologin_time'] && $this->data['session_time'] < $this->time_now - (86400 * (int) phpbb::$config['max_autologin_time']) + 60))
		{
			return false;
		}

		// Only update session DB a minute or so after last update or if page changes
		if ($this->time_now - $this->data['session_time'] > 60 || ($this->update_session_page && $this->data['session_page'] != $this->server['page']['page']))
		{
			$sql_ary = array('session_time' => $this->time_now);

			if ($this->update_session_page)
			{
				$sql_ary['session_page'] = substr($this->server['page']['page'], 0, 199);
				$sql_ary['session_forum_id'] = $this->server['page']['forum'];
			}

			$sql = 'UPDATE ' . SESSIONS_TABLE . ' SET ' . phpbb::$db->sql_build_array('UPDATE', $sql_ary) . "
				WHERE session_id = '" . phpbb::$db->sql_escape($this->session_id) . "'";
			phpbb::$db->sql_query($sql);
		}

		$this->is_registered = ($this->data['user_id'] != ANONYMOUS && ($this->data['user_type'] == USER_NORMAL || $this->data['user_type'] == USER_FOUNDER)) ? true : false;
		$this->is_bot = (!$this->is_registered && $this->data['user_id'] != ANONYMOUS) ? true : false;
		$this->data['user_lang'] = basename($this->data['user_lang']);

		return true;
	}

	/**
	* Check if the request originated from the same page.
	* @param bool $check_script_path If true, the path will be checked as well
	*/
	private function validate_referer($check_script_path = false)
	{
		// no referer - nothing to validate, user's fault for turning it off (we only check on POST; so meta can't be the reason)
		if (empty($this->server['referer']) || empty($this->server['host']))
		{
			return true;
		}

		// Specialchar host, because it's the only one not specialchared
		$host = htmlspecialchars($this->server['host']);
		$ref = substr($this->server['referer'], strpos($this->server['referer'], '://') + 3);

		if (!(stripos($ref, $host) === 0))
		{
			return false;
		}
		else if ($check_script_path && rtrim($this->server['page']['root_script_path'], '/') !== '')
		{
			$ref = substr($ref, strlen($host));
			$server_port = $this->server['port'];

			if ($server_port !== 80 && $server_port !== 443 && stripos($ref, ":$server_port") === 0)
			{
				$ref = substr($ref, strlen(":$server_port"));
			}

			if (!(stripos(rtrim($ref, '/'), rtrim($this->server['page']['root_script_path'], '/')) === 0))
			{
				return false;
			}
		}

		return true;
	}

	/**
	* Fill data array with a "faked" user account
	*/
	private function default_data()
	{
		return array(
			'user_id'		=> ANONYMOUS,
		);
	}

	/**
	* Check for a bot by comparing user agent and ip
	*/
	private function check_bot()
	{
		$bot = false;

		foreach (phpbb_cache::obtain_bots() as $row)
		{
			if ($row['bot_agent'] && preg_match('#' . str_replace('\*', '.*?', preg_quote($row['bot_agent'], '#')) . '#i', $this->server['browser']))
			{
				$bot = $row['user_id'];
			}

			// If ip is supplied, we will make sure the ip is matching too...
			if ($row['bot_ip'] && ($bot || !$row['bot_agent']))
			{
				// Set bot to false, then we only have to set it to true if it is matching
				$bot = false;

				foreach (explode(',', $row['bot_ip']) as $bot_ip)
				{
					if (strpos($this->server['ip'], $bot_ip) === 0)
					{
						$bot = (int) $row['user_id'];
						break;
					}
				}
			}

			if ($bot)
			{
				break;
			}
		}

		return $bot;
	}

	/**
	* Check if session is valid by comparing ip, forwarded for, browser and referer
	*/
	private function session_valid($log_failure = true)
	{
		// Validate IP length according to admin ... enforces an IP
		// check on bots if admin requires this
//		$quadcheck = (phpbb::$config['ip_check_bot'] && $this->data['user_type'] & USER_BOT) ? 4 : phpbb::$config['ip_check'];

		if (strpos($this->server['ip'], ':') !== false && strpos($this->data['session_ip'], ':') !== false)
		{
			$session_ip = short_ipv6($this->data['session_ip'], phpbb::$config['ip_check']);
			$user_ip = short_ipv6($this->server['ip'], phpbb::$config['ip_check']);
		}
		else
		{
			$session_ip = implode('.', array_slice(explode('.', $this->data['session_ip']), 0, phpbb::$config['ip_check']));
			$user_ip = implode('.', array_slice(explode('.', $this->server['ip']), 0, phpbb::$config['ip_check']));
		}

		$session_browser = (phpbb::$config['browser_check']) ? trim(strtolower(substr($this->data['session_browser'], 0, 149))) : '';
		$user_browser = (phpbb::$config['browser_check']) ? trim(strtolower(substr($this->server['browser'], 0, 149))) : '';

		$session_forwarded_for = (phpbb::$config['forwarded_for_check']) ? substr($this->data['session_forwarded_for'], 0, 254) : '';
		$user_forwarded_for = (phpbb::$config['forwarded_for_check']) ? substr($this->server['forwarded_for'], 0, 254) : '';

		// referer checks
		$check_referer_path = phpbb::$config['referer_validation'] == REFERER_VALIDATE_PATH;
		$referer_valid = true;

		// we assume HEAD and TRACE to be foul play and thus only whitelist GET
		if (phpbb::$config['referer_validation'] && $this->server['request_method'])
		{
			$referer_valid = $this->validate_referer($check_referer_path);
		}

		if ($user_ip !== $session_ip || $user_browser !== $session_browser || $user_forwarded_for !== $session_forwarded_for || !$referer_valid)
		{
			// Added logging temporarly to help debug bugs...
			if (defined('DEBUG_EXTRA') && $this->data['user_id'] != ANONYMOUS && $log_failure)
			{
				if ($referer_valid)
				{
					add_log('critical', 'LOG_IP_BROWSER_FORWARDED_CHECK', $user_ip, $session_ip, $user_browser, $session_browser, htmlspecialchars($user_forwarded_for), htmlspecialchars($session_forwarded_for));
				}
				else
				{
					add_log('critical', 'LOG_REFERER_INVALID', $this->server['referer']);
				}
			}

			return false;
		}

		return true;
	}
}

?>