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

abstract class Controller
{
    /**
     * @var  Request  The current Request object
     */
    public $request;

    /**
     * @var  Integer  The default response status
     */
    public $response_status = 200;

    /**
     * Sets the controller request object.
     *
     * @param   \Request $request  The current request object
     */
    public function __construct(\Request $request)
    {
        $this->request = $request;
    }

    /**
     * Runs before the action method is called.
     *
     * Override in subclasses to set up authentication checks, load shared data,
     * or run any pre-action logic. Return value is ignored.
     *
     * @return void
     */
    public function before(): void
    {
    }

    /**
     * Runs after the action method is called and wraps the result in a Response.
     *
     * If $response is not already a Response instance, it is passed to
     * Response::forge() with the controller's default status code.
     *
     * @param  \Response|string|null  $response  The action's return value.
     * @return \Response                          Always returns a Response instance.
     */
    public function after(\Response|string|null $response): \Response
    {
        // Make sure the $response is a Response object
        if (! $response instanceof Response) {
            return \Response::forge($response, $this->response_status);
        }

        return $response;
    }

    /**
     * Returns a named route parameter, or all route parameters if no name is given.
     *
     * @param  string  $param    The name of the named route parameter.
     * @param  mixed   $default  Value to return when the parameter is not present.
     * @return mixed             The parameter value, or $default.
     */
    public function param(string $param, mixed $default = null): mixed
    {
        return $this->request->param($param, $default);
    }

    /**
     * Returns all named route parameters as an associative array.
     *
     * @return array<string, mixed>  All named parameters from the matched route.
     */
    public function params(): array
    {
        return $this->request->params();
    }
}
