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
 * Markdown class tests
 *
 * @group Core
 * @group Markdown
 */
class Test_Markdown extends TestCase
{
	public static function setUpBeforeClass()
	{
		Markdown::_init();
	}

	/**
	 * Markdown Extra: table, fenced code, attribute id.
	 *
	 * @var  string
	 */
	protected static $fixture = "| A | B |
|---|---|
| 1 | 2 |

```
code
```

## Title {#tid}
";

	/**
	 * @test
	 */
	public function test_parse_markdown_extra_features()
	{
		$html = Markdown::parse(static::$fixture);

		$this->assertContains('<table>', $html);
		$this->assertContains('<th>A</th>', $html);
		$this->assertContains('<td>1</td>', $html);
		$this->assertContains('<pre><code>code', $html);
		$this->assertContains('id="tid"', $html);
	}

	/**
	 * @test
	 */
	public function test_installed_michelf_version()
	{
		$path = VENDORPATH.'composer'.DIRECTORY_SEPARATOR.'installed.json';
		$this->assertFileExists($path);

		$json = json_decode(file_get_contents($path), true);
		$packages = isset($json['packages']) ? $json['packages'] : $json;
		$version = null;
		foreach ($packages as $package)
		{
			if ($package['name'] === 'michelf/php-markdown')
			{
				$version = ltrim($package['version'], 'v');
				break;
			}
		}

		$this->assertNotNull($version, 'michelf/php-markdown must be installed');
		$this->assertTrue(
			version_compare($version, '1.9.1', '>=') && version_compare($version, '2.0.0', '<'),
			'Expected michelf/php-markdown >=1.9.1 <2.0.0, got '.$version
		);
	}
}
