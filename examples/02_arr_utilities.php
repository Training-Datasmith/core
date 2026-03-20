<?php

declare(strict_types=1);

/**
 * Example: FuelPHP Core Arr class — utility methods for array manipulation.
 *
 * Arr provides dot-notation access, filtering, sorting, flattening, and more.
 * It is one of the most-used utility classes in FuelPHP applications.
 *
 * This file can be run standalone (no bootstrap required for pure Arr logic)
 * if you load the class directly.
 */

use Fuel\Core\Arr;

// ─── Dot-notation access ─────────────────────────────────────────────────────

$data = [
    'user' => [
        'name'    => 'Alice',
        'address' => ['city' => 'Paris', 'zip' => '75001'],
    ],
    'roles' => ['admin', 'editor'],
];

echo Arr::get($data, 'user.name')           . "\n"; // "Alice"
echo Arr::get($data, 'user.address.city')   . "\n"; // "Paris"
echo Arr::get($data, 'user.phone', 'N/A')   . "\n"; // "N/A" (default)

Arr::set($data, 'user.email', 'alice@example.com');
echo Arr::get($data, 'user.email')          . "\n"; // "alice@example.com"

Arr::delete($data, 'user.address.zip');
var_dump(Arr::key_exists($data, 'user.address.zip')); // false

// ─── Pluck values from a collection ─────────────────────────────────────────

$products = [
    ['id' => 1, 'name' => 'Widget A', 'price' => 9.99],
    ['id' => 2, 'name' => 'Widget B', 'price' => 14.99],
    ['id' => 3, 'name' => 'Widget C', 'price' => 4.99],
];

$names  = Arr::pluck($products, 'name');
// ['Widget A', 'Widget B', 'Widget C']

$priceById = Arr::pluck($products, 'price', 'id');
// [1 => 9.99, 2 => 14.99, 3 => 4.99]

// ─── Sorting ─────────────────────────────────────────────────────────────────

$sorted = Arr::sort($products, 'price', 'asc');
// Sorted ascending by price: Widget C, Widget A, Widget B

// ─── Flatten / reverse flatten ───────────────────────────────────────────────

$nested = ['a' => ['b' => ['c' => 'deep']], 'x' => 'top'];
$flat   = Arr::flatten($nested);
// ['a:b:c' => 'deep', 'x' => 'top']

$restored = Arr::reverse_flatten($flat);
// ['a' => ['b' => ['c' => 'deep']], 'x' => 'top']

// ─── Filter by key prefix ────────────────────────────────────────────────────

$config = [
    'db_host'     => 'localhost',
    'db_name'     => 'myapp',
    'cache_driver'=> 'redis',
];

$dbConfig = Arr::filter_prefixed($config, 'db_');
// ['host' => 'localhost', 'name' => 'myapp']

// ─── Assoc ↔ keyval conversion ───────────────────────────────────────────────

$table = [
    ['code' => 'USD', 'name' => 'US Dollar'],
    ['code' => 'EUR', 'name' => 'Euro'],
];

$map = Arr::assoc_to_keyval($table, 'code', 'name');
// ['USD' => 'US Dollar', 'EUR' => 'Euro']

$back = Arr::keyval_to_assoc($map, 'code', 'name');
// [['code' => 'USD', 'name' => 'US Dollar'], ...]

// ─── Merge (preserves numeric keys) ─────────────────────────────────────────

$base     = ['a' => 1, 'b' => ['x' => 10]];
$override = ['b' => ['y' => 20], 'c' => 3];

$merged = Arr::merge($base, $override);
// ['a' => 1, 'b' => ['x' => 10, 'y' => 20], 'c' => 3]
