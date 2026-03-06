<?php
/**
 * Fuel is a fast, lightweight, community driven PHP 5.4+ framework.
 *
 * @package    Fuel
 * @version    1.8.2
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2019 Fuel Development Team
 * @link       https://fuelphp.com
 */

namespace Fuel\Core;

// ------------------------------------------------------------------------

/**
* Html Class
 *
 * @package		Fuel
 * @subpackage	Core
 * @category	Core
 * @author		Alfredo Rivera
 * @link		http://docs.fuelphp.com/classes/html.html
 */
class Html
{
	public static $doctypes;
	public static $html5 = true;

	/**
	 * Creates an html link
	 *
	 * @param	string	$href	the url
	 * @param	string	$text	the text value
	 * @param	array	$attr	the attributes array
	 * @param	bool	$secure	true to force https, false to force http
	 * @return	string	the html link
	 */
	public static function anchor($href, $text = null, array $attr = [], $secure = null)
	{
		if ( ! preg_match('#^(\w+://|javascript:|\#)# i', $href))
		{
			$urlparts = explode('?', $href, 2);
			$href = \Uri::create($urlparts[0], [], $urlparts[1] ?? [], $secure);
		}
		elseif ( ! preg_match('#^(javascript:|\#)# i', $href) and is_bool($secure))
		{
			$href = http_build_url($href, ['scheme' => $secure ? 'https' : 'http']);

			// Trim the trailing slash
			$href = rtrim($href, '/');
		}

		// Create and display a URL hyperlink
		is_null($text) and $text = $href;

		$attr['href'] = $href;

		return html_tag('a', $attr, $text);
	}

	/**
	 * Creates an html image tag
	 *
	 * Sets the alt attribute to filename of it is not supplied.
	 *
	 * @param	string	$src	the source
	 * @param	array	$attr	the attributes array
	 * @return	string	the image tag
	 */
	public static function img($src, array $attr = [])
	{
		if ( ! preg_match('#^(\w+://)# i', $src))
		{
			$src = \Uri::base(false).$src;
		}
		$attr['src'] = $src;
		$attr['alt'] ??= pathinfo($src, PATHINFO_FILENAME);
		return html_tag('img', $attr);
	}

	/**
	 * Adds the given schema to the given URL if it is not already there.
	 *
	 * @param	string	$url	the url
	 * @param	string	$schema	the schema
	 * @return	string	url with schema
	 */
	public static function prep_url(string $url, string $schema = 'http'): string
	{
		if ( ! preg_match('#^(\w+://|javascript:)# i', $url))
		{
			return $schema.'://'.$url;
		}

		return $url;
	}

	/**
	 * Creates a mailto link.
	 *
	 * @param	string	$email		The email address
	 * @param	string	$text		The text value
	 * @param	string	$subject	The subject
	 * @param	array	$attr		attributes for the tag
	 * @return	string	The mailto link
	 */
	public static function mail_to(string $email, $text = null, $subject = null, $attr = [])
	{
		$text or $text = $email;

		$subject and $subject = '?subject='.$subject;

		return html_tag('a', [
			'href' => 'mailto:'.$email.$subject,
		] + $attr, $text);
	}

	/**
	 * Creates a mailto link with Javascript to prevent bots from picking up the
	 * email address.
	 *
	 * @param	string	$email		the email address
	 * @param	string	$text		the text value
	 * @param	string	$subject	the subject
	 * @param	array	$attr		attributes for the tag
	 * @return	string	the javascript code containing email
	 */
	public static function mail_to_safe($email, $text = null, $subject = null, $attr = []): string
	{
		$text or $text = str_replace('@', '[at]', $email);

		$email = explode("@", $email);

		$subject and $subject = '?subject='.$subject;

		$attr = array_to_attr($attr);
		$attr = ($attr == '' ? '' : ' ').$attr;

		$output = '<script type="text/javascript">';
		$output .= '(function() {';
		$output .= 'var user = "'.$email[0].'";';
		$output .= 'var at = "@";';
		$output .= 'var server = "'.$email[1].'";';
		$output .= "document.write('<a href=\"' + 'mail' + 'to:' + user + at + server + '$subject\"$attr>$text</a>');";
		$output .= '})();';
		return $output . '</script>';
	}

	/**
	 * Generates a html meta tag
	 *
	 * @param	string|array	$name		multiple inputs or name/http-equiv value
	 * @param	string			$content	content value
	 * @param	string			$type		name or http-equiv
	 * @return	string
	 */
	public static function meta($name = '', $content = '', $type = 'name')
	{
		if( ! is_array($name))
		{
			$result = html_tag('meta', [$type => $name, 'content' => $content]);
		}
		elseif(is_array($name))
		{
			$result = "";
			foreach($name as $array)
			{
				$meta = $array;
				$result .= "\n" . html_tag('meta', $meta);
			}
		}
		return $result;
	}

	/**
	 * Generates a html doctype tag
	 *
	 * @param	string	$type	doctype declaration key from doctypes config
	 * @return	string
	 */
	public static function doctype($type = 'xhtml1-trans')
	{
		if(static::$doctypes === null)
		{
			\Config::load('doctypes', true);
			static::$doctypes = \Config::get('doctypes', []);
		}

		if(is_array(static::$doctypes) and isset(static::$doctypes[$type]))
		{
			if($type == "html5")
			{
				static::$html5 = true;
			}
			return static::$doctypes[$type];
		}
        return false;
	}

	/**
	 * Generates a html5 audio tag
	 * It is required that you set html5 as the doctype to use this method
	 *
	 * @param	string|array	$src	one or multiple audio sources
	 * @param	array			$attr	tag attributes
	 * @return	string
	 */
	public static function audio($src = '', $attr = false)
	{
		if(static::$html5)
		{
			if(is_array($src))
			{
				$source = '';
				foreach($src as $item)
				{
					$source .= html_tag('source', ['src' => $item]);
				}
			}
			else
			{
				$source = html_tag('source', ['src' => $src]);
			}
			return html_tag('audio', $attr, $source);
		}
	}

	/**
	 * Generates a html un-ordered list tag
	 *
	 * @param	array			$list	list items, may be nested
	 * @param	array|string	$attr	outer list attributes
	 * @return	string
	 */
	public static function ul(array $list = [], $attr = false)
	{
		return static::build_list('ul', $list, $attr);
	}

	/**
	 * Generates a html ordered list tag
	 *
	 * @param	array			$list	list items, may be nested
	 * @param	array|string	$attr	outer list attributes
	 * @return	string
	 */
	public static function ol(array $list = [], $attr = false)
	{
		return static::build_list('ol', $list, $attr);
	}

	/**
     * Generates the html for the list methods
     *
     * @param	string	$type	list type (ol or ul)
     * @param	array	$list	list items, may be nested
     * @param	array	$attr	tag attributes
     * @param	string	$indent	indentation
     */
    protected static function build_list($type = 'ul', array $list = [], $attr = false, string $indent = ''): string
	{
		if ( ! is_array($list))
		{
			$result = false;
		}

		$out = '';
		foreach ($list as $key => $val)
		{
			if ( ! is_array($val))
			{
				$out .= $indent."\t".html_tag('li', [], $val).PHP_EOL;
			}
			else
			{
				$out .= $indent."\t".html_tag('li', [], $key.PHP_EOL.static::build_list($type, $val, '', $indent."\t\t").$indent."\t").PHP_EOL;
			}
		}
		return $indent.html_tag($type, $attr, PHP_EOL.$out.$indent).PHP_EOL;
	}
}
