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
 * Input class tests
 *
 * @group Core
 * @group Input
 */
class Test_Input extends TestCase
{
	/**
	 * Missing Content-Type must not be passed to string functions as null.
	 *
	 * @test
	 */
	public function test_hydrate_without_content_type_header()
	{
		unset($_SERVER['HTTP_CONTENT_TYPE'], $_SERVER['CONTENT_TYPE']);

		$request = (new \ReflectionClass('Request'))->newInstanceWithoutConstructor();

		$ref = new \ReflectionClass('Input_Instance');
		$dummy = $ref->newInstanceWithoutConstructor();
		$instance = new Input_Instance($request, $dummy);

		$method = $ref->getMethod('hydrate');
		$method->setAccessible(true);

		$threw = false;
		set_error_handler(function ($errno, $errstr) use (&$threw) {
			$threw = true;
			return true;
		});
		try
		{
			$method->invoke($instance);
		}
		finally
		{
			restore_error_handler();
		}

		$this->assertFalse($threw, 'hydrate must not warn when Content-Type is missing');
		$this->assertSame('', \Input::headers('Content-Type', ''));
	}
}
