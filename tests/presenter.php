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
 * Presenter class tests
 *
 * @group Core
 * @group Presenter
 */
class Test_Presenter extends TestCase
{
	/**
	 * Presenter::forge must tolerate a null request module (PHP 8 rejects ucfirst(null)).
	 *
	 * @test
	 */
	public function test_forge_with_null_request_module()
	{
		$request = (new \ReflectionClass('Request'))->newInstanceWithoutConstructor();
		$request->module = null;
		Request::active($request);

		try
		{
			Presenter::forge('Php8CompatMissingPresenter');
			$this->fail('Expected OutOfBoundsException when presenter class is missing');
		}
		catch (\OutOfBoundsException $e)
		{
			$this->assertContains('Php8CompatMissingPresenter', $e->getMessage());
		}
		finally
		{
			Request::active(null);
		}
	}
}
