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

/**
 * The Asset class allows you to easily work with your apps assets.
 * It allows you to specify multiple paths to be searched for the
 * assets.
 *
 * You can configure the paths by copying the core/config/asset.php
 * config file into your app/config folder and changing the settings.
 *
 * @package     Fuel
 * @subpackage  Core
 */
class Asset_Instance
{
	/**
	 * @var  array  the asset paths to be searched
	 */
	protected $_asset_paths = [
		'css' => [],
		'js'  => [],
		'img' => [],
	];

	/**
	 * @var  array  the sub-folders to be searched
	 */
	protected $_path_folders = [
		'css' => 'css/',
		'js'  => 'js/',
		'img' => 'img/',
	];

	/**
	 * @var  array  custom type renderers
	 */
	protected $_renderers = [
	];

	/**
	 * @var  string  the URL to be prepended to all assets
	 */
	protected $_asset_url = '/';

	/**
	 * @var  bool  whether to append the file mtime to the url
	 */
	protected bool $_add_mtime;

	/**
	 * @var  array  holds the groups of assets
	 */
	protected $_groups = [];

	/**
	 * @var  string  prefix for generated output to provide proper indentation
	 */
	protected string $_indent;

	/**
	 * @var  bool  if true, directly renders the output of no group name is given
	 */
	protected bool $_auto_render;

	/**
	 * @var  bool  if true the 'not found' exception will not be thrown and the asset is ignored.
	 */
	protected bool $_fail_silently;

	/**
	 * @var  bool  if true, will always true to resolve assets. if false, it will only try to resolve if the asset url is relative.
	 */
	protected bool $_always_resolve;

	/**
     * Parse the config and initialize the object instance
     */
    public function __construct(Array $config)
	{
		// look for global search path folders
		foreach ($config as $key => $value)
		{
			if (\Str::ends_with($key, '_dir'))
			{
				$key = substr((string) $key, 0, -4);
				$this->_path_folders[$key] = $this->_unify_path($value);
			}
		}

		// global search paths
		foreach ($config['paths'] as $path)
		{
			$this->add_path($path);
		}

		// per-type search paths
		foreach ($config['folders'] as $type => $folders)
		{
			is_array($folders) or $folders = [$folders];

			foreach ($folders as $path)
			{
				$this->add_path($path, $type);
			}
		}

		$this->_add_mtime = (bool) $config['add_mtime'];
		$this->_asset_url = $config['url'];
		$this->_indent = str_repeat((string) $config['indent_with'], $config['indent_level']);
		$this->_auto_render = (bool) $config['auto_render'];
		$this->_fail_silently = (bool) $config['fail_silently'];
		$this->_always_resolve = (bool) $config['always_resolve'];
	}

	/**
	 * Provide backward compatibility for old type methods
	 *
	 * @param	$method
	 * @param	$args
	 * @return	mixed
	 * @throws	\BadMethodCallException
	 */
	public function __call(string $method, array $args)
	{
		// check if we can render this type
		if ( ! isset($this->_path_folders[$method]))
		{
			throw new \BadMethodCallException('Call to undefined method Fuel\Core\Asset_Instance::'.$method.'()');
		}

		// add the type to the arguments
		array_unshift($args, $method);

		// call assettype to store the info
		return call_user_func_array($this->assettype(...), $args);
	}

	/**
	 * Adds a new asset type to the list so we can load files of this type
	 *
	 * @param	string   $type      new path type
	 * @param	string   $path      optional default path
	 * @param	Closure  $renderer  function to custom render this type
	 *
	 * @return  object   current instance
	 */
	public function add_type(string $type, $path = null, $renderer = null): static
	{
		isset($this->_asset_paths[$type]) or $this->_asset_paths[$type] = [];
		isset($this->_path_folders[$type]) or $this->_path_folders[$type] = $type.'/';

		if ( ! is_null($path))
		{
			$path = $this->_unify_path($path);
			$this->_asset_paths[$type][] = $path;
		}

		if ( ! is_null($renderer))
		{
			if ( ! $renderer instanceOf \Closure)
			{
				throw new \OutOfBoundsException('Asset type renderer must be passed as a Closure!');
			}

			$this->_renderers[$type] = $renderer;
		}

		return $this;
	}

	/**
	 * Adds the given path to the front of the asset paths array.  It adds paths
	 * in a way so that asset paths are used First in Last Out.
	 *
	 * @param	string	$path  the path to add
	 * @param	string	$type  optional path type (js, css or img)
	 * @return	object	current instance
	 */
	public function add_path($path, $type = null): static
	{
		is_null($type) and $type = $this->_path_folders;
		empty($path) and $path = DOCROOT;

		if( is_array($type))
		{
			foreach ($type as $key => $folder)
			{
				is_numeric($key) and $key = $folder;
				$folder = $this->_unify_path($path).ltrim($this->_unify_path($folder), DS);
				in_array($folder, $this->_asset_paths[$key]) or array_unshift($this->_asset_paths[$key], $folder);
			}
		}
		else
		{
			// create the asset type if it doesn't exist
			if ( ! isset($this->_asset_paths[$type]))
			{
				$this->_asset_paths[$type] = [];
				$this->_path_folders[$type] = $type.'/';
			}

			$path = $this->_unify_path($path);
			in_array($path, $this->_asset_paths[$type]) or array_unshift($this->_asset_paths[$type], $path);
		}
		return $this;
	}

	/**
	 * Removes the given path from the asset paths array
	 *
	 * @param	string	$path  the path to remove
	 * @param	string	$type  optional path type (js, css or img)
	 * @return	object	current instance
	 */
	public function remove_path($path, $type = null): static
	{
		is_null($type) and $type = $this->_path_folders;

		if( is_array($type))
		{
			foreach ($type as $key => $folder)
			{
				is_numeric($key) and $key = $folder;
				$folder = $this->_unify_path($path).ltrim($this->_unify_path($folder), DS);
				if (($found = array_search($folder, $this->_asset_paths[$key])) !== false)
				{
					unset($this->_asset_paths[$key][$found]);
				}
			}
		}
		else
		{
			$path = $this->_unify_path($path);
			if (($key = array_search($path, $this->_asset_paths[$type])) !== false)
			{
				unset($this->_asset_paths[$type][$key]);
			}
		}

		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Asset type store.
	 *
	 * Either adds the asset to the group, or directly return the tag.
	 *
	 * @param	string	       $type   The asset type
	 * @param	mixed	       $files  The file name, or an array files.
	 * @param	array	       $attr   An array of extra attributes
	 * @param	string	       $group  The asset group name
	 * @param	boolean	       $raw    whether to return the raw file or not when group is not set (optional)
	 * @return	string|object  Rendered asset or current instance when adding to group
	 */
	public function assettype($type, $files = [], $attr = [], $group = null, $raw = false)
	{
		static $temp_group = 50000000;

		if ($group === null)
		{
			$render = $this->_auto_render;
			$group = $render ? (string) (++$temp_group) : '_default_';
		}
		else
		{
			$render = false;
		}

		$this->_parse_assets($type, $files, $attr, $group, $raw);

		if ($render)
		{
			return $this->render($group, $raw);
		}

		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * Find File
	 *
	 * Locates a file in all the asset paths.
	 *
	 * @param	string	$file    The filename to locate
	 * @param	string	$type    The type of asset file to search
	 * @param	string	$folder  The sub-folder to look in (optional)
	 * @return	mixed	Either the path to the file or false if not found
	 */
	public function find_file($file, $type, $folder = ''): string|false
	{
		foreach ($this->_asset_paths[$type] as $path)
		{
			empty($folder) or $folder = $this->_unify_path($folder);

			if (is_file($newfile = $path.$folder.$this->_unify_path($file, null, false)))
			{
				// return the file found, make sure it uses forward slashes on Windows
				return str_replace(DS, '/', $newfile);
			}
		}

		return false;
	}

	// --------------------------------------------------------------------

	/**
	 * Get File
	 *
	 * Locates a file in all the asset paths, and return it relative to the docroot
	 *
	 * @param	string	$file    The filename to locate
	 * @param	string	$type    The type of asset file
	 * @param	string	$folder  The sub-folder to look in (optional)
	 * @return	mixed	Either the path to the file or false if not found
	 */
	public function get_file($file, $type, $folder = ''): string|false
	{
		if ($file = $this->find_file($file, $type, $folder))
		{
			str_starts_with((string) $file, DOCROOT) and $file = substr((string) $file, strlen(DOCROOT));

			return $this->_asset_url.$file;
		}

		return false;
	}

	/**
	 * Renders the given group.  Each tag will be separated by a line break.
	 * You can optionally tell it to render the files raw.  This means that
	 * all CSS and JS files in the group will be read and the contents included
	 * in the returning value.
	 *
	 * @param	mixed	$group  the group to render
	 * @param	bool	$raw    whether to return the raw file or not
	 * @return	string	the group's output
	 * @throws	\FuelException
	 */
	public function render($group = null, $raw = false): string
	{
		// determine the group to render
		is_null($group) and $group = '_default_';

		if (is_string($group))
		{
			isset($this->_groups[$group]) and $group = $this->_groups[$group];
		}

		is_array($group) or $group = [];

		// storage for the result
		$result = [];

		// pre-define known types so the order is correct
		foreach($this->_path_folders as $type => $unused)
		{
			$result[$type] = '';
		}

		// loop over the group entries
		foreach ($group as $item)
		{
			// determine file name and inline status
			$type = $item['type'];
			$filename = $item['file'];
			$attr = $item['attr'];
			$inline = $item['raw'];

			// make sure we have storage space for this result
			if ( ! isset($result[$type]))
			{
				$result[$type] = '';
			}

			// only do a file search if the asset is not a URL
			if ( ! preg_match('|^(\w+:)?//|', (string) $filename))
			{
				// and only if the asset is local to the applications base_url
				if ($this->_always_resolve or ! preg_match('|^(\w+:)?//|', $this->_asset_url) or str_starts_with($this->_asset_url, \Config::get('base_url')))
				{
					if ( ! ($file = $this->find_file($filename, $type)))
					{
						if ($raw or $inline)
						{
							$file = $filename;
						}
						else
						{
							if ($this->_fail_silently)
							{
								continue;
							}

							throw new \FuelException('Could not find asset: '.$filename);
						}
					}
					else
					{
						if ($raw or $inline)
						{
							$file = file_get_contents($file);
							$inline = true;
						}
						else
						{
							$file = $this->_asset_url.$file.($this->_add_mtime ? '?'.filemtime($file) : '');
							$file = str_replace(str_replace(DS, '/', DOCROOT), '', $file);
						}
					}
				}
				else
				{
					// a remote file and multiple paths? use the first one!
					$path = reset($this->_asset_paths[$type]);
					$file = $this->_asset_url.$path.$filename;
					if ($raw or $inline)
					{
						$file = file_get_contents($file);
						$inline = true;
					}
					else
					{
						$file = str_replace(str_replace(DS, '/', DOCROOT), '', $file);
					}
				}
			}
			else
			{
				$file = $filename;
			}

			// deal with stray backslashes on Windows
			$file = str_replace('\\', '/', $file);

			// call the renderer for this type
			if (isset($this->_renderers[$type]))
			{
				$method = $this->_renderers[$type];
				$result[$type] .= $method($file, $attr, $inline);
			}
			else
			{
				$method = 'render_'.$type;
				if (method_exists($this, $method))
				{
					$result[$type] .= $this->$method($file, $attr, $inline);
				}
				else
				{
					throw new \OutOfBoundsException('Asset does not know how to render files of type "'.$type.'"!');
				}
			}
		}

		// return them in the correct order, as a string
		return implode("", $result);
	}

	// --------------------------------------------------------------------
    /**
     * CSS tag renderer
     *
     * @param	$file
     * @param	$attr
     * @param	$inline
     */
    protected function render_css(string $file, array $attr, $inline): string
	{
		// make sure we have a type
		isset($attr['type']) or $attr['type'] = 'text/css';

		// render inline. or not
		if ($inline)
		{
			return html_tag('style', $attr, PHP_EOL.$file.PHP_EOL).PHP_EOL;
		}
        if ( ! isset($attr['rel']) or empty($attr['rel']))
			{
				$attr['rel'] = 'stylesheet';
			}
        $attr['href'] = $file;

		// return the result
		return $this->_indent.html_tag('link', $attr).PHP_EOL;
	}

	// --------------------------------------------------------------------
    /**
     * JS tag renderer
     *
     * @param	$file
     * @param	$attr
     * @param	$inline
     */
    protected function render_js(string $file, array $attr, $inline): string
	{
		// make sure we have a type
		isset($attr['type']) or $attr['type'] = 'text/javascript';

		// render inline. or not
		if ($inline)
		{
			return html_tag('script', $attr, PHP_EOL.$file.PHP_EOL).PHP_EOL;
		}
        $attr['src'] = $file;

		// return the result
		return $this->_indent.html_tag('script', $attr, '').PHP_EOL;
	}

	// --------------------------------------------------------------------

	/**
	 * IMG tag renderer
	 *
	 * @param	$file
	 * @param	$attr
	 * @param	$inline
	 * @return	string
	 */
	protected function render_img($file, array $attr, $inline)
	{
		// render the image
		$attr['src'] = $file;
		$attr['alt'] ??= '';

		// return the result
		return html_tag('img', $attr );
	}

	// --------------------------------------------------------------------

	/**
	 * Parse Assets
	 *
	 * Pareses the assets and adds them to the group
	 *
	 * @param	string	$type    The asset type
	 * @param	mixed	$assets  The file name, or an array files.
	 * @param	array	$attr    An array of extra attributes
	 * @param	string	$group   The asset group name
	 * @param	bool	$raw
	 * @return	string
	 */
	protected function _parse_assets($type, $assets, $attr, $group, $raw = false)
	{
		if ( ! is_array($assets))
		{
			$assets = [$assets];
		}

		foreach ($assets as $key => $asset)
		{
			// Prevent duplicate files in a group.
			if (\Arr::get($this->_groups, "$group.$key.file") == $asset)
			{
				continue;
			}

			$this->_groups[$group][] = [
				'type'	=>	$type,
				'file'	=>	$asset,
				'raw'	=>	$raw,
				'attr'	=>	(array) $attr,
			];
		}
	}

	// --------------------------------------------------------------------
    /**
     * Unify the path
     *
     * make sure the directory separator in the path is correct for the
     * platform used, is terminated with a directory separator, and all
     * relative path references are removed
     *
     * @param	string	$path      The path
     * @param	mixed	$ds        Optional directory separator
     * @param	boolean	$trailing  Optional whether to add trailing directory separator
     */
    protected function _unify_path($path, $ds = null, $trailing = true): string
	{
		$ds === null and $ds = DS;

		return rtrim(str_replace(['\\', '/'], $ds, $path), $ds).($trailing ? $ds : '');
	}

}
