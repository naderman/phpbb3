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
* Represents a text which has a formatted and
*
* @package phpbb_text
*/
class phpbb_text_formatting implements phpbb_text_formatting_interface
{
	protected $plaintext;
	protected $html;

	protected $server_url;

	/**
	* Creates a formatted text instance from plaintext
	*/
	public function __construct($plaintext, $server_url)
	{
		$this->plaintext = $plaintext;
		$this->server_url = $server_url;
		$this->html = null;
	}

	/**
	* {@inheritdoc}
	*/
	public function to_html()
	{
		if ($this->html === null)
		{
			$this->html = $this->make_clickable($this-plaintext, $this->server_url);
		}

		return $this->html;
	}

	/**
	* {@inheritdoc}
	*/
	public function to_text()
	{
		return $this->plaintext;
	}

	/**
	* make_clickable function
	*
	* Replace magic urls of form http://xxx.xxx., www.xxx. and xxx@xxx.xxx.
	* Cuts down displayed size of link if over 50 chars, turns absolute links
	* into relative versions when the server/script path matches the link
	*
	* @param string $text  The text to replace links in.
	* @param string $class A CSS class to add to resulting HTML anchors.
	*/
	public function make_clickable($text, $class = 'postlink')
	{
		$class_html = ($class) ? ' class="' . $class . '"' : '';
		$local_class_html = ($class) ? ' class="' . $class . '-local"' : '';

		$replacers = array(
			// relative urls for this board
			new phpbb_text_make_clickable_replacer(
				'#(^|[\n\t (>.])(' . preg_quote($this->server_url, '#') . ')/(' . get_preg_expression('relative_url_inline') . ')#i',
				MAGIC_URL_LOCAL,
				$local_class_html
			),

			// matches a xxxx://aaaaa.bbb.cccc. ...
			new phpbb_text_make_clickable_replacer(
				'#(^|[\n\t (>.])(' . get_preg_expression('url_inline') . ')#i',
				MAGIC_URL_FULL,
				$class_html
			),

			// matches a "www.xxxx.yyyy[/zzzz]" kinda lazy URL thing
			new phpbb_text_make_clickable_replacer(
				'#(^|[\n\t (>])(' . get_preg_expression('www_url_inline') . ')#i',
				MAGIC_URL_WWW,
				$class_html
			),

			// matches an email@domain type address at the start of a line, or after a space or after what might be a BBCode.
			new phpbb_text_make_clickable_replacer(
				'/(^|[\n\t (>])(' . get_preg_expression('email') . ')/i',
				MAGIC_URL_EMAIL,
				''
			),
		);

		foreach ($replacers as $replacer)
		{
			$text = $replacer->apply($text);
		}

		return $text;
	}
}

/**
* Internal class used for replacing URLs with HTML.
*
* @package phpbb_text
*/
class phpbb_text_make_clickable_replacer
{
	protected $pattern;
	protected $type;
	protected $extra_html;

	/**
	*
	* @param string $pattern
	* @param string $extra_html
	*/
	public function __construct($pattern, $type, $extra_html)
	{
		$this->pattern = $pattern;
		$this->type = $type;
		$this->extra_html = $extra_html;
	}

	/**
	* Applies this magic URL to the given text
	*
	* @param  string $text The raw input
	* @return string       Resulting HTML
	*/
	public function apply($text)
	{
		return preg_replace_callback($this->pattern, array($this, 'callback'), $text);
	}

	/**
	* A subroutine of make_clickable used with preg_replace
	* It places correct HTML around an url, shortens the displayed text
	* and makes sure no entities are inside URLs
	*/
	protected function callback($matches)
	{
		$type = $this->type;
		$whitespace = $matches[1];
		$url = $matches[2];
		$relative_url = isset($matches[3]) ? $matches[3] : '';
		$class = $this->extra_html;

		$orig_url       = $url;
		$orig_relative  = $relative_url;
		$append         = '';
		$url            = htmlspecialchars_decode($url);
		$relative_url   = htmlspecialchars_decode($relative_url);

		// make sure no HTML entities were matched
		$chars = array('<', '>', '"');
		$split = false;

		foreach ($chars as $char)
		{
			$next_split = strpos($url, $char);
			if ($next_split !== false)
			{
				$split = ($split !== false) ? min($split, $next_split) : $next_split;
			}
		}

		if ($split !== false)
		{
			// an HTML entity was found, so the URL has to end before it
			$append         = substr($url, $split) . $relative_url;
			$url            = substr($url, 0, $split);
			$relative_url   = '';
		}
		else if ($relative_url)
		{
			// same for $relative_url
			$split = false;
			foreach ($chars as $char)
			{
				$next_split = strpos($relative_url, $char);
				if ($next_split !== false)
				{
					$split = ($split !== false) ? min($split, $next_split) : $next_split;
				}
			}

			if ($split !== false)
			{
				$append         = substr($relative_url, $split);
				$relative_url   = substr($relative_url, 0, $split);
			}
		}

		// if the last character of the url is a punctuation mark, exclude it from the url
		$last_char = ($relative_url) ? $relative_url[strlen($relative_url) - 1] : $url[strlen($url) - 1];

		switch ($last_char)
		{
			case '.':
			case '?':
			case '!':
			case ':':
			case ',':
				$append = $last_char;
				if ($relative_url)
				{
					$relative_url = substr($relative_url, 0, -1);
				}
				else
				{
					$url = substr($url, 0, -1);
				}
			break;

			// set last_char to empty here, so the variable can be used later to
			// check whether a character was removed
			default:
				$last_char = '';
			break;
		}

		$short_url = (strlen($url) > 55) ? substr($url, 0, 39) . ' ... ' . substr($url, -10) : $url;

		switch ($type)
		{
			case MAGIC_URL_LOCAL:
				$tag            = 'l';
				$relative_url   = preg_replace('/[&?]sid=[0-9a-f]{32}$/', '', preg_replace('/([&?])sid=[0-9a-f]{32}&/', '$1', $relative_url));
				$url            = $url . '/' . $relative_url;
				$text           = $relative_url;

				// this url goes to http://domain.tld/path/to/board/ which
				// would result in an empty link if treated as local so
				// don't touch it and let MAGIC_URL_FULL take care of it.
				if (!$relative_url)
				{
					return $whitespace . $orig_url . '/' . $orig_relative; // slash is taken away by relative url pattern
				}
			break;

			case MAGIC_URL_FULL:
				$tag    = 'm';
				$text   = $short_url;
			break;

			case MAGIC_URL_WWW:
				$tag    = 'w';
				$url    = 'http://' . $url;
				$text   = $short_url;
			break;

			case MAGIC_URL_EMAIL:
				$tag    = 'e';
				$text   = $short_url;
				$url    = 'mailto:' . $url;
			break;
		}

		$url    = htmlspecialchars($url);
		$text   = htmlspecialchars($text);
		$append = htmlspecialchars($append);

		$html   = "$whitespace<!-- $tag --><a$class href=\"$url\">$text</a><!-- $tag -->$append";

		return $html;
	}
}
