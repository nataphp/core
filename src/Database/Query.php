<?php
/**
 * NataPHP Framework.
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

namespace Nata\Database;

use Doctrine\DBAL\Query\QueryBuilder;
use Nata\Database\Query\Comparison;
use Nata\Database\Query\QueryExpression;
use Nata\Database\Query\QueryFunction;
use BadMethodCallException;
use Closure;
use InvalidArgumentException;
use Nata\Core\App;
use Nata\Core\Registry;
use Nata\Database\Exception\DatabaseException;
use Nata\Database\Query\ParameterManager;
use Nata\Database\Query\Upsert;
use Nata\Routing\Router;
use ReflectionProperty;

/**
 * Query builder abstraction for Doctrine Query Builder.
 */
class Query {

/**
 * Database connection.
 *
 * @var Connection
 */
    protected $_connection;

/**
 * Query builder.
 *
 * @var \Doctrine\DBAL\Query\QueryBuilder
 */
    protected $_builder;

/**
 * SQL built.
 *
 * @var string
 */
    protected $_sql;

/**
 * Driver name.
 *
 * @var string
 */
    protected $_driverName;

/**
 * Query SQL type.
 *
 * @var int
 */
    protected $_type = 0;

/**
 * Query options.
 *
 * @var array
 */
    protected $_options = [];

/**
 * Table aliases.
 *
 * @var array
 */
    protected $_aliases = [];

/**
 * Parameter manager instance.
 *
 * @var \Nata\Database\Query\ParameterManager
 */
    protected $_parameterManager;

/**
 * Runtime cache holder.
 *
 * @var array
 */
    protected $_cacheConfig;

/**
 * Runtime cache holder.
 *
 * @var array
 */
    protected $_cacheKey;

/**
 * Runtime cache holder.
 *
 * @var array
 */
    protected static $_runtimeCache = [];


/**
 * Constructor.
 *
 * @param Connection|string $connection
 * @return void
 */
    public function __construct($connection) {
        $this->_connection = $connection;
    }

/**
 * Get connection instance.
 *
 * @return Connection
 */
    public function getConnection(): Connection {
        if (!($this->_connection instanceof Connection)) {
            $this->_connection = ConnectionManager::get($this->_connection);
        }
        return $this->_connection;
    }

/**
 * Get SQL type.
 *
 * @return int Type query
 */
    public function getType() {
        return $this->_type;
    }

/**
 * Get SQL type name.
 *
 * @return string Respective query type
 */
    public function getTypeName() {
        switch ($this->getType()) {
            case 1:
                return 'delete';
            case 2:
                return 'update';
            case 3:
                return 'insert';
            case 4:
                return 'upsert';
            default:
                return 'select';
        }
    }

/**
 * Check if query type is given name/constant.
 *
 * @param string|const $type \Nata\Database\Query constant or type name
 * @return bool True or false
 */
    public function is($type) {
        return $this->getTypeName() === $type;
    }

/**
 * Get query expression instance.
 *
 * @return \Nata\Database\Query\QueryExpression
 */
    public function expr() {
        return new QueryExpression;
    }

/**
 * Get function instance.
 *
 * @return \Nata\Database\QueryFunction
 */
    public function func() {
        return new QueryFunction($this->getConnection());
    }

/**
 * Get parameter's manager instance.
 *
 * @return ParameterManager
 */
    public function getParameterManager(): ParameterManager {
        if ($this->_parameterManager === null) {
            $this->_parameterManager = new ParameterManager();
        }
        return $this->_parameterManager;
    }

/**
 * Get/Set SQL parameters.
 *
 * @param string|array $param Null to get, string/array to set
 * @param mixed $value Parameter value to set
 * @return $this|array
 */
    public function params($param = null, $value = null) {
        if ($param === null) {
            return $this->getParameterManager()->get();
        }

        if (func_num_args() === 2) {
            $param = [$param => $value];
        }

        $this->getParameterManager()->clear()->add($param);

        return $this;
    }

/**
 * Add SQL parameters.
 *
 * @param string|array $param Null to get, string/array to set
 * @param mixed $value Parameter value to set
 * @return $this
 */
    public function addParams($param, $value = null) {
        if (func_num_args() === 2) {
            $param = [$param => $value];
        }

        $this->getParameterManager()->add($param);
        return $this;
    }

/**
 * Clear.
 *
 * @param string|array $param Null to get, string/array to set
 * @param mixed $value Parameter value to set
 * @return $this|array
 */
    protected function _clearClause($clause) {
        $builder = $this->_loadBuilder();
        switch ($clause) {
            case 'where':
                $builder->resetWhere();
                break;
            case 'groupBy':
                $builder->resetGroupBy();
                break;
            case 'having':
                $builder->resetHaving();
                break;
            case 'orderBy':
                $builder->resetOrderBy();
                break;
            case 'select':
                $this->_dbalSetPrivate($builder, 'select', []);
                $this->_dbalSetPrivate($builder, 'sql', null);
                break;
            case 'from':
                $this->_dbalSetPrivate($builder, 'from', []);
                $this->_dbalSetPrivate($builder, 'join', []);
                $this->_dbalSetPrivate($builder, 'sql', null);
                break;
            case 'values':
                $this->_dbalSetPrivate($builder, 'values', []);
                $this->_dbalSetPrivate($builder, 'sql', null);
                break;
            default:
                if (method_exists($builder, 'resetQueryPart')) {
                    $builder->resetQueryPart($clause);
                    break;
                }
                throw new BadMethodCallException('Cannot reset query clause: ' . $clause);
        }
        $this->getParameterManager()->clear($clause);
    }

/**
 * @param \Doctrine\DBAL\Query\QueryBuilder $object
 */
    private function _dbalGetPrivate(object $object, string $property): mixed {
        $ref = new ReflectionProperty($object, $property);
        $ref->setAccessible(true);

        return $ref->getValue($object);
    }

/**
 * @param \Doctrine\DBAL\Query\QueryBuilder $object
 */
    private function _dbalSetPrivate(object $object, string $property, mixed $value): void {
        $ref = new ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }

/**
 * Map Doctrine DBAL 4 From value objects to legacy array shape.
 *
 * @param list<\Doctrine\DBAL\Query\From> $fromList
 * @return array<int, array{table: string, alias: ?string}>
 */
    private function _dbalMapFromParts(array $fromList): array {
        $out = [];
        foreach ($fromList as $from) {
            $out[] = ['table' => $from->table, 'alias' => $from->alias];
        }

        return $out;
    }

/**
 * Legacy JOIN map compatible with historical getQueryPart('join') consumers.
 *
 * @param array<string, list<\Doctrine\DBAL\Query\Join>> $joinByAlias
 * @return array<string, list<array{joinType: string, joinTable: string, joinAlias: string, joinCondition: ?string}>>
 */
    private function _dbalLegacyJoinMap(array $joinByAlias): array {
        $legacy = [];
        foreach ($joinByAlias as $parentAlias => $joins) {
            foreach ($joins as $j) {
                $legacy[$parentAlias][] = [
                    'joinType' => strtolower($j->type),
                    'joinTable' => $j->table,
                    'joinAlias' => $j->alias,
                    'joinCondition' => $j->condition,
                ];
            }
        }

        return $legacy;
    }

/**
 * Read query parts in shapes similar to Doctrine DBAL 2/3 QueryBuilder getters.
 *
 * @param \Doctrine\DBAL\Query\QueryBuilder $qb
 * @return array<string, mixed>|mixed
 */
    private function _dbalGetQueryPart(QueryBuilder $qb, ?string $clause = null) {
        if ($clause === null) {
            return [
                'select' => $this->_dbalGetPrivate($qb, 'select'),
                'from' => $this->_dbalMapFromParts($this->_dbalGetPrivate($qb, 'from')),
                'join' => $this->_dbalLegacyJoinMap($this->_dbalGetPrivate($qb, 'join')),
                'where' => $this->_dbalStringifyPredicate($this->_dbalGetPrivate($qb, 'where')),
                'groupBy' => $this->_dbalGetPrivate($qb, 'groupBy'),
                'having' => $this->_dbalStringifyPredicate($this->_dbalGetPrivate($qb, 'having')),
                'orderBy' => $this->_dbalGetPrivate($qb, 'orderBy'),
                'set' => $this->_dbalGetPrivate($qb, 'set'),
                'values' => $this->_dbalGetPrivate($qb, 'values'),
            ];
        }

        return match ($clause) {
            'select' => $this->_dbalGetPrivate($qb, 'select'),
            'from' => $this->_dbalMapFromParts($this->_dbalGetPrivate($qb, 'from')),
            'join' => $this->_dbalLegacyJoinMap($this->_dbalGetPrivate($qb, 'join')),
            'where' => $this->_dbalStringifyPredicate($this->_dbalGetPrivate($qb, 'where')),
            'groupBy' => $this->_dbalGetPrivate($qb, 'groupBy'),
            'having' => $this->_dbalStringifyPredicate($this->_dbalGetPrivate($qb, 'having')),
            'orderBy' => $this->_dbalGetPrivate($qb, 'orderBy'),
            'set' => $this->_dbalGetPrivate($qb, 'set'),
            'values' => $this->_dbalGetPrivate($qb, 'values'),
            default => method_exists($qb, 'getQueryPart')
                ? $qb->getQueryPart($clause)
                : throw new BadMethodCallException('Unsupported query part: ' . $clause),
        };
    }

    private function _dbalStringifyPredicate(mixed $predicate): string {
        if ($predicate === null) {
            return '';
        }

        return (string) $predicate;
    }

/**
 * Apply legacy QueryBuilder::add($clause, ...) behaviour on DBAL 4.
 *
 * @param \Doctrine\DBAL\Query\QueryBuilder $qb
 */
    private function _dbalAdd(QueryBuilder $qb, string $clause, mixed $sqlPart, bool $append): void {
        switch ($clause) {
            case 'select':
                $parts = (array)$sqlPart;
                if (!$append) {
                    if ($parts === []) {
                        $this->_dbalSetPrivate($qb, 'select', []);
                    } else {
                        $qb->select(...$parts);
                    }
                } elseif ($parts !== []) {
                    $qb->addSelect(...$parts);
                }
                break;
            case 'groupBy':
                $parts = (array)$sqlPart;
                if (!$append) {
                    if ($parts === []) {
                        $qb->resetGroupBy();
                    } else {
                        $qb->groupBy(...$parts);
                    }
                } elseif ($parts !== []) {
                    $qb->addGroupBy(...$parts);
                }
                break;
            case 'orderBy':
                $chunk = (string)$sqlPart;
                $current = $append ? $this->_dbalGetPrivate($qb, 'orderBy') : [];
                $current[] = $chunk;
                $this->_dbalSetPrivate($qb, 'orderBy', $current);
                break;
            case 'set':
                $assignment = (string)$sqlPart;
                $current = $append ? $this->_dbalGetPrivate($qb, 'set') : [];
                $current[] = $assignment;
                $this->_dbalSetPrivate($qb, 'set', $current);
                break;
            default:
                if (method_exists($qb, 'add')) {
                    $qb->add($clause, $sqlPart, $append);
                    break;
                }
                throw new BadMethodCallException('Unsupported generic query part: ' . $clause);
        }

        $this->_dbalSetPrivate($qb, 'sql', null);
    }

/**
 * Create a named parameter for given value.
 *
 * @param string|array $param Null to get, string/array to set
 * @return string
 */
    public function createNamedParameter($value) {
        return $this->_loadBuilder()->createNamedParameter($value);
    }

/**
 * Apply the options.
 *
 * @param array $options String/array to set
 * @return $this
 */
    public function applyOptions(array $options) {
        $valid = [
            'fields' => 'select',
            'conditions' => 'where',
            'join' => 'join',
            'order' => 'order',
            'limit' => 'limit',
            'offset' => 'offset',
            'group' => 'group',
            'having' => 'having',
            'contain' => 'contain',
            'page' => 'page',
        ];

        ksort($options);

        foreach ($options as $option => $values) {
            if (isset($valid[$option])) {
                $this->{$valid[$option]}($values);
            } else {
                $this->_options[$option] = $values;
            }
        }

        return $this;
    }

/**
 * Get current options.
 *
 * @return array Options
 */
    public function getOptions() {
        return $this->_options;
    }

/**
 * Get query aliases.
 *
 * @param string $tableName Null to get all, table name to get aliast
 * @return array|string Alias(es)
 */
    public function aliases($tableName = null) {
        if ($tableName === null) {
            return $this->_aliases;
        }
        return isset($this->_aliases[$tableName]) ? $this->_aliases[$tableName] : null;
    }

/**
 * Get aliased field for given table name or
 * extracts the table/alias from FROM clause.
 *
 * @param string $field Field name
 * @param string $table Table name/alias
 * @return string Aliased field
 */
    public function aliasField($field, $table = null) {
        if ($table === null) {
            [$alias] = array_values($this->_aliases);
        } else {
            $alias = $this->aliases($table);
        }

        if ($alias === null || strpos($field, '.') !== false) {
            return $field;
        }

        return $alias . '.' . $field;
    }

/**
 * Get query clause.
 *
 * @param string|null $clause Null to get all, part name to get it
 * @return array|string|int Part parameters
 */
    public function clause($clause = null) {
        $map = [
            'order' => 'orderBy'
        ];

        if ($clause !== null && isset($map[$clause])) {
            $clause = $map[$clause];
        }

        $builder = $this->_loadBuilder();

        return $this->_dbalGetQueryPart($builder, $clause);
    }

/**
 * SELECT parameter for Query Builder.
 *
 * @param array|string $conditions WHERE conditions
 * @return $this
 */
    public function select($select = null) {
        $builder = $this->_loadBuilder();
        if ($select === null) {
            return $this->_dbalGetQueryPart($builder, 'select');
        }

        $this->_clearClause('select');
        $select = is_array($select) ? $select : func_get_args();
        $parts = $this->_selectExpression($select);
        if ($parts !== []) {
            $builder->select(...$parts);
        }

        return $this;
    }

/**
 * Additional SELECT parameter for Query Builder.
 *
 * @param array|string $select SELECT Expression
 * @return $this
 */
    public function addSelect($select) {
        $select = is_array($select) ? $select : func_get_args();
        $parts = $this->_selectExpression($select);
        if ($parts !== []) {
            $this->_loadBuilder()->addSelect(...$parts);
        }
        return $this;
    }

/**
 * Prepare SELECT expression.
 *
 * @todo Class to handle the SELECT expressions
 *
 * @param array|string $fields SELECT fields
 * @return array SELECT Expression
 */
    protected function _selectExpression($selects) {
        foreach ($selects as $key => $select) {
            if ($select instanceof Query) {
                $this->getParameterManager()->add($select->params(), 'select');
                $select = '(' . $select->sql() . ')';
            } elseif ($select instanceof QueryExpression) {
                $select = $select->getSql();
            } elseif ($select instanceof QueryFunction) {
                $select = (string) $select;
            } elseif ($select instanceof Closure) {
                $select = $select($this);
            } elseif (!is_int($key)) {
                $this->getParameterManager()->add($select, 'select');
                $select = '?';
            }

            if (!is_int($key)) {
               $select .= ' AS ' . $key;
            }

            $selects[$key] = $select;
        }
        return array_values($selects);
    }

/**
 * Additional SELECT parameter for Query Builder.
 *
 * @param array|string $fields SELECT fields
 * @return $this
 */
    public function distinct() {
        if (!method_exists($this->_loadBuilder(), 'distinct')) {
            throw new BadMethodCallException('DISTINCT is not supported on Doctrine DBAL 2');
        }
        $this->_loadBuilder()->distinct();
        return $this;
    }

/**
 * Set/Get FROM parameter for Query Builder.
 *
 * @param string $table Table name
 * @param string $alias Table alias
 * @return $this|array
 */
    public function from($table = null, $alias = null) {
        if ($table === null) {
            return $this->_dbalMapFromParts($this->_dbalGetPrivate($this->_loadBuilder(), 'from'));
        }

        if (is_string($table)) {
            $table = $this->_escapeReservedKeyword($table);
            $alias = $this->_escapeReservedKeyword($alias);
            $this->_aliases[$table] = $alias;
        } elseif ($table instanceof Query) {
            $this->getParameterManager()->add($table->params(), 'from');
            $table = '(' . $table->sql() . ')';
        }

        $this->_clearClause('from');
        $this->_loadBuilder()->from($table, $alias);

        return $this;
    }

/**
 * Set FROM parameter for Query Builder.
 *
 * @param string $table Table name
 * @param string $alias Table alias
 * @return $this
 */
    public function addFrom($table, $alias = null) {
        $table = $this->_escapeReservedKeyword($table);
        $alias = $this->_escapeReservedKeyword($alias);
        $this->_aliases[$table] = $alias;
        $this->_loadBuilder()->from($table, $alias);
        return $this;
    }

/**
 * JOIN clause.
 *
 * ### Example
 *
 * ...
 *
 * $query
 *     ->from('users', 'u')
 *     ->join('comments', 'c', 'c.id = u.id');
 *
 * ...
 *
 * $query
 *     ->from('users', 'u')
 *     ->join([
 *        'table' => 'comments',
 *        'alias' => 'c',
 *        'conditions' => 'c.id = u.id',
 *        'type' => 'left'
 *     ]);
 *
 * ...
 *
 * @param string|array $table Table name to join
 * @param string $alias Table alias
 * @param string $conditions Join conditions
 * @param string $type Type of join
 * @return $this
 */
    public function join($table = null, $tableAlias = null, $conditions = null, $type = 'inner') {
        if (func_num_args() === 0) {
            return $this->_dbalLegacyJoinMap($this->_dbalGetPrivate($this->_loadBuilder(), 'join'));
        }

        $validTypes = ['inner', 'left', 'right'];
        if (!in_array($type, $validTypes)) {
            throw new InvalidArgumentException(sprintf(
                "Invalid JOIN type '%s'. Valid types: '%s'",
                $type,
                implode("', '", $validTypes)
            ));
        }

        $join = $table;
        if (!is_array($table)) {
            $join = [
                'table' => $table,
                'alias' => $tableAlias,
                'conditions' => $conditions
            ];
        }

        if (!isset($join['alias']) || empty($join['alias'])) {
            throw new InvalidArgumentException(sprintf(
                "JOIN of type '%s' on table '%s' without alias set.",
                $type,
                $join['table']
            ));
        }

        $fromMapped = $this->_dbalMapFromParts($this->_dbalGetPrivate($this->_loadBuilder(), 'from'));
        if ($fromMapped === []) {
            throw new InvalidArgumentException(sprintf(
                "Missing FROM clause for use on JOIN of type '%s' on table '%s'.",
                $type,
                $join['table']
            ));
        }

        $from = $fromMapped[0];
        if (!isset($from['alias']) || $from['alias'] === null || $from['alias'] === '') {
            throw new InvalidArgumentException(sprintf(
                "Missing FROM's table alias for use on JOIN of type '%s'.",
                $type,
                $join['table']
            ));
        }

        $expression = new QueryExpression($join['conditions']);

        $method = $type === 'left' ? 'leftJoin' : ($type === 'right' ? 'rightJoin' : 'innerJoin');
        $this->_loadBuilder()->{$method}($from['alias'], $join['table'], $join['alias'], $expression->getSql());

        $this->getParameterManager()->add($expression->getParams(), 'join');

        return $this;
    }

/**
 * Alias for 'join' method.
 *
 * @see \Nata\Database\Query::join()
 */
    public function innerJoin($table, $tableAlias = null, $conditions = null) {
        return $this->join($table, $tableAlias, $conditions);
    }

/**
 * Alias for left join type.
 *
 * @see \Nata\Database\Query::join()
 */
    public function leftJoin($table, $tableAlias = null, $conditions = []) {
        return $this->join($table, $tableAlias, $conditions, 'left');
    }

/**
 * Alias for right join type.
 *
 * @see \Nata\Database\Query::join()
 */
    public function rightJoin($table, $tableAlias = null, $conditions = []) {
        return $this->join($table, $tableAlias, $conditions, 'right');
    }

/**
 * WHERE parameter for Query Builder.
 *
 * @param array|string $conditions WHERE conditions
 * @return $this
 */
    public function where($conditions = null) {
        $builder = $this->_loadBuilder();
        if (func_num_args() === 0) {
            return $this->_dbalGetQueryPart($builder, 'where');
        }

        $this->_clearClause('where');

        if (is_string($conditions)) {
            $this->getParameterManager()->reserveFor($conditions, 'where');
        }

        $expr = new QueryExpression($conditions);
        if ($sql = $expr->getSql()) {
            $builder->where($sql);
            $this->getParameterManager()->add($expr->getParams(), 'where');
        }

        return $this;
    }

/**
 * WHERE AND parameter for Query Builder.
 *
 * @param array|string $conditions WHERE conditions
 * @return $this
 */
    public function andWhere($conditions) {
        $expr = new QueryExpression($conditions, 'AND', $this);
        if ($sql = $expr->getSql()) {
            $this->_loadBuilder()->andWhere($sql);
            $this->getParameterManager()->add($expr->getParams(), 'where');
        }

        if (is_string($conditions)) {
            $this->getParameterManager()->reserveFor($conditions, 'where');
        }

        return $this;
    }

/**
 * WHERE OR parameter for Query Builder.
 *
 * @param array|string $conditions WHERE conditions
 * @return $this
 */
    public function orWhere($conditions) {
        $expr = new QueryExpression($conditions, 'AND', $this);
        if ($sql = $expr->getSql()) {
            $this->_loadBuilder()->orWhere($sql);
            $this->getParameterManager()->add($expr->getParams(), 'where');
        }

        if (is_string($conditions)) {
            $this->getParameterManager()->reserveFor($conditions, 'where');
        }

        return $this;
    }

/**
 * HAVING parameter alias for Query Builder.
 *
 * @param string|array $fields Field(s) name
 * @return $this
 */
    public function having($conditions = null) {
        $builder = $this->_loadBuilder();
        if (func_num_args() === 0) {
            return $this->_dbalGetQueryPart($builder, 'having');
        }

        $this->_clearClause('having');

        if (is_string($conditions)) {
            $this->getParameterManager()->reserveFor($conditions, 'having');
        }

        $expr = new QueryExpression($conditions);
        if ($sql = $expr->getSql()) {
            $builder->having($sql);
            $this->getParameterManager()->add($expr->getParams(), 'having');
        }

        return $this;
    }

/**
 * HAVING parameter alias for Query Builder.
 *
 * @param string|array $fields Field(s) name
 * @return $this
 */
    public function andHaving($conditions) {
        if (is_string($conditions)) {
            $this->getParameterManager()->reserveFor($conditions, 'having');
        }
        $expr = new QueryExpression($conditions, 'AND', $this);
        if ($sql = $expr->getSql()) {
            $this->_loadBuilder()->andHaving($sql);
            $this->getParameterManager()->add($expr->getParams(), 'having');
        }
        return $this;
    }

/**
 * HAVING parameter alias for Query Builder.
 *
 * @param string|array $fields Field(s) name
 * @return $this
 */
    public function orHaving($conditions) {
        if (is_string($conditions)) {
            $this->getParameterManager()->reserveFor($conditions, 'having');
        }

        $expr = new QueryExpression($conditions, 'OR', $this);
        if ($sql = $expr->getSql()) {
            $this->_loadBuilder()->orHaving($sql);
            $this->getParameterManager()->add($expr->getParams(), 'having');
        }
        return $this;
    }

/**
 * GROUP BY parameter alias for Query Builder.
 *
 * @param string|array $fields Field(s) name
 * @return $this
 */
    public function groupBy($fields = null) {
        if ($fields === null) {
            return $this->_dbalGetQueryPart($this->_loadBuilder(), 'groupBy');
        }

        $this->_clearClause('groupBy');

        $fields = is_array($fields) ? $fields : func_get_args();
        if ($fields !== []) {
            $this->_loadBuilder()->groupBy(...$fields);
        }

        return $this;
    }

/**
 * Add GROUP BY parameter alias for Query Builder
 * It works differenty from 'orderBy'
 * This method accepts and array of parameters, if so, it will use the 'addOrderBy'
 *
 * @param string|array $fields Array of parameters or field name
 * @return $this
 */
    public function addGroupBy($fields) {
        $fields = is_array($fields) ? $fields : func_get_args();
        if ($fields !== []) {
            $this->_loadBuilder()->addGroupBy(...$fields);
        }
        return $this;
    }

/**
 * ORDER BY parameter alias for Query Builder.
 *
 * Note that this method will clear previous conditions. If you would
 * like to add conditions, use 'addOrder' method.
 *
 * ## Examples
 *
 *  // Both examples are equivalent
 *  $query->order('email', 'desc');
 *  $query->order(['email' => 'desc']);
 *
 *  // Passing an array of ORDER BY conditions
 *  $query->order([
 *      'email' => 'desc',
 *      'name' => 'desc'
 *  ]);
 *
 *  // Using FIELD() function by passing the values
 *  $query->order([
 *      'email' => ['cool@email.pt', 'another@email.pt']
 *  ], 'asc');
 *
 * @param string|array $fields Array of parameters or field name
 * @param string $sort Optionally set the sort order (ASC or DESC)
 * @return $this
 */
    public function order($fields = null, $sort = 'ASC') {
        if (func_num_args() === 0) {
            return $this->_dbalGetQueryPart($this->_loadBuilder(), 'orderBy');
        }

        if ($fields === null || $fields === false) {
            $this->_clearClause('orderBy');
            return $this;
        }

        return $this->_orderBy($fields, $sort, false);
    }

/**
 * Alias of 'orderBy' method.
 *
 * @see \Nata\ORM\Query::orderBy()
 * @return $this
 */
    public function addOrder($fields = null, $sort = 'ASC') {
        return $this->_orderBy($fields, $sort, true);
    }

/**
 * ORDER BY FIELD convenience method.
 *
 * @param string|array $fieldName Field name to order by
 * @param string $sort Optionally set the sort order (ASC or DESC)
 * @return $this
 */
    public function orderByField($fieldName, $values, $sort = 'ASC') {
        return $this->_orderBy([
            $fieldName => $values
        ], $sort, true);
    }

/**
 * @see \Nata\ORM\Query::orderByField()
 */
    public function addOrderByField($fieldName, $values, $sort = 'ASC') {
        return $this->_orderBy([
            $fieldName => $values
        ], $sort, true);
    }

/**
 * ORDER BY parameter alias for Query Builder
 * It works differenty from 'orderBy'
 * This method accepts and array of parameters, of so, it will use the 'addOrderBy'
 *
 * @param string|array $fields Array of parameters or field name
 * @param string $sort Optionally set the sort order (ASC or DESC)
 * @return $this
 */
    private function _orderBy($fields, $sort, $append = false) {
        if ($append === false) {
            $this->_clearClause('orderBy');
        }

        if (!empty($fields)) {
            if (!is_array($fields)) {
                $fields = [$fields => $sort];
            }

            foreach ($fields as $field => $direction) {
                $order = null;
                if (is_int($field)) {
                    $order = $direction;
                } elseif (is_array($direction)) {
                    $field = $this->_orderByField($field, $direction);
                    $direction = $sort;
                }

                if ($order === null) {
                    $direction = strtoupper($direction);
                    if (!in_array($direction, ['ASC', 'DESC'], true)) {
                        continue;
                    }
                    $order = $this->_escapeReservedKeyword($field) . ' ' . $direction;
                }

                $qb = $this->_loadBuilder();
                $current = $this->_dbalGetPrivate($qb, 'orderBy');
                $current[] = $order;
                $this->_dbalSetPrivate($qb, 'orderBy', $current);
                $this->_dbalSetPrivate($qb, 'sql', null);
            }

        }

        return $this;
    }

/**
 * Set ORDER BY clause and respective fields.
 *
 * @param array $orderBy ORDER BY expressions
 * @return string FIELD SQL part for ORDER BY
 */
    protected function _orderByField($field, $values, $method = '') {
        if (empty($values)) {
            throw new InvalidArgumentException(sprintf(
                'Missing values for order.',
                $method
            ));
        }

        $values = array_values((array)$values);
        $placeholders = implode(
            ',',
            array_fill(0, count($values), '?')
        );

        $sql = sprintf('FIELD(%s,%s)', $this->_escapeReservedKeyword($field), $placeholders);

        $this->getParameterManager()->add($values, 'orderBy');

        return $sql;
    }

/**
 * LIMIT parameter alias for Query Builder 'setMaxResults'
 * It's possible to set the offset also in this method
 *
 * @param int $limit If this argument is empty, $offset becomes $limit
 * @return $this
 */
    public function limit(int $limit = null) {
        if (func_num_args() === 0) {
            return $this->_loadBuilder()->getMaxResults();
        }

        $this->_loadBuilder()->setMaxResults((is_numeric($limit) ? $limit : null));

        return $this;
    }

/**
 * Get/set OFFSET.
 *
 * @param int $offset Results offset
 * @return $this
 */
    public function offset(int $offset = 0) {
        if (func_num_args() === 0) {
            return $this->_loadBuilder()->getFirstResult();
        }
        $this->_loadBuilder()->setFirstResult((is_numeric($offset) ? $offset : 0));
        return $this;
    }

/**
 * UPSERT query.
 *
 * @param string $table Table name
 * @param string $alias Table alias
 * @return Upsert
 */
    public function upsert($table, $alias = null): Upsert {
        if ($this->_driverName === null) {
            $driver = $this->_connection->getDoctrineConnection()->getParams()['driver'] ?? null;
            [$d, $name] = explode('_', $driver);
            $this->_driverName = ucfirst($name);
        }

        $className = App::className($this->_driverName, 'Database/Query/Upsert');
        if (!$className) {
            throw new DatabaseException("Upsert it's not supported.");
        }

        return new $className($this->_connection, $this->getParameterManager(), $this->_connection->schema(), $table, $alias);
    }

/**
 * UPDATE query.
 *
 * @param string $table Table name
 * @param string $alias Table alias
 * @return $this
 */
    public function update($table = null, $alias = null) {
        $this->_type = 2;
        $tableSpec = $table;
        if ($table !== null && $alias !== null && $alias !== '') {
            $tableSpec = $table . ' ' . $alias;
        }
        $this->_loadBuilder()->update((string)$tableSpec);
        return $this;
    }

/**
 * INSERT query.
 *
 * @param string $table Table name
 * @param string $alias Table alias
 * @return $this
 */
    public function insert($table = null, $alias = null) {
        $this->_type = 3;
        $tableSpec = (string)$table;
        if ($alias !== null && $alias !== '') {
            $tableSpec = $table . ' ' . $alias;
        }
        $this->_loadBuilder()->insert($tableSpec);
        return $this;
    }

/**
 * DELETE query.
 *
 * @param string $table Table name
 * @return $this
 */
    public function delete($table = null) {
        $this->_type = 1;
        $this->_loadBuilder()->delete($table);
        return $this;
    }

/**
 * Set SET values.
 *
 * @param string|array $field Field name or array of values
 * @param string $value Field value to update
 * @return $this
 */
    public function set($fields = null, $value = null) {
        $builder = $this->_loadBuilder();
        if ($fields === null) {
            return $this->_dbalGetQueryPart($builder, 'set');
        }

        if (is_string($fields)) {
            $fields = [$fields => $value];
        }

        foreach ($fields as $field => $value) {
            $comp = new Comparison($this->_escapeReservedKeyword($field), $value, Comparison::EQ, null, Comparison::SET);
            $sql = $comp->getSql();
            $this->_dbalAdd($builder, 'set', $sql, true);
            $this->getParameterManager()->add($comp->getParams(), 'set');
        }

        return $this;
    }

/**
 * Set VALUES values.
 *
 * @param string|array $field Field name or array of values
 * @param string $value Field value
 * @return $this
 */
    public function values($fields = null, $value = null) {
        if ($fields === null) {
            return $this->_dbalGetQueryPart($this->_loadBuilder(), 'values');
        }

        $this->_clearClause('values');

        return $this->_values($fields, $value, false);
    }

/**
 * Set VALUES values for INSERT query.
 *
 * @param string|array $field Field name or array of values
 * @param string $value Field value to update
 * @return $this
 */
    public function addValues($fields, $value = null) {
        return $this->_values($fields, $value, true);
    }

/**
 * Set VALUES key/value pair values.
 *
 * @param string|array $field Field name or array of values
 * @param string $value Field value
 * @return $this
 */
    protected function _values($field, $value, $append = false) {
        if (!is_array($field)) {
            $field = [$field => $value];
        }

        foreach ($field as $fieldName => $value) {
            if (is_int($fieldName)) {
                continue;
            }

            $this->_loadBuilder()->setValue($this->_escapeReservedKeyword($fieldName), '?');
            $this->getParameterManager()->add($value, 'values');
        }

        return $this;
    }

/**
 * Makes the necessary calculations for OFFSET and LIMIT for given page.
 *
 * @param int $num Page number.
 * @param int $limit Number of results per page.
 * @return $this
 */
    public function page($num, $limit = null) {
        if ($limit !== null) {
            $this->limit($limit);
        }

        $limit = $this->limit();
        if ($limit === null) {
            $limit = 20;
            $this->limit($limit);
        }

        $num = (int)$num;
        if (empty($num)) {
            $num = 1;
        }

        $offset = ($num - 1) * $limit;
        if (PHP_INT_MAX <= $offset) {
            $offset = PHP_INT_MAX;
        }

        $this->offset((int)$offset);

        return $this;
    }

/**
 * Either appends to or replaces a single, generic query part.
 *
 * The available parts are: 'select', 'from', 'set', 'where',
 * 'groupBy', 'having' and 'orderBy'.
 *
 * @param string $clause Clause name
 * @param string $sqlPart SQL part
 * @param bool $append
 * @return $this Query builder instance.
 */
    public function add($clause, $sqlPart, $append = false) {
        $this->_dbalAdd($this->_loadBuilder(), $clause, $sqlPart, $append);
        return $this;
    }

/**
 * Get SQL query as string.
 *
 * @return string
 */
    public function sql() {
        if ($this->_sql !== null) {
            return $this->_sql;
        }
        return $this->_loadBuilder()->getSql();
    }

/**
 * Fetch single row/result.
 *
 * @return array First one row
 */
    public function fetch() {
        return $this->_connection
            ->getDoctrineConnection()
            ->fetchAssociative($this->sql(), $this->getParameterManager()->getPrepared(false));
    }

/**
 * Fetch column results.
 *
 * @return mixed Raw results
 */
    public function fetchColumn() {
        return $this->_connection
            ->getDoctrineConnection()
            ->fetchOne($this->sql(), $this->getParameterManager()->getPrepared(false));
    }

/**
 * Fetch all results.
 *
 * @return mixed Raw results
 */
    public function fetchAll() {
        return $this->execute();
    }

/**
 * Execute query.
 *
 * @param string $sql Valid SQL string
 * @param array $params SQL parameters
 * @return mixed SQL results
 */
    public function execute($sql = null, array $params = []) {
        if ($sql === null) {
            return $this->_executeBuildSql();
        }
        return $this->_connection
            ->getDoctrineConnection()
            ->executeQuery($sql, $params);
    }

/**
 * Execute query from the Querybuilder.
 *
 * @return mixed Raw results
 */
    protected function _executeBuildSql() {
        $sql = $this->sql();
        $parameters = $this->getParameterManager();

        if ($this->is('select')) {
            $params = $parameters->getPrepared();
            $stmt = $this->_connection->getDoctrineConnection()->prepare($sql);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            return $stmt->executeQuery()->fetchAllAssociative();
        }

        $params = $parameters->getPrepared(true);
        return $this->_connection->executeQuery($sql, $params)->rowCount();
    }

/**
 * Escape reserved keyword.
 *
 * @param string $keyword Keyword to escape
 * @return string Escaped keyword
 */
    public function _escapeReservedKeyword(string $keyword): string {
        if ($keyword && $keyword !== '*') {
            $platform = $this->getConnection()->getDatabasePlatform();
            if ($platform->getReservedKeywordsList()->isKeyword($keyword)) {
                $keyword = $platform->quoteIdentifier($keyword);
            }
        }
        return $keyword;
    }

/**
 * Generate runtime cache key.
 *
 * @param string $sql SQL query
 * @param array $params SQL parameters
 * @return string Cache key
 */
    protected function _generateCacheKey($sql, $params): string {
        return md5($sql . implode('', $params));
    }

/**
 * Runtime cache.
 *
 * @param string $key Cache key
 * @param array $results Cache results
 * @return mixed Cache results
 */
    protected function _runtimeCache(string $key, array $results = null) {
        return $results;

        $realAuthUser = Registry::get('realAuthUser');
        if (!$realAuthUser || !$realAuthUser->is_developer) {
            return $results;
        }

        $request = Router::getRequest(true);
        if (!$request || !$request->query('debug')) {
            return $results;
        }

        if ($results === null) {
            return static::$_runtimeCache[$key] ?? null;
        }
        return static::$_runtimeCache[$key] = $results;
    }

/**
 * Get/Set connection instance.
 *
 * @return Connection
 */
    protected function _loadBuilder() {
        if ($this->_builder === null) {
            $this->_builder = new QueryBuilder($this->getConnection()->getDoctrineConnection());
        }
        return $this->_builder;
    }

/**
 * Clone current instance.
 *
 * @return $this Current Instance
 */
    public function __clone() {
        $query = $this;
        $query->_builder = clone $this->_loadBuilder();
        $query->_parameterManager = clone $this->getParameterManager();
        return $query;
    }

/**
 * __toString.
 *
 * @return string
 */
    public function __toString() {
        return $this->sql();
    }

/**
 * __debugInfo.
 *
 * @return array
 */
    public function __debugInfo() {
        return [
            'sql' => $this->sql(),
            'params' => $this->params()
        ];
    }

}
