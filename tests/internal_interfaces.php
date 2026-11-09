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
 * Test seam: records credentials and optional synthetic PDO failures (production unchanged).
 */
class InternalInterfaces_PDO_CaptureConnection extends Database_PDO_Connection
{
	public static $pdo_password;

	public static $pdo_probe_message;

	public static $mask_probe_secret;

	protected function _connect()
	{
		static::$pdo_password = $this->connection_password_for_pdo();

		if (static::$pdo_probe_message !== null)
		{
			throw new \PDOException(static::$pdo_probe_message);
		}

		if (static::$mask_probe_secret !== null)
		{
			throw new \PDOException('SQLSTATE[HY000] auth failed password='.static::$mask_probe_secret);
		}

		parent::_connect();
	}
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
	 * @return  InternalInterfaces_PDO_CaptureConnection
	 */
	protected function pdo_capture_connection($name, array $connection)
	{
		InternalInterfaces_PDO_CaptureConnection::$pdo_password = 'UNSET';
		InternalInterfaces_PDO_CaptureConnection::$pdo_probe_message = null;
		InternalInterfaces_PDO_CaptureConnection::$mask_probe_secret = null;

		$config = array(
			'type' => 'pdo',
			'connection' => $connection,
		);

		$ref = new \ReflectionClass('Fuel\Core\InternalInterfaces_PDO_CaptureConnection');
		$ctor = $ref->getConstructor();
		$ctor->setAccessible(true);
		$conn = $ref->newInstanceWithoutConstructor();
		$ctor->invoke($conn, $name, $config);

		return $conn;
	}

	/**
	 * @test
	 */
	public function test_pdo_connect_password_credentials_for_pdo()
	{
		$dsn = 'mysql:host=127.0.0.1;dbname=iface_missing';
		$base = array('dsn' => $dsn, 'username' => 'iface');

		$cases = array(
			array('absent', $base, null),
			array('null', $base + array('password' => null), null),
			array('empty', $base + array('password' => ''), ''),
			array('secret', $base + array('password' => 'Sup3rSecret'), 'Sup3rSecret'),
		);

		foreach ($cases as $case)
		{
			list($label, $connection, $expected) = $case;
			$db = $this->pdo_capture_connection('iface_pdo_'.$label.'_'.md5($label), $connection);

			try
			{
				$db->connect();
				$this->fail('Expected Database_Exception for '.$label);
			}
			catch (\Database_Exception $e)
			{
			}

			$this->assertSame(
				$expected,
				InternalInterfaces_PDO_CaptureConnection::$pdo_password,
				'PDO password credential mismatch for '.$label
			);
		}
	}

	/**
	 * @test
	 */
	public function test_pdo_connect_null_credentials_surface_database_exception_on_php8()
	{
		if (version_compare(PHP_VERSION, '8.0', '<'))
		{
			$this->assertTrue(true);

			return;
		}

		$dsn = 'mysql:host=127.0.0.1;dbname=iface_missing';
		$base = array('dsn' => $dsn, 'username' => 'iface');
		$probe = 'iface-pdo-probe-driver-message';

		$cases = array(
			array('absent', $base),
			array('null', $base + array('password' => null)),
		);

		foreach ($cases as $case)
		{
			list($label, $connection) = $case;
			$db = $this->pdo_capture_connection('iface_pdo_catch_'.$label.'_'.md5($label), $connection);
			InternalInterfaces_PDO_CaptureConnection::$pdo_probe_message = $probe;

			try
			{
				$db->connect();
				$this->fail('Expected Database_Exception for '.$label.' connect failure');
			}
			catch (\PhpErrorException $e)
			{
				$this->fail('Connect failure must not surface PhpErrorException for '.$label.': '.$e->getMessage());
			}
			catch (\Database_Exception $e)
			{
				$this->assertSame($probe, $e->getMessage(), 'Driver message must stay intact for '.$label);
			}
			finally
			{
				InternalInterfaces_PDO_CaptureConnection::$pdo_probe_message = null;
			}
		}
	}

	/**
	 * @test
	 */
	public function test_pdo_connect_masks_password_in_error_message()
	{
		$secret = 'Sup3rSecret';
		$db = $this->pdo_capture_connection(
			'iface_pdo_mask_'.md5(__METHOD__),
			array(
				'dsn' => 'mysql:host=127.0.0.1;dbname=iface_missing',
				'username' => 'iface',
				'password' => $secret,
			)
		);
		InternalInterfaces_PDO_CaptureConnection::$mask_probe_secret = $secret;

		try
		{
			$db->connect();
			$this->fail('Expected Database_Exception from masking probe');
		}
		catch (\Database_Exception $e)
		{
			$this->assertNotContains($secret, $e->getMessage());
			$this->assertContains(str_repeat('*', 10), $e->getMessage());
		}
	}

	/**
	 * @test
	 */
	public function test_mongo_db_where_positional_calls()
	{
		$ref = new \ReflectionClass('Mongo_Db');
		$calls = array(
			array('where_gt', array('age', 12), array('age' => array('$gt' => 12))),
			array('where_gte', array('age', 18), array('age' => array('$gte' => 18))),
			array('where_lt', array('age', 120), array('age' => array('$lt' => 120))),
			array('where_lte', array('age', 65), array('age' => array('$lte' => 65))),
			array('where_between', array('age', 10, 20), array('age' => array('$gte' => 10, '$lte' => 20))),
			array('where_between_ne', array('age', 10, 20), array('age' => array('$gt' => 10, '$lt' => 20))),
			array('where_ne', array('name', 'Allerd'), array('name' => array('$ne' => 'Allerd'))),
		);

		foreach ($calls as $call)
		{
			list($method, $args, $expected_wheres) = $call;
			$db = $ref->newInstanceWithoutConstructor();
			call_user_func_array(array($db, $method), $args);
			$this->assertEquals($expected_wheres, $db->wheres, $method.' positional call');
		}
	}
}
