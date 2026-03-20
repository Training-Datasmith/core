<?php

declare (strict_types=1);
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

class Security_Exception extends \DomainException
{
}
/**
 * Security Class
 *
 * @package		Fuel
 * @category	Core
 * @author		Dan Horrigan
 * @link		http://docs.fuelphp.com/classes/security.html
 */
class Security
{
    /**
     * @var  string  the token as submitted in the cookie from the previous request
     */
    protected static $csrf_old_token = false;
    /**
     * @var  string  the array key for cookie & post vars to check for the token
     */
    protected static $csrf_token_key = false;
    /**
     * @var  string  the token for the next request
     */
    protected static $csrf_token = false;
    /**
     * Class init
     *
     * Fetches CSRF settings and current token
     *
     * @throws SecurityException it the CSRF token validation failed
     * @throws FuelException if no security output filter is defined
     */
    public static function _init(): void
    {
        static::$csrf_token_key = \Config::get('security.csrf_token_key', 'fuel_csrf_token');
        static::$csrf_old_token = \Input::cookie(static::$csrf_token_key, false);
        // if csrf automatic checking is enabled, and it fails validation, bail out!
        if (\Config::get('security.csrf_autoload', false)) {
            $check_token_methods = \Config::get('security.csrf_autoload_methods', ['post', 'put', 'delete']);
            if (in_array(strtolower(\Input::method()), $check_token_methods) and !static::check_token()) {
                if (\Config::get('security.csrf_bad_request_on_fail', false)) {
                    throw new \Http_Bad_Request_Exception('CSRF validation failed, Possible hacking attempt detected!');
                }
                throw new \Security_Exception('CSRF validation failed, Possible hacking attempt detected!');
            }
        }
        // throw an exception if the output filter setting is missing from the app config
        if (\Config::get('security.output_filter') === null) {
            throw new \Fuel_Exception('There is no security.output_filter defined in your application config file');
        }
        // deal with duplicate filters, no need to slow the framework down
        foreach (['output_filter', 'uri_filter', 'input_filter'] as $setting) {
            $config = \Config::get('security.' . $setting, []);
            is_array($config) and \Config::set('security.' . $setting, \Arr::unique($config));
        }
    }
    /**
     * Cleans the request URI
     *
     * @param  string  $uri     uri to clean
     * @param  bool    $strict  whether to remove relative directories
     * @return array|mixed
     */
    public static function clean_uri($uri, $strict = false)
    {
        $filters = \Config::get('security.uri_filter', []);
        $filters = is_array($filters) ? $filters : [$filters];
        $strict and $uri = str_replace(['//', '../'], '/', $uri);
        return static::clean($uri, $filters);
    }
    /**
     * Cleans the global $_GET, $_POST and $_COOKIE arrays
     */
    public static function clean_input(): void
    {
        $_GET = static::clean($_GET);
        $_POST = static::clean($_POST);
        $_COOKIE = static::clean($_COOKIE);
    }
    /**
     * Generic variable clean method
     *
     * @param  mixed   $var
     * @param  mixed   $filters
     * @param  string  $type
     * @return array|mixed
     */
    public static function clean($var, $filters = null, $type = 'security.input_filter')
    {
        // deal with objects that can be sanitized
        if ($var instanceof \Sanitization) {
            $var->sanitize();
        } elseif (is_array($var) or $var instanceof \Traversable and $var instanceof \ArrayAccess) {
            // recurse on array values
            foreach ($var as $key => $value) {
                $var[$key] = static::clean($value, $filters, $type);
            }
        } else {
            is_null($filters) and $filters = \Config::get($type, []);
            $filters = is_array($filters) ? $filters : [$filters];
            foreach ($filters as $filter) {
                // is this filter a callable local function?
                if (is_string($filter) and is_callable('static::' . $filter)) {
                    $var = static::$filter($var);
                } elseif (is_callable($filter)) {
                    $var = call_user_func($filter, $var);
                } else {
                    $var = preg_replace('#[' . $filter . ']#ui', '', (string) $var);
                }
            }
        }
        return $var;
    }
    public static function xss_clean($value, array $options = [], $spec = '')
    {
        if (!is_array($value)) {
            if (!function_exists('htmLawed')) {
                import('htmlawed/htmlawed', 'vendor');
            }
            return htm_lawed($value, array_merge(['safe' => 1, 'balanced' => 0], $options), $spec);
        }
        foreach ($value as $k => $v) {
            $value[$k] = static::xss_clean($v, $options, $spec);
        }
        return $value;
    }
    public static function strip_tags($value)
    {
        if (!is_array($value)) {
            $value = strip_tags((string) $value);
        } else {
            foreach ($value as $k => $v) {
                $value[$k] = static::strip_tags($v);
            }
        }
        return $value;
    }
    public static function htmlentities($value, $flags = null, $encoding = null, $double_encode = null)
    {
        static $already_cleaned = [];
        is_null($flags) and $flags = \Config::get('security.htmlentities_flags', ENT_QUOTES);
        is_null($encoding) and $encoding = \Fuel::$encoding;
        is_null($double_encode) and $double_encode = \Config::get('security.htmlentities_double_encode', false);
        // Nothing to escape for non-string scalars, or for already processed values
        if (is_null($value) or is_bool($value) or is_int($value) or is_float($value) or in_array($value, $already_cleaned, true)) {
            return $value;
        }
        if (is_string($value)) {
            $value = htmlentities($value, $flags, $encoding, $double_encode);
        } elseif (is_object($value) and $value instanceof \Sanitization) {
            $value->sanitize();
            return $value;
        } elseif (is_array($value) or $value instanceof \Iterator and $value instanceof \ArrayAccess) {
            // Add to $already_cleaned variable when object
            is_object($value) and $already_cleaned[] = $value;
            foreach ($value as $k => $v) {
                $value[$k] = static::htmlentities($v, $flags, $encoding, $double_encode);
            }
        } elseif ($value instanceof \Iterator or $value::class == 'stdClass') {
            // Add to $already_cleaned variable
            $already_cleaned[] = $value;
            foreach ($value as $k => $v) {
                $value->{$k} = static::htmlentities($v, $flags, $encoding, $double_encode);
            }
        } elseif (is_object($value)) {
            // Check if the object is whitelisted and return when that's the case
            foreach (\Config::get('security.whitelisted_classes', []) as $class) {
                if (is_a($value, $class)) {
                    // Add to $already_cleaned variable
                    $already_cleaned[] = $value;
                    return $value;
                }
            }
            // Throw exception when it wasn't whitelisted and can't be converted to String
            if (!method_exists($value, '__toString')) {
                throw new \RuntimeException('Object class "' . $value::class . '" could not be converted to string or ' . 'sanitized as ArrayAccess. Whitelist it in security.whitelisted_classes in app/config/config.php ' . 'to allow it to be passed unchecked.');
            }
            $value = static::htmlentities((string) $value, $flags, $encoding, $double_encode);
        }
        return $value;
    }
    /**
     * Check CSRF Token
     *
     * @param   string  $value  CSRF token to be checked, checks post when empty
     */
    public static function check_token($value = null): bool
    {
        $value = $value ?: \Input::param(static::$csrf_token_key, \Input::json(static::$csrf_token_key, 'fail'));
        // always reset token once it's been checked and still the same, and we've configured we want to rotate
        if (hash_equals((string) static::fetch_token(), (string) static::$csrf_old_token) and !empty($value) and \Config::get('security.csrf_rotate', true)) {
            static::set_token(true);
        }
        return $value === static::$csrf_old_token;
    }
    /**
     * Fetch CSRF Token for the next request
     *
     * @return  string
     */
    public static function fetch_token()
    {
        if (static::$csrf_token !== false) {
            return static::$csrf_token;
        }
        static::set_token(true);
        return static::$csrf_token;
    }
    /**
     * Generate new token. Based on an example from OWASP
     */
    public static function generate_token(): string
    {
        // generate a random token base
        $token_base = \Config::get('security.token_salt', '') . random_bytes(64);
        // return the hashed token
        if (function_exists('hash_algos')) {
            foreach (['sha512', 'sha384', 'sha256', 'sha224', 'sha1', 'md5'] as $hash) {
                if (in_array($hash, hash_algos())) {
                    return hash($hash, $token_base);
                }
            }
        }
        // if all else fails
        return md5($token_base);
    }
    /**
     * Setup the next token to be used.
     *
     * @param   $rotate   bool   if true, generate a new token, even if the current token is still valid
     */
    public static function set_token($rotate = true): void
    {
        // re-use old token when found (= not expired) and expiration is used (otherwise always reset)
        if ($rotate === false and static::$csrf_old_token !== false) {
            static::$csrf_token = static::$csrf_old_token;
        } else {
            static::$csrf_token = static::generate_token();
            $expiration = \Config::get('security.csrf_expiration', 0);
            \Cookie::set(static::$csrf_token_key, static::$csrf_token, $expiration);
        }
    }
    /**
     * JS fetch token
     *
     * Produces JavaScript fuel_csrf_token() function that will return the current
     * CSRF token when called. Use to fill right field on form submit for AJAX operations.
     */
    public static function js_fetch_token(): string
    {
        $output = '<script type="text/javascript">
	function fuel_csrf_token()
	{
		if (document.cookie.length > 0)
		{
			var c_name = ' . json_encode(static::$csrf_token_key) . ';
			c_start = document.cookie.indexOf(c_name + "=");
			if (c_start != -1)
			{
				c_start = c_start + c_name.length + 1;
				c_end = document.cookie.indexOf(";" , c_start);
				if (c_end == -1)
				{
					c_end=document.cookie.length;
				}
				return decodeURIComponent(document.cookie.substring(c_start, c_end));
			}
		}
		return "";
	}' . PHP_EOL;
        return $output . ('</script>' . PHP_EOL);
    }
    /**
     * JS set token
     *
     * Produces JavaScript fuel_set_csrf_token() function that will update the current
     * CSRF token in the form when called, based on the value of the csrf cookie
     */
    public static function js_set_token(): string
    {
        $output = '<script type="text/javascript">
	function fuel_set_csrf_token(form)
	{
		if (document.cookie.length > 0 && typeof form != undefined)
		{
			var c_name = ' . json_encode(static::$csrf_token_key) . ';
			c_start = document.cookie.indexOf(c_name + "=");
			if (c_start != -1)
			{
				c_start = c_start + c_name.length + 1;
				c_end = document.cookie.indexOf(";" , c_start);
				if (c_end == -1)
				{
					c_end=document.cookie.length;
				}
				value=decodeURIComponent(document.cookie.substring(c_start, c_end));
				if (value != "")
				{
					for(i=0; i<form.elements.length; i++)
					{
						if (form.elements[i].name == c_name)
						{
							form.elements[i].value = value;
							break;
						}
					}
				}
			}
		}
	}' . PHP_EOL;
        return $output . ('</script>' . PHP_EOL);
    }
}