<?php

declare(strict_types=1);

/**
 * Example: FuelPHP Core — Response building and route matching.
 *
 * This example shows how to construct HTTP responses and how routes compile
 * and match URIs. These are the two most fundamental components in Fuel\Core.
 *
 * Requires the FuelPHP bootstrap (bootstrap.php) to run.
 */

use Fuel\Core\Response;
use Fuel\Core\Route;

// ─── Response ────────────────────────────────────────────────────────────────

// 1. Simple 200 response
$r = Response::forge('Hello, World!');
echo $r->body()  . "\n"; // "Hello, World!"
echo $r->status  . "\n"; // 200

// 2. Response with custom headers
$json = Response::forge(
    json_encode(['ok' => true]),
    200,
    ['Content-Type' => 'application/json']
);
$json->send_headers(); // Sends HTTP headers if headers not yet sent
echo $json . "\n";

// 3. Add headers fluently (chainable)
$response = Response::forge('Created', 201);
$response->set_header('Location', '/api/resource/42')
         ->set_header('X-Request-Id', uniqid());

// 4. Check a status message
echo Response::$statuses[404] . "\n"; // "Not Found"
echo Response::$statuses[429] . "\n"; // "Too Many Requests"

// 5. Redirect (exits — uncomment in a real controller)
// Response::redirect('/login', 'location', 302);

// ─── Route ───────────────────────────────────────────────────────────────────

// 6. Simple static route
$route = new Route('about/team', 'pages/team');
// Matches URI "about/team" → segments ['pages', 'team']

// 7. Route with named parameter
$route = new Route('user/:id', 'users/show/$1');
// Matches "user/42" → named_params['id'] = '42'

// 8. Route with type constraints
$route = new Route('articles/:num/comments/:num', 'articles/comments/$1/$2');
// Matches "articles/5/comments/12"

// 9. Wildcard route
$route = new Route('api/:any', 'api/proxy/$1');
// Matches "api/v2/products/list"

// 10. Check how a route compiles (debug)
// Routes are compiled internally to regex; :num → [[:digit:]]+, :alpha → [[:alpha:]]+
