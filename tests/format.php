<?php

declare(strict_types=1);
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
 * Format class tests
 *
 * @group Core
 * @group Format
 */
class Test_Format extends TestCase
{
    protected function setUp()
    {
        Config::load('format', true);
        Config::set('format', [
            'csv' => [
                'import' => [
                    'delimiter' => ',',
                    'enclosure' => '"',
                    'newline'   => "\n",
                    'escape'    => '\\',
                ],
                'export' => [
                    'delimiter' => ',',
                    'enclosure' => '"',
                    'newline'   => "\n",
                    'escape'    => '\\',
                ],
                'regex_newline'   => "\n",
                'enclose_numbers' => true,
            ],
            'xml' => [
                'basenode' => 'xml',
                'use_cdata' => false,
                'bool_representation' => null,
            ],
            'json' => [
                'encode' => [
                    'options' => JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP,
                ],
            ],
        ]);
    }

    public static function array_provider()
    {
        return [
            [
                [
                    ['field1' => 'Value 1', 'field2' => 35, 'field3' => 123123],
                    ['field1' => 'Value 1', 'field2' => "Value\nline 2", 'field3' => 'Value 3'],
                ],
                '"field1","field2","field3"
"Value 1","35","123123"
"Value 1","Value
line 2","Value 3"',
            ],
        ];
    }

    public static function array_provider2()
    {
        return [
            [
                [
                    ['First' => 'Jane','Last' => 'Doe','Email' => 'jane@doe.com','Nr1' => 3434534,'Nr2' => 1,'Remark' => "asdfasdf\nasdfasdf",'Nr3' => 23432],
                    ['First' => 'John','Last' => 'Doe','Email' => 'john@doe.com','Nr1' => 52939494,'Nr2' => 1,'Remark' => 'dfdfdf','Nr3' => 35353],
                ],
                '"First","Last","Email","Nr1","Nr2","Remark","Nr3"
"Jane","Doe","jane@doe.com",3434534,1,"asdfasdf'."\n".'asdfasdf",23432
"John","Doe","john@doe.com",52939494,1,"dfdfdf",35353',
            ],
        ];
    }

    public static function array_provider3()
    {
        return [
            [
                [
                    ['First' => 'Jane','Last' => 'Doe','Email' => 'jane@doe.com','Nr1' => 3434534,'Nr2' => 1,'Remark' => "asdfasdf\nasdfasdf",'Nr3' => 23432],
                    ['First' => 'John','Last' => 'Doe','Email' => 'john@doe.com','Nr1' => 52939494,'Nr2' => 1,'Remark' => 'dfdfdf','Nr3' => 35353],
                ],
                'First;Last;Email;Nr1;Nr2;Remark;Nr3
Jane;Doe;jane@doe.com;3434534;1;asdfasdf'."\n".'asdfasdf;23432
John;Doe;john@doe.com;52939494;1;dfdfdf;35353',
            ],
        ];
    }

    public static function array_provider4()
    {
        return [
            [
                [
                    [0 => 'Value 1', 1 => 35, 2 => 123123],
                    [0 => 'Value 1', 1 => "Value\nline 2", 2 => 'Value 3'],
                ],
                '"Value 1","35","123123"
"Value 1","Value
line 2","Value 3"',
            ],
        ];
    }

    public static function array_provider5()
    {
        return [
            [
                [
                    ['field1' => 'Value 1', 'field2' => 35, 'field3' => true, 'field4' => false],
                ],
                '<?xml version="1.0" encoding="utf-8"?>
<xml><item><field1>Value 1</field1><field2>35</field2><field3>1</field3><field4/></item></xml>
',
                '<?xml version="1.0" encoding="utf-8"?>
<xml><item><field1>Value 1</field1><field2>35</field2><field3>1</field3><field4></field4></item></xml>
',
                '<?xml version="1.0" encoding="utf-8"?>
<xml><item><field1>Value 1</field1><field2>35</field2><field3>true</field3><field4>false</field4></item></xml>
',
                '<?xml version="1.0" encoding="utf-8"?>
<xml><item><field1>Value 1</field1><field2>35</field2><field3>1</field3><field4>0</field4></item></xml>
',
            ],
        ];
    }

    /**
     * Test for Format::forge($foo, 'csv')->to_array()
     *
     * @test
     * @dataProvider array_provider
     */
    public function test_from_csv($array, $csv)
    {
        $this->assertEquals($array, Format::forge($csv, 'csv')->to_array());
    }

    /**
     * Test for Format::forge($foo, 'csv')->to_array()
     *
     * @test
     * @dataProvider array_provider2
     */
    public function test_from_csv2($array, $csv)
    {
        $this->assertEquals($array, Format::forge($csv, 'csv')->to_array());
    }

    /**
     * Test for Format::forge($foo, 'csv')->to_array() with different delimiter and no enclosures
     *
     * @test
     * @dataProvider array_provider3
     */
    public function test_from_csv3($array, $csv)
    {
        \Config::set('format.csv.import.enclosure', '');
        \Config::set('format.csv.import.delimiter', ';');
        \Config::set('format.csv.export.enclosure', '');
        \Config::set('format.csv.export.delimiter', ';');

        $this->assertEquals($array, Format::forge($csv, 'csv')->to_array());

        \Config::set('format.csv.import.enclosure', '"');
        \Config::set('format.csv.import.delimiter', ',');
        \Config::set('format.csv.export.enclosure', '"');
        \Config::set('format.csv.export.delimiter', ',');
    }

    /**
     * Test for Format::forge($foo, 'csv')->to_array() without CSV headers
     *
     * @test
     * @dataProvider array_provider4
     */
    public function test_from_csv4($array, $csv)
    {
        $this->assertEquals($array, Format::forge($csv, 'csv', false)->to_array());
    }

    /**
     * Test for Format::forge($foo)->to_csv()
     *
     * @test
     * @dataProvider array_provider
     */
    public function test_to_csv($array, $csv)
    {
        $this->assertEquals($csv, Format::forge($array)->to_csv());
    }

    /**
     * Test for Format::forge($foo)->to_csv() without enclosuring numbers
     *
     * @test
     * @dataProvider array_provider2
     */
    public function test_to_csv2($array, $csv)
    {
        $this->assertEquals($csv, Format::forge($array)->to_csv(null, null, false));
    }

    /**
     * Test for Format::forge($foo)->to_csv() with different delimiter and no enclosures
     *
     * @test
     * @dataProvider array_provider3
     */
    public function test_to_csv3($array, $csv)
    {
        \Config::set('format.csv.import.enclosure', '');
        \Config::set('format.csv.import.delimiter', ';');
        \Config::set('format.csv.export.enclosure', '');
        \Config::set('format.csv.export.delimiter', ';');

        $this->assertEquals($csv, Format::forge($array)->to_csv());

        \Config::set('format.csv.import.enclosure', '"');
        \Config::set('format.csv.import.delimiter', ',');
        \Config::set('format.csv.export.enclosure', '"');
        \Config::set('format.csv.export.delimiter', ',');
    }

    /**
     * Test for Format::forge($foo)->_from_xml()
     *
     * @test
     */
    public function test__from_xml()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>

<phpunit colors="true" stopOnFailure="false" bootstrap="bootstrap_phpunit.php">
	<php>
		<server name="doc_root" value="../../"/>
		<server name="app_path" value="fuel/app"/>
		<server name="core_path" value="fuel/core"/>
		<server name="package_path" value="fuel/packages"/>
	</php>
	<testsuites>
		<testsuite name="core">
			<directory suffix=".php">../core/tests</directory>
		</testsuite>
		<testsuite name="packages">
			<directory suffix=".php">../packages/*/tests</directory>
		</testsuite>
		<testsuite name="app">
			<directory suffix=".php">../app/tests</directory>
		</testsuite>
	</testsuites>
</phpunit>';

        $expected = [
            '@attributes' => [
                'colors' => 'true',
                'stopOnFailure' => 'false',
                'bootstrap' => 'bootstrap_phpunit.php',
            ],
            'php' => [
                'server' => [
                    0 => [
                        '@attributes' => [
                            'name' => 'doc_root',
                            'value' => '../../',
                        ],
                    ],
                    1 => [
                        '@attributes' => [
                            'name' => 'app_path',
                            'value' => 'fuel/app',
                        ],
                    ],
                    2 => [
                        '@attributes' => [
                            'name' => 'core_path',
                            'value' => 'fuel/core',
                        ],
                    ],
                    3 => [
                        '@attributes' => [
                            'name' => 'package_path',
                            'value' => 'fuel/packages',
                        ],
                    ],
                ],
            ],
            'testsuites' => [
                'testsuite' => [
                    0 => [
                        '@attributes' => [
                            'name' => 'core',
                        ],
                        'directory' => '../core/tests',
                    ],
                    1 => [
                        '@attributes' => [
                            'name' => 'packages',
                        ],
                        'directory' => '../packages/*/tests',
                    ],
                    2 => [
                        '@attributes' => [
                            'name' => 'app',
                        ],
                        'directory' => '../app/tests',
                    ],
                ],
            ],
        ];

        $this->assertEquals(Format::forge($expected)->to_php(), Format::forge($xml, 'xml')->to_php());
    }

    /**
     * Test for Format::forge(null)->to_array()
     *
     * @test
     */
    public function test_to_array_empty()
    {
        $array = null;
        $expected = [];
        $this->assertEquals($expected, Format::forge($array)->to_array());
    }

    /**
     * Test for Format::forge($foo)->to_xml()
     *
     * @test
     */
    public function test_to_xml()
    {
        $array = [
            'articles' => [
                [
                    'title' => 'test',
                    'author' => 'foo',
                ],
            ],
        ];

        $expected = '<?xml version="1.0" encoding="utf-8"?>
<xml><articles><article><title>test</title><author>foo</author></article></articles></xml>
';

        $this->assertEquals($expected, Format::forge($array)->to_xml());
    }

    /**
     * Test for Format::forge($foo)->to_xml(null, null, 'root')
     *
     * @test
     */
    public function test_to_xml_basenode()
    {
        $array = [
            'articles' => [
                [
                    'title' => 'test',
                    'author' => 'foo',
                ],
            ],
        ];

        $expected = '<?xml version="1.0" encoding="utf-8"?>
<root><articles><article><title>test</title><author>foo</author></article></articles></root>
';

        $this->assertEquals($expected, Format::forge($array)->to_xml(null, null, 'root'));
    }

    /**
     * Test for Format::forge($foo)->to_xml() espace tags
     *
     * @test
     */
    public function test_to_xml_escape_tags()
    {
        $array = [
            'articles' => [
                [
                    'title' => 'test',
                    'author' => '<h1>hero</h1>',
                ],
            ],
        ];

        $expected = '<?xml version="1.0" encoding="utf-8"?>
<xml><articles><article><title>test</title><author>&lt;h1&gt;hero&lt;/h1&gt;</author></article></articles></xml>
';

        $this->assertEquals($expected, Format::forge($array)->to_xml());
    }

    /**
     * Test for Format::forge($foo)->to_xml(null, null, null, null, true)
     *
     * @test
     * @dataProvider array_provider5
     */
    public function test_to_xml_boolean($array, $default, $default_libxml277, $true, $number)
    {
        // default
        if (LIBXML_VERSION >= 20708) {
            // libxml v2.7.8 and later
            $this->assertEquals($default, Format::forge($array)->to_xml());
        } else {
            // libxml v2.7.7 and before
            $this->assertEquals($default_libxml277, Format::forge($array)->to_xml());
        }

        // true/false
        $this->assertEquals($true, Format::forge($array)->to_xml(null, null, null, null, true));
        // 1/0
        $this->assertEquals($number, Format::forge($array)->to_xml(null, null, null, null, 1));
    }

    /**
     * Test for Format::forge($foo)->to_xml(null, null, 'xml', true)
     *
     * @test
     */
    public function test_to_xml_cdata()
    {
        $array = [
            'articles' => [
                [
                    'title' => 'test',
                    'author' => '<h1>hero</h1>',
                ],
            ],
        ];

        $expected = '<?xml version="1.0" encoding="utf-8"?>
<xml><articles><article><title>test</title><author><![CDATA[<h1>hero</h1>]]></author></article></articles></xml>
';

        $this->assertEquals($expected, Format::forge($array)->to_xml(null, null, 'xml', true));
    }

    /**
     * Test for Format::forge($namespaced_xml, 'xml')->to_array()
     *
     * @test
     */
    public function test_namespaced_xml()
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?>
<xml xmlns="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/" xmlns:app="http://www.w3.org/2007/app"><article><title>test</title><app:title>app test</app:title></article></xml>';

        $data = Format::forge($xml, 'xml')->to_array();

        $expected = [
            'article' => [
                'title' => 'test',
            ],
        ];

        $this->assertEquals($expected, $data);
    }

    /**
     * Test for Format::forge($namespaced_xml, 'xml:ns')->to_array()
     *
     * @test
     */
    public function test_namespaced_xml_and_include_xmlns()
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?>
<xml xmlns="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/" xmlns:app="http://www.w3.org/2007/app"><article><title>test</title><app:title>app test</app:title></article></xml>';

        $data = Format::forge($xml, 'xml:ns')->to_array();

        $expected = [
            '@attributes' => [
                'xmlns' => 'http://www.w3.org/2005/Atom',
                'xmlns:media' => 'http://search.yahoo.com/mrss/',
                'xmlns:app' => 'http://www.w3.org/2007/app',
            ],
            'article' => [
                'title' => 'test',
                'app:title' => 'app test',
            ],
        ];

        $this->assertEquals($expected, $data);
    }

    /**
     * Test for Format::forge($foo)->to_json()
     *
     * @test
     */
    public function test_to_json()
    {
        $array = [
            'articles' => [
                [
                    'title' => 'test',
                    'author' => 'foo',
                    'tag' => '<tag>',
                    'apos' => 'McDonald\'s',
                    'quot' => '"test"',
                    'amp' => 'M&M',

                ],
            ],
        ];

        $expected = '{"articles":[{"title":"test","author":"foo","tag":"\u003Ctag\u003E","apos":"McDonald\u0027s","quot":"\u0022test\u0022","amp":"M\u0026M"}]}';

        $this->assertEquals($expected, Format::forge($array)->to_json());

        // pretty json
        $expected = '{
	"articles": [
		{
			"title": "test",
			"author": "foo",
			"tag": "\u003Ctag\u003E",
			"apos": "McDonald\u0027s",
			"quot": "\u0022test\u0022",
			"amp": "M\u0026M"
		}
	]
}';
        $this->assertEquals($expected, Format::forge($array)->to_json(null, true));

        // change config options
        $config = \Config::get('format.json.encode.options');
        \Config::set('format.json.encode.options', 0);

        $expected = <<<EOD
{"articles":[{"title":"test","author":"foo","tag":"<tag>","apos":"McDonald's","quot":"\"test\"","amp":"M&M"}]}
EOD;
        $this->assertEquals($expected, Format::forge($array)->to_json());

        // pretty json
        $expected = <<<EOD
{
	"articles": [
		{
			"title": "test",
			"author": "foo",
			"tag": "<tag>",
			"apos": "McDonald's",
			"quot": "\"test\"",
			"amp": "M&M"
		}
	]
}
EOD;
        $this->assertEquals($expected, Format::forge($array)->to_json(null, true));

        // restore config options
        \Config::set('format.json.encode.options', $config);
    }
}
