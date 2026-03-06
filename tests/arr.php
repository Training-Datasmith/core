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
 * Arr class tests
 *
 * @group Core
 * @group Arr
 */
class Test_Arr extends TestCase
{
    public static function person_provider()
    {
        return [
            [
                [
                    'name' => 'Jack',
                    'age' => '21',
                    'weight' => 200,
                    'location' => [
                        'city' => 'Pittsburgh',
                        'state' => 'PA',
                        'country' => 'US',
                    ],
                ],
            ],
        ];
    }

    public static function collection_provider()
    {
        $object = new \stdClass();
        $object->id = 7;
        $object->name = 'Bert';
        $object->surname = 'Visser';

        return [
            [
                [
                    [
                        'id' => 2,
                        'name' => 'Bill',
                        'surname' => 'Cosby',
                    ],
                    [
                        'id' => 5,
                        'name' => 'Chris',
                        'surname' => 'Rock',
                    ],
                    $object,
                ],
            ],
        ];
    }

    /**
     * Test Arr::pluck()
     *
     * @test
     * @dataProvider collection_provider
     */
    public function test_pluck($collection)
    {
        $output = \Arr::pluck($collection, 'id');
        $expected = [2, 5, 7];
        $this->assertEquals($expected, $output);
    }

    /**
     * Test Arr::pluck()
     *
     * @test
     * @dataProvider collection_provider
     */
    public function test_pluck_with_index($collection)
    {
        $output = \Arr::pluck($collection, 'name', 'id');
        $expected = [2 => 'Bill', 5 => 'Chris', 7 => 'Bert'];
        $this->assertEquals($expected, $output);
    }

    /**
     * Tests Arr::assoc_to_keyval()
     *
     * @test
     */
    public function test_assoc_to_keyval()
    {
        $assoc = [
            [
                'color' => 'red',
                'rank' => 4,
                'name' => 'Apple',
                ],
            [
                'color' => 'yellow',
                'rank' => 3,
                'name' => 'Banana',
                ],
            [
                'color' => 'purple',
                'rank' => 2,
                'name' => 'Grape',
                ],
            ];

        $expected = [
            'red' => 'Apple',
            'yellow' => 'Banana',
            'purple' => 'Grape',
            ];
        $output = Arr::assoc_to_keyval($assoc, 'color', 'name');
        $this->assertEquals($expected, $output);
    }

    /**
     * Tests Arr::keyval_to_assoc()
     *
     * @test
     */
    public function test_keyval_to_assoc()
    {
        $keyval = [
            'red' => 'Apple',
            'yellow' => 'Banana',
            'purple' => 'Grape',
            ];

        $expected = [
            [
                'color' => 'red',
                'name' => 'Apple',
                ],
            [
                'color' => 'yellow',
                'name' => 'Banana',
                ],
            [
                'color' => 'purple',
                'name' => 'Grape',
                ],
            ];

        $output = Arr::keyval_to_assoc($keyval, 'color', 'name');
        $this->assertEquals($expected, $output);
    }

    /**
     * Tests Arr::key_exists()
     *
     * @test
     * @dataProvider person_provider
     */
    public function test_key_exists_with_key_found($person)
    {
        $expected = true;
        $output = Arr::key_exists($person, 'name');
        $this->assertEquals($expected, $output);
    }

    /**
     * Tests Arr::key_exists()
     *
     * @test
     * @dataProvider person_provider
     */
    public function test_key_exists_with_key_not_found($person)
    {
        $expected = false;
        $output = Arr::key_exists($person, 'unknown');
        $this->assertEquals($expected, $output);
    }

    /**
     * Tests Arr::key_exists()
     *
     * @test
     * @dataProvider person_provider
     */
    public function test_key_exists_with_dot_separated_key($person)
    {
        $expected = true;
        $output = Arr::key_exists($person, 'location.city');
        $this->assertEquals($expected, $output);
    }

    /**
     * Tests Arr::get()
     *
     * @test
     * @dataProvider person_provider
     */
    public function test_get_with_element_found($person)
    {
        $expected = 'Jack';
        $output = Arr::get($person, 'name', 'Unknown Name');
        $this->assertEquals($expected, $output);
    }

    /**
     * Tests Arr::get()
     *
     * @test
     * @dataProvider person_provider
     */
    public function test_get_with_element_not_found($person)
    {
        $expected = 'Unknown job';
        $output = Arr::get($person, 'job', 'Unknown job');
        $this->assertEquals($expected, $output);
    }

    /**
     * Tests Arr::get()
     *
     * @test
     * @dataProvider person_provider
     */
    public function test_get_with_dot_separated_key($person)
    {
        $expected = 'Pittsburgh';
        $output = Arr::get($person, 'location.city', 'Unknown City');
        $this->assertEquals($expected, $output);

    }

    /**
     * Tests Arr::get()
     *
     * @test
     * @expectedException InvalidArgumentException
     */
    public function test_get_throws_exception_when_array_is_not_an_array()
    {
        $output = Arr::get('Jack', 'name', 'Unknown Name');
    }

    /**
     * Tests Arr::get()
     *
     * @test
     * @dataProvider person_provider
     */
    public function test_get_when_dot_notated_key_is_not_array($person)
    {
        $expected = 'Unknown Name';
        $output = Arr::get($person, 'foo.first', 'Unknown Name');
        $this->assertEquals($expected, $output);
    }

    /**
     * Tests Arr::get()
     *
     * @test
     * @dataProvider person_provider
     */
    public function test_get_with_all_elements_found($person)
    {
        $expected = [
            'name' => 'Jack',
            'weight' => 200,
        ];
        $output = Arr::get($person, ['name', 'weight'], 'Unknown');
        $this->assertEquals($expected, $output);
    }

    /**
     * Tests Arr::get()
     *
     * @test
     * @dataProvider person_provider
     */
    public function test_get_with_all_elements_not_found($person)
    {
        $expected = [
            'name' => 'Jack',
            'height' => 'Unknown',
        ];
        $output = Arr::get($person, ['name', 'height'], 'Unknown');
        $this->assertEquals($expected, $output);
    }

    /**
     * Tests Arr::get()
     *
     * @test
     * @dataProvider person_provider
     */
    public function test_get_when_keys_is_not_an_array($person)
    {
        $expected = 'Jack';
        $output = Arr::get($person, 'name', 'Unknown');
        $this->assertEquals($expected, $output);
    }

    /**
     * Tests Arr::flatten()
     *
     * @test
     */
    public function test_flatten()
    {
        $indexed = [ ['a'], ['b'], ['c'] ];

        $expected = [
            '0_0' => 'a',
            '1_0' => 'b',
            '2_0' => 'c',
        ];

        $output = Arr::flatten($indexed, '_');
        $this->assertEquals($expected, $output);
    }

    /**
     * Tests Arr::flatten_assoc()
     *
     * @test
     */
    public function test_flatten_assoc()
    {
        $people = [
            [
                'name' => 'Jack',
                'age' => 21,
            ],
            [
                'name' => 'Jill',
                'age' => 23,
            ],
        ];

        $expected = [
            '0:name' => 'Jack',
            '0:age' => 21,
            '1:name' => 'Jill',
            '1:age' => 23,
        ];

        $output = Arr::flatten_assoc($people);
        $this->assertEquals($expected, $output);
    }

    /**
     * Tests Arr::flatten_assoc() with recursive arrays
     *
     * @test
     */
    public function test_flatten_recursive_index()
    {
        $people = [
            [
                'name' => 'Jack',
                'age' => 21,
                'children' => [
                    [
                        'name' => 'Johnny',
                        'age' => 4,
                    ],
                    [
                        'name' => 'Jimmy',
                        'age' => 3,
                    ],
                ],
            ],
            [
                'name' => 'Jill',
                'age' => 23,
            ],
        ];

        $expected = [
            '0:name' => 'Jack',
            '0:age' => 21,
            '0:children:0:name' => 'Johnny',
            '0:children:0:age' => 4,
            '0:children:1:name' => 'Jimmy',
            '0:children:1:age' => 3,
            '1:name' => 'Jill',
            '1:age' => 23,
        ];

        $output = Arr::flatten($people, ':');
        $this->assertEquals($expected, $output);
    }

    /**
     * Tests Arr::merge_assoc()
     *
     * @test
     */
    public function test_merge_assoc()
    {
        $arr1 = [
            'one' => 1,
            2 => 2,
            3 => 3,
            4 => [
                56,
            ],
            5 => 87,
        ];

        $arr2 = [
            1 => 27,
            2 => 90,
            4 => [
                'give_me' => 'bandwidth',
            ],
            6 => '90',
            7 => 'php',
        ];

        $expected = [
            'one' => 1,
            2 => 90,
            3 => 3,
            4 => [
                56,
                'give_me' => 'bandwidth',
            ],
            5 => 87,
            1 => 27,
            6 => '90',
            7 => 'php',
        ];

        $output = Arr::merge_assoc($arr1, $arr2);
        $this->assertEquals($expected, $output);
    }

    /**
     * Tests Arr::insert()
     *
     * @test
     */
    public function test_insert()
    {
        $people = ['Jack', 'Jill'];

        $expected = ['Humpty', 'Jack', 'Jill'];
        $output = Arr::insert($people, 'Humpty', 0);

        $this->assertEquals(true, $output);
        $this->assertEquals($expected, $people);
    }

    /**
     * Tests Arr::insert()
     *
     * @test
     */
    public function test_insert_with_index_out_of_range()
    {
        $people = ['Jack', 'Jill'];

        $output = Arr::insert($people, 'Humpty', 4);

        $this->assertFalse($output);
    }

    /**
     * Tests Arr::insert_after_key()
     *
     * @test
     */
    public function test_insert_after_key_that_exists()
    {
        $people = ['Jack', 'Jill'];

        $expected = ['Jack', 'Jill', 'Humpty'];
        $output = Arr::insert_after_key($people, 'Humpty', 1);

        $this->assertTrue($output);
        $this->assertEquals($expected, $people);
    }

    /**
     * Tests Arr::insert_after_key()
     *
     * @test
     */
    public function test_insert_after_key_that_does_not_exist()
    {
        $people = ['Jack', 'Jill'];
        $output = Arr::insert_after_key($people, 'Humpty', 6);
        $this->assertFalse($output);
    }

    /**
     * Tests Arr::insert_after_value()
     *
     * @test
     */
    public function test_insert_after_value_that_exists()
    {
        $people = ['Jack', 'Jill'];
        $expected = ['Jack', 'Humpty', 'Jill'];
        $output = Arr::insert_after_value($people, 'Humpty', 'Jack');
        $this->assertTrue($output);
        $this->assertEquals($expected, $people);
    }

    /**
     * Tests Arr::insert_after_value()
     *
     * @test
     */
    public function test_insert_after_value_that_does_not_exists()
    {
        $people = ['Jack', 'Jill'];
        $output = Arr::insert_after_value($people, 'Humpty', 'Joe');
        $this->assertFalse($output);
    }

    /**
     * Tests Arr::average()
     *
     * @test
     */
    public function test_average()
    {
        $arr = [13, 8, 6];
        $this->assertEquals(9, Arr::average($arr));
    }

    /**
     * Tests Arr::average()
     *
     * @test
     */
    public function test_average_of_empty_array()
    {
        $arr = [];
        $this->assertEquals(0, Arr::average($arr));
    }

    /**
     * Tests Arr::filter_prefixed()
     *
     * @test
     */
    public function test_filter_prefixed()
    {
        $arr = ['foo' => 'baz', 'prefix_bar' => 'yay'];

        $output = Arr::filter_prefixed($arr, 'prefix_');
        $this->assertEquals(['bar' => 'yay'], $output);
    }

    /**
     * Tests Arr::sort()
     *
     * @test
     * @expectedException InvalidArgumentException
     */
    public function test_sort_of_non_array()
    {
        $sorted = Arr::sort('not an array', 'foo.key');
    }

    public function sort_provider()
    {
        return [
            [
                // Unsorted Array
                [
                    [
                        'info' => [
                            'pet' => [
                                'type' => 'dog',
                            ],
                        ],
                    ],
                    [
                        'info' => [
                            'pet' => [
                                'type' => 'fish',
                            ],
                        ],
                    ],
                    [
                        'info' => [
                            'pet' => [
                                'type' => 'cat',
                            ],
                        ],
                    ],
                ],

                // Sorted Array
                [
                    [
                        'info' => [
                            'pet' => [
                                'type' => 'cat',
                            ],
                        ],
                    ],
                    [
                        'info' => [
                            'pet' => [
                                'type' => 'dog',
                            ],
                        ],
                    ],
                    [
                        'info' => [
                            'pet' => [
                                'type' => 'fish',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Tests Arr::sort()
     *
     * @test
     * @dataProvider sort_provider
     */
    public function test_sort_asc($data, $expected)
    {
        $this->assertEquals($expected, Arr::sort($data, 'info.pet.type', 'asc'));
    }

    /**
     * Tests Arr::sort()
     *
     * @test
     * @dataProvider sort_provider
     */
    public function test_sort_desc($data, $expected)
    {
        $expected = array_reverse($expected);
        $this->assertEquals($expected, Arr::sort($data, 'info.pet.type', 'desc'));
    }

    /**
     * Tests Arr::sort()
     *
     * @test
     * @dataProvider sort_provider
     * @expectedException InvalidArgumentException
     */
    public function test_sort_invalid_direction($data, $expected)
    {
        $this->assertEquals($expected, Arr::sort($data, 'info.pet.type', 'downer'));
    }

    public function test_sort_empty()
    {
        $expected = [];
        $output = Arr::sort([], 'test', 'test');
        $this->assertEquals($expected, $output);
    }

    /**
     * Tests Arr::filter_keys()
     *
     * @test
     */
    public function test_filter_keys()
    {
        $data = [
            'epic' => 'win',
            'weak' => 'sauce',
            'foo' => 'bar',
        ];
        $expected = [
            'epic' => 'win',
            'foo' => 'bar',
        ];
        $expected_remove = [
            'weak' => 'sauce',
        ];
        $keys = ['epic', 'foo'];
        $this->assertEquals($expected, Arr::filter_keys($data, $keys));
        $this->assertEquals($expected_remove, Arr::filter_keys($data, $keys, true));
    }

    /**
     * Tests Arr::to_assoc()
     *
     * @test
     */
    public function test_to_assoc_with_even_number_of_elements()
    {
        $arr = ['foo', 'bar', 'baz', 'yay'];
        $expected = ['foo' => 'bar', 'baz' => 'yay'];
        $this->assertEquals($expected, Arr::to_assoc($arr));
    }

    /**
     * Tests Arr::to_assoc()
     *
     * @test
     * @expectedException BadMethodCallException
     */
    public function test_to_assoc_with_odd_number_of_elements()
    {
        $arr = ['foo', 'bar', 'baz'];
        Arr::to_assoc($arr);
    }

    /**
     * Tests Arr::prepend()
     *
     * @test
     */
    public function test_prepend()
    {
        $arr = [
            'two' => 2,
            'three' => 3,
        ];
        $expected = [
            'one' => 1,
            'two' => 2,
            'three' => 3,
        ];
        Arr::prepend($arr, 'one', 1);
        $this->assertEquals($expected, $arr);
    }

    /**
     * Tests Arr::prepend()
     *
     * @test
     */
    public function test_prepend_array()
    {
        $arr = [
            'two' => 2,
            'three' => 3,
        ];
        $expected = [
            'one' => 1,
            'two' => 2,
            'three' => 3,
        ];
        Arr::prepend($arr, ['one' => 1]);
        $this->assertEquals($expected, $arr);
    }

    /**
     * Tests Arr::is_multi()
     *
     * @test
     */
    public function test_multidimensional_array()
    {
        // Single array
        $arr_single = ['one' => 1, 'two' => 2];
        $this->assertFalse(Arr::is_multi($arr_single));

        // Multi-dimensional array
        $arr_multi = ['one' => ['test' => 1], 'two' => ['test' => 2], 'three' => ['test' => 3]];
        $this->assertTrue(Arr::is_multi($arr_multi));

        // Multi-dimensional array (not all elements are arrays)
        $arr_multi_strange = ['one' => ['test' => 1], 'two' => ['test' => 2], 'three' => 3];
        $this->assertTrue(Arr::is_multi($arr_multi_strange, false));
        $this->assertFalse(Arr::is_multi($arr_multi_strange, true));
    }

    /**
     * Tests Arr::search()
     *
     * @test
     */
    public function test_search_single_array()
    {
        // Single array
        $arr_single = ['one' => 1, 'two' => 2];
        $expected = 'one';
        $this->assertEquals($expected, Arr::search($arr_single, 1));

        // Default
        $expected = null;
        $this->assertEquals($expected, Arr::search($arr_single, 3));
        $expected = 'three';
        $this->assertEquals($expected, Arr::search($arr_single, 3, 'three'));

        // Single array (int key)
        $arr_single = [0 => 'zero', 'one' => 1, 'two' => 2];
        $expected = 0;
        $this->assertEquals($expected, Arr::search($arr_single, 0));
    }

    /**
     * Tests Arr::search()
     *
     * @test
     */
    public function test_search_multi_array()
    {
        // Multi-dimensional array
        $arr_multi = ['one' => ['test' => 1], 'two' => ['test' => 2], 'three' => ['test' => ['a' => 'a', 'b' => 'b']]];
        $expected = 'one';
        $this->assertEquals($expected, Arr::search($arr_multi, ['test' => 1], null, false));
        $expected = null;
        $this->assertEquals($expected, Arr::search($arr_multi, 1, null, false));

        // Multi-dimensional array (recursive)
        $expected = 'one.test';
        $this->assertEquals($expected, Arr::search($arr_multi, 1));

        $expected = 'three.test.b';
        $this->assertEquals($expected, Arr::search($arr_multi, 'b', null, true));
    }

    /**
     * Tests Arr::sum()
     *
     * @test
     */
    public function test_sum_multi_array()
    {
        $arr_multi = [
            [
                'name' => 'foo',
                'scores' => [
                    'sports' => 5,
                    'math' => 20,
                ],
            ],
            [
                'name' => 'bar',
                'scores' => [
                    'sports' => 7,
                    'math' => 15,
                ],
            ],
            [
                'name' => 'fuel',
                'scores' => [
                    'sports' => 8,
                    'math' => 5,
                ],
            ],
            [
                'name' => 'php',
                'scores' => [
                    'math' => 10,
                ],
            ],
        ];

        $expected = 50;
        $test = \Arr::sum($arr_multi, 'scores.math');
        $this->assertEquals($expected, $test);

        $expected = 20;
        $test = \Arr::sum($arr_multi, 'scores.sports');
        $this->assertEquals($expected, $test);
    }

    /**
     * Tests Arr::previous_by_key()
     *
     * @test
     */
    public function test_previous_by_key()
    {
        // our test array
        $arr = [2 => 'A', 4 => 'B', 6 => 'C'];

        // test: key not found in array
        $expected = false;
        $test = \Arr::previous_by_key($arr, 1);
        $this->assertTrue($expected === $test);

        // test: no previous key
        $expected = null;
        $test = \Arr::previous_by_key($arr, 2);
        $this->assertTrue($expected === $test);

        // test: strict key comparison
        $expected = false;
        $test = \Arr::previous_by_key($arr, '2', false, true);
        $this->assertTrue($expected === $test);

        // test: get previous key
        $expected = 2;
        $test = \Arr::previous_by_key($arr, 4);
        $this->assertTrue($expected === $test);

        // test: get previous value
        $expected = 'A';
        $test = \Arr::previous_by_key($arr, 4, true);
        $this->assertTrue($expected === $test);
    }

    /**
     * Tests Arr::next_by_key()
     *
     * @test
     */
    public function test_next_by_key()
    {
        // our test array
        $arr = [2 => 'A', 4 => 'B', 6 => 'C'];

        // test: key not found in array
        $expected = false;
        $test = \Arr::next_by_key($arr, 1);
        $this->assertTrue($expected === $test);

        // test: no next key
        $expected = null;
        $test = \Arr::next_by_key($arr, 6);
        $this->assertTrue($expected === $test);

        // test: strict key comparison
        $expected = false;
        $test = \Arr::next_by_key($arr, '6', false, true);
        $this->assertTrue($expected === $test);

        // test: get next key
        $expected = 6;
        $test = \Arr::next_by_key($arr, 4);
        $this->assertTrue($expected === $test);

        // test: get next value
        $expected = 'C';
        $test = \Arr::next_by_key($arr, 4, true);
        $this->assertTrue($expected === $test);
    }

    /**
     * Tests Arr::previous_by_value()
     *
     * @test
     */
    public function test_previous_by_value()
    {
        // our test array
        $arr = [2 => 'A', 4 => '2', 6 => 'C'];

        // test: value not found in array
        $expected = false;
        $test = \Arr::previous_by_value($arr, 'Z');
        $this->assertTrue($expected === $test);

        // test: no previous value
        $expected = null;
        $test = \Arr::previous_by_value($arr, 'A');
        $this->assertTrue($expected === $test);

        // test: strict value comparison
        $expected = false;
        $test = \Arr::previous_by_value($arr, 2, true, true);
        $this->assertTrue($expected === $test);

        // test: get previous value
        $expected = 'A';
        $test = \Arr::previous_by_value($arr, '2');
        $this->assertTrue($expected === $test);

        // test: get previous key
        $expected = 4;
        $test = \Arr::previous_by_value($arr, 'C', false);
        $this->assertTrue($expected === $test);
    }

    /**
     * Tests Arr::next_by_value()
     *
     * @test
     */
    public function test_next_by_value()
    {
        // our test array
        $arr = [2 => 'A', 4 => '2', 6 => 'C'];

        // test: value not found in array
        $expected = false;
        $test = \Arr::next_by_value($arr, 'Z');
        $this->assertTrue($expected === $test);

        // test: no next value
        $expected = null;
        $test = \Arr::next_by_value($arr, 'C');
        $this->assertTrue($expected === $test);

        // test: strict value comparison
        $expected = false;
        $test = \Arr::next_by_value($arr, 2, true, true);
        $this->assertTrue($expected === $test);

        // test: get next value
        $expected = 'C';
        $test = \Arr::next_by_value($arr, '2');
        $this->assertTrue($expected === $test);

        // test: get next key
        $expected = 4;
        $test = \Arr::next_by_value($arr, 'A', false);
        $this->assertTrue($expected === $test);
    }

    /**
     * Tests Arr::subset()
     *
     * @test
     * @dataProvider person_provider
     */
    public function test_subset_basic_usage($person)
    {
        $expected = [
            'name' => 'Jack',
            'location' => [
                'city' => 'Pittsburgh',
                'state' => 'PA',
                'country' => 'US',
            ],
        ];

        $got = \Arr::subset($person, ['name', 'location']);
        $this->assertEquals($expected, $got);

        $expected = [
            'name' => 'Jack',
            'location' => [
                'country' => 'US',
            ],
        ];

        $got = \Arr::subset($person, ['name', 'location.country']);
        $this->assertEquals($expected, $got);
    }

    /**
     * Tests Arr::subset()
     *
     * @test
     * @dataProvider person_provider
     */
    public function test_subset_missing_items($person)
    {
        $expected = [
            'name' => 'Jack',
            'location' => [
                'street' => null,
                'country' => 'US',
            ],
            'occupation' => null,
        ];

        $got = \Arr::subset($person, ['name', 'location.street', 'location.country', 'occupation']);
        $this->assertEquals($expected, $got);

        $expected = [
            'name' => 'Jack',
            'location' => [
                'street' => 'Unknown',
                'country' => 'US',
            ],
            'occupation' => 'Unknown',
        ];

        $got = \Arr::subset($person, ['name', 'location.street', 'location.country', 'occupation'], 'Unknown');
        $this->assertEquals($expected, $got);
    }

    /**
     * Tests Arr::filter_recursive()
     */
    public function test_filter_recursive()
    {
        $arr = [
            'user_name' => 'John',
            'user_surname' => 'Lastname',
            'info' => [
                0 => [
                    'data' => 'a value',
                ],
                1 => [
                    'data' => '',
                ],
                2 => [
                    'data' => 0,
                ],
            ],
        ];

        $expected = [
            'user_name' => 'John',
            'user_surname' => 'Lastname',
            'info' => [
                0 => [
                    'data' => 'a value',
                ],
            ],
        ];
        $got = \Arr::filter_recursive($arr);
        $this->assertEquals($expected, $got);

        $expected = [
            'user_name' => 'John',
            'user_surname' => 'Lastname',
            'info' => [
                0 => [
                    'data' => 'a value',
                ],
                1 => [
                ],
                2 => [
                    'data' => 0,
                ],
            ],
        ];
        $got = \Arr::filter_recursive(
            $arr,
            function ($item) {
                return $item !== '';
            }
        );
        $this->assertEquals($expected, $got);
    }
}
