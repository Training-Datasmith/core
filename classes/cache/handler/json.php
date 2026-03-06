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

class Cache_Handler_Json implements \Cache_Handler_Driver
{
	public function readable($contents): mixed
	{
		$array = false;
		if (str_starts_with($contents, 'a'))
		{
			$contents = substr($contents, 1);
			$array = true;
		}

		return json_decode($contents, $array);
	}

	public function writable($contents): string
	{
		$array = '';
		if (is_array($contents))
		{
			$array = 'a';
		}

		return $array.json_encode($contents);
	}
}
