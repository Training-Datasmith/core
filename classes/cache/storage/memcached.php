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

class Cache_Storage_Memcached extends \Cache_Storage_Driver
{
    /**
     * @const  string  Tag used for opening & closing cache properties
     */
    public const PROPS_TAG = 'Fuel_Cache_Properties';
    /**
     * @var  array  driver specific configuration
     */
    protected $config = [];
    /*
     * @var  Memcached  storage for the memcached object
     */
    protected static $memcached = false;
    // ---------------------------------------------------------------------
    public function __construct($identifier, $config)
    {
        parent::__construct($identifier, $config);
        $this->config = $config['memcached'] ?? [];
        // make sure we have a memcache id
        $this->config['cache_id'] = $this->_validate_config('cache_id', $this->config['cache_id'] ?? 'fuel');
        // check for an expiration override
        $this->expiration = $this->_validate_config('expiration', $this->config['expiration'] ?? $this->expiration);
        if (static::$memcached === false) {
            // make sure we have memcached servers configured
            $this->config['servers'] = $this->_validate_config('servers', $this->config['servers']);
            // do we have the PHP memcached extension available
            if (!class_exists('Memcached')) {
                throw new \Fuel_Exception('Memcached cache are configured, but your PHP installation doesn\'t have the Memcached extension loaded.');
            }
            // instantiate the memcached object
            static::$memcached = new \Memcached();
            // add the configured servers
            static::$memcached->add_servers($this->config['servers']);
            // check if we can connect to all the server(s)
            $added = static::$memcached->get_stats();
            foreach ($this->config['servers'] as $server) {
                $server = $server['host'] . ':' . $server['port'];
                if (!isset($added[$server]) or $added[$server]['pid'] == -1) {
                    throw new \Fuel_Exception('Memcached cache is configured, but there is no connection possible. Check your configuration.');
                }
            }
        }
    }
    // ---------------------------------------------------------------------
    /**
     * Check if other caches or files have been changed since cache creation
     *
     * @param   array
     * @return  bool
     */
    public function check_dependencies(array $dependencies)
    {
        foreach ($dependencies as $dep) {
            // get the section name and identifier
            $sections = explode('.', (string) $dep);
            if (count($sections) > 1) {
                $identifier = array_pop($sections);
                $sections = '.' . implode('.', $sections);
            } else {
                $identifier = $dep;
                $sections = '';
            }
            // get the cache index
            $index = static::$memcached->get($this->config['cache_id'] . $sections);
            // get the key from the index
            $key = isset($index[$identifier][0]) ? $index[$identifier] : false;
            // key found and newer?
            if ($key === false or $key[1] > $this->created) {
                return false;
            }
        }
        return true;
    }
    /**
     * Delete Cache
     */
    public function delete(): void
    {
        // get the memcached key for the cache identifier
        $key = $this->_get_key(true);
        // delete the key from the memcached server
        if ($key and static::$memcached->delete($key) === false) {
            if (static::$memcached->get_result_code() !== \Memcached::RES_NOTFOUND) {
                throw new \Fuel_Exception('Memcached returned error code "' . static::$memcached->get_result_code() . '" on delete. Check your configuration.');
            }
        }
        $this->reset();
    }
    /**
     * Purge all caches
     *
     * @param   string  $section  limit purge to subsection
     */
    public function delete_all($section): void
    {
        // determine the section index name
        $section = $this->config['cache_id'] . (empty($section) ? '' : '.' . $section);
        // get the directory index
        $index = static::$memcached->get($this->config['cache_id'] . '__DIR__');
        if (is_array($index)) {
            // limit the delete if we have a valid section
            if (!empty($section)) {
                $dirs = in_array($section, $index) ? [$section] : [];
            } else {
                $dirs = $index;
            }
            // loop through the indexes, delete all stored keys, then delete the indexes
            foreach ($dirs as $dir) {
                $list = static::$memcached->get($dir);
                foreach ($list as $item) {
                    static::$memcached->delete($item[0]);
                }
                static::$memcached->delete($dir);
            }
            // update the directory index
            $index = array_diff($index, $dirs);
            static::$memcached->set($this->config['cache_id'] . '__DIR__', $index);
        }
    }
    // ---------------------------------------------------------------------
    /**
     * Prepend the cache properties
     *
     * @return  string
     */
    protected function prep_contents()
    {
        $properties = ['created' => $this->created, 'expiration' => $this->expiration, 'dependencies' => $this->dependencies, 'content_handler' => $this->content_handler];
        $properties = '{{' . static::PROPS_TAG . '}}' . json_encode($properties) . '{{/' . static::PROPS_TAG . '}}';
        return $properties . $this->contents;
    }
    /**
     * Remove the prepended cache properties and save them in class properties
     *
     * @param   string
     * @throws \UnexpectedValueException
     */
    protected function unprep_contents($payload)
    {
        $properties_end = strpos((string) $payload, '{{/' . static::PROPS_TAG . '}}');
        if ($properties_end === false) {
            throw new \UnexpectedValueException('Cache has bad formatting');
        }
        $this->contents = substr((string) $payload, $properties_end + strlen('{{/' . static::PROPS_TAG . '}}'));
        $props = substr(substr((string) $payload, 0, $properties_end), strlen('{{' . static::PROPS_TAG . '}}'));
        $props = json_decode($props, true);
        if ($props === null) {
            throw new \UnexpectedValueException('Cache properties retrieval failed');
        }
        $this->created = $props['created'];
        $this->expiration = is_null($props['expiration']) ? null : (int) ($props['expiration'] - time());
        $this->dependencies = $props['dependencies'];
        $this->content_handler = $props['content_handler'];
    }
    /**
     * Save a cache, this does the generic pre-processing
     *
     * @return  bool  success
     * @throws \FuelException
     */
    protected function _set()
    {
        // get the memcached key for the cache identifier
        $key = $this->_get_key();
        $payload = $this->prep_contents();
        // calculate relative expiration time (eg. 60s)
        $expiration = !is_null($this->expiration) ? $this->expiration - time() : 0;
        // if expiration value is less than 30 days, use relative value, otherwise use unix timestamp:
        $expiration = $expiration <= 2592000 ? (int) $expiration : (int) $this->expiration;
        // write it to the memcached server
        if (static::$memcached->set($key, $payload, $expiration) === false) {
            throw new \Fuel_Exception('Memcached returned error code "' . static::$memcached->get_result_code() . '" on write. Check your configuration.');
        }
        // update the index
        $this->_update_index($key);
        return true;
    }
    /**
     * Load a cache, this does the generic post-processing
     *
     * @return  bool  success
     */
    protected function _get()
    {
        // get the memcached key for the cache identifier
        $key = $this->_get_key();
        // fetch the cached data from the Memcached server
        $payload = static::$memcached->get($key);
        try {
            $this->unprep_contents($payload);
        } catch (\UnexpectedValueException) {
            return false;
        }
        return true;
    }
    /**
     * validate a driver config value
     *
     * @param   string  $name  name of the config variable to validate
     * @param   mixed   $value
     * @return  mixed
     * @throws \FuelException
     */
    protected function _validate_config($name, $value)
    {
        switch ($name) {
            case 'cache_id':
                if (empty($value) or !is_string($value)) {
                    $value = 'fuel';
                }
                break;
            case 'expiration':
                if (empty($value) or !is_numeric($value)) {
                    $value = null;
                }
                break;
            case 'servers':
                // do we have a servers config
                if (empty($value) or !is_array($value)) {
                    $value = ['default' => ['host' => '127.0.0.1', 'port' => '11211']];
                }
                // validate the servers
                foreach ($value as $key => $server) {
                    // do we have a host?
                    if (!isset($server['host']) or !is_string($server['host'])) {
                        throw new \Fuel_Exception('Invalid Memcached server definition in the cache configuration.');
                    }
                    // do we have a port number?
                    if (!isset($server['port']) or !is_numeric($server['port']) or $server['port'] < 1025 or $server['port'] > 65535) {
                        throw new \Fuel_Exception('Invalid Memcached server definition in the cache configuration.');
                    }
                    // do we have a relative server weight?
                    if (!isset($server['weight']) or !is_numeric($server['weight']) or $server['weight'] < 0) {
                        // set a default
                        $value[$key]['weight'] = 0;
                    }
                }
                break;
            default:
                break;
        }
        return $value;
    }
    /**
     * Get's the memcached key belonging to the cache identifier
     *
     * @param   bool  $remove  if true, remove the key retrieved from the index
     * @return  string
     */
    protected function _get_key($remove = false)
    {
        // get the current index information
        [$identifier, $sections, $index] = $this->_get_index();
        // get the key from the index
        $key = $index[$identifier][0] ?? false;
        if ($remove === true) {
            if ($key !== false) {
                unset($index[$identifier]);
                static::$memcached->set($this->config['cache_id'] . $sections, $index);
            }
        } else {
            // create a new key if needed
            $key === false and $key = $this->_new_key();
        }
        return $key;
    }
    /**
     * Generate a new unique key for the current identifier
     *
     * @return  string
     */
    protected function _new_key()
    {
        $key = '';
        while (strlen($key) < 32) {
            $key .= mt_rand(0, mt_getrandmax());
        }
        return md5($this->config['cache_id'] . '_' . uniqid($key, true));
    }
    /**
     * Get the section index
     *
     * @return  array  containing the identifier, the sections, and the section index
     */
    protected function _get_index()
    {
        // get the section name and identifier
        $sections = explode('.', $this->identifier);
        if (count($sections) > 1) {
            $identifier = array_pop($sections);
            $sections = '.' . implode('.', $sections);
        } else {
            $identifier = $this->identifier;
            $sections = '';
        }
        // get the cache index and return it
        return [$identifier, $sections, static::$memcached->get($this->config['cache_id'] . $sections)];
    }
    /**
     * Update the section index
     *
     * @param  string  cache key
     */
    protected function _update_index($key)
    {
        // get the current index information
        [$identifier, $sections, $index] = $this->_get_index();
        // create a new index and store the key
        is_array($index) or $index = [];
        // store the key in the index and write the index back
        $index[$identifier] = [$key, $this->created];
        static::$memcached->set($this->config['cache_id'] . $sections, $index, 0);
        // get the directory index
        $index = static::$memcached->get($this->config['cache_id'] . '__DIR__');
        if (is_array($index)) {
            if (!in_array($this->config['cache_id'] . $sections, $index)) {
                $index[] = $this->config['cache_id'] . $sections;
            }
        } else {
            $index = [$this->config['cache_id'] . $sections];
        }
        // update the directory index
        static::$memcached->set($this->config['cache_id'] . '__DIR__', $index, 0);
    }
}