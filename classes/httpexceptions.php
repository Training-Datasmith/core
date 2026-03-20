<?php

declare (strict_types=1);
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

class Http_Bad_Request_Exception extends Http_Exception
{
    public function response()
    {
        return new \Response(\View::forge('400'), 400);
    }
}
class Http_No_Access_Exception extends Http_Exception
{
    public function response()
    {
        return new \Response(\View::forge('403'), 403);
    }
}
class Http_Not_Found_Exception extends Http_Exception
{
    public function response()
    {
        return new \Response(\View::forge('404'), 404);
    }
}
class Http_Server_Error_Exception extends Http_Exception
{
    public function response()
    {
        return new \Response(\View::forge('500'), 500);
    }
}