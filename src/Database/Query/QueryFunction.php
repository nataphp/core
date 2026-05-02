<?php
/**
 * NataPHP Framework
 *
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @link          http://nataphp.com NataPHP Project
 * @since         NataPHP 1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\Database\Query;

use Nata\Database\Connection;

/**
 * QueryFunction class is responsible to help create SQL functions.
 */
class QueryFunction {

/**
 * SQL expression.
 *
 * @var string
 */
    protected $_expression;

/**
 * Database connection.
 *
 * @var Connection
 */
    protected $_connection;

/**
 * Constructor.
 *
 * @param Connection $connection Database connection
 * @return void
 */
    public function __construct(Connection $connection) {
        $this->_connection = $connection;
    }

/**
 * Returns the SQL AVG() function.
 *
 * @param string $field Field name to calculate average
 * @param string|null $alias Optional alias for the result
 * @return $this
 */
    public function avg($field, $alias = null) {
        $this->_expression = 'AVG(' . $field . ')';
        if ($alias !== null) {
            $this->_expression .= ' AS ' . $alias;
        }
        return $this;
    }

/**
 * Returns the SQL COUNT() function.
 *
 * @param string $field Field name to count (use '*' for all)
 * @param string|null $alias Optional alias for the result
 * @return $this
 */
    public function count($field = '*', $alias = null) {
        $this->_expression = 'COUNT(' . $field . ')';
        if ($alias !== null) {
            $this->_expression .= ' AS ' . $alias;
        }
        return $this;
    }

/**
 * Returns the SQL SUM() function.
 *
 * @param string $field Field name to sum
 * @param string|null $alias Optional alias for the result
 * @return $this
 */
    public function sum($field, $alias = null) {
        $this->_expression = 'SUM(' . $field . ')';
        if ($alias !== null) {
            $this->_expression .= ' AS ' . $alias;
        }
        return $this;
    }

/**
 * Returns the SQL MAX() function.
 *
 * @param string $field Field name to find maximum
 * @param string|null $alias Optional alias for the result
 * @return $this
 */
    public function max($field, $alias = null) {
        $this->_expression = 'MAX(' . $field . ')';
        if ($alias !== null) {
            $this->_expression .= ' AS ' . $alias;
        }
        return $this;
    }

/**
 * Returns the SQL MIN() function.
 *
 * @param string $field Field name to find minimum
 * @param string|null $alias Optional alias for the result
 * @return $this
 */
    public function min($field, $alias = null) {
        $this->_expression = 'MIN(' . $field . ')';
        if ($alias !== null) {
            $this->_expression .= ' AS ' . $alias;
        }
        return $this;
    }

/**
 * __toString method.
 *
 * @return string
 */
    public function __toString() {
        return $this->_expression;
    }

}
