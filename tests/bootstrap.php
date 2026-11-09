<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

$core_path = realpath(__DIR__.'/..').DIRECTORY_SEPARATOR;
$app_path = realpath(__DIR__.'/fixture_app').DIRECTORY_SEPARATOR;
$vendor_path = $core_path.'vendor'.DIRECTORY_SEPARATOR;
$package_path = $core_path.'packages'.DIRECTORY_SEPARATOR;

define('DOCROOT', $core_path);
define('COREPATH', $core_path);
define('APPPATH', $app_path);
define('PKGPATH', $package_path);
define('VENDORPATH', $vendor_path);

defined('FUEL_START_TIME') or define('FUEL_START_TIME', microtime(true));
defined('FUEL_START_MEM') or define('FUEL_START_MEM', memory_get_usage());

if ( ! is_file(VENDORPATH.'autoload.php'))
{
	die('Composer is not installed. Run tests/run.sh or composer update in the project root.');
}
require VENDORPATH.'autoload.php';

require COREPATH.'classes'.DIRECTORY_SEPARATOR.'autoloader.php';

class_alias('Fuel\\Core\\Autoloader', 'Autoloader');

require_once APPPATH.'bootstrap.php';

$_SERVER['REQUEST_URI'] = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
$_SERVER['HTTP_HOST'] = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$_SERVER['SERVER_NAME'] = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
$_SERVER['SERVER_PORT'] = isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : '80';
$_SERVER['HTTPS'] = isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : '';

\Config::load('routes', true);
if ( ! is_array(\Config::get('routes')))
{
	\Config::set('routes', array());
}

\Fuel::init('config');

\Fuel::$is_test = true;

if (class_exists('\PHPUnit\Framework\TestCase'))
{
	import('testcase_ns');
}
else
{
	import('testcase');
}
