<?php
/**
 * Fuel is a fast, lightweight, community driven PHP 5.4+ framework.
 *
 * @package    Fuel
 * @version    1.8.2
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2019 Fuel Development Team
 * @copyright  2008 - 2009 Kohana Team
 * @link       https://fuelphp.com
 */

namespace Fuel\Core;

class Database_Expression implements \Stringable
{
	/**
     * Sets the expression string.
     *
     *     $expression = new Database_Expression('COUNT(users.id)');
     *
     * @param string $_value expression string
     */
    public function __construct(protected $_value)
    {
    }

	/**
     * Get the expression value as a string.
     *
     *     $sql = $expression->value();
     */
    public function value(): string
	{
		return (string) $this->_value;
	}

	/**
     * Return the value of the expression as a string.
     *
     *     echo $expression;
     *
     * @uses    Database_Expression::value
     */
    public function __toString(): string
	{
		return $this->value();
	}

}
