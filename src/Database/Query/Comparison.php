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

use Nata\Database\Query;
use Nata\I18n\Time;
use Nata\Utility\Validation;

/**
 * Comparison class is responsible to dynamically create SQL query parts.
 */
class Comparison {

/**
 * Comparison and logical operators.
 *
 * @const string
 */
    const EQ  = '=';
    const NEQ = '<>';
    const LT  = '<';
    const LTE = '<=';
    const GT  = '>';
    const GTE = '>=';
    const LIKE = 'LIKE';
    const NLIKE = 'NOT LIKE';
    const IN = 'IN';
    const NIN = 'NOT IN';
    const EXISTS = 'EXISTS';
    const NEXISTS = 'NOT EXISTS';
    const ISNULL = 'IS NULL';
    const ISNNULL = 'IS NOT NULL';
    const BTWN = 'BETWEEN';
    const NBTWN = 'NOT BETWEEN';

/**
 * Modes of operation.
 *
 * @var int
 */
    public const PREFIX = 0;
    public const POSTFIX = 1;
    public const SET = 2;

/**
 * Built expression.
 *
 * @var string
 */
    private $_field;

/**
 * Built expression.
 *
 * @var string
 */
    private $_operator = '=';

/**
 * Expression value parameter.
 *
 * @var mixed
 */
    private $_value;

/**
 * Value type.
 *
 * @var string
 */
    private $_type;

/**
 * Mode.
 *
 * @var int
 */
    private $_mode;

/**
 * Built SQL.
 *
 * @var string
 */
    private $_sql;

/**
 * Expression value parameter.
 *
 * @var mixed
 */
    private $_params = [];


/**
 * Constructor.
 *
 * @param string $field Field name
 * @param mixed $value Value
 * @param string $operator Operator
 * @param string $type Type of operation
 * @param int $mode Mode of operation
 */
    public function __construct($field, $value, $operator = '=', $type = null, int $mode = 0) {
        $this->_field = $field;
        $this->_value = $value;
        $this->_operator = $operator;
        $this->_type = $type;
        $this->_mode = $mode;
    }

/**
 * Convert the comparison into an SQL string to be used
 * on a query.
 *
 * @return array
 */
    public function getParams() {
        $this->getSql();
        return $this->_params;
    }

/**
 * Convert the comparison into an SQL string to be used
 * on a query.
 *
 * @return $this
 */
    public function getSql() {
        if ($this->_sql === null) {
            $this->_sql = '';

            $value = $this->_prepareValue($this->_value);

            if ($this->_type === 'unary') {
                $this->_sql = $this->_getUnaryExpression($value);
            } elseif (Validation::notEmpty($value) && !empty($this->_field)) {
                $this->_sql = sprintf('%s %s %s', $this->_field, $this->_operator, $value);
            }

        }

        return $this->_sql;
    }

/**
 * Convert the comparison into an SQL string to be used
 * on a query.
 *
 * @return $this
 */
    protected function _getValueSql($value) {
        if ($value instanceof Query) {
            $this->_params = $this->_value->params();
            return sprintf('%s %s (%s)', $this->_field, $this->_operator, $this->_value->sql());
        }
        return $value;
    }

/**
 * Convert the comparison into an SQL string to be used
 * on a query.
 *
 * @return $this
 */
    protected function _getUnaryExpression($value) {
        if ($this->_mode === static::POSTFIX) {
            $operation = $this->_field . ' ' . $this->_operator;
        } elseif ($this->_mode === static::PREFIX) {
            $operation = $this->_operator . ' ' . $value;
        } else {
            $operation = $this->_operator . ' ' . $value;
        }
        return $operation;
    }

/**
 * Convert the passed argument into an SQL string to be used
 * on a query.
 *
 * @param string|array|Query $value Value to prepare
 * @return string
 */
    protected function _prepareValue($value) {
        if (strpos($this->_operator, 'NULL') !== false) {
            return;
        }

        // Subquery
        if ($value instanceof Query) {
            $this->_mergeParams($value->params());
            $value = '(' . $value->sql() . ')';
        // Case expression
        } elseif ($value instanceof CaseExpression || $value instanceof QueryExpression) {
            $this->_mergeParams($value->getParams());
            $value = $value->getSql();
        // Array
        } elseif (is_array($value)) {
            $value = $this->_setParam($value);
            if (stripos($this->_operator, 'between') !== false) {
                $value = $this->_getForBetween($value);
            } elseif (stripos($this->_operator, 'in') !== false) {
                $value = $this->_getForIn($value);
            }
        } elseif ($value !== null) {
            $this->_setParam($value);
            $value = '?';
        } elseif ($this->_mode === static::SET) {
            $value = 'NULL';
        }

        return $value;
    }

/**
 * Prepare BETWEEN condition.
 *
 * @param array $values Values to compare
 * @return string
 */
    protected function _getForBetween($values) {
        $sql = '';
        $cast = null;
        foreach ($values as $value) {
            if (!empty($sql)) {
                $sql .= ' AND ';
            }

            if ($cast === null) {
                if (Validation::date($value)) {
                    $cast = 'CAST(? AS DATE)';
                } elseif (Validation::datetime($value)) {
                    $cast = 'CAST(? AS DATETIME)';
                }
            }

            $sql .= $cast ? $cast : '?';
        }
        return $sql;
    }

/**
 * Prepare IN condition.
 *
 * @param array $values Values to compare
 * @return string
 */
    protected function _getForIn($value) {
        $sql = null;
        if (!empty($value)) {
            $value = (array)$value;
            $sql = '(' . implode(',', array_fill(0, count($value), '?')) . ')';
        }
        return $sql;
    }

/**
 * Set parameter(s).
 *
 * @param mixed $param Parameter(s) to set
 * @return array Prepared parameters that are set
 */
    protected function _setParam($param) {
        if ($this->_mode === Comparison::POSTFIX) {
            return;
        }

        if (!is_array($param)) {
            $param = [$param];
        }

        foreach ($param as $index => $value) {
            // Boolean
            if (is_bool($value)) {
                $value = $value === true ? 1 : 0;
            // Time
            } elseif ($value instanceof Time) {
                $value = $value->timezone('UTC')->format('Y-m-d H:i:s');
            // Datetime in other formats
            } elseif (Validation::datetime($value)) {
                $value = (new Time($value))->timezone('UTC')->format('Y-m-d H:i:s');
            }
            $param[$index] = $value;
        }

        return $this->_params = array_values($param);
    }

/**
 * Convert the comparison into an SQL string to be used
 * on a query.
 *
 * @return $this
 */
    protected function _mergeParams(array $params) {
        $this->_params = array_merge($this->_params, $params);
    }

/**
 * __toString method.
 *
 * @return string SQL string
 */
    public function __toString() {
        return $this->getSql();
    }

}
