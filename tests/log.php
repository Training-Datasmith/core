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
 * Log class tests
 *
 * @group Core
 * @group Log
 */
class Test_Log extends TestCase
{
	protected $threshold_backup;

	protected function setUp()
	{
		$this->threshold_backup = \Config::get('log_threshold');
	}

	protected function tearDown()
	{
		\Config::set('log_threshold', $this->threshold_backup);
	}

	protected function log_file_contents()
	{
		$path = \Config::get('log_path', APPPATH.'logs'.DS);
		$file = \Config::get('log_file', 'test.php');
		$full = $path.$file;

		return is_file($full) ? file_get_contents($full) : '';
	}

	protected function truncate_log()
	{
		$path = \Config::get('log_path', APPPATH.'logs'.DS);
		$file = \Config::get('log_file', 'test.php');
		$full = $path.$file;
		if ( ! is_dir($path))
		{
			mkdir($path, 0777, true);
		}
		if ( ! is_file($full))
		{
			\Log::initialize();
		}
		file_put_contents($full, "<?php defined('COREPATH') or exit('No direct script access allowed'); ?>".PHP_EOL.PHP_EOL);
	}

	public function test_instance_is_monolog_logger()
	{
		$this->assertInstanceOf('\Monolog\Logger', \Log::instance());
	}

	public function test_logs_info_and_error_when_threshold_all()
	{
		$this->truncate_log();
		\Config::set('log_threshold', \Fuel::L_ALL);

		$info_msg = 'core_log_test_info_'.mt_rand();
		$error_msg = 'core_log_test_error_'.mt_rand();

		$this->assertTrue(\Log::info($info_msg));
		$this->assertTrue(\Log::error($error_msg));

		$contents = $this->log_file_contents();
		$this->assertContains('INFO', $contents);
		$this->assertContains($info_msg, $contents);
		$this->assertContains('ERROR', $contents);
		$this->assertContains($error_msg, $contents);
	}

	public function test_info_not_logged_when_threshold_error()
	{
		$this->truncate_log();
		\Config::set('log_threshold', \Fuel::L_ERROR);

		$info_msg = 'core_log_suppressed_info_'.mt_rand();
		$this->assertFalse(\Log::info($info_msg));

		$contents = $this->log_file_contents();
		$this->assertNotContains($info_msg, $contents);
	}

	public function test_legacy_numeric_error_level_maps_to_error()
	{
		$this->truncate_log();
		\Config::set('log_threshold', \Fuel::L_ALL);

		$msg = 'core_log_legacy_error_'.mt_rand();
		$this->assertTrue(\Log::write(1, $msg));

		$contents = $this->log_file_contents();
		$this->assertContains('ERROR', $contents);
		$this->assertContains($msg, $contents);
	}
}
