<?php
/**
 * NataPHP Framework
 *
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
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

namespace Nata\ORM;

use Nata\ORM\Cache;
use Nata\Collection\Iterator\MapReduce;
use Nata\Database\Query as DatabaseQuery;
use JsonSerializable;
use IteratorAggregate;
use Iterator;
use RecordNotFoundException;
use Exception;
use Closure;
use Nata\ORM\Loader\Eager;

/**
 * Query builder.
 */
class Query extends DatabaseQuery implements JsonSerializable, IteratorAggregate {

/**
 * Indicates that the operation should append to the list
 *
 * @var int
 */
    const APPEND = 0;

/**
 * Indicates that the operation should prepend to the list
 *
 * @var int
 */
    const PREPEND = 1;

/**
 * Indicates that the operation should overwrite the list
 *
 * @var bool
 */
    const OVERWRITE = true;

/**
 * Table instance.
 *
 * @var \Nata\ORM\Table
 */
    private $_repository;

/**
 * Table name.
 *
 * @see Table::table()
 * @var string
 */
    private $_table;

/**
 * Table alias.
 *
 * @see Table::alias()
 * @var string
 */
    private $_tableAlias;

/**
 * Database connection instance.
 *
 * @var \Nata\Database\Connection
 */
    protected $_connection;

/**
 * Eager loader instance.
 *
 * @var \Nata\ORM\EagerLoader
 */
    private $_eagerLoader;

/**
 * Hydrate results with Entities.
 *
 * @var boolean
 */
    private $_hydrate = true;

/**
 * Add fields to SELECT.
 *
 * @var boolean
 */
    private $_autoFields = true;

/**
 * Fetch single entity/record on query execution.
 *
 * @var boolean
 */
    private $_single = false;

/**
 * List of map-reduce routines that should be applied over the query
 * result
 *
 * @var array
 */
    protected $_mapReduce = [];

/**
 * List of formatter classes or callbacks that will post-process the
 * results when fetched
 *
 * @var array
 */
    protected $_formatters = [];

/**
 * Count result.
 *
 * @var int
 */
    private $_count;

/**
 * Counter closure.
 *
 * @var closure
 */
    private $_counter;

/**
 * Cache instance.
 *
 * @var \Nata\ORM\Cache
 */
    private $_cache;

/**
 * Dirty results.
 *
 * @var bool
 */
    private $_dirty = false;

/**
 * Result set instance.
 *
 * @var \Nata\ORM\ResultSet
 */
    private $_result;

/**
 * Query build state.
 *
 * @var boolean
 */
    private $_queryBuilt = false;

/**
 * True if the beforeFind event has already been triggered for this query.
 *
 * @var bool
 */
    private $_beforeFindFired = false;

/**
 * Constructor.
 *
 * @param \Nata\ORM\Table $table Table instance
 * @return void
 */
    public function __construct(Table $table) {
        $this->_repository = $table;
        $this->_table = $table->table();
        $this->_tableAlias = $table->alias();
        $this->_connection = $table->connection();
        parent::__construct($this->_connection);
        parent::from($table->table(), $table->alias());
    }

/**
 * Get/Set cache handler instance.
 *
 * ### Usage
 *
 * // Simple key and cache config
 * $query->cache('my_query', 'my_config');
 *
 * // Using a closure as key, in this example, creating a key from current SQL
 * $query->cache(function ($q) {
 *     return md5($q->sql());
 * });
 *
 * // Disable set cache
 * $query->cache(false);
 *
 * @param string|closure $key Cache key
 * @param string $config Cache configuration
 * @return $this
 */
    public function cache($key, $config = 'default') {
        if (is_array($key)) {
            extract($key);
        }

        if ($key === false) {
            $this->_cache = null;
        } else {
            if (is_callable($key)) {
                $key = $key($this);
            }
            $key = $this->_repository->alias() . '.' . $key;
            $this->_cache = new Cache($key, $config);
        }

        return $this;
    }

/**
 * Get/Set eager loader instance.
 *
 * @param \Nata\ORM\EagerLoader $instance Eager loader instance
 * @return $this|\Nata\ORM\Loader\Eager
 */
    public function eagerLoader(Eager $instance = null) {
        if ($instance === null) {
            if ($this->_eagerLoader === null) {
                $this->_eagerLoader = new Eager($this->_repository);
            }
            return $this->_eagerLoader;
        }
        return $this;
    }

/**
 * Returns true if query is eager loaded.
 *
 * @return bool
 */
    public function eagerLoaded() {
        return ($this->_eagerLoader instanceof Eager);
    }

/**
 * Get query's \Nata\ORM\Table instance.
 *
 * @return \Nata\ORM\Table
 */
    public function repository() {
        return $this->_repository;
    }

/**
 * Get/set dirty state.
 *
 * @param bool $dirty Dirty state
 * @return \Nata\ORM\Query
 */
    public function dirty($dirty = null) {
        if ($dirty === null) {
            return $this->_dirty;
        }

        $this->_dirty = $dirty;

        return $this;
    }

/**
 * Get results as Entities or as a simple array.
 *
 * @param boolean $hydrate True to hydrate result with entities
 * @return $this|bool
 */
    public function hydrate($hydrate = null) {
        if ($hydrate === null) {
            return $this->_hydrate;
        }
        $this->_hydrate = $hydrate;
        return $this;
    }

/**
 * Set/get autoFields value.
 *
 * @param boolean $autoFields True to add fields
 * @return $this|bool
 */
    public function autoFields($autoFields = null) {
        if ($autoFields === null) {
            return $this->_autoFields;
        }

        $this->_autoFields = $autoFields;
        return $this;
    }

/**
 * Get/set option to only get a single record.
 *
 * @param boolean $single Get a single entity/row.
 * @return bool|$this
 */
    public function single($single = null) {
        if ($single === null) {
            return $this->_single;
        }
        $this->_single = $single;
        return $this;
    }

/**
 * Load associations.
 *
 * @param string|array $associations Associations to load
 * @param closure $builder Query builder
 * @return $this
 */
    public function contain($associations, callable $builder = null) {
        $this->eagerLoader()->contain($associations, $builder);
        return $this;
    }

/**
 * Load records that matches this associations.
 *
 * @param string|array $associations Associations to load
 * @param closure $builder Query builder
 * @return $this
 */
    public function matching($associations, callable $builder = null) {
        $this->eagerLoader()->matching($associations, $builder);
        return $this;
    }

/**
 * Load records that doesn't match this associations.
 *
 * @param string|array $associations Associations to load
 * @param closure $builder Query builder
 * @return $this
 */
    public function orMatching($associations, callable $builder = null) {
        $this->eagerLoader()->orMatching($associations, $builder);
        return $this;
    }

/**
 * Load records that matches this associations.
 *
 * @param string|array $associations Associations to load
 * @param closure $builder Query builder
 * @return $this
 */
    public function notMatching($associations, callable $builder = null) {
        $this->eagerLoader()->notMatching($associations, $builder);
        return $this;
    }

/**
 * Load records that doesn't match this associations.
 *
 * @param string|array $associations Associations to load
 * @param closure $builder Query builder
 * @return $this
 */
    public function orNotMatching($associations, callable $builder = null) {
        $this->eagerLoader()->orNotMatching($associations, $builder);
        return $this;
    }

/**
 * Apply custom finds to against an existing query object.
 *
 * Allows custom find methods to be combined and applied to each other.
 *
 * ```
 * $table->find('all')->find('recent');
 * ```
 *
 * The above is an example of stacking multiple finder methods onto
 * a single query.
 *
 * @param string $finder The finder method to use.
 * @param array $options The options for the finder.
 * @return $this Returns a modified query.
 * @see \Nata\ORM\Table::find()
 */
    public function find($finder, array $options = []) {
        return $this->_repository->callFinder($finder, $this, $options);
    }

/**
 * SELECT parameter for Query Builder.
 *
 * @param array|string $conditions WHERE conditions
 * @return \Nata\ORM\Query|array
 */
    public function select($fields = null) {
        if (func_num_args() > 1) {
            $fields = func_get_args();
        }
        return parent::select($fields);
    }

/**
 * Additional SELECT parameter for Query Builder.
 *
 * @param array|string $fields SELECT fields
 * @return \Nata\ORM\Query
 */
    public function addSelect($fields) {
        return parent::addSelect($fields);
    }

/**
 * {@inheritDoc}.
 *
 * @return \Nata\ORM\Query
 */
    public function where($conditions = null) {
        $this->_count = null;
        return parent::where($conditions);
    }

/**
 * {@inheritDoc}.
 *
 * @return \Nata\ORM\Query
 */
    public function andWhere($conditions) {
        $this->_count = null;
        return parent::andWhere($conditions);
    }

/**
 * {@inheritDoc}.
 *
 * @return \Nata\ORM\Query
 */
    public function orWhere($conditions) {
        $this->_count = null;
        return parent::orWhere($conditions);
    }

/**
 * {@inheritDoc}.
 *
 * @return \Nata\ORM\Query
 */
    public function having($conditions = null) {
        $this->_count = null;
        return parent::having($conditions);
    }

/**
 * {@inheritDoc}.
 *
 * @return \Nata\ORM\Query
 */
    public function andHaving($conditions) {
        $this->_count = null;
        return parent::orHaving($conditions);
    }

/**
 * {@inheritDoc}.
 *
 * @return \Nata\ORM\Query
 */
    public function orHaving($conditions) {
        $this->_count = null;
        return parent::orHaving($conditions);
    }

/**
 * {@inheritDoc}.
 *
 * @return \Nata\ORM\Query
 */
    public function limit($limit = null) {
        return func_num_args() > 0 ? parent::limit($limit) : parent::limit();
    }

/**
 * Create INSERT query.
 * Table name and table alias, if not given, defaults to
 * current table.
 *
 * ```
 * $query->insert()->values(['name' => 'Sérgio']);
 * ```
 *
 * @param string $table Table name
 * @param string $alias Table alias
 * @return \Nata\ORM\Query
 */
    public function insert($table = null, $alias = null) {
        if ($table === null) {
            $table = $this->_table;
        }
        // MySQL/MariaDB: INSERT must not use a table alias between the table name and
        // the column list (e.g. INSERT INTO t1 t_alias (c) is invalid). ORM select/update
        // use aliases; insert must use the table name only.
        return parent::insert($table, null);
    }

/**
 * Create UPDATE query.
 * Table name and table alias, if not given, defaults to
 * current table.
 *
 * ```
 * $query->update()->set(['name' => 'Sérgio']);
 * ```
 *
 * ```
 * $query->update()->set(['age' => 'age + 1']);
 * ```
 *
 * @param string $table Table name
 * @param string $alias Table alias
 * @return \Nata\ORM\Query
 */
    public function update($table = null, $alias = null) {
        return $this->_setTableAndAlias(__FUNCTION__, $table, $alias);
    }

/**
 * Set UPDATE values.
 *
 * ```
 * $query->update()->set(['name' => 'Sérgio']);
 * ```
 *
 * ```
 * $query->update()->set(['age' => 'age + 1']);
 * ```
 *
 * @param string|array $fields Field name or array of fields and values
 * @param string $value Field value
 * @return \Nata\ORM\Query
 */
    public function set($fields = null, $value = null) {
        return parent::set($fields, $value);
    }

/**
 * Create DELETE query.
 * Table name and table alias, if not given, defaults to
 * current table.
 *
 * ```
 * $query->delete()->where(['name' => 'Sérgio']);
 * ```
 *
 * ```
 * $query->delete()->where(['id' => '23']);
 * ```
 *
 * @param string $table Table name
 * @param string $alias Table alias
 * @return \Nata\ORM\Query
 */
    public function delete($table = null, $alias = null) {
        return $this->_setTableAndAlias(__FUNCTION__, $table, $alias);
    }

/**
 * Get SQL query as string.
 *
 * @return string
 */
    private function _setTableAndAlias($method, $table, $alias = null) {
        if ($table === null) {
            $table = $this->_table;
        }
        if ($alias === null) {
            $alias = $this->_tableAlias;
        }
        return parent::{$method}($table, $alias);
    }

/**
 * Get result count.
 * If ounter is set, it will execute the function and return the result.
 *
 * @param string $field Field for COUNT()
 * @return int Total rows
 */
    public function count($field = '*') {
        if ($this->_count) {
            return $this->_count;
        }

        $field = $field !== '*' ? $this->aliasField($field, $this->_table) : $field;

        $query = clone $this;
        $query
            ->autoFields(false)
            ->select('COUNT(' . $field . ')')
            ->offset(0)
            ->order(null)
            ->limit(null);

        if ($counter = $this->_counter) {
            $count = $counter($query);

            if (is_numeric($count)) {
                return $this->_count = $count;
            }
        }

        $this->_count = (int)$query->fetchColumn();

        unset($query);

        return $this->_count;
    }

/**
 * Allows to create and alternative method for counting the
 * total records of a query.
 *
 * ### Usage
 *
 * // Prepare count() returned value
 * $query->counter(function($q) {
 *     return 10000;
 * });
 * $query->count(); // 10000
 *
 * @param closure $counter Closure
 * @return $this
 */
    public function counter(Closure $counter) {
        $this->_counter = $counter;
        return $this;
    }

/**
 * Query execution.
 *
 * @param string $sql Valid SQL string
 * @param array $params SQL parameters
 * @return mixed
 */
    public function execute($sql = null, array $params = []) {
        return parent::execute($sql, $params);
    }

/**
 * Get SQL query as string.
 *
 * @return string
 */
    public function sql() {
        $this->_buildQuery();
        $this->triggerBeforeFind();
        return parent::sql();
    }

/**
 * If needed, set SELECT.
 * Prepare loading of associations.
 *
 * @return \Nata\ORM\Query Current Instance
 */
    private function _buildQuery() {
        if (!$this->is('select') || $this->_queryBuilt) {
            return;
        }

        $this->_queryBuilt = true;

        $parts = $this->clause();

        if (empty($parts['select']) && $this->_autoFields) {
            parent::select($this->_repository->aliasField('*'));
        }

        $eagerLoader = $this->eagerLoader();
        $eagerLoader->buildContainQuery($this);
        $eagerLoader->buildMatchQuery($this);
    }

/**
 * Get first result.
 *
 * @return \Nata\ORM\Entity|array
 */
    public function first() {
        return $this->limit(1)
            ->single(true)
            ->all()
            ->first();
    }

/**
 * Get the first result from the executing query or raise an exception.
 *
 * @throws \Cake\Datasource\Exception\RecordNotFoundException When there is no first record.
 * @return mixed The first result from the ResultSet.
 */
    public function firstOrFail() {
        $entity = $this->first();
        if ($entity) {
            return $entity;
        }
        throw new RecordNotFoundException(sprintf(
            'Record not found in table "%s"',
            $this->_table
        ), 404);
    }

/**
 * Trigger the beforeFind event on the query's repository object.
 *
 * Will not trigger more than once, and only for select queries.
 *
 * @return bool
 */
    public function triggerBeforeFind() {
        if ($this->_beforeFindFired === true || !$this->is('select')) {
            return false;
        }

        $table = $this->repository();
        $this->_beforeFindFired = true;
        $table->dispatchEvent('Model.beforeFind', [
            $this,
            $this->_options,
            !$this->eagerLoaded()
        ]);

        return true;
    }

/**
 * Get all results.
 *
 * @return \Nata\ORM\ResultSet
 */
    public function all() {
        if ($this->_result) {
            return $this->_result;
        }

        $results = null;
        if ($this->_cache) {
            $results = $this->_cache->fetch();
        }

        if (!$results) {
            $results = $this->execute();
            if ($this->_cache) {
                $this->_cache->write($results);
            }
        }

        if (is_array($results)) {
            $this->eagerLoader()->parentRows($results);
        }

        return $this->_result = $this->_decorateResults($results);
    }

/**
 * Decoration of the result set.
 *
 * @param array $results Raw query result.
 * @return array
 */
    private function _decorateResults($results) {
        $result = new ResultSet($this, $results);
        foreach ($this->_mapReduce as $functions) {
            $result = new MapReduce($result, $functions['mapper'], $functions['reducer']);
        }
        foreach ($this->_formatters as $formatter) {
            $result = $formatter($result, $this);
            if (!($result instanceof Iterator)) {
                throw new Exception(sprintf(
                    'Query::formatResults() must return "Iterator" instance. Returned "%s" instead.',
                    (is_object($result) ? get_class($result) : gettype($result))
                ));
            }
        }
        return $result;
    }

/**
 * Register a new MapReduce routine to be executed on top of the database results
 * Both the mapper and caller callable should be invokable objects.
 *
 * The MapReduce routing will only be run when the query is executed and the first
 * result is attempted to be fetched.
 *
 * If the first argument is set to null, it will return the list of previously
 * registered map reduce routines.
 *
 * If the third argument is set to true, it will erase previous map reducers
 * and replace it with the arguments passed.
 *
 * @param callable|null $mapper The mapper callable.
 * @param callable|null $reducer The reducing function.
 * @param bool $overwrite Set to true to overwrite existing map + reduce functions.
 * @return $this|array
 * @see \Nata\Collection\Iterator\MapReduce for details on how to use emit data to the map reducer.
 */
    public function mapReduce(callable $mapper = null, callable $reducer = null, $overwrite = false) {
        if ($overwrite) {
            $this->_mapReduce = [];
        }
        if ($mapper === null) {
            return $this->_mapReduce;
        }
        $this->_mapReduce[] = [
            'mapper' => $mapper,
            'reducer' => $reducer
        ];
        return $this;
    }

/**
 * Registers a new formatter callback function that is to be executed when trying
 * to fetch the results from the database.
 *
 * Formatting callbacks will get a first parameter, a `ResultSetDecorator`, that
 * can be traversed and modified at will.
 *
 * Callbacks are required to return an iterator object, which will be used as
 * the return value for this query's result. Formatter functions are applied
 * after all the `MapReduce` routines for this query have been executed.
 *
 * If the first argument is set to null, it will return the list of previously
 * registered map reduce routines.
 *
 * If the second argument is set to true, it will erase previous formatters
 * and replace them with the passed first argument.
 *
 * ### Example:
 *
 * ```
 * // Return all results from the table indexed by id
 * $query->select(['id', 'name'])->formatResults(function ($results) {
 *   return $results->indexBy('id');
 * });
 *
 * // Add a new column to the ResultSet
 * $query->select(['name', 'birth_date'])->formatResults(function ($results) {
 *   return $results->map(function ($row) {
 *     $row['age'] = $row['birth_date']->diff(new DateTime)->y;
 *     return $row;
 *   });
 * });
 * ```
 *
 * @param callable|null $formatter The formatting callable.
 * @param bool|int $mode Whether or not to overwrite, append or prepend the formatter.
 * @return $this|array
 */
    public function formatResults(callable $formatter = null, $mode = 0) {
        if ($mode === self::OVERWRITE) {
            $this->_formatters = [];
        }
        if ($formatter === null) {
            return $this->_formatters;
        }
        if ($mode === self::PREPEND) {
            array_unshift($this->_formatters, $formatter);
            return $this;
        }
        $this->_formatters[] = $formatter;
        return $this;
    }

/**
 * \Nata\Collection\CollectionTrait alias.
 *
 * @see \Nata\Collection\CollectionTrait::isEmpty()
 * @return bool
 */
    public function isEmpty() {
        return $this->all()->isEmpty();
    }

/**
 * Get result set as array.
 *
 * @return array
 */
    public function toArray() {
        return $this->all()->toArray();
    }

/**
 * \IteratorAggregate implementation.
 *
 * @return array
 */
    public function getIterator(): Iterator {
        return $this->all();
    }

/**
 * \JsonSerializable class implementation.
 *
 * @return array
 */
    public function jsonSerialize(): mixed {
        return $this->all();
    }

/**
 * \JsonSerializable class implementation.
 *
 * @return array
 */
    public function __sleep() {
        return array_diff(
            array(
                '_connection'
            ), array_keys(get_object_vars($this)));
    }

/**
 * \JsonSerializable class implementation.
 *
 * @return array
 */
    public function __wakeup() {}

/**
 * \Serializable class implementation.
 *
 * @return string Serialized data
 */
    public function __serialize() {
        $this->all();
        return serialize($this);
    }

/**
 * \Serializable class implementation.
 *
 * @param string $data Serialized data
 * @return void
 */
    public function __unserialize($data) {
        $query = unserialize($this);
        $this->__construct($query->repository());
        return $this;
    }

/**
 * Type cast to string.
 *
 * @return string
 */
    public function __toString() {
        return $this->sql();
    }

/**
 * Clone current instance.
 *
 * @return \Nata\ORM\Query Current Instance
 */
    public function __clone() {
        $query = parent::__clone();
        if ($this->_eagerLoader) {
            $query->_eagerLoader = clone $this->_eagerLoader;
        }
        return $query;
    }

}
