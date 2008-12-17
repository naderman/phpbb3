<?php
/**
*
* @package acm
* @version $Id$
* @copyright (c) 2005, 2008 phpBB Group
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
* Class for obtaining cached entries, for example censor word list, configuration...
*
* @package acm
*/
class phpbb_cache
{
	/**
	* We do not want this object instantiable
	*/
	private function ___construct() { }

	public $phpbb_required = array('config', 'acm', 'db');

	/**
	* Get config values
	*/
	public static function obtain_config()
	{
		if ((phpbb::$config = phpbb::$acm->get('#config')) !== false)
		{
			$sql = 'SELECT config_name, config_value
				FROM ' . CONFIG_TABLE . '
				WHERE is_dynamic = 1';
			$result = phpbb::$db->sql_query($sql);

			while ($row = phpbb::$db->sql_fetchrow($result))
			{
				phpbb::$config[$row['config_name']] = $row['config_value'];
			}
			phpbb::$db->sql_freeresult($result);
		}
		else
		{
			phpbb::$config = $cached_config = array();

			$sql = 'SELECT config_name, config_value, is_dynamic
				FROM ' . CONFIG_TABLE;
			$result = phpbb::$db->sql_query($sql);

			while ($row = phpbb::$db->sql_fetchrow($result))
			{
				if (!$row['is_dynamic'])
				{
					$cached_config[$row['config_name']] = $row['config_value'];
				}

				phpbb::$config[$row['config_name']] = $row['config_value'];
			}
			phpbb::$db->sql_freeresult($result);

			phpbb::$acm->put('#config', $cached_config);
		}

		return phpbb::$config;
	}

	/**
	* Obtain list of naughty words and build preg style replacement arrays for use by the
	* calling script
	*/
	public static function obtain_word_list()
	{
		if (($censors = phpbb::$acm->get('word_censors')) === false)
		{
			$sql = 'SELECT word, replacement
				FROM ' . WORDS_TABLE;
			$result = phpbb::$db->sql_query($sql);

			$censors = array();
			while ($row = phpbb::$db->sql_fetchrow($result))
			{
				$censors['match'][] = '#(?<!\w)(' . str_replace('\*', '\w*?', preg_quote($row['word'], '#')) . ')(?!\w)#i';
				$censors['replace'][] = $row['replacement'];
			}
			phpbb::$db->sql_freeresult($result);

			phpbb::$acm->put('word_censors', $censors);
		}

		return $censors;
	}

	/**
	* Obtain currently listed icons
	*/
	public static function obtain_icons()
	{
		if (($icons = phpbb::$acm->get('icons')) === false)
		{
			// Topic icons
			$sql = 'SELECT *
				FROM ' . ICONS_TABLE . '
				ORDER BY icons_order';
			$result = phpbb::$db->sql_query($sql);

			$icons = array();
			while ($row = phpbb::$db->sql_fetchrow($result))
			{
				$icons[$row['icons_id']]['img'] = $row['icons_url'];
				$icons[$row['icons_id']]['width'] = (int) $row['icons_width'];
				$icons[$row['icons_id']]['height'] = (int) $row['icons_height'];
				$icons[$row['icons_id']]['display'] = (bool) $row['display_on_posting'];
			}
			phpbb::$db->sql_freeresult($result);

			phpbb::$acm->put('icons', $icons);
		}

		return $icons;
	}

	/**
	* Obtain ranks
	*/
	public static function obtain_ranks()
	{
		if (($ranks = phpbb::$acm->get('ranks')) === false)
		{
			$sql = 'SELECT *
				FROM ' . RANKS_TABLE . '
				ORDER BY rank_min DESC';
			$result = phpbb::$db->sql_query($sql);

			$ranks = array();
			while ($row = phpbb::$db->sql_fetchrow($result))
			{
				if ($row['rank_special'])
				{
					$ranks['special'][$row['rank_id']] = array(
						'rank_title'	=>	$row['rank_title'],
						'rank_image'	=>	$row['rank_image']
					);
				}
				else
				{
					$ranks['normal'][] = array(
						'rank_title'	=>	$row['rank_title'],
						'rank_min'		=>	$row['rank_min'],
						'rank_image'	=>	$row['rank_image']
					);
				}
			}
			phpbb::$db->sql_freeresult($result);

			phpbb::$acm->put('ranks', $ranks);
		}

		return $ranks;
	}

	/**
	* Obtain allowed extensions
	*
	* @param mixed $forum_id If false then check for private messaging, if int then check for forum id. If true, then only return extension informations.
	*
	* @return array allowed extensions array.
	*/
	public static function obtain_attach_extensions($forum_id)
	{
		if (($extensions = phpbb::$acm->get('extensions')) === false)
		{
			$extensions = array(
				'_allowed_post'	=> array(),
				'_allowed_pm'	=> array(),
			);

			// The rule is to only allow those extensions defined. ;)
			$sql = 'SELECT e.extension, g.*
				FROM ' . EXTENSIONS_TABLE . ' e, ' . EXTENSION_GROUPS_TABLE . ' g
				WHERE e.group_id = g.group_id
					AND (g.allow_group = 1 OR g.allow_in_pm = 1)';
			$result = phpbb::$db->sql_query($sql);

			while ($row = phpbb::$db->sql_fetchrow($result))
			{
				$extension = strtolower(trim($row['extension']));

				$extensions[$extension] = array(
					'display_cat'	=> (int) $row['cat_id'],
					'download_mode'	=> (int) $row['download_mode'],
					'upload_icon'	=> trim($row['upload_icon']),
					'max_filesize'	=> (int) $row['max_filesize'],
					'allow_group'	=> $row['allow_group'],
					'allow_in_pm'	=> $row['allow_in_pm'],
				);

				$allowed_forums = ($row['allowed_forums']) ? unserialize(trim($row['allowed_forums'])) : array();

				// Store allowed extensions forum wise
				if ($row['allow_group'])
				{
					$extensions['_allowed_post'][$extension] = (!sizeof($allowed_forums)) ? 0 : $allowed_forums;
				}

				if ($row['allow_in_pm'])
				{
					$extensions['_allowed_pm'][$extension] = 0;
				}
			}
			phpbb::$db->sql_freeresult($result);

			phpbb::$acm->put('extensions', $extensions);
		}

		// Forum post
		if ($forum_id === false)
		{
			// We are checking for private messages, therefore we only need to get the pm extensions...
			$return = array('_allowed_' => array());

			foreach ($extensions['_allowed_pm'] as $extension => $check)
			{
				$return['_allowed_'][$extension] = 0;
				$return[$extension] = $extensions[$extension];
			}

			$extensions = $return;
		}
		else if ($forum_id === true)
		{
			return $extensions;
		}
		else
		{
			$forum_id = (int) $forum_id;
			$return = array('_allowed_' => array());

			foreach ($extensions['_allowed_post'] as $extension => $check)
			{
				// Check for allowed forums
				if (is_array($check))
				{
					$allowed = (!in_array($forum_id, $check)) ? false : true;
				}
				else
				{
					$allowed = true;
				}

				if ($allowed)
				{
					$return['_allowed_'][$extension] = 0;
					$return[$extension] = $extensions[$extension];
				}
			}

			$extensions = $return;
		}

		if (!isset($extensions['_allowed_']))
		{
			$extensions['_allowed_'] = array();
		}

		return $extensions;
	}

	/**
	* Obtain active bots
	*/
	public static function obtain_bots()
	{
		if (($bots = phpbb::$acm->get('bots')) === false)
		{
			// @todo We order by last visit date. This way we are able to safe some cycles by checking the most active ones first.
			$sql = 'SELECT user_id, bot_agent, bot_ip
				FROM ' . BOTS_TABLE . '
				WHERE bot_active = 1
				ORDER BY ' . phpbb::$db->sql_function('length_varchar', 'bot_agent') . 'DESC';
			$result = phpbb::$db->sql_query($sql);

			$bots = array();
			while ($row = phpbb::$db->sql_fetchrow($result))
			{
				$bots[] = $row;
			}
			phpbb::$db->sql_freeresult($result);

			phpbb::$acm->put('bots', $bots);
		}

		return $bots;
	}

	/**
	* Obtain cfg file data
	*
	* @param array $theme An array containing the path to the item
	*
	* @param string $item The specific item to get: 'theme', 'template', or 'imageset'
	*
	*/
	public static function obtain_cfg_item($theme, $item = 'theme')
	{
		$parsed_array = phpbb::$acm->get('cfg_' . $item . '_' . $theme[$item . '_path']);

		if ($parsed_array === false)
		{
			$parsed_array = array();
		}

		$reparse = false;
		$filename = PHPBB_ROOT_PATH . 'styles/' . $theme[$item . '_path'] . '/' . $item . '/' . $item . '.cfg';

		if (!file_exists($filename))
		{
			return $parsed_array;
		}

		if (!isset($parsed_array['filetime']) || ((phpbb::$config['load_tplcompile'] && @filemtime($filename) > $parsed_array['filetime'])))
		{
			$reparse = true;
		}

		// Re-parse cfg file
		if ($reparse)
		{
			$parsed_array = parse_cfg_file($filename);
			$parsed_array['filetime'] = @filemtime($filename);

			phpbb::$acm->put('cfg_' . $item . '_' . $theme[$item . '_path'], $parsed_array);
		}

		return $parsed_array;
	}

	/**
	* Obtain disallowed usernames
	*/
	public static function obtain_disallowed_usernames()
	{
		if (($usernames = phpbb::$acm->get('disallowed_usernames')) === false)
		{
			$sql = 'SELECT disallow_username
				FROM ' . DISALLOW_TABLE;
			$result = phpbb::$db->sql_query($sql);

			$usernames = array();
			while ($row = phpbb::$db->sql_fetchrow($result))
			{
				$usernames[] = str_replace('%', '.*?', preg_quote(utf8_clean_string($row['disallow_username']), '#'));
			}
			phpbb::$db->sql_freeresult($result);

			phpbb::$acm->put('disallowed_usernames', $usernames);
		}

		return $usernames;
	}

	/**
	* Obtain hooks...
	*/
	public static function obtain_hooks()
	{
		if (($hook_files = phpbb::$acm->get('hooks')) === false)
		{
			$hook_files = array();

			// Now search for hooks...
			$dh = @opendir(PHPBB_ROOT_PATH . 'includes/hooks/');

			if ($dh)
			{
				while (($file = readdir($dh)) !== false)
				{
					if (strpos($file, 'hook_') === 0 && substr($file, -(strlen(PHP_EXT) + 1)) === '.' . PHP_EXT)
					{
						$hook_files[] = substr($file, 0, -(strlen(PHP_EXT) + 1));
					}
				}
				closedir($dh);
			}

			phpbb::$acm->put('hooks', $hook_files);
		}

		return $hook_files;
	}
}

?>