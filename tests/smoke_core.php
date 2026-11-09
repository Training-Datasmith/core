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

if ($failures > 0)
{
	fwrite(STDERR, "SMOKE FAILED ($failures check(s))".PHP_EOL);
	exit(1);
}

echo 'SMOKE OK'.PHP_EOL;
exit(0);
