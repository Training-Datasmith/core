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
 * HTTP request method constants as a backed string enum.
 *
 * Replaces the stringly-typed method comparisons used in Route::_parse_search()
 * and Controller_Rest. Use HttpMethod::GET->value (= 'GET') when comparing
 * against $request->get_method().
 *
 * Example:
 *   if ($method === HttpMethod::GET->value) { ... }
 *   // or with match:
 *   match (HttpMethod::from($method)) {
 *       HttpMethod::GET  => $this->action_index(),
 *       HttpMethod::POST => $this->action_create(),
 *   };
 *
 * @since 1.8.2
 */
enum HttpMethod: string
{
    case GET     = 'GET';
    case POST    = 'POST';
    case PUT     = 'PUT';
    case PATCH   = 'PATCH';
    case DELETE  = 'DELETE';
    case HEAD    = 'HEAD';
    case OPTIONS = 'OPTIONS';

    /**
     * Returns true if this method is considered "safe" (read-only, no side effects).
     *
     * Safe methods: GET, HEAD, OPTIONS.
     *
     * @return bool
     */
    public function isSafe(): bool
    {
        return match ($this) {
            self::GET, self::HEAD, self::OPTIONS => true,
            default                              => false,
        };
    }

    /**
     * Returns true if this method is idempotent (repeating it has the same effect).
     *
     * Idempotent methods: GET, HEAD, PUT, DELETE, OPTIONS.
     *
     * @return bool
     */
    public function isIdempotent(): bool
    {
        return match ($this) {
            self::GET, self::HEAD, self::PUT, self::DELETE, self::OPTIONS => true,
            default => false,
        };
    }
}
