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

use Paragon_Ie\Fuel\Base64url_Safe;
use Paragon_Ie\Fuel\Binary;
use phpseclib\Crypt\AES;
use phpseclib\Crypt\Hash;
/**
 * Sodium encryption/decryption code based on HaLite from ParagonIE
 *
 * Copyright (c) 2016 - 2018 Paragon Initiative Enterprises.
 * Copyright (c) 2014 Steve "Sc00bz" Thomas (steve at tobtu dot com)
 */
class Crypt
{
    /**
     * Crypto default configuration
     *
     * @var	array
     */
    protected static $defaults = [];
    /**
     * Defined Crypto instances
     *
     * @var	array
     */
    protected static $_instances = [];
    /**
     * initialisation and auto configuration
     */
    public static function _init(): void
    {
        // load the ParagonIE classes we need
        import('paragonie.php', 'vendor');
        // load the config
        \Config::load('crypt', true);
        static::$defaults = \Config::get('crypt', []);
        // keep track of updates to the config
        $update = false;
        // check for legacy config
        if (empty(static::$defaults['legacy'])) {
            $flag = true;
            foreach (['crypto_key', 'crypto_iv', 'crypto_hmac'] as $key) {
                if (empty(static::$defaults[$key]) or strlen((string) static::$defaults[$key]) % 4 !== 0) {
                    $flag = false;
                }
            }
            // and if we found something valid, convert it
            if ($flag) {
                static::$defaults['legacy'] = [];
                foreach (['crypto_key', 'crypto_iv', 'crypto_hmac'] as $key) {
                    static::$defaults['legacy'][$key] = static::$defaults[$key];
                    unset(static::$defaults[$key]);
                }
                $update = true;
            }
        }
        // check the sodium config
        if (empty(static::$defaults['sodium']['cipherkey'])) {
            static::$defaults['sodium'] = ['cipherkey' => sodium_bin2hex(random_bytes(SODIUM_CRYPTO_STREAM_KEYBYTES))];
            $update = true;
        }
        // update the config if needed
        if ($update === true) {
            try {
                \Config::save('crypt', static::$defaults);
            } catch (\File_Access_Exception) {
                // failed to write the config file, inform the user
                echo \View::forge('errors/crypt_keys', ['keys' => static::$defaults]);
                die;
            }
        }
    }
    /**
     * forge
     *
     * create a new named instance
     *
     * @param	string	$name	instance name
     * @param	array	$config	optional runtime configuration
     * @return  \Crypt
     */
    public static function forge($name = '__default__', array $config = [])
    {
        if (!array_key_exists($name, static::$_instances)) {
            static::$_instances[$name] = new static($config);
        }
        return static::$_instances[$name];
    }
    /**
     * Return a specific named instance
     *
     * @param	string  $name	instance name
     * @return  mixed   Crypt if the instance exists, false if not
     */
    public static function instance($name = '__default__')
    {
        if (!array_key_exists($name, static::$_instances)) {
            return static::forge($name);
        }
        return static::$_instances[$name];
    }
    /**
     * capture static calls to methods
     *
     * @param	mixed	$method
     * @param	array	$args	The arguments will passed to $method.
     * @return	mixed	return value of $method.
     */
    public static function __callstatic(string $method, array $args)
    {
        // static method calls are called on the default instance
        return call_user_func_array([static::instance(), $method], $args);
    }
    // --------------------------------------------------------------------
    /**
     * generate a URI safe base64 encoded string
     *
     * @param	string	$value
     */
    protected static function safe_b64encode($value): string
    {
        $data = base64_encode($value);
        return str_replace(['+', '/', '='], ['-', '_', ''], $data);
    }
    /**
     * decode a URI safe base64 encoded string
     *
     * @param	string	$value
     */
    protected static function safe_b64decode($value): string
    {
        $data = str_replace(['-', '_'], ['+', '/'], $value);
        $mod4 = strlen($data) % 4;
        if ($mod4) {
            $data .= substr('====', $mod4);
        }
        return base64_decode($data);
    }
    /**
     * compare two strings in a timing-insensitive way to prevent time-based attacks
     *
     * @param	string	$a
     * @param	string	$b
     * @return	bool
     */
    protected static function secure_compare($a, $b)
    {
        return hash_equals((string) $a, (string) $b);
    }
    /**
     * Split a key (using HKDF-BLAKE2b instead of HKDF-HMAC-*)
     *
     * @param string $key
     * @param string $salt
     * @return string[]
     */
    protected static function split_keys($key, $salt): array
    {
        return [static::hkdf_blake2b($key, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, 'Halite|EncryptionKey', $salt), static::hkdf_blake2b($key, SODIUM_CRYPTO_AUTH_KEYBYTES, 'AuthenticationKeyFor_|Halite', $salt)];
    }
    /**
     * Split a message string into an array (assigned to variables via list()).
     *
     * Should return exactly 6 elements.
     *
     * @param string $ciphertext
     *
     * @return array<int, mixed>
     */
    protected static function split_message($message): array
    {
        // get the message length
        $length = Binary::safe_strlen($message);
        // check ig it's long enough
        if ($length < 120) {
            throw new \Fuel_Exception('Crypt: Message is too short');
        }
        // the salt is used for key splitting (via HKDF)
        $salt = Binary::safe_substr($message, 0, 32);
        // this is the nonce (we authenticated it)
        $nonce = Binary::safe_substr($message, 32, SODIUM_CRYPTO_STREAM_NONCEBYTES);
        // This is the crypto_stream_xor()ed ciphertext
        $encrypted = Binary::safe_substr($message, 56, $length - 120);
        // $auth is the last 32 bytes
        $auth = Binary::safe_substr($message, $length - SODIUM_CRYPTO_GENERICHASH_BYTES_MAX);
        // We don't need this anymore.
        static::memzero($message);
        // Now we return the pieces in a specific order:
        return [$salt, $nonce, $encrypted, $auth];
    }
    /**
     * Use a derivative of HKDF to derive multiple keys from one.
     * http://tools.ietf.org/html/rfc5869
     *
     * This is a variant from hash_hkdf() and instead uses BLAKE2b provided by
     * libsodium.
     *
     * Important: instead of a true HKDF (from HMAC) construct, this uses the
     * crypto_generichash() key parameter. This is *probably* okay.
     *
     * @param string $ikm Initial Keying Material
     * @param int $length How many bytes?
     * @param string $info What sort of key are we deriving?
     * @param string $salt
     * @return string
     */
    protected static function hkdf_blake2b($ikm, $length, string $info = '', $salt = '')
    {
        // Sanity-check the desired output length.
        if ($length < 0 or $length > 255 * SODIUM_CRYPTO_GENERICHASH_KEYBYTES) {
            throw new \Fuel_Exception('hkdfBlake2b Argument 2: Bad HKDF Digest Length');
        }
        // "If [salt] not provided, is set to a string of HashLen zeroes."
        if (empty($salt)) {
            $salt = \str_repeat("\x00", SODIUM_CRYPTO_GENERICHASH_KEYBYTES);
        }
        // HKDF-Extract:
        // PRK = HMAC-Hash(salt, IKM)
        // The salt is the HMAC key.
        $prk = static::raw_keyed_hash($ikm, $salt);
        $t = '';
        $last_block = '';
        for ($block_index = 1; Binary::safe_strlen($t) < $length; ++$block_index) {
            // T(i) = HMAC-Hash(PRK, T(i-1) | info | 0x??)
            $last_block = static::raw_keyed_hash($last_block . $info . \chr($block_index), $prk);
            // T = T(1) | T(2) | T(3) | ... | T(N)
            $t .= $last_block;
        }
        // ORM = first L octets of T
        $orm = Binary::safe_substr($t, 0, $length);
        return $orm;
    }
    /**
     * Wrapper around SODIUM_CRypto_generichash()
     *
     * Expects a key (binary string).
     * Returns raw binary.
     *
     * @param string $input
     * @param string $key
     * @param int $length
     */
    protected static function raw_keyed_hash($input, $key, $length = SODIUM_CRYPTO_GENERICHASH_BYTES): string
    {
        if ($length < SODIUM_CRYPTO_GENERICHASH_BYTES_MIN) {
            throw new \Fuel_Exception(sprintf('Output length must be at least %d bytes.', SODIUM_CRYPTO_GENERICHASH_BYTES_MIN));
        }
        if ($length > SODIUM_CRYPTO_GENERICHASH_BYTES_MAX) {
            throw new \Fuel_Exception(sprintf('Output length must be at most %d bytes.', SODIUM_CRYPTO_GENERICHASH_BYTES_MAX));
        }
        return sodium_crypto_generichash($input, $key, $length);
    }
    /**
     * Calculate a MAC. This is used internally.
     *
     * @param string $message
     * @param string $authKey
     */
    protected static function calculate_mac($message, $auth_key): string
    {
        return sodium_crypto_generichash($message, $auth_key, SODIUM_CRYPTO_GENERICHASH_BYTES_MAX);
    }
    /**
     * Verify a Message Authentication Code (MAC) of a message, with a shared
     * key.
     *
     * @param string $mac             Message Authentication Code
     * @param string $message         The message to verify
     * @param string $authKey         Authentication key (symmetric)
     * @param SymmetricConfig $config Configuration object
     *
     * @return bool
     */
    protected static function verify_mac($mac, $message, $auth_key)
    {
        if (Binary::safe_strlen($mac) !== SODIUM_CRYPTO_GENERICHASH_BYTES_MAX) {
            throw new \Fuel_Exception('Crypt::verify_mac - Argument 1: Message Authentication Code is not the correct length; is it encoded?');
        }
        $calc = sodium_crypto_generichash($message, $auth_key, SODIUM_CRYPTO_GENERICHASH_BYTES_MAX);
        $res = Binary::hash_equals($mac, $calc);
        static::memzero($calc);
        return $res;
    }
    /**
     * Wrapper for sodium_memzero, it's actually not possible to zero
     * memory buffers in PHP. You need the native library for that.
     *
     * @param string|null $var
     *
     * @return void
     */
    protected static function memzero(&$var)
    {
        // check if we have native support
        if (extension_loaded('sodium')) {
            sodium_memzero($var);
        } elseif (extension_loaded('libsodium') and is_callable('\Sodium\memzero')) {
            @call_user_func(\Sodium\memzero(...), $var);
        }
    }
    // --------------------------------------------------------------------
    /**
     * Crypto object used to encrypt/decrypt
     *
     * @var	object
     */
    protected $crypter;
    /**
     * Hash object used to generate hashes
     *
     * @var	object
     */
    protected $hasher;
    /**
     * Legacy crypter and hasher for backwards compatibility
     */
    protected $legacy_crypter;
    protected $legacy_hasher;
    /**
     * Crypto configuration
     */
    protected array $config;
    /**
     * Class constructor
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge(static::$defaults, $config);
        // in case we need to decode legacy encrypted strings
        if (!empty($this->config['legacy'])) {
            $this->legacy_crypter = new AES();
            $this->legacy_hasher = new Hash('sha256');
            $this->legacy_crypter->enable_continuous_buffer();
            $this->legacy_hasher->set_key(static::safe_b64decode($this->config['legacy']['crypto_hmac']));
        }
    }
    /**
     * capture calls to normal methods
     *
     * @param	mixed	$method
     * @param	array	$args	The arguments will passed to $method.
     * @return	mixed	return value of $method.
     * @throws	\ErrorException
     */
    public function __call(string $method, array $args)
    {
        // validate the method called
        if (!in_array($method, ['encode', 'decode', 'legacy_decode'])) {
            throw new \ErrorException('Call to undefined method ' . self::class . '::' . $method . '()', E_ERROR, 0, __FILE__, __LINE__);
        }
        // static method calls are called on the default instance
        return call_user_func_array([$this, $method], $args);
    }
    /**
     * encrypt a string value, optionally with a custom key
     *
     * @param	string		$value		value to encrypt
     * @param	string|bool	$key		optional custom key to be used for this encryption
     * @param	void		$keylength	no longer used
     * @return	string	encrypted value
     */
    protected function encode($value, $key = false, $keylength = false): string
    {
        // get the binary key
        if (!$key) {
            $key = static::$defaults['sodium']['cipherkey'];
        }
        $key = sodium_hex2bin((string) $key);
        // Generate a nonce and a HKDF salt
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $salt = random_bytes(32);
        /**
         * Split our key into two keys: One for encryption, the other for
         * authentication. By using separate keys, we can reasonably dismiss
         * likely cross-protocol attacks.
         *
         * This uses salted HKDF to split the keys, which is why we need the
         * salt in the first place.
         */
        [$enc_key, $auth_key] = static::split_keys($key, $salt);
        // Encrypt our message with the encryption key
        $encrypted = sodium_crypto_stream_xor($value, $nonce, $enc_key);
        static::memzero($enc_key);
        // Calculate an authentication tag
        $auth = static::calculate_mac($salt . $nonce . $encrypted, $auth_key);
        static::memzero($auth_key);
        // total encrypted message
        $message = $salt . $nonce . $encrypted . $auth;
        // wipe every superfluous piece of data from memory
        static::memzero($nonce);
        static::memzero($salt);
        static::memzero($encrypted);
        static::memzero($auth);
        // return the base64 encoded message
        return 'S:' . Base64url_Safe::encode($message);
    }
    /**
     * decrypt a string value, optionally with a custom key
     *
     * @param	string		$value		value to decrypt
     * @param	string|bool	$key		optional custom key to be used for this encryption
     * @param	void		$keylength	no longer used
     * @access	public
     * @return	string	encrypted value
     */
    protected function decode($value, $key = false, $keylength = false)
    {
        // legacy or sodium value?
        $value = explode('S:', $value);
        if (!isset($value[1])) {
            // decode using the legacy method
            return $this->legacy_decode($value[0], $key, $keylength);
        }
        $value = $value[1];
        // get the binary key
        if (!$key) {
            $key = static::$defaults['sodium']['cipherkey'];
        }
        $key = sodium_hex2bin((string) $key);
        // get the base64 decoded message
        $value = Base64url_Safe::decode($value);
        // split the message into it's components
        [$salt, $nonce, $encrypted, $auth] = static::split_message($value);
        /* Split our key into two keys: One for encryption, the other for
         * authentication. By using separate keys, we can reasonably dismiss
         * likely cross-protocol attacks.
         *
         * This uses salted HKDF to split the keys, which is why we need the
         * salt in the first place.
         */
        [$enc_key, $auth_key] = static::split_keys($key, $salt);
        // Check the MAC first
        $res = static::verify_mac($auth, $salt . $nonce . $encrypted, $auth_key);
        static::memzero($salt);
        static::memzero($auth_key);
        if ($res) {
            // crypto_stream_xor() can be used to encrypt and decrypt
            $message = sodium_crypto_stream_xor((string) $encrypted, (string) $nonce, $enc_key);
        }
        static::memzero($encrypted);
        static::memzero($nonce);
        static::memzero($enc_key);
        return $res ? $message : false;
    }
    /**
     * decrypt a string value, optionally with a custom key
     *
     * @param	string		$value		value to decrypt
     * @param	string|bool	$key		optional custom key to be used for this encryption
     * @param	int|bool	$keylength	optional key length
     * @access	public
     * @return	string	encrypted value
     */
    protected function legacy_decode($value, $key = false, $keylength = false)
    {
        // make sure we have legacy keys
        if (empty($this->config['legacy']['crypto_key'])) {
            throw new \Fuel_Exception('Can not decode this string, no legacy crypt keys defined');
        }
        if (!$key) {
            $key = static::safe_b64decode($this->config['legacy']['crypto_key']);
            // Used for backwards compatibility with encrypted data prior
            // to FuelPHP 1.7.2, when phpseclib was updated, and became a
            // bit smarter about figuring out key lengths.
            $keylength = 128;
        }
        if ($keylength) {
            $this->legacy_crypter->set_key_length($keylength);
        }
        $this->legacy_crypter->set_key($key);
        $this->legacy_crypter->set_iv(static::safe_b64decode($this->config['legacy']['crypto_iv']));
        $value = static::safe_b64decode($value);
        if ($value = $this->validate_hmac($value)) {
            return $this->legacy_crypter->decrypt($value);
        }
        return false;
    }
    protected function validate_hmac($value): string|false
    {
        // strip the hmac-sha256 hash from the value
        $hmac = substr((string) $value, strlen((string) $value) - 43);
        // and remove it from the value
        $value = substr((string) $value, 0, strlen((string) $value) - 43);
        // only return the value if it wasn't tampered with
        return static::secure_compare(static::safe_b64encode($this->legacy_hasher->hash($value)), $hmac) ? $value : false;
    }
}