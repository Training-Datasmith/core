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

class InternalInterfaces_TestModel extends Model_Crud
{
	protected static $_table_name = 'iface_smoke';
	protected static $_primary_key = 'id';
}

/**
 * PHP internal interface compatibility tests
 *
 * @group Core
 * @group InternalInterfaces
 */
class Test_Internal_Interfaces extends TestCase
{
	protected static $rows = array(
		array('id' => 1, 'name' => 'alpha'),
		array('id' => 2, 'name' => 'beta'),
	);

	protected static $sql = 'SELECT iface';

	/**
	 * @param  string  $class
	 * @return \Database_Result
	 */
	protected function cached_result($class)
	{
		return new $class(static::$rows, static::$sql, false);
	}

	/**
	 * @test
	 */
	public function test_database_pdo_cached_interfaces()
	{
		$this->assertCachedResultInterfaces($this->cached_result('Database_PDO_Cached'));
	}

	/**
	 * @test
	 */
	public function test_database_mysqli_cached_interfaces()
	{
		$this->assertCachedResultInterfaces($this->cached_result('Database_MySQLi_Cached'));
	}

	/**
	 * @param  \Database_Result  $result
	 */
	protected function assertCachedResultInterfaces($result)
	{
		$this->assertSame(2, count($result));

		$seen = array();
		foreach ($result as $row)
		{
			$seen[] = $row['id'];
		}
		$this->assertEquals(array(1, 2), $seen);

		$this->assertTrue($result->seek(1));
		$this->assertFalse($result->seek(99));

		$this->assertTrue($result->offsetExists(0));
		$this->assertEquals(static::$rows[0], $result->offsetGet(0));

		try
		{
			$result->offsetSet(0, array('id' => 9));
			$this->fail('offsetSet must throw FuelException');
		}
		catch (\FuelException $e)
		{
			$this->assertContains('read-only', strtolower($e->getMessage()));
		}
	}

	/**
	 * @test
	 */
	public function test_model_crud_interfaces_and_serialization()
	{
		$model = new InternalInterfaces_TestModel(array('id' => 5, 'name' => 'a'));
		$model->is_new(false);

		$keys = array();
		foreach ($model as $key => $value)
		{
			$keys[] = $key;
		}
		$this->assertEquals(array('id', 'name'), $keys);

		$model['extra'] = 'z';
		$this->assertEquals('z', $model['extra']);

		$payload = $model->serialize();
		$clone = new InternalInterfaces_TestModel();
		$clone->unserialize($payload);

		$this->assertEquals('a', $clone['name']);
		$this->assertFalse($clone->is_new());

		if (version_compare(PHP_VERSION, '7.4', '<'))
		{
			$this->assertSame(0, strpos(serialize($model), 'C:'));
		}
		else
		{
			$this->assertSame(0, strpos(serialize($model), 'O:'));
		}

		if (version_compare(PHP_VERSION, '7.4', '>='))
		{
			$class = get_class($model);
			$inner = $model->serialize();
			$legacy = sprintf('C:%d:"%s":%d:{%s}', strlen($class), $class, strlen($inner), $inner);
			$from_legacy = unserialize($legacy);
			$this->assertInstanceOf($class, $from_legacy);
			$this->assertEquals('a', $from_legacy['name']);
			$this->assertFalse($from_legacy->is_new());
		}
	}

	/**
	 * @test
	 */
	public function test_pdo_connect_masks_null_password_without_deprecation()
	{
		$name = 'iface_pdo_'.md5(__METHOD__);
		\Config::set('db.'.$name, array(
			'type' => 'pdo',
			'connection' => array(
				'dsn' => 'mysql:host=127.0.0.1;dbname=iface_missing',
				'username' => 'iface',
			),
		));

		try
		{
			\Database_Connection::instance($name)->connect();
			$this->fail('Expected Database_Exception from failed PDO connect');
		}
		catch (\Database_Exception $e)
		{
			$this->assertNotSame('', $e->getMessage());
		}
	}

	/**
	 * @test
	 */
	public function test_mongo_db_class_loads_without_parameter_deprecation()
	{
		$this->assertTrue(class_exists('Mongo_Db'));
		$method = new \ReflectionMethod('Mongo_Db', 'where_gt');
		$params = $method->getParameters();
		$this->assertFalse($params[0]->isOptional());
		$this->assertFalse($params[1]->isOptional());
	}
}
