<?php
/**
 * Plain-PHP crypt/log smoke checks (no PHPUnit). Exits 0 only if all checks pass.
 */

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');

define('FUEL_CORE_SMOKE', true);

$failures = 0;

function smoke_fail($message)
{
	fwrite(STDERR, "FAIL: ".$message.PHP_EOL);
	global $failures;
	$failures++;
}

function smoke_pass($message)
{
	echo 'PASS: '.$message.PHP_EOL;
}

require __DIR__.'/bootstrap.php';

$clear = 'This is a string to encrypt';
$legacy_blob = 'LO01JUJcY4z-BiQeWuOMxaEl9JYJjmLeoZCLgXqMVmtZSXpwN0NPSnJIblRmV0VvZHJKZjUycE5NNWRPOU5EdWlxTjR3MnJMMUtJx';
$cipherkey = 'e9fb7405ce10a96c76a9d279d5260ce4cb9ceca8774beec90da6f61d8bd2b8af';

\Config::load('crypt', true);
\Config::set('crypt.legacy.crypto_key', '9Kgt0c4LIb1g8GIhyAjnEnuU');
\Config::set('crypt.legacy.crypto_iv', 'PuMQGc0vA-ykX_QShEKRg3B4');
\Config::set('crypt.legacy.crypto_hmac', 'Pfk4CY2qc_okomcUH8MuIG1M');
\Config::set('crypt.sodium.cipherkey', $cipherkey);
\Crypt::_init();

$decoded = \Crypt::legacy_decode($legacy_blob);
if ($decoded === $clear)
{
	smoke_pass('legacy vector decrypts to expected plaintext');
}
else
{
	smoke_fail('legacy vector decrypt');
}

$tampered = substr_replace($legacy_blob, 'X', 10, 1);
if (\Crypt::legacy_decode($tampered) === false)
{
	smoke_pass('tampered legacy blob returns false');
}
else
{
	smoke_fail('tampered legacy blob should return false');
}

if (\Crypt::legacy_decode($legacy_blob, '0123456789abcdefXX') === false)
{
	smoke_pass('wrong legacy key returns false');
}
else
{
	smoke_fail('wrong legacy key should return false');
}

$threw = false;
try
{
	$crypt = new \Crypt(array(
		'legacy' => array(
			'crypto_key' => '',
			'crypto_iv' => 'PuMQGc0vA-ykX_QShEKRg3B4',
			'crypto_hmac' => 'Pfk4CY2qc_okomcUH8MuIG1M',
		),
		'sodium' => array(
			'cipherkey' => $cipherkey,
		),
	));
	$crypt->__call('legacy_decode', array($legacy_blob));
}
catch (\FuelException $e)
{
	$threw = true;
}
if ($threw)
{
	smoke_pass('missing legacy crypto_key throws FuelException');
}
else
{
	smoke_fail('missing legacy crypto_key should throw FuelException');
}

$encoded = \Crypt::encode($clear);
$roundtrip = \Crypt::decode($encoded);
if ($roundtrip === $clear)
{
	smoke_pass('sodium encode/decode round trip');
}
else
{
	smoke_fail('sodium encode/decode round trip');
}

$log_path = \Config::get('log_path', APPPATH.'logs'.DS);
$log_file = \Config::get('log_file', 'test.php');
$full = $log_path.$log_file;
if ( ! is_dir($log_path))
{
	smoke_fail('fixture log_path directory must exist');
}
else
{
	file_put_contents($full, "<?php defined('COREPATH') or exit('No direct script access allowed'); ?>".PHP_EOL.PHP_EOL);
	\Config::set('log_threshold', \Fuel::L_ALL);
	$info_msg = 'smoke_info_'.mt_rand();
	$error_msg = 'smoke_error_'.mt_rand();
	if (\Log::instance() instanceof \Monolog\Logger)
	{
		smoke_pass('Log::instance() is Monolog\\Logger');
	}
	else
	{
		smoke_fail('Log::instance() type');
	}
	if (\Log::info($info_msg) === true && \Log::error($error_msg) === true)
	{
		smoke_pass('Log::info and Log::error return true at L_ALL');
	}
	else
	{
		smoke_fail('Log::info/Log::error return values at L_ALL');
	}
	$contents = file_get_contents($full);
	if (strpos($contents, 'INFO') !== false && strpos($contents, $info_msg) !== false
		&& strpos($contents, 'ERROR') !== false && strpos($contents, $error_msg) !== false)
	{
		smoke_pass('log file contains INFO/ERROR and messages');
	}
	else
	{
		smoke_fail('log file contents');
	}
	$legacy_msg = 'smoke_legacy_error_'.mt_rand();
	if (\Log::write(1, $legacy_msg) === true)
	{
		smoke_pass('Log::write(1, ...) returns true');
	}
	else
	{
		smoke_fail('Log::write(1, ...) return value');
	}
	$contents = file_get_contents($full);
	if (strpos($contents, 'ERROR') !== false && strpos($contents, $legacy_msg) !== false)
	{
		smoke_pass('Log::write(1, ...) stored as ERROR');
	}
	else
	{
		smoke_fail('Log::write(1, ...) log line');
	}
}

unset($_SERVER['HTTP_CONTENT_TYPE'], $_SERVER['CONTENT_TYPE']);
$hydrate_threw = false;
$hydrate_error = null;
try
{
	$request = (new \ReflectionClass('Fuel\\Core\\Request'))->newInstanceWithoutConstructor();
	$input_ref = new \ReflectionClass('Fuel\\Core\\Input_Instance');
	$dummy = $input_ref->newInstanceWithoutConstructor();
	$instance = new \Input_Instance($request, $dummy);
	$hydrate = $input_ref->getMethod('hydrate');
	$hydrate->setAccessible(true);
	$hydrate->invoke($instance);
}
catch (\Throwable $e)
{
	$hydrate_threw = true;
	$hydrate_error = $e->getMessage();
}
if ( ! $hydrate_threw)
{
	smoke_pass('Input_Instance::hydrate() with no Content-Type header');
}
else
{
	smoke_fail('Input_Instance::hydrate() with no Content-Type: '.$hydrate_error);
}

$cleaned = \Security::clean('"H&M"', array('htmlentities'));
if ($cleaned === '&quot;H&amp;M&quot;')
{
	smoke_pass('Security::clean applies static method filter without deprecated callable');
}
else
{
	smoke_fail('Security::clean static method filter');
}

$req = (new \ReflectionClass('Fuel\\Core\\Request'))->newInstanceWithoutConstructor();
$req->module = null;
\Request::active($req);
$presenter_ok = false;
try
{
	\Presenter::forge('Php8CompatMissingPresenter');
}
catch (\OutOfBoundsException $e)
{
	$presenter_ok = true;
}
catch (\Throwable $e)
{
	smoke_fail('Presenter::forge with null module: '.$e->getMessage());
}
\Request::active(null);
if ($presenter_ok)
{
	smoke_pass('Presenter::forge with null module reaches missing-class handling');
}
else
{
	smoke_fail('Presenter::forge with null module');
}

$markdown_fixture = "| A | B |
|---|---|
| 1 | 2 |

```
code
```

## Title {#tid}
";

try
{
	\Markdown::_init();
	$markdown_html = \Markdown::parse($markdown_fixture);
}
catch (\Throwable $e)
{
	smoke_fail('Markdown::parse threw: '.$e->getMessage());
	$markdown_html = '';
}

if ($markdown_html !== '' && strpos($markdown_html, '<table>') !== false
	&& strpos($markdown_html, '<th>A</th>') !== false
	&& strpos($markdown_html, '<td>1</td>') !== false
	&& strpos($markdown_html, '<pre><code>code') !== false
	&& strpos($markdown_html, 'id="tid"') !== false)
{
	smoke_pass('Markdown::parse renders Extra table, fenced code, and header id');
}
else
{
	smoke_fail('Markdown::parse Extra output');
}

$installed_path = VENDORPATH.'composer'.DIRECTORY_SEPARATOR.'installed.json';
$markdown_version = null;
if (is_file($installed_path))
{
	$installed = json_decode(file_get_contents($installed_path), true);
	$packages = isset($installed['packages']) ? $installed['packages'] : $installed;
	foreach ($packages as $package)
	{
		if ($package['name'] === 'michelf/php-markdown')
		{
			$markdown_version = ltrim($package['version'], 'v');
			break;
		}
	}
}
if ($markdown_version !== null
	&& version_compare($markdown_version, '1.9.1', '>=')
	&& version_compare($markdown_version, '2.0.0', '<'))
{
	smoke_pass('michelf/php-markdown >=1.9.1 <2.0.0 (got '.$markdown_version.')');
}
else
{
	smoke_fail('michelf/php-markdown version bound (got '.($markdown_version === null ? 'unknown' : $markdown_version).')');
}

$iface_rows = array(
	array('id' => 1, 'name' => 'alpha'),
	array('id' => 2, 'name' => 'beta'),
);
$iface_sql = 'SELECT iface';

foreach (array('Database_PDO_Cached', 'Database_MySQLi_Cached') as $cached_class)
{
	$iface_ok = true;
	try
	{
		$result = new $cached_class($iface_rows, $iface_sql, false);
		if (count($result) !== 2)
		{
			$iface_ok = false;
		}
		$seen = array();
		foreach ($result as $row)
		{
			$seen[] = $row['id'];
		}
		if ($seen !== array(1, 2) || ! $result->seek(1) || $result->seek(99)
			|| ! $result->offsetExists(0) || $result->offsetGet(0) !== $iface_rows[0])
		{
			$iface_ok = false;
		}
		try
		{
			$result->offsetSet(0, array('id' => 9));
			$iface_ok = false;
		}
		catch (\FuelException $e)
		{
		}
	}
	catch (\Throwable $e)
	{
		smoke_fail($cached_class.' interface check: '.$e->getMessage());
		$iface_ok = false;
	}
	if ($iface_ok)
	{
		smoke_pass($cached_class.' Countable/Iterator/SeekableIterator/ArrayAccess');
	}
	else
	{
		smoke_fail($cached_class.' interface check');
	}
}

class SmokeInternalTestModel extends \Model_Crud
{
	protected static $_table_name = 'iface_smoke';
	protected static $_primary_key = 'id';
}

$model_iface_ok = true;
try
{
	$model = new SmokeInternalTestModel(array('id' => 5, 'name' => 'a'));
	$model->is_new(false);
	$keys = array();
	foreach ($model as $key => $unused)
	{
		$keys[] = $key;
	}
	if ($keys !== array('id', 'name'))
	{
		$model_iface_ok = false;
	}
	$model['extra'] = 'z';
	if ($model['extra'] !== 'z')
	{
		$model_iface_ok = false;
	}
	$payload = $model->serialize();
	$restored = new SmokeInternalTestModel();
	$restored->unserialize($payload);
	if ($restored['name'] !== 'a' || $restored->is_new())
	{
		$model_iface_ok = false;
	}
	if (version_compare(PHP_VERSION, '7.4', '<'))
	{
		if (strpos(serialize($model), 'C:') !== 0)
		{
			$model_iface_ok = false;
		}
	}
	else
	{
		if (strpos(serialize($model), 'O:') !== 0)
		{
			$model_iface_ok = false;
		}
		$class = get_class($model);
		$inner = $model->serialize();
		$legacy = sprintf('C:%d:"%s":%d:{%s}', strlen($class), $class, strlen($inner), $inner);
		$from_legacy = unserialize($legacy);
		if ( ! $from_legacy instanceof SmokeInternalTestModel
			|| $from_legacy['name'] !== 'a' || $from_legacy->is_new())
		{
			$model_iface_ok = false;
		}
	}
}
catch (\Throwable $e)
{
	smoke_fail('Model_Crud interface/serialize: '.$e->getMessage());
	$model_iface_ok = false;
}
if ($model_iface_ok)
{
	smoke_pass('Model_Crud Iterator/ArrayAccess/Serializable');
}
else
{
	smoke_fail('Model_Crud interface/serialize check');
}

$pdo_iface_ok = false;
try
{
	$pdo_name = 'iface_pdo_smoke_'.mt_rand();
	\Config::set('db.'.$pdo_name, array(
		'type' => 'pdo',
		'connection' => array(
			'dsn' => 'mysql:host=127.0.0.1;dbname=iface_missing',
			'username' => 'iface',
		),
	));
	\Database_Connection::instance($pdo_name)->connect();
}
catch (\Database_Exception $e)
{
	$pdo_iface_ok = ($e->getMessage() !== '');
}
catch (\Throwable $e)
{
	smoke_fail('PDO connect path: '.$e->getMessage());
}
if ($pdo_iface_ok)
{
	smoke_pass('Database_PDO_Connection connect masks null password on failure');
}
else
{
	smoke_fail('Database_PDO_Connection connect path');
}

if (class_exists('Mongo_Db'))
{
	smoke_pass('Mongo_Db class loads without optional-before-required signature');
}
else
{
	smoke_fail('Mongo_Db class missing');
}

if ($failures > 0)
{
	fwrite(STDERR, "SMOKE FAILED ($failures check(s))".PHP_EOL);
	exit(1);
}

echo 'SMOKE OK'.PHP_EOL;
exit(0);
