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
use Nata\Database\Query\Comparison;
use UnexpectedValueException;
use Closure;
use Nata\Utility\Text;

/**
 * Expression class is responsible to dynamically create SQL query parts.
 */
class QueryExpression {

/**
 * Conjunction.
 *
 * @var string
 */
    private $_conjunction;

/**
 * Query instance.
 *
 * @var \Nata\Database\Query
 */
    private $_query;

/**
 * Conditions.
 *
 * @var array
 */
    private $_conditions = [];

/**
 * Expression value parameters.
 *
 * @var string
 */
    private $_sql;

/**
 * Expression value parameters.
 *
 * @var array
 */
    private $_params = [];


/**
 * Constructor.
 *
 * @param Comparison|QueryExpression|CaseExpression|string|array $conditions Conditions
 * @param \Nata\Database\Connection $connection DB connection.
 */
    public function __construct(Comparison|QueryExpression|CaseExpression|string|array $conditions = [], string $conjunction = 'AND', Query $query = null) {
        $this->_conjunction = strtoupper($conjunction);
        $this->_query = $query;
        if ($conditions) {
            $this->add($conditions);
        }
    }

/**
 * Get/Set conjunction.
 *
 * @param string $conjunction Conjunction
 * @return $this|string Conjunction
 */
    public function conjunction(string $conjunction = null) {
        if ($conjunction === null) {
            return $this->_conjunction;
        }
        $this->_conjunction = $conjunction;
        return $this;
    }

/**
 * Convert clause method arguments to expression instances.
 *
 * @param string|array|Comparison|QueryExpression|CaseExpression $conditions
 * @return QueryExpression
 */
    public function add(string|array|Comparison|QueryExpression|CaseExpression $conditions) {
        $this->_resetSql();
        $this->_conditions[] = $conditions;
        return $this;
    }

/**
 * Creates an equality comparison expression with the given arguments.
 *
 * First argument is considered the left expression and the second is the right expression.
 * When converted to string, it will generated a <left expr> = <right expr>. Example:
 *
 *     [php]
 *     // u.id = ?
 *     $expr->eq('u.id', '?');
 *
 * @param mixed $x The left expression.
 * @param mixed $y The right expression.
 * @return $this
 */
    public function eq($x, $y) {
        return $this->add(new Comparison($x, $y, Comparison::EQ));
    }

/**
 * Creates a non equality comparison expression with the given arguments.
 * First argument is considered the left expression and the second is the right expression.
 * When converted to string, it will generated a <left expr> <> <right expr>. Example:
 *
 *     [php]
 *     // u.id <> 1
 *     $q->where($q->expr()->neq('u.id', '1'));
 *
 * @param mixed $x The left expression.
 * @param mixed $y The right expression.
 * @return $this
 */
    public function neq($x, $y) {
        return $this->add(new Comparison($x, $y, Comparison::NEQ));
    }

/**
 * Creates a lower-than comparison expression with the given arguments.
 * First argument is considered the left expression and the second is the right expression.
 * When converted to string, it will generated a <left expr> < <right expr>. Example:
 *
 *     [php]
 *     // u.id < ?
 *     $q->where($q->expr()->lt('u.id', '?'));
 *
 * @param mixed $x The left expression.
 * @param mixed $y The right expression.
 * @return $this
 */
    public function lt($x, $y) {
        return $this->add(new Comparison($x, $y, Comparison::LT));
    }

/**
 * Creates a lower-than-equal comparison expression with the given arguments.
 * First argument is considered the left expression and the second is the right expression.
 * When converted to string, it will generated a <left expr> <= <right expr>. Example:
 *
 *     [php]
 *     // u.id <= ?
 *     $q->where($q->expr()->lte('u.id', '?'));
 *
 * @param mixed $x The left expression.
 * @param mixed $y The right expression.
 * @return $this
 */
    public function lte($x, $y) {
        return $this->add(new Comparison($x, $y, Comparison::LTE));
    }

/**
 * Creates a greater-than comparison expression with the given arguments.
 * First argument is considered the left expression and the second is the right expression.
 * When converted to string, it will generated a <left expr> > <right expr>. Example:
 *
 *     [php]
 *     // u.id > ?
 *     $q->where($q->expr()->gt('u.id', '?'));
 *
 * @param mixed $x The left expression.
 * @param mixed $y The right expression.
 * @return $this
 */
    public function gt($x, $y) {
        return $this->add(new Comparison($x, $y, Comparison::GT));
    }

/**
 * Creates a greater-than-equal comparison expression with the given arguments.
 * First argument is considered the left expression and the second is the right expression.
 * When converted to string, it will generated a <left expr> >= <right expr>. Example:
 *
 *     [php]
 *     // u.id >= ?
 *     $q->where($q->expr()->gte('u.id', '?'));
 *
 * @param mixed $x The left expression.
 * @param mixed $y The right expression.
 * @return $this
 */
    public function gte($x, $y) {
        return $this->add(new Comparison($x, $y, Comparison::GTE));
    }

/**
 * Uses greater-than-equal and lower-than-equal comparison expression . Example:
 *
 *     [php]
 *     // u.id >= ?
 *     $q->where($q->expr()->between('u.id', 1, 10));
 *
 * @param string $f Field name.
 * @param mixed $x The left expression.
 * @param mixed $y The right expression.
 * @return $this
 */
    public function between($f, $x, $y) {
        return $this->add(new Comparison($f, [$x, $y], Comparison::BTWN));
    }

/**
 * Uses greater-than-equal and lower-than-equal comparison expression . Example:
 *
 *     [php]
 *     // u.id >= ?
 *     $q->where($q->expr()->notBetween('u.id', 1, 10));
 *
 * @param string $f Field name.
 * @param mixed $x The left expression.
 * @param mixed $y The right expression.
 * @return $this
 */
    public function notBetween(string $f, mixed $x, mixed $y) {
        return $this->add(new Comparison($f, [$x, $y], Comparison::NBTWN));
    }

/**
 * Creates an IS NULL expression with the given arguments.
 *
 * @param string $x The field in string format to be restricted by IS NULL.
 * @return $this
 */
    public function isNull(string $x) {
        return $this->add(new Comparison($x, null, 'IS NULL', 'unary', Comparison::POSTFIX));
    }

/**
 * Creates an IS NOT NULL expression with the given arguments.
 *
 * @param string $x The field in string format to be restricted by IS NOT NULL.
 * @return $this
 */
    public function isNotNull(string $x) {
        return $this->add(new Comparison($x, null, 'IS NOT NULL', 'unary', Comparison::POSTFIX));
    }

/**
 * Creates a LIKE() comparison expression with the given arguments.
 *
 * @param string $x Field in string format to be inspected by LIKE() comparison.
 * @param mixed $y Argument to be used in LIKE() comparison.
 * @return $this
 */
    public function like(string $x, mixed $y) {
        return $this->add(new Comparison($x, $y, 'LIKE'));
    }

/**
 * Creates a NOT LIKE() comparison expression with the given arguments.
 *
 * @param string $x Field in string format to be inspected by NOT LIKE() comparison.
 * @param mixed $y Argument to be used in NOT LIKE() comparison.
 * @return $this
 */
    public function notLike(string $x, mixed $y) {
        return $this->add(new Comparison($x, $y, 'NOT LIKE'));
    }

/**
 * Creates a IN () comparison expression with the given arguments.
 *
 * @param string $x The field in string format to be inspected by IN() comparison.
 * @param string|array $y The placeholder or the array of values to be used by IN() comparison.
 * @return $this
 */
    public function in(string $x, string|array $y) {
        return $this->add(new Comparison($x, $y, 'IN'));
    }

/**
 * Creates a NOT IN () comparison expression with the given arguments.
 *
 * @param string $x The field in string format to be inspected by NOT IN() comparison.
 * @param string|array $y The placeholder or the array of values to be used by NOT IN() comparison.
 * @return $this
 */
    public function notIn(string $x, string|array $y) {
        return $this->add(new Comparison($x, $y, 'NOT IN'));
    }

/**
 * Creates a CASE expression.
 * Renders: CASE <field> WHEN <when> THEN <then> ... [ELSE <else|field>] END
 *
 * @param string $field The field used in CASE <field>
 * @param array $map Associative array: whenValue => thenValue
 * @param mixed $else Optional ELSE value; when null, uses the field itself
 * @return $this
 */
    public function addCase(string $field, array $map, mixed $else = null) {
        return $this->add(new CaseExpression($field, $map, $else));
    }

/**
 * @todo
 */
    public function exists(Query $query) {
        return $this->add(new Comparison(null, $query, 'EXISTS', 'unary', Comparison::PREFIX));
    }

/**
 * @todo
 */
    public function notExists(Query $query) {
        return $this->add(new Comparison(null, $query, 'NOT EXISTS', 'unary', Comparison::PREFIX));
    }

/**
 * Returns a new QueryExpression object containing all the conditions passed
 * and set up the conjunction to be "AND"
 *
 * @param \Closure|string|array $conditions to be joined with AND
 * @param array $types TODO
 * @return \Nata\Database\Expression\QueryExpression
 */
    public function and(Closure|string|array $conditions = [], $types = []) {
        return $this->_andOr($conditions, $types, 'AND');
    }

/**
 * Returns a new QueryExpression object containing all the conditions passed
 * and set up the conjunction to be "AND"
 *
 * @param \Closure|string|array $conditions to be joined with OR
 * @param array $types TODO
 * @return \Nata\Database\Expression\QueryExpression
 */
    public function or(Closure|string|array $conditions = [], array $types = []) {
        return $this->_andOr($conditions, $types, 'OR');
    }

/**
 * Returns a new QueryExpression object containing all the conditions passed
 * and set up the conjunction to be "AND"
 *
 * @param \Closure|string|array $conditions to be joined with OR
 * @param array $types TODO
 * @param string $conjunction AND or OR
 * @return \Nata\Database\Expression\QueryExpression
 */
    protected function _andOr(Closure|string|array $conditions, array $types, string $conjunction) {
        if ($conditions instanceof Closure) {
            return $conditions(new self([], $conjunction, $this->_query), $this->_query);
        }
        return new self($conditions, $conjunction, $this->_query);
    }

/**
 * __toString.
 *
 * @return string Condition's SQL string
 */
    public function getSql(): string {
        if ($this->_sql === null) {
            $this->_sql = '';
            $conjunctionSql = $this->_getConjunctionSql();
            foreach ($this->_conditions as $index => $condition) {
                $sql = '';
                if (is_string($condition)) {
                    $this->_sql .= $condition;
                    continue;
                }

                if ($condition instanceof Closure) {
                    $condition = $this->_getClosureExpression($condition);
                }

                if ($condition instanceof QueryExpression || $condition instanceof Comparison || $condition instanceof CaseExpression) {
                    $sql = $condition->getSql();
                    $this->_mergeParams($condition->getParams());
                } else {
                    $sql = $this->_nestedCondition($condition);
                }

                if (empty($sql)) {
                    continue;
                }

                if (!empty($this->_sql)) {
                    $this->_sql .= $conjunctionSql;
                }

                $this->_sql .= $sql;
            }
        }
        return $this->_sql;
    }

/**
 * Convert clause method arguments to expressions.
 *
 * @param array $conditions Nested conditions
 * @return string SQL string
 */
    protected function _nestedCondition(array $condition): string {
        $sql = '';
        $conjunctionSql = $this->_getConjunctionSql();
        foreach ($condition as $index => $arg) {
            $append = '';
            if ($arg instanceof Closure) {
                $conjunction = in_array($index, ['OR', 'AND']) ? $index : 'AND';
                $expr = $this->_getClosureExpression($arg, $conjunction);
                $append .= $this->_wrapSqlExpression($expr);
            } elseif (is_int($index)) {
                $append .= $this->_nestedCondition($arg);
            } elseif (in_array($index, ['OR', 'AND'])) {
                $expr = new QueryExpression($arg, $index, $this->_query);
                $append .= $this->_wrapSqlExpression($expr);
            } else {
                $comparison = $this->_getComparison($index, $arg);
                $append .= $comparison->getSql();
                $this->_mergeParams($comparison->getParams());
            }

            if (empty($append)) {
                continue;
            }

            if (!empty($sql)) {
                $sql .= $conjunctionSql;
            }

            $sql .= $append;
        }
        return $sql;
    }

/**
 * Wrap parenthesis Expression SQL.
 * Check if there's multiple parameters, if so, wrap SQL with parentesis.
 *
 * @param QueryExpression $expr Query expression
 * @return string SQL expression
 */
    protected function _wrapSqlExpression(QueryExpression $expr): string {
        $sql = '';
        if ($expSql = $expr->getSql()) {
            $expParams = $expr->getParams();
            $sql = $expSql;
            if (count($expParams) > 1) {
                $sql = '(' . $expSql . ')';
            }
            $this->_mergeParams($expParams);
        }
        return $sql;
    }

/**
 * Get SQL conjunction.
 *
 * @return string SQL conjunction
 */
    protected function _getConjunctionSql(): string {
        return ' ' . $this->_conjunction . ' ';
    }

/**
 * Convert clause method arguments to expressions.
 *
 * @param array $conditions Nested conditions
 * @return QueryExpression Expression
 */
    protected function _getClosureExpression($closure, $conjunction = 'AND'): QueryExpression {
        $expr = $closure(new QueryExpression([], $conjunction, $this->_query), $this->_query);
        if (!($expr instanceof QueryExpression)) {
            throw new UnexpectedValueException('Closure must return the given QueryExpression instance.');
        }
        return $expr;
    }

/**
 * Return expression parameters.
 *
 * @return array
 */
    public function getParams(): array {
        if ($this->_sql === null) {
            $this->getSql();
        }
        return $this->_params;
    }

/**
 * Get expression operator and field.
 * If no operator will assume '='.
 *
 * @param string $field Field name with Operator
 * @param mixed $value Value
 * @return Comparison Field name and Operator
 */
    protected function _getComparison(string $fieldOperator, mixed $value): Comparison|null {
        $fieldOperator = trim($fieldOperator);

        [$field, $operator] = Text::explode(' ', $fieldOperator, false, 2, '');

        // IN
        if (is_array($value) && stripos($operator, 'in') === false && stripos($operator, 'between') === false) {
            $operator = 'IN';
        // IS
        } elseif ($value === null && $operator && stripos($operator, 'is') !== false) {
            $operator = (!empty($operator) ? $operator . ' ' : '') . 'NULL';
        } elseif ($fieldOperator === 'EXISTS' || $fieldOperator === 'NOT EXISTS') {
            $operator = $fieldOperator;
        }

        switch ($operator) {
            case Comparison::NEQ:
            case '!=':
                return new Comparison($field, $value, Comparison::NEQ);
            case Comparison::LT:
            case Comparison::LTE:
            case Comparison::GT:
            case Comparison::GTE:
            case Comparison::LIKE:
            case Comparison::NLIKE:
            case Comparison::IN:
            case Comparison::NIN:
            case Comparison::BTWN:
            case Comparison::NBTWN:
                return new Comparison($field, $value, $operator);
            case Comparison::ISNULL:
                return new Comparison($field, null, Comparison::ISNULL, 'unary', Comparison::POSTFIX);
            case Comparison::ISNNULL:
                return new Comparison($field, null, Comparison::ISNNULL, 'unary', Comparison::POSTFIX);
            case Comparison::EXISTS:
                return new Comparison(null, $value, Comparison::EXISTS, 'unary', Comparison::PREFIX);
            case Comparison::NEXISTS:
                return new Comparison(null, $value, Comparison::NEXISTS, 'unary', Comparison::PREFIX);
            default:
                return new Comparison($field, $value, Comparison::EQ);
        }

        return null;
    }

/**
 * Quotes a given input parameter.
 *
 * @param mixed $input The parameter to be quoted.
 */
    protected function _mergeParams(array $params): void {
        $this->_params = array_merge($this->_params, $params);
    }

/**
 * Reset generated SQL and set parameters.
 *
 * @return void
 */
    protected function _resetSql(): void {
        $this->_sql = null;
        $this->_params = [];
    }

/**
 * __toString.
 *
 * @return string Condition's SQL string
 */
    public function __toString(): string {
        return $this->getSql();
    }

}
