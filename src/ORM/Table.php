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

use Nata\Core\App;
use Nata\Core\NataObject;
use Nata\Event\Listener;
use Nata\I18n\Time;
use Nata\Utility\Inflector;
use Nata\Database\ConnectionManager;
use Nata\ORM\AssociationRegistry;
use Nata\ORM\Association\HasOne;
use Nata\ORM\Association\HasMany;
use Nata\ORM\Association\BelongsTo;
use Nata\ORM\Association\BelongsToMany;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Types\IntegerType;
use Nata\Database\Query as DatabaseQuery;
use Nata\Database\Connection;
use Nata\Database\Schema;
use TableException;
use Throwable;
use Exception;
use MissingEntityException;
use LogicException;
use InvalidArgumentException;
use InvalidPrimaryKeyException;
use BadMethodCallException;

/**
 * Table's CRUD controls with associational control, SQL builder
 */
class Table extends NataObject implements Listener {

/**
 * Tables with One-to-One association.
 *
 * @var array
 */
    public $hasOne = [];

/**
 * Tables with One-to-Many association.
 *
 * @var array
 */
    public $hasMany = [];

/**
 * Tables with Many-to-One association.
 *
 * @var array
 */
    public $belongsTo = [];

/**
 * Tables with Many-to-Many association.
 *
 * @var array
 */
    public $belongsToMany = [];
    public $hasAndBelongsToMany = [];

/**
 * Name of the table class.
 *
 * @var string
 */
    protected $_name;

/**
 * Created field name.
 *
 * @var string
 */
    protected string $_createdAt = 'created';

/**
 * Updated field name.
 *
 * @var string
 */
    protected string $_updatedAt = 'updated';

/**
 * Table name.
 *
 * @var string
 */
    protected $_table;

/**
 * Table name prefix.
 *
 * @var string
 */
    protected $_tablePrefix;

/**
 * The name of the field that represents a human readable representation of a row.
 *
 * @var string
 */
    protected $_displayField;

/**
 * Table alias.
 *
 * @var string
 */
    protected $_alias;

/**
 * Registry key used to create this table object.
 *
 * @var string
 */
    protected $_registryAlias;

/**
 * The name of the field(s) that represents the primary key(s) in the table.
 *
 * @var string|array
 */
    protected $_primaryKey;

/**
 * Allow deletion of missing rows from passed
 * data to Table::save().
 *
 * @var bool
 */
    protected $_deleteMissing = false;

/**
 * Database connection.
 *
 * @var \Nata\Database\Connection
 */
    protected $_connection;

/**
 * Table Schema instance.
 *
 * @var \Nata\ORM\Schema
 */
    protected $_schema;

/**
 * Table configuration.
 *
 * @var array
 */
    protected $_config = [];

/**
 * Association registry instance.
 *
 * @var \Nata\ORM\AssociationRegistry
 */
    protected $_associations;

/**
 * Translate instance
 *
 * @var \Nata\ORM\BehaviorRegistry
 */
    protected $_behaviors;

/**
 * Entity class name.
 *
 * @var string
 */
    protected $_entityClass;


/**
 * Table Constructor
 *
 * @param array $config Table options
 * @return void
 */
    public function __construct(array $config = []) {
        $this->_config = $config;
        $this->_table = $config['table'];
        $this->_alias = Inflector::camelize($this->_table);
        $this->_name = $config['className'];

        if ($config['tablePrefix'] !== null) {
            $this->_tablePrefix = $config['tablePrefix'];
        }

        if (!empty($config['registryAlias'])) {
            $this->_registryAlias = $config['registryAlias'];
        }

        if (!empty($this->hasAndBelongsToMany)) {
            $this->belongsToMany = array_merge($this->hasAndBelongsToMany, $this->belongsToMany);
        }

        $this->addAssociations([
            'hasOne' => $this->hasOne,
            'hasMany' => $this->hasMany,
            'belongsTo' => $this->belongsTo,
            'belongsToMany' => $this->belongsToMany
        ]);

        $this->initialize($config);

        $this->startup();

        $this->dispatchEvent('Model.afterStartup');
    }

/**
 * Table instance initialization
 *
 * @param array $config Table configuration
 * @return void
 */
    public function initialize($config) {
        $this->config($config);
    }

/**
 * Table instance initialization
 *
 * @return void
 */
    public function startup() {}

/**
 * Get/Set table name prefix.
 *
 * @param string $prefix Set table prefix
 * @return $this|string
 */
    public function tablePrefix($prefix = null) {
        if ($prefix === null) {
            return $this->_tablePrefix;
        }

        $this->_tablePrefix = $prefix;

        // Clear schema's runtime cache
        if ($this->_schema instanceof Schema) {
            $this->_schema = null;
        }

        return $this;
    }

/**
 * Get/Set table name.
 *
 * @param string $table Table name
 * @return $this|string
 */
    public function table($table = null) {
        if ($table === null) {
            if ($this->_table === null) {
                $table = namespaceSplit(get_class($this));
                $table = substr(end($table), 0, -5);
                if (empty($table)) {
                    $table = $this->alias();
                }
                $this->table($table);
            }
            return $this->_tablePrefix . $this->_table;
        }

        $this->_table = Inflector::underscore($table);

        // Clear schema's runtime cache
        if ($this->_schema instanceof Schema) {
            $this->_schema = null;
        }

        return $this;
    }

/**
 * Get/Set table name alias.
 *
 * @param string $alias Set alias
 * @return $this|string
 */
    public function alias($alias = null) {
        if ($alias === null) {
            if ($this->_alias === null) {
                $alias = namespaceSplit(get_class($this));
                $alias = substr(end($alias), 0, -5) ?: $this->_table;
                $this->_alias = $alias;
            }
            return $this->_alias;
        }
        $this->_alias = $alias;
        return $this;
    }

/**
 * Get query's table field dot separated with table name alias.
 *
 * @param string $fieldName Field name
 * @return string
 */
    public function aliasField($fieldName) {
        if (strpos($fieldName, '.') === false) {
            return $this->_alias . '.' . $fieldName;
        }
        return $fieldName;
    }

/**
 * Returns the table registry key used to create this table instance.
 *
 * @param string|null $registryAlias the key used to access this object
 * @return string
 */
    public function registryAlias($registryAlias = null) {
        if ($registryAlias === null) {
            if ($this->_registryAlias === null) {
                $this->_registryAlias = $this->alias();
            }
            return $this->_registryAlias;
        }
        $this->_registryAlias = $registryAlias;
        return $this;
    }

/**
 * Get/Set primary key(s) used in this table.
 *
 * @param string|array $primaryKey Primary key name
 * @return Table|string|array
 * @throws \TableException If trying to set primary key that
 */
    public function primaryKey($primaryKey = null) {
        if ($primaryKey === null) {
            if ($this->_primaryKey === null) {
                $index = $this->schema()->primaryKey();
                if ($index instanceof Index) {
                    [$key] = $index->getColumns();
                } else {
                    $key = 'id';
                }
                $this->_primaryKey = $key;
            }
            return $this->_primaryKey;
        }

        $primaryKeys = (array)$primaryKey;
        foreach ($primaryKeys as $index => $primaryKey) {
            if (!$this->hasField($primaryKey)) {
                throw new TableException(sprintf(
                    'Trying to set "%s" as primary key #%s but field doesn\'t exist in table "%s".',
                    $primaryKey,
                    $index + 1,
                    $this->_table
                ));
            }
        }

        $this->_primaryKey = $primaryKeys;
        return $this;
    }

/**
 * Get table class name.
 *
 * @return string
 */
    public function name() {
        return $this->_name;
    }

/**
 * Returns a list of all events that will fire in the model during it's lifecycle.
 * You can override this function to add you own listener callbacks
 *
 * @return array
 */
    public function implementedEvents() {
        $eventMap = [
            'Model.afterStartup' => 'afterStartup',
            'Model.beforeFind' => 'beforeFind',
            'Model.beforeFind' => 'beforeFind',
            'Model.beforeMarshal' => 'beforeMarshal',
            'Model.beforeSave' => 'beforeSave',
            'Model.afterSave' => 'afterSave',
            'Model.beforeInsert' => 'beforeInsert',
            'Model.afterInsert' => 'afterInsert',
            'Model.beforeUpdate' => 'beforeUpdate',
            'Model.afterUpdate' => 'afterUpdate',
            'Model.beforeDelete' => 'beforeDelete',
            'Model.afterDelete' => 'afterDelete',
            'Model.beforeDeleteAll' => 'beforeDeleteAll',
            'Model.afterDeleteAll' => 'afterDeleteAll',
            'Model.shutdown' => 'beforeShutdown'
        ];

        $events = [];
        foreach ($eventMap as $event => $method) {
            if (!method_exists($this, $method)) {
                continue;
            }

            $events[$event] = $method;
        }

        return $events;
    }

/**
 * Perform the various shutdown processes for this table.
 * Fire the Components and Controller callbacks in the correct order.
 *
 * @return void
 */
    public function shutdownProcess() {
        $this->dispatchEvent('Model.shutdown');
    }

/**
 * Get database connection.
 *
 * @param Connection|string $conn Connection instance
 * or connection's config name.
 * @return Connection|$this
 */
    public function connection($conn = null) {
        if ($conn === null) {
            if ($this->_connection === null) {
                $this->_connection = $this->_config['connection'];
            }

            if (is_string($this->_connection)) {
                $this->_connection = ConnectionManager::get($this->_connection);
            }

            return $this->_connection;
        }
        $this->_connection = $conn;
        return $this;
    }

/**
 * Set/get Entity class name.
 *
 * @param string $name Entity class alias
 * @return string Entity class name
 * @throws \MissingEntityException
 */
    public function entityClass($name = null) {
        if ($name === null) {
            if (!$this->_entityClass) {
                $this->_entityClass = $this->_getDefaultEntityClass();
            }
            return $this->_entityClass;
        }
        $class = App::className($name, 'Model/Entity');
        if (!class_exists($class)) {
            throw new MissingEntityException([$class]);
        }
        $this->_entityClass = $class;
        return $this;
    }

/**
 * Get entity class name.
 *
 * @return string Default entity class name
 */
    protected function _getDefaultEntityClass() {
        $className = '\Nata\ORM\Entity';
        $self = get_called_class();
        $parts = explode('\\', $self);

        if ($self !== __CLASS__ && count($parts) >= 3) {
            $alias = Inflector::singularize(array_pop($parts));
            $name = implode('\\', array_slice($parts, 0, -1)) . '\Entity\\' . $alias;

            if (class_exists($name)) {
                $className = $name;
            }

        }
        return $className;
    }

/**
 * Add behavior.
 *
 * @param string $name Behavior name
 * @param array $options Options
 * @return boolean True if loaded
 */
    public function addBehavior($name, $options = []) {
        return $this->behaviors()->load($name, $options);
    }

/**
 * Remove behavior.
 *
 * @param string $name Behavior name
 * @return boolean True if loaded
 */
    public function removeBehavior($name) {
        return $this->behaviors()->unload($name);
    }

/**
 * Check if behavior exists.
 *
 * @param string $name Behavior name
 * @return boolean True if loaded
 */
    public function hasBehavior($name) {
        return $this->behaviors()->has($name);
    }

/**
 * Get Behavior registry instance.
 *
 * @param string $name Behavior name
 * @return \Nata\ORM\BehaviorRegistry
 */
    public function behaviors(): BehaviorRegistry {
        if ($this->_behaviors === null) {
            $this->_behaviors = new BehaviorRegistry($this);
        }
        return $this->_behaviors;
    }

/**
 * Get associations registry instance.
 *
 * @return \Nata\ORM\AssociationRegistry
 */
    public function associations(): AssociationRegistry {
        if ($this->_associations === null) {
            $this->_associations = new AssociationRegistry($this);
        }
        return $this->_associations;
    }

/**
 * Setup multiple associations.
 *
 * It takes an array containing set of table names indexed by association type
 * as argument:
 *
 * ```
 * $this->Posts->addAssociations([
 *   'belongsTo' => [
 *     'Users' => ['className' => 'App\Model\Table\UsersTable']
 *   ],
 *   'hasMany' => ['Comments'],
 *   'belongsToMany' => ['Tags']
 * ]);
 * ```
 *
 * Each association type accepts multiple associations where the keys
 * are the aliases, and the values are association config data. If numeric
 * keys are used the values will be treated as association aliases.
 *
 * @param array $params Set of associations to bind (indexed by association type)
 * @return void
 * @see \Nata\ORM\Table::belongsTo()
 * @see \Nata\ORM\Table::hasOne()
 * @see \Nata\ORM\Table::hasMany()
 * @see \Nata\ORM\Table::belongsToMany()
 */
    public function addAssociations(array $params) {
        foreach ($params as $assocType => $tables) {
            foreach ($tables as $associated => $options) {
                if (is_numeric($associated)) {
                    $associated = $options;
                    $options = [];
                }
                $this->{$assocType}($associated, $options);
            }
        }
    }

/**
 * Add HasOne association.
 *
 * @param string $associated Association alias
 * @param array $options Association options
 * @return \Nata\ORM\Association\HasOne
 */
    public function hasOne($associated, array $options = []) {
        $options += ['sourceTable' => $this];
        $association = new HasOne($associated, $options);
        return $this->associations()->add($associated, $association);
    }

/**
 * Add HasMany association.
 *
 * @param string $associated Association alias
 * @param array $options Association options
 * @return \Nata\ORM\Association\HasMany
 */
    public function hasMany($associated, array $options = []) {
        $options += ['sourceTable' => $this];
        $association = new HasMany($associated, $options);
        return $this->associations()->add($associated, $association);
    }

/**
 * Add BelongsTo association.
 *
 * @param string $alias Association alias
 * @param array $options Association options
 * @return \Nata\ORM\Association\BelongsTo
 */
    public function belongsTo($associated, array $options = []) {
        $options += ['sourceTable' => $this];
        $association = new BelongsTo($associated, $options);
        return $this->associations()->add($associated, $association);
    }

/**
 * Add BelongsToMany association.
 *
 * @param string $alias Association alias
 * @param array $options Association options
 * @return \Nata\ORM\Association\BelongsToMany
 */
    public function belongsToMany($associated, array $options = []) {
        $options += ['sourceTable' => $this];
        $association = new BelongsToMany($associated, $options);
        return $this->associations()->add($associated, $association);
    }

/**
 * Check if current table's instance has a association with
 * given table.
 *
 * @param string $alias Association alias
 * @return boolean True if there's a association, false otherwise
 */
    public function hasAssociationWith($alias) {
        return $this->associations()->has($alias);
    }

/**
 * Returns the display field or sets a new one
 *
 * @param string|null $key sets a new name to be used as display field
 * @return string
 */
    public function displayField($key = null) {
        if ($key !== null) {
            $this->_displayField = $key;
        }
        if ($this->_displayField === null) {
            $schema = $this->schema();
            $primary = (array)$this->primaryKey();
            $this->_displayField = array_shift($primary);
            if ($schema->column('title')) {
                $this->_displayField = 'title';
            }
            if ($schema->column('name')) {
                $this->_displayField = 'name';
            }
        }
        return $this->_displayField;
    }

/**
 * Get/Set \Nata\ORM\Schema instance.
 *
 * @param Schema $schema Table Schema instance
 * @return Schema|$this
 */
    public function schema(Schema $schema = null) {
        if ($schema === null) {
            if ($this->_schema === null) {
                $this->_schema = $this->connection()->schema()->tableName($this->table());
            }
            return $this->_schema;
        }
        $this->_schema = $schema;
        return $this;
    }

/**
 * Set properties on demand.
 *
 * @param string $name Name of property
 * @return mixed Property value
 */
    public function __get($name) {
        if ($this->hasAssociationWith($name)) {
            return $this->associations()->get($name);
        }
    }

/**
 * Call methods dynamically.
 *
 * @param string $name Method name
 * @param array $args Method arguments
 * @return mixed
 * @throws \BadMethodCallException
 */
    public function __call($method, array $args) {
        if ($this->_behaviors && $this->_behaviors->hasMethod($method)) {
            return $this->_behaviors->call($method, $args);
        }

        if (preg_match('/^find(?:\w+)?By/', $method) > 0) {
            return $this->_dynamicFinder($method, $args);
        }

        throw new BadMethodCallException(sprintf('Method %s doesn\'t exist in %s.', $method, $this->name()));
    }

/**
 * Set methods on demand.
 * Fire the SQLBuilder callbacks in the correct order.
 *
 * @param string $name Name of called method
 * @param array $options Method arguments
 * @return \Nata\ORM\Query
 */
    public function find($find = 'all', array $options = []) {
        $query = $this->query();
        return $this->callFinder($find, $query, $options);
    }

/**
 * All finder method.
 *
 * @param \Nata\ORM\Query $query Query Builder instance
 * @return \Nata\ORM\Query
 */
    public function findAll($query, $options) {
        return $query;
    }

/**
 * Call finder method.
 *
 * @param string $finder Finder name
 * @param \Nata\ORM\Query $query Query builder instance
 * @param array $options finder options
 * @return \Nata\ORM\Query
 * @throws \BadMethodCallException
 */
    public function callFinder($type, Query $query, array $options = []) {
        $query->applyOptions($options);
        $options = $query->getOptions();
        $finder = 'find' . $type;

        if (method_exists($this, $finder)) {
            return $this->{$finder}($query, $options);
        }

        if ($this->_behaviors && $this->_behaviors->hasFinder($type)) {
            return $this->_behaviors->callFinder($type, [$query, $options]);
        }

        throw new BadMethodCallException(
            sprintf('Unknown finder method "%s"', $type)
        );
    }

/**
 * Provides the dynamic findBy and findByAll methods.
 *
 * @param string $method The method name that was fired.
 * @param array $args List of arguments passed to the function.
 * @return mixed
 * @throws \BadMethodCallException when there are missing arguments, or when
 *  and & or are combined.
 */
    protected function _dynamicFinder($method, $args) {
        $method = Inflector::underscore($method);
        preg_match('/^find_([\w]+)_by_/', $method, $matches);

        if (empty($matches)) {
            // find_by_ is 8 characters.
            $fields = substr($method, 8);
            $findType = 'all';
        } else {
            $fields = substr($method, strlen($matches[0]));
            $findType = Inflector::variable($matches[1]);
        }

        $hasOr = strpos($fields, '_or_');
        $hasAnd = strpos($fields, '_and_');
        $makeConditions = function ($fields, $args) {
            $conditions = [];
            if (count($args) < count($fields)) {
                throw new BadMethodCallException(sprintf(
                    'Not enough arguments for magic finder. Got %s required %s',
                    count($args),
                    count($fields)
                ));
            }
            foreach ($fields as $field) {
                $conditions[$this->aliasField($field)] = array_shift($args);
            }
            return $conditions;
        };

        if ($hasOr !== false && $hasAnd !== false) {
            throw new BadMethodCallException(
                'Cannot mix "and" & "or" in a magic finder. Use find() instead.'
            );
        }

        if ($hasOr === false && $hasAnd === false) {
            $conditions = $makeConditions([$fields], $args);
        } elseif ($hasOr !== false) {
            $fields = explode('_or_', $fields);
            $conditions = [
                'OR' => $makeConditions($fields, $args)
            ];
        } elseif ($hasAnd !== false) {
            $fields = explode('_and_', $fields);
            $conditions = $makeConditions($fields, $args);
        }

        return $this->find($findType, [
            'conditions' => $conditions
        ]);
    }

/**
 * Sets up a query object so results appear as an indexed array, useful for any
 * place where you would want a list such as for populating input select boxes.
 *
 * When calling this finder, the fields passed are used to determine what should
 * be used as the array key, value and optionally what to group the results by.
 * By default the primary key for the model is used for the key, and the display
 * field as value.
 *
 * The results of this finder will be in the following form:
 *
 * ```
 * [
 *  1 => 'value for id 1',
 *  2 => 'value for id 2',
 *  4 => 'value for id 4'
 * ]
 * ```
 *
 * You can specify which property will be used as the key and which as value
 * by using the `$options` array, when not specified, it will use the results
 * of calling `primaryKey` and `displayField` respectively in this table:
 *
 * ```
 * $table->find('list', [
 *  'keyField' => 'name',
 *  'valueField' => 'age'
 * ]);
 * ```
 *
 * Results can be put together in bigger groups when they share a property, you
 * can customize the property to use for grouping by setting `groupField`:
 *
 * ```
 * $table->find('list', [
 *  'groupField' => 'category_id',
 * ]);
 * ```
 *
 * When using a `groupField` results will be returned in this format:
 *
 * ```
 * [
 *  'group_1' => [
 *      1 => 'value for id 1',
 *      2 => 'value for id 2',
 *  ]
 *  'group_2' => [
 *      4 => 'value for id 4'
 *  ]
 * ]
 * ```
 *
 * @param \Cake\ORM\Query $query The query to find with
 * @param array $options The options for the find
 * @return \Cake\ORM\Query The query builder
 */
    public function findList(Query $query, array $options) {
        $options += [
            'keyField' => $this->primaryKey(),
            'valueField' => $this->displayField(),
            'groupField' => null
        ];

        if (isset($options['idField'])) {
            $options['keyField'] = $options['idField'];
            unset($options['idField']);
            trigger_error('Option "idField" is deprecated, use "keyField" instead.', E_USER_WARNING);
        }

        if (!$query->clause('select') &&
            !is_object($options['keyField']) &&
            !is_object($options['valueField']) &&
            !is_object($options['groupField'])
        ) {
            $fields = array_merge(
                (array)$options['keyField'],
                (array)$options['valueField'],
                (array)$options['groupField']
            );
            $columns = $this->schema()->columns();
            if (count($fields) === count(array_intersect($fields, $columns))) {
                $query->select($fields);
            }
        }

        $options = $this->_setFieldMatchers(
            $options,
            ['keyField', 'valueField', 'groupField']
        );

        return $query->formatResults(function ($results) use ($options) {
            return $results->combine(
                $options['keyField'],
                $options['valueField'],
                $options['groupField']
            );
        });
    }

/**
 * Results for this finder will be a nested array, and is appropriate if you want
 * to use the parent_id field of your model data to build nested results.
 *
 * Values belonging to a parent row based on their parent_id value will be
 * recursively nested inside the parent row values using the `children` property
 *
 * You can customize what fields are used for nesting results, by default the
 * primary key and the `parent_id` fields are used. If you wish to change
 * these defaults you need to provide the keys `keyField` or `parentField` in
 * `$options`:
 *
 * ```
 * $table->find('threaded', [
 *  'keyField' => 'id',
 *  'parentField' => 'ancestor_id'
 * ]);
 * ```
 *
 * @param \Cake\ORM\Query $query The query to find with
 * @param array $options The options to find with
 * @return \Cake\ORM\Query The query builder
 */
    public function findThreaded(Query $query, array $options) {
        $options += [
            'keyField' => $this->primaryKey(),
            'parentField' => 'parent_id'
        ];

        $options = $this->_setFieldMatchers($options, ['keyField', 'parentField']);

        return $query->formatResults(function ($results) use ($options) {
            return $results->nest($options['keyField'], $options['parentField']);
        });
    }

/**
 * Out of an options array, check if the keys described in `$keys` are arrays
 * and change the values for closures that will concatenate the each of the
 * properties in the value array when passed a row.
 *
 * This is an auxiliary function used for result formatters that can accept
 * composite keys when comparing values.
 *
 * @param array $options the original options passed to a finder
 * @param array $keys the keys to check in $options to build matchers from
 * the associated value
 * @return array
 */
    protected function _setFieldMatchers($options, $keys) {
        foreach ($keys as $field) {
            if (!is_array($options[$field])) {
                continue;
            }

            if (count($options[$field]) === 1) {
                $options[$field] = current($options[$field]);
                continue;
            }

            $fields = $options[$field];
            $options[$field] = function ($row) use ($fields) {
                $matches = [];
                foreach ($fields as $field) {
                    $matches[] = $row[$field];
                }
                return implode(';', $matches);
            };
        }
        return $options;
    }

/**
 * Get one entity by primary key.
 *
 * @param array $primaryKey Row primary key value
 * @return \Nata\ORM\Entity
 * @throws \InvalidPrimaryKeyException
 */
    public function get($primaryKey, array $options = []) {
        $key = (array)$this->primaryKey();
        $alias = $this->alias();
        foreach ($key as $index => $keyname) {
            $key[$index] = $alias . '.' . $keyname;
        }

        $primaryKey = (array)$primaryKey;
        if (count($key) !== count($primaryKey)) {
            $primaryKey = $primaryKey ?: [null];
            $primaryKey = array_map(function ($key) {
                return var_export($key, true);
            }, $primaryKey);

            throw new InvalidPrimaryKeyException(sprintf(
                'Record not found in table "%s" with primary key [%s]',
                $this->table(),
                implode(', ', $primaryKey)
            ));
        }

        $conditions = array_combine($key, $primaryKey);

        $cacheConfig = isset($options['cache']) ? $options['cache'] : false;
        $cacheKey = isset($options['key']) ? $options['key'] : false;
        $finder = isset($options['finder']) ? $options['finder'] : 'all';
        unset($options['key'], $options['cache'], $options['finder']);
        $query = $this->find($finder, $options)->where($conditions);

        if ($cacheConfig) {
            if (!$cacheKey) {
                $cacheKey = sprintf(
                    "get:%s.%s%s",
                    $this->connection()->config,
                    $this->table(),
                    json_encode($primaryKey)
                );
            }
            $query->cache($cacheKey, $cacheConfig);
        }

        return $query->firstOrFail();
    }

/**
 * Get SQL Query Builder instance.
 *
 * @return \Nata\ORM\Query
 */
    public function query() {
        return new Query($this);
    }

/**
 * Save (Insert or Update) multiple entities/rows.
 *
 * Usage examples:
 *
 *  ## Insert new entity:
 *  $table->saveAll([
 *      0 => [
 *          'name' => 'John Doe',
 *      ],
 *      1 => [
 *          'name' => 'Mary Doe',
 *      ]
 *  ]);
 *  // Return
 *  Array(
 *      0 => [
 *          'id' => 1,
 *          'name' => 'John Doe',
 *      ],
 *      1 => [
 *          'id' => 2,
 *          'name' => 'Mary Doe',
 *      ]
 *  );
 *
 * @param Collection|array $data Data of data to save
 * @param array $options Options for saving data
 * @return array|bool Returned data with ID, or false
 */
    public function saveAll($data, array $options = []) {
        $entities = [];
        foreach ($data as $index => $entity) {
            $entities[$index] = $this->save($entity, $options);
        }
        return $entities;
    }

/**
 * Save (Insert or Update) entity/row.
 *
 * Usage examples:
 *
 *  #### Insert entity:
 *
 * ```
 *  $table->save([
 *      'name' => 'John Doe'
 *  ]);
 *  // Return
 *  \Nata\ORM\Entity (
 *      ['id'] => 1,
 *      ['name'] => 'John Doe'
 *  );
 * ```
 *
 * @param array|Nata\ORM\Entity $entity Entity to save
 * @param array $options Options for saving data
 * @return \Nata\ORM\Entity|bool Returned entity with ID, or false
 */
    public function save($entity, array $options = []) {
        if (empty($entity)) {
            return false;
        }

        $options += [
            'atomic' => true,
            'associated' => true,
            'async' => false, // TODO
            '__parent' => true
        ];

        $entity = $this->newEntity($entity);
        if (!$entity->isDirty()) {
            return $entity;
        }

        // Get entity hash before events
        $entityHash = spl_object_hash($entity);

        // Before Save
        $eventArg = ['entity' => $entity, 'options' => $options];
        $event = $this->dispatchEvent('Model.beforeSave', $eventArg);
        if ($event) {
            $eventArg['options'] = $options = $event->data('options');
        }

        // Before Insert/Update
        $eventName = $entity->isNew() ? 'Insert' : 'Update';
        $this->dispatchEvent('Model.before' . $eventName, $eventArg);

        // Check if entity was replaced/removed on events.
        // This is not allowed because it might remove essential properties set in
        // associations and trigger an foreign key integrity database error.
        if (!($entity instanceof Entity) || ($entity instanceof Entity && $entityHash !== spl_object_hash($entity))) {
            throw new LogicException(sprintf(
                'You cannot replace "%s" instance with a new one. You must either keep the original instance or set no instance at all.',
                get_class($entity)
            ));
        }

        $connection = $this->connection();
        try {
            if ($options['atomic'] && $options['__parent']) {
                $connection->beginTransaction();
            }

            if ($this->_save($entity, $options) === false) {
                if ($options['atomic'] && $options['__parent']) {
                    $connection->rollBack();
                }
                return false;
            }

            if ($options['atomic'] && $options['__parent']) {
                if ($connection->commit() === false) {
                    $connection->rollBack();
                    return false;
                }
            }

        // Retryable exception
        } catch (RetryableException $e) {
            if ($options['atomic'] && $options['__parent']) {
                $connection->beginTransaction();
            }

            if ($this->_save($entity, $options) === false) {
                if ($options['atomic'] && $options['__parent']) {
                    $connection->rollBack();
                }
                return false;
            }

            if ($options['atomic'] && $options['__parent']) {
                if ($connection->commit() === false) {
                    $connection->rollBack();
                    return false;
                }
            }
        }

        $entity->clean();

        // After events
        $this->dispatchEvent('Model.after' . $eventName, $eventArg);
        $this->dispatchEvent('Model.afterSave', $eventArg);

        $entity->isNew(false);

        return $entity;
    }

/**
 * Prepare, process and attempt to persist entity data and respective associations.
 *
 * @param Entity $entity Data to be saved
 * @param array $options Save options
 * @return bool False if there was an error
 */
    private function _save(Entity $entity, array $options): bool {
        // Associations before saving
        $this->associations()->beforeSave($entity, $options);
        if ($entity->hasErrors()) {
            return false;
        }

        // Check if is really new based on existing primary key or unique indexes.
        // Were using database query directly by performance reasons as well as we don't
        // need to trigger table events (beforeFind, etc), we want what's currently in the table
        // without any kind of filtering.
        $query = (new DatabaseQuery($this->connection()))->from($this->table(), $this->alias());
        $existingPrimaryKeys = $this->_getExistsConditions($query, $entity)->limit(1)->fetch();
        $this->_setUniqueKeys($existingPrimaryKeys, $entity, true);

        $values = $this->_extractValues($entity, $options);

        // If no dirty values are found, check if there are dirty associations, if so, an entity has to be persisted.
        if (empty($values) && $this->associations()->hasDirtyProperties($entity, $options) && $entity->isNew()) {
            $values[$this->_createdAt] = $this->_nowDatetime();
        }

        $success = empty($values);
        if ($values) {
            // Timestamp
            $datetime = $this->_nowDatetime();
            if ($entity->isNew()) {
                unset($values[$this->_updatedAt]);
                // Insert timestamp
                if ($this->hasField($this->_createdAt)) {
                    $values[$this->_createdAt] = $datetime;
                }
                $success = $this->_insert($values, $entity, $options);
                if ($success) {
                    $entity->set($this->_createdAt, $datetime);
                }
            } else {
                unset($values[$this->_createdAt]);
                // Update timestamp
                if ($this->hasField($this->_updatedAt)) {
                    $values[$this->_updatedAt] = $datetime;
                }
                $success = $this->_update($values, $entity, $options);
                if ($success) {
                    $entity->set($this->_updatedAt, $datetime);
                }
            }
            $values = null;
        }

        // Associations after saving
        $this->associations()->afterSave($entity, $options);
        if ($entity->hasErrors()) {
            return false;
        }

        return $success;
    }

/**
 * Insert data.
 *
 * @param array $values Data to insert/persist
 * @param Entity $entity Entity instance
 * @param array $options Operation options
 * @return bool True if inserted successfully
 */
    private function _insert(array $values, Entity $entity, array $options): bool {
        $primaryKey = (array)$this->primaryKey();

        // Check for empty primary key
        foreach ($primaryKey as $pKey) {
            if (!isset($values[$pKey]) || !empty($values[$pKey])) {
                continue;
            }
            throw new InvalidPrimaryKeyException(sprintf(
                'Empty primary key "%s".',
                $pKey
            ));
        }

        $rowCount = $this->query()
            ->insert()
            ->values($values)
            ->execute();

        // Check if a primary key value was not given for persistence
        $primaryKeyValue = $entity->get($primaryKey[0]);
        if (empty($primaryKeyValue)) {
            $insertId = $this->_lastInsertId();
            // Check if new record was saved successfully
            // We must check the lastInsertId for the existing record ID
            // that may not be present in the source entity
            if (empty($insertId) && $rowCount === 0) {
                return false;
            }

            $entity->set($primaryKey[0], $insertId)->dirty($primaryKey[0], false);
        }

        return true;
    }

/**
 * Update data.
 *
 * @param array $values Data to updated/persisted
 * @param Entity $entity Entity instance
 * @param array $options Operation options
 * @return bool True if updated successfully
 */
    private function _update(array $values, Entity $entity, array $options): bool {
        $primaryKey = (array)$this->primaryKey();
        $conditions = [];
        foreach ($primaryKey as $pKey) {
            $pKValue = $entity->get($pKey);
            if (empty($pKValue)) {
                continue;
            }
            $conditions[$pKey] = $pKValue;
            // Remove primary key from $values
            unset($values[$pKey]);
        }

        // Check missing primary key in a composite primary key config
        if (count($primaryKey) > 1 && count($primaryKey) !== count($conditions)) {
            throw new InvalidPrimaryKeyException(sprintf(
                'Missing composite primary key. Expecting "%s" but got only "%s"',
                implode(', ', $primaryKey),
                implode(', ', array_keys($conditions))
            ));
        }

        if (!$conditions) {
            throw new LogicException(sprintf(
                'Missing primary key in entity "%s" of "%s"',
                App::classShortName($entity),
                $this->registryAlias()
            ));
        }

        if ($values) {
            $this->query()
                ->update()
                ->set($values)
                ->where($conditions)
                ->limit(1)
                ->execute();
        }

        return true;
    }

/**
 * Extract values from entity's dirty properties with minimal validation going on.
 *
 * @param Entity $entity Entity instance
 * @return array Values to be persisted
 */
    private function _extractValues(Entity $entity): array {
        $schema = $this->schema();
        $databasePlatform = $this->connection()->getDatabasePlatform();

        $dirty = $entity->extract($schema->columns(), true);
        $values = [];
        foreach ($dirty as $propertyName => $propertyValue) {
            $column = $schema->column($propertyName);
            $type = $column->getType();
            $typeName = $type->lookupName($type);

            // If is boolean
            if (is_bool($propertyValue)) {
                $propertyValue = (int)$propertyValue;
            // If trim string
            } elseif (is_string($propertyValue)) {
                $propertyValue = trim($propertyValue);
            }

            // Check if is date/time field
            if (stripos($typeName, 'date') !== false || $typeName === 'time' || $typeName === 'year') {
                $propertyValue = $this->_formatDatabaseDateTime($type, $propertyValue, $databasePlatform);
            } else {
                $propertyValue = $type->convertToDatabaseValue($propertyValue, $databasePlatform);
            }

            if (is_array($propertyValue) || (is_object($propertyValue) && method_exists($propertyValue, '__toString') === false)) {
                $entity->errors($propertyName, sprintf('Expected "%s" but got "%s"', $typeName, gettype($propertyValue)));
                continue;
            }

            // Check if null/empty string is not allowed
            if ($column->getNotNull() && ($propertyValue === null || ($propertyValue !== null && strlen($propertyValue) === 0))) {
                continue;
            }

            // Set value to null if has no length
            if ($column->getNotNull() === false && $propertyValue !== null && (
                (is_string($propertyValue) && (strlen($propertyValue) === 0 || ($typeName === 'json' && $propertyValue === '[]'))) ||
                (is_array($propertyValue) && count($propertyValue) === 0)
            )) {
                $propertyValue = null;
            }

            $values[$propertyName] = $propertyValue;
        }

        return $values;
    }

/**
 * Convert datetime formats to friendlier database format.
 *
 * @param object $type Column type instance
 * @param mixed $value Value
 * @param object $databasePlatform Value
 * @return string|null Formatted datetime
 */
    private function _formatDatabaseDateTime($type, $value, $databasePlatform): ?string {
        if (empty($value)) {
            return null;
        }

        // Year
        if (is_numeric($value) && strlen($value) === 4) {
            return $value;
        }

        if (!($value instanceof Time)) {
            $value = new Time($value);
        }

        $timezone = $value->timezone();

        // Database timezone
        $value->timezone('UTC');

        $string = $type->convertToDatabaseValue($value, $databasePlatform);

        // Revert to original timezone
        $value->timezone($timezone);

        return $string;
    }

/**
 * Insert single entity.
 *
 * @param Entity|array $entity Entity to insert
 * @param array $options Insert options
 * @return Entity|boolean Entity if successful, false otherwise
 */
    public function insert($entity, array $options = []) {
        $options += [
            'timestamps' => true
        ];

        if (!($entity instanceof Entity)) {
            $entity = $this->newEntity($entity);
        }

        $this->dispatchEvent('Model.beforeInsert', ['entity' => $entity]);
        $this->dispatchEvent('Model.beforeSave', ['entity' => $entity]);

        $values = $this->_extractValues($entity, $options);

        if (!isset($values[$this->_createdAt]) && $this->hasField($this->_createdAt)) {
            $values[$this->_createdAt] = $this->_nowDatetime();
        }

        $rowCount = $this->query()
            ->insert()
            ->values($values)
            ->execute();

        if ($rowCount === 0) {
            return false;
        }

        $entity->set($this->_primaryKey, $this->_lastInsertId());
        $entity->clean();

        $this->dispatchEvent('Model.afterInsert', ['entity' => $entity]);
        $this->dispatchEvent('Model.afterSave', ['entity' => $entity]);

        return $entity;
    }

/**
 * Update single entity.
 *
 * @param Entity|array $entity Entity to update
 * @param array $options Update options
 * @return Entity|boolean Entity if successful, false otherwise
 * @throws \InvalidArgumentException
 */
    public function update($entity, array $options = []) {
        $options += [
            'timestamps' => true
        ];

        if (!($entity instanceof Entity)) {
            $entity = $this->newEntity($entity);
        }

        $this->dispatchEvent('Model.beforeUpdate', ['entity' => $entity]);
        $this->dispatchEvent('Model.beforeSave', ['entity' => $entity]);

        [$primaryKey] = (array)$this->primaryKey();
        $primaryKeyValue = $entity->get($primaryKey);
        if (empty($primaryKeyValue)) {
            $this->_throwMissingPrimaryKeyException('Unable to update. Missing primary key "%s".');
        }

        $values = $this->_extractValues($entity, $options);
        if (!isset($values[$this->_updatedAt]) && $this->hasField($this->_updatedAt)) {
            $values[$this->_updatedAt] = $this->_nowDatetime();
        }

        // Remove Primary Key from list of values to update
        unset($values[$primaryKey]);

        if (!empty($values)) {
            $rowCount = $this->query()
                ->update()
                ->set($values)
                ->where([
                    $primaryKey => $primaryKeyValue
                ])
                ->limit(1)
                ->execute();

            if ($rowCount === 0) {
                return false;
            }
        }

        $entity->clean();

        $this->dispatchEvent('Model.afterUpdate', ['entity' => $entity]);
        $this->dispatchEvent('Model.afterSave', ['entity' => $entity]);

        return $entity;
    }

/**
 * Bulk Update fields in given conditions.
 *
 * @param array $fields Fields and respective data to update
 * @param array $conditions Update WHERE conditions
 * @param array $options Options
 * @return int Affected rows count
 */
    public function updateAll(array $fields, array $conditions, array $options = []) {
        $options += [
            'timestamps' => true
        ];

        if ($this->hasField($this->_updatedAt) && !isset($fields[$this->_updatedAt]) && $options['timestamps']) {
            $fields[$this->_updatedAt] = $this->_nowDatetime();
        }

        $this->dispatchEvent('Model.beforeUpdateAll', [
            'fields' => $fields,
            'conditions' => $conditions
        ]);

        $rowCount = $this->query()
            ->update()
            ->set($fields)
            ->where($conditions)
            ->execute();

        $this->dispatchEvent('Model.afterUpdateAll', [
            'fields' => $fields,
            'conditions' => $conditions,
            'rowCount' => $rowCount
        ]);

        return $rowCount;
    }

/**
 * Delete single entity.
 *
 * @param \Nata\ORM\Entity|array $entity Single entity/row
 * @param array $options delete options
 * @return boolean Success
 * @throws \InvalidArgumentException
 */
    public function delete($entity, array $options = []) {
        if (!($entity instanceof Entity)) {
            $entity = $this->newEntity($entity);
        }

        $this->dispatchEvent('Model.beforeDelete', ['entity' => $entity]);

        [$primaryKey] = (array)$this->primaryKey();
        $primaryKeyValue = $entity->get($primaryKey);
        if (empty($primaryKeyValue)) {
            $this->_throwMissingPrimaryKeyException('Unable to delete. Missing primary key "%s".');
        }

        $rowCount = $this->query()
            ->delete()
            ->where([
                $primaryKey => $primaryKeyValue
            ])
            ->limit(1)
            ->execute();

        $this->dispatchEvent('Model.afterDelete', ['entity' => $entity]);

        return $rowCount > 0;
    }

/**
 * Bulk Delete records in given conditions.
 *
 * @param array $conditions Delete WHERE conditions
 * @return int Row count
 */
    public function deleteAll(array $conditions) {
        if (!$conditions) {
            return false;
        }

        $this->dispatchEvent('Model.beforeDeleteAll', [
            'conditions' => $conditions
        ]);

        $maxAttempts = 3;
        $attempts = 0;
        while (true) {
            $attempts++;

            try {
                $rowCount = $this->_deleteAll($conditions);
            } catch (Throwable $throwable) {
                if (stripos($throwable->getMessage(), '1213 Deadlock found') !== false && $attempts <= $maxAttempts) {
                    sleep(2);
                    continue;
                }
                throw $throwable;
            }

            break;
        }

        $this->dispatchEvent('Model.afterDeleteAll', [
            'conditions' => $conditions,
            'rowCount' => $rowCount
        ]);

        return $rowCount;
    }

/**
 * Throw missing primary key exception.
 *
 * @param string $message Exception message
 * @throws \InvalidArgumentException
 */
    protected function _deleteAll(array $conditions) {
        return $this->query()
            ->delete()
            ->where($conditions)
            ->execute();
    }

/**
 * Get current UTC date.
 *
 * @return string Datetime
 */
    protected function _nowDatetime() {
        return (new Time)->timezone('UTC')->format('Y-m-d H:i:s');
    }

/**
 * Get last insert ID (alias for DBAL lastInsertId()).
 *
 * @return int Last insert id
 */
    protected function _lastInsertId() {
        return $this->_connection->lastInsertId();
    }

/**
 * Hydrate data array.
 *
 * @param array $data Multidimensional array of data
 * @param array $options Entity options
 * @return array Array of Entities
 */
    public function newEntities(array $data = [], array $options = []) {
        if (empty($data)) {
            return [];
        }

        $entities = [];
        foreach ($data as $key => $row) {
            if (empty($row)) {
                continue;
            }

            $entities[$key] = $this->newEntity($row, $options);
        }

        return $entities;
    }

/**
 * Create new entity.
 *
 * If data it's not an array, assumes it's the primary key.
 *
 * @param array|string|int $data Array of data
 * @param array $options Entity options
 * @return \Nata\ORM\Entity
 */
    public function newEntity($data = [], array $options = []) {
        if ($data instanceof Entity) {
            return $data;
        }

        if ($data === null) {
            $data = [];
        }

        [$primaryKey] = (array)$this->primaryKey();
        $field = $this->schema()->column($primaryKey);
        if (is_numeric($data) && $field->getType() instanceof IntegerType) {
            $data = [$primaryKey => $data];
            if (!isset($options['markNew'])) {
                $options['markNew'] = false;
            }
            if (!isset($options['markClean'])) {
                $options['markClean'] = true;
            }
        } elseif (!is_array($data)) {
            $data = [];
        }

        $options += [
            'source' => $this->_registryAlias,
            'markNew' => !(isset($data[$primaryKey]) && !empty($data[$primaryKey])),
            'enum' => $this->behaviors()->has('Enum') ? $this->enum() : null
        ];

        $this->dispatchEvent('Model.beforeMarshal', ['data' => $data, 'options' => $options]);

        $entityClass = $this->entityClass();
        $entity = new $entityClass($data, $options);

        $this->dispatchEvent('Model.afterMarshal', ['entity' => $entity]);

        return $entity;
    }

/**
 * Truncate table completly.
 * Use this wisely.
 *
 * @return void
 * @throws \Exception
 */
    public function truncate() {
        $conn = $this->connection()->getDoctrineConnection();
        try {
            $conn->executeQuery('SET FOREIGN_KEY_CHECKS = 0', []);
            $conn->executeQuery('TRUNCATE ' . $this->table(), []);
            $conn->executeQuery('SET FOREIGN_KEY_CHECKS = 1', []);
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

/**
 * Throw missing primary key exception.
 *
 * @param string $message Exception message
 * @throws \InvalidArgumentException
 */
    private function _throwMissingPrimaryKeyException($message) {
        throw new InvalidArgumentException(sprintf(
            $message,
            implode(', ', (array)$this->primaryKey())
        ));
    }

/**
 * Check if record exists.
 * If an entity is passed it will take into account
 * the unique indexes to check if exists.
 *
 * @param Entity|array $conditions WHERE conditions
 * @return boolean True if exists, false otherwise
 */
    public function exists($conditions) {
        if ($conditions instanceof Entity) {
            $existingPrimaryKeys = $this->_getExistsConditions($this->query(), $conditions)
                ->hydrate(false)
                ->first();
            return $this->_setUniqueKeys($existingPrimaryKeys, $conditions, true);
        }
        return (bool)$this->query()
            ->where($conditions)
            ->count();
    }

/**
 * Check if record exists.
 * If an entity is passed it will take into account
 * the unique indexes to check if exists.
 *
 * @param mixed $existingPrimaryKeys Existing primary keys to set
 * @param Entity $entity Entity
 * @param bool $setNewFlag Set new flag
 * @return bool
 */
    protected function _setUniqueKeys($existingPrimaryKeys, Entity $entity, bool $setNewFlag) {
        if (!$existingPrimaryKeys) {
            if ($setNewFlag) {
                $entity->isNew(true)->wasNew(true);
            }
            return false;
        }

        foreach ($existingPrimaryKeys as $prop => $val) {
            $entity->set($prop, $val, ['guard' => false])->dirty($prop, false);
        }

        if ($setNewFlag) {
            $entity->isNew(false)->wasNew(false);
        }

        $existingPrimaryKeys = null;

        return true;
    }

/**
 * Build given query with unique keys and primary keys.
 *
 * @param Query $query Query to build
 * @param Entity $entity Entity to check
 * @return Query|DatabaseQuery Query built with conditions
 */
    public function _getExistsConditions(DatabaseQuery $query, Entity $entity) {
        $schema = $this->schema();
        $valuePlaceholder = 'nata-non-value-placeholder';
        $dateValuePlaceholders = [
            'datetime' => '1900-01-01 00:00:00',
            'date' => '1900-01-01',
            'time' => '00:00:00'
        ];

        // Find by primary keys
        $primaryKeys = $schema->primaryKey()->getColumns();
        $conditions = [];
        foreach ($primaryKeys as $primaryKey) {
            $value = $entity->get($primaryKey);
            if (empty($value)) {
                $value = $valuePlaceholder;
            }
            $conditions[$primaryKey] = $value;
        }
        $query->andWhere($conditions);

        // Find by unique indexes
        $uniqueIndexes = $schema->uniqueIndexes();
        foreach ($uniqueIndexes as $uniqueIndex) {
            $conditions = [];
            $columns = $uniqueIndex->getColumns();
            foreach ($columns as $columnName) {
                $column = $schema->column($columnName);
                $type = $column->getType();
                $typeName = $type->lookupName($type);

                $value = $entity->get($columnName);
                if (empty($value)) {
                    $value = str_contains($typeName, 'date') || str_contains($typeName, 'time') ? $dateValuePlaceholders[$typeName] : $valuePlaceholder;
                }
                $conditions['AND'][] = [$columnName => $value];
            }
            $query->orWhere($conditions);
        }

        return $query->select($primaryKeys);
    }

/**
 * Touch an entity.
 *
 * Bumps timestamp fields for an entity.
 * This method will overwrite any pre-existing value.
 *
 * @param Entity $entity Entity instance.
 * @return bool true if a field is updated, false if no action performed
 */
    public function touch(Entity $entity): bool {
        if ($entity->isNew()) {
            return false;
        }

        $fieldName = $this->_updatedAt;
        $entity->set($fieldName, new Time());
        return $this->update($entity) instanceof Entity;
    }

/**
 * Check if field(s) exists.
 *
 * @param string|array $name Field name(s)
 * @return bool True if exists, false otherwise
 */
    public function hasField($name) {
        $diff = array_diff((array)$name, $this->schema()->columns());
        return empty($diff);
    }

/**
 * Check if finder for the table exists.
 *
 * @param string $type Name of finder to check
 * @return bool Returns true if the finder exists for the table
 */
    public function hasFinder($type) {
        $finder = 'find' . $type;
        if (method_exists($this, $finder)) {
            return true;
        }
        return $this->_behaviors && $this->_behaviors->hasFinder($type);
    }

/**
 * After startup event.
 *
 * @param \Nata\Event\Event $event Event instance
 * @return \Nata\Event\Event Event instance
 */
    public function afterStartup($event) {}

/**
 * Returns an array that can be used to describe the internal state of this
 * object.
 *
 * @return array
 */
    public function __debugInfo() {
        return [
            'registryAlias' => $this->_registryAlias,
            'table' => $this->table(),
            'entityClass' => $this->entityClass(),
            'primaryKey' => $this->primaryKey(),
            'associations' => $this->associations()->get(),
            'behaviors' => $this->behaviors()->loaded()
        ];
    }

}
