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
use Nata\ORM\TableRegistry;
use Nata\ORM\Entity;
use Nata\ORM\Query;
use Nata\Utility\Inflector;
use LogicException;
use Nata\Collection\Collection;

class Association extends NataObject {

/**
 * Association type.
 *
 * @var string
 */
    protected $_type;

/**
 * Association name.
 *
 * @var string
 */
    protected $_name;

/**
 * Source table class instance
 *
 * @var \Nata\ORM\Table
 */
    protected $_source;

/**
 * Target table class instance
 *
 * @var \Nata\ORM\Table
 */
    protected $_target;

/**
 * Alias of target table class.
 *
 * @var string
 */
    protected $_associationAlias;

/**
 * Binding key (Defaults to primary key).
 *
 * @var string
 */
    protected $_bindingKey;

/**
 * Source foreign key.
 *
 * @var string
 */
    protected $_foreignKey;

/**
 * Source foreign model.
 * This is used in case of a polymorphic model setting.
 *
 * @var string
 */
    protected $_foreignModel;

/**
 * Polymorphic source table/model for foreign key(s).
 *
 * @var bool
 * @deprecated Use $_polymorphic instead
 */
    protected $_dynamic;

/**
 * Polymorphic source table/model for foreign key(s).
 *
 * @var bool
 */
    protected $_polymorphic;

/**
 * Name of the (polymorphic) foreign association.
 * This is used in case of a polymorphic model setting to use
 * the foreign association config.
 *
 * @var string
 */
    protected $_foreignAssociation;

/**
 * Column name that stores the association name alongside the polymorphic model name.
 * Allows multiple polymorphic associations from the same source table to the same
 * target table to be distinguished (e.g. Logo vs Icon both pointing to Files).
 *
 * @var string|null
 */
    protected $_foreignAssociationName;

/**
 * Property name to be used in result set.
 *
 * @var string
 */
    protected $_propertyName;

/**
 * Singularized Property name.
 *
 * @var string
 */
    protected $_singularPropertyName;

/**
 * Single record.
 *
 * @var bool
 */
    protected $_single = false;

/**
 * Save strategy.
 *
 * - 'append' Will add records to join table.
 * - 'replace' Removes all linked records and add new.
 * - 'compare' Removes only the records not present on the new list.
 *
 * @var string
 */
    protected $_saveStrategy = 'compare';

/**
 * Association conditions.
 *
 * @var array
 */
    protected $_conditions;

/**
 * Default values for the association.
 *
 * @var array
 */
    protected $_defaultValues;

/**
 * Association finder.
 *
 * @var string
 */
    protected $_finder;

/**
 * Association sort/order.
 *
 * @var array
 */
    protected $_sort;

/**
 * Association errors.
 *
 * @var string|array
 */
    protected $_errors;


/**
 * Constructor.
 *
 * @param array $options Association options
 * @return void
 */
    public function __construct(string $alias, array $options = []) {
        $options += [
            'sourceTable' => null,
            'finder' => null,
            'single' => null,
            'dynamic' => null,
            'polymorphic' => null,
            'foreignKey' => null,
            'foreignModel' => null,
            'foreignModel' => null,
            'foreignAssociation' => null,
            'foreignAssociationName' => null,
            'propertyName' => null,
            'className' => $alias,
            'touch' => null,
            'conditions' => null,
            'saveStrategy' => null,
            'defaultValues' => null,
            'sort' => null,
        ];

        $this->_name = $alias;
        $this->_source = $options['sourceTable'];
        $this->_target = $options['className'];

        if ($options['dynamic'] !== null) {
            $this->_polymorphic = $options['dynamic'];
        }
        if ($options['polymorphic'] !== null) {
            $this->_polymorphic = $options['polymorphic'];
        }
        if ($options['foreignKey']) {
            $this->_foreignKey = $options['foreignKey'];
        }
        if ($options['foreignModel']) {
            $this->_foreignModel = $options['foreignModel'];
        }
        if ($options['foreignAssociation']) {
            $this->_foreignAssociation = $options['foreignAssociation'];
        }
        if ($options['foreignAssociationName']) {
            $this->_foreignAssociationName = $options['foreignAssociationName'];
        }
        if ($options['conditions']) {
            $this->_conditions = $options['conditions'];
        }
        if ($options['finder']) {
            $this->_finder = $options['finder'];
        }
        if ($options['propertyName']) {
            $this->_propertyName = $options['propertyName'];
        }
        if ($options['saveStrategy'] !== null) {
            $this->_saveStrategy = $options['saveStrategy'];
        }
        if ($options['defaultValues'] !== null) {
            $this->_defaultValues = $options['defaultValues'];
        }
        if ($options['single'] !== null) {
            $this->_single = $options['single'];
        }
        if ($options['sort'] !== null) {
            $this->_sort = $options['sort'];
        }

        $this->initialize($options);
    }

/**
 * Initialization hook method.
 *
 * Implement this method to avoid having to overwrite
 * the constructor and call parent.
 *
 * @return void
 */
    public function initialize() {}

/**
 * Association type.
 *
 * @return string Association type
 */
    public function getType() {
        if ($this->_type === null) {
            $this->_type = App::classShortName($this);
        }
        return $this->_type;
    }

/**
 * Association name.
 *
 * @return string Association alias name
 */
    public function getName() {
        return $this->_name;
    }

/**
 * Class name.
 *
 * @return string Association class name
 */
    public function className() {
        return $this->target()->registryAlias();
    }

/**
 * Check if association is of given type.
 *
 * @param string $type Type of association
 * @return bool True if is given type, false otherwise
 */
    public function is($type) {
        return $this->getType() === $type;
    }

/**
 * Get/Set source table name or instance.
 *
 * @param \Nata\ORM\Table|string $source Table name/instance
 * @return \Nata\ORM\Table|$this
 */
    public function source($source = null) {
        if ($source === null) {
            if ($this->_source === null || is_string($this->_source)) {
                $this->_source = TableRegistry::get($this->_source);
            }
            return $this->_source;
        }
        $this->_source = $source;
        return $this;
    }

/**
 * Get/Set target table name or instance.
 *
 * @param \Nata\ORM\Table|string $target Table name/instance
 * @return \Nata\ORM\Table|$this
 */
    public function target($target = null) {
        if ($target === null) {
            if (is_string($this->_target)) {
                $this->_target = TableRegistry::get($this->_target);
            }
            if (empty($this->_target)) {
                throw new LogicException(sprintf(
                    'Missing target for association "%s" in "%s"',
                    $this->_name,
                    $this->source()->registryAlias()
                ));
            }
            return $this->_target;
        }
        $this->_target = $target;
        return $this;
    }

/**
 * The name of the column in the current table, that will be used for matching the foreignKey.
 * Defaults to the primary key.
 *
 * @param string $bindingKey Binding key
 * @return $this|string
 */
    public function bindingKey($bindingKey = null) {
        if ($bindingKey === null) {
            if ($this->_bindingKey === null) {
                $this->_bindingKey = $this->isOwningSide($this->source()) ?
                    $this->source()->primaryKey() : $this->target()->primaryKey();
            }
            return $this->_bindingKey;
        }

        $this->_bindingKey = $bindingKey;
        return $this;
    }

/**
 * Get/Set default values.
 *
 * @param array $defaultValues Default values
 * @return array|$this
 */
    public function defaultValues(array $defaultValues = null) {
        if ($defaultValues === null) {
            return $this->_defaultValues;
        }
        $this->_defaultValues = $defaultValues;
        return $this;
    }

/**
 * Current table source foreign key name.
 *
 * @param string $foreignKey Foreign key
 * @return $this|string
 */
    public function foreignKey($foreignKey = null) {
        if ($foreignKey === null) {
            if ($this->_foreignKey === null) {
                $this->_foreignKey = 'foreign_key';
                if ($this->_foreignAssociation) {
                    $this->_foreignKey = $this->foreignAssociation()->foreignKey();
                } elseif (!$this->polymorphic()) {
                    $this->_foreignKey = Inflector::singularize($this->source()->table()) . '_id';
                }
            }
            return $this->_foreignKey;
        }

        $this->_foreignKey = $foreignKey;
        return $this;
    }

/**
 * Column name that stores the polymorphic model name (registry alias)
 *
 * @param string $foreignModel Foreign model
 * @return $this|string
 */
    public function foreignModel($foreignModel = null) {
        if ($foreignModel === null) {
            if ($this->_foreignModel === null && $this->polymorphic() === true) {
                $this->_foreignModel = $this->_foreignAssociation ? $this->foreignAssociation()->foreignModel() : 'foreign_model';
            }
            return $this->_foreignModel;
        }

        $this->_foreignModel = $foreignModel;
        return $this;
    }

/**
 * Column name that stores the association name for polymorphic disambiguation.
 *
 * @param string|null $name Column name
 * @return $this|string|null
 */
    public function foreignAssociationName($name = null) {
        if ($name === null) {
            return $this->_foreignAssociationName;
        }
        $this->_foreignAssociationName = $name;
        return $this;
    }

/**
 * Reference to a foreign association to obtain the polymorphic association configuration.
 *
 * @param string $foreignAssociation Foreign association
 * @return \Nata\ORM\Association|$this
 */
    public function foreignAssociation($foreignAssociation = null) {
        if ($foreignAssociation === null) {
            if (is_string($this->_foreignAssociation)) {
                $this->_foreignAssociation = $this->target()->associations()->get($this->_foreignAssociation);
            }
            return $this->_foreignAssociation;
        }

        $this->_foreignAssociation = $foreignAssociation;
        return $this;
    }

/**
 * Set/get polymorphic source table/model for foreign key(s).
 * For backward compatibility.
 *
 * @param bool $dynamic Dynamic source model for foreign key.
 * @deprecated Use polymorphic() instead
 * @return $this|bool
 */
    public function dynamic($dynamic = null) {
        if ($dynamic === null) {
            return $this->polymorphic();
        }
        return $this->polymorphic($dynamic);
    }

/**
 * Set/get polymorphic source table/model for foreign key(s).
 *
 * @param bool $polymorphic Polymorphic source model for foreign key.
 * @return $this|bool
 */
    public function polymorphic(bool $polymorphic = null) {
        if ($polymorphic === null) {
            if ($this->_polymorphic === null && $this->_foreignAssociation) {
                $this->_polymorphic = $this->foreignAssociation()->polymorphic();
            }
            return $this->_polymorphic;
        }
        $this->_polymorphic = $polymorphic;
        return $this;
    }

/**
 * Get/Set source table name or instance.
 *
 * @param array|string $conditions Associations conditions
 * @return $this|array|string
 */
    public function conditions($conditions = null) {
        if ($conditions === null) {
            return $this->_conditions;
        }
        $this->_conditions = $conditions;
        return $this;
    }

/**
 * Get/Set save strategy.
 *
 * @param string $saveStrategy Save strategy
 * @return $this|string
 */
    public function saveStrategy($saveStrategy = null) {
        if ($saveStrategy === null) {
            return $this->_saveStrategy;
        }
        $this->_saveStrategy = $saveStrategy;
        return $this;
    }

/**
 * Get/Set default finder.
 * If null, defaults to 'all'.
 *
 * @param string $finder Default association finder
 * @return string|$this
 */
    public function finder($finder = null) {
        if ($finder === null) {
            if ($this->_finder === null) {
                $this->_finder = 'all';
            }
            return $this->_finder;
        }
        $this->_finder = $finder;
        return $this;
    }

/**
 * Get/Set default sort/order for association.
 *
 * @param array $sort Sort array
 * @return array|$this
 */
    public function sort($sort = null) {
        if ($sort === null) {
            return $this->_sort;
        }
        $this->_sort = $sort;
        return $this;
    }

/**
 * Get/Set property name.
 * Defaults to underscored association name alias.
 *
 * @param string $propertyName Property name
 * @return string|$this
 */
    public function propertyName($propertyName = null) {
        if ($propertyName === null) {
            if ($this->_propertyName === null) {
                [$plugin, $alias] = pluginSplit($this->_name);
                $this->_propertyName = Inflector::underscore($alias);
            }
            return $this->_propertyName;
        }
        $this->_propertyName = $propertyName;
        return $this;
    }

/**
 * Get property name singularized.
 *
 * @return string
 */
    public function singularPropertyName(): string {
        if ($this->_singularPropertyName === null) {
            $this->_singularPropertyName = Inflector::singularize($this->propertyName());
        }
        return $this->_singularPropertyName;
    }

/**
 * Get/Set option to retrieve single record for this association.
 *
 * @param bool $single Single association record
 * @return bool\$this
 */
    public function single($single = null) {
        if ($single === null) {
            return $this->_single;
        }
        $this->_single = $single;
        return $this;
    }

/**
 * Proxies the finding operation to the target table's find method
 * and modifies the query accordingly based of this association
 * configuration
 *
 * @param string|array $type the type of query to perform, if an array is passed,
 *   it will be interpreted as the `$options` parameter
 * @param array $options The options to for the find
 * @see \Nata\ORM\Table::find()
 * @return \Nata\ORM\Query
 */
    public function find($type = null, array $options = array()) {
        $type = $type ?: $this->finder();
        $conditions = $this->conditions();
        $sort = $this->sort();

        [$type, $opts] = $this->_extractFinder($type);
        $query = $this->target()
            ->find($type, $options + $opts);

        if (!empty($conditions)) {
            $query->andWhere($conditions);
        }

        if (!empty($sort)) {
            $query->order($sort);
        }

        return $query;
    }

/**
 * Proxies the update operation to the target table's updateAll method
 *
 * @param array $fields A hash of field => new value.
 * @param mixed $conditions Conditions to be used, accepts anything Query::where()
 * can take.
 * @see \Nata\ORM\Table::updateAll()
 * @return bool Success Returns true if one or more rows are affected.
 */
    public function updateAll($fields, $conditions) {
        $target = $this->target();
        $expression = $target->query()
            ->where($this->conditions())
            ->andWhere($conditions)
            ->clause('where');
        return $target->updateAll($fields, $expression);
    }

/**
 * Proxies the delete operation to the target table's deleteAll method
 *
 * @param mixed $conditions Conditions to be used, accepts anything Query::where()
 * can take.
 * @return bool Success Returns true if one or more rows are affected.
 * @see \Nata\ORM\Table::deleteAll()
 */
    public function deleteAll($conditions) {
        $target = $this->target();
        $expression = $target->query()
            ->where($this->conditions())
            ->andWhere($conditions)
            ->clause('where');
        return $target->deleteAll($expression);
    }

/**
 * Apply association table alias to given field name.
 *
 * @param string $fieldName Field name
 * @return string Aliased field name
 */
    protected function _associationAliasField($fieldName) {
        if (strpos($fieldName, '.') === false) {
            return $this->_associationAlias() . '.' . $fieldName;
        }
        return $fieldName;
    }

/**
 * Association table alias.
 *
 * @return $this|string
 */
    protected function _associationAlias() {
        if ($this->_associationAlias === null) {
            [$p, $this->_associationAlias] = pluginSplit($this->_name);
        }

        return $this->_associationAlias;
    }

/**
 * Helper method to infer the requested finder and its options.
 *
 * Returns the inferred options from the finder $type.
 *
 * ### Examples:
 *
 * The following will call the finder 'translations' with the value of the finder as its options:
 * $query->contain(['Comments' => ['finder' => ['translations']]]);
 * $query->contain(['Comments' => ['finder' => ['translations' => []]]]);
 * $query->contain(['Comments' => ['finder' => ['translations' => ['locales' => ['en_US']]]]]);
 *
 * @param string|array $finderData The finder name or an array having the name as key
 * and options as value.
 * @return array
 */
    protected function _extractFinder($finderData) {
        $finderData = (array)$finderData;
        if (is_numeric(key($finderData))) {
            return [current($finderData), []];
        }
        return [key($finderData), current($finderData)];
    }

/**
 * Get property from Entity or Data array.
 *
 * @param \Nata\ORM\Entity|array $entity Array or Entity
 * @param string $property Property name
 * @return int Entity primary key
 * @throws \LogicException
 */
    protected function _extractPropertyValue($entity, $property) {
        if (isset($entity[$property])) {
            return $entity[$property];
        }
    }

/**
 * Contain/Matching query builder.
 *
 * @param string $type Type of builder
 * @param array $containment Builder closure
 * @param \Nata\ORM\Query $query Query instance
 * @return \Nata\ORM\Query
 */
    protected function _queryBuilder($type, $containment, Query $query) {
        extract($containment);

        if ($builder !== null) {
            $_query = $builder($query);
            if (!($_query instanceof Query)) {
                throw new LogicException(sprintf(
                    'The %s query builder for "%s" must return the Query instance, returned an "%s" instead.',
                    App::classShortName($query->repository()->name()),
                    $type,
                    gettype($_query)
                ));
            }
            return $_query;
        } elseif ($entity !== null) {

            if ($this->polymorphic()) {
                $query->andWhere([
                    $this->foreignModel() => $entity->source(),
                    $this->foreignKey() => $entity->id
                ]);
            }

        }
        return $query->{$type}($associations);
    }

/**
 * Set target query into source entity.
 *
 * @param \Nata\ORM\Entity|array $sourceEntity Source entity or array
 * @param \Nata\ORM\Query $query Target query
 * @return \Nata\ORM\Entity|array
 */
    protected function _setTargetQuery($sourceEntity, $query) {
        $propertyName = $this->propertyName();

        if ($query instanceof Query) {
            if ($query->single() === true || $this->_single) {
                $propertyName = Inflector::singularize($propertyName);
                $query = $query->first();
            } else {
                $query = $query->all();
            }
        }

        $sourceEntity[$propertyName] = $query;

        return $sourceEntity;
    }

/**
 * Get prefixed name of association table given table instances.
 *
 * @param \Nata\ORM\Table $table Table name
 * @param \Nata\ORM\Table $associatedTable Associated table
 * @return string Table name
 */
    protected function _prefixedTableName($table, $associatedTable) {
        return Inflector::singularize($table->table()) . '_' . $associatedTable->table();
    }

/**
 * Add through auto fields.
 *
 * @param \Nata\ORM\Table $joinModel Join model
 * @param \Nata\ORM\Query $query Query
 * @return array Fields
 */
    protected function _autoFields($joinModel, $query, $alias = null) {
        if (!$query->autoFields()) {
            return $query;
        }

        if ($alias === null) {
            $alias = $this->_associationAlias();
        }

        [$p, $alias] = pluginSplit($alias);

        $select = [];
        $fields = [];
        foreach ($joinModel->schema()->columns() as $field) {
            $prefixedField = $this->_autoFieldPrefix($alias, $field, true);
            $fields[] = trim($prefixedField, '\'');
            $select[] = $alias . '.' . $field . ' AS ' . $prefixedField;
        }

        $query->addSelect($select);

        return $fields;
    }

/**
 * Always make sure that foreign key required to load association is selected in query.
 *
 * @param \Nata\ORM\Query $query Query instance
 * @param string $property Property name
 */
    protected function _selectAssociationField($query, $property, $alias = '') {
        if (!$query->autoFields()) {
            return $query;
        }

        $from = $query->from();
        $select = $query->select();

        if (is_array($from) && isset($from[0]['alias'])) {
            $alias = $from[0]['alias'];
        }

        if (!empty($alias)) {
            $property = $alias . '.' . $property;
        }

        if (in_array($alias . '.*', $select) || in_array($property, $select)) {
            return;
        }

        $query->addSelect($property);
    }

/**
 * Create model prefix for SELECT's JOIN auto fields.
 *
 * @param string $alias Alias name
 * @param string $fieldName Field name to append
 * @param bool $safe SQL safe alias
 * @return string Alias for joined autofields
 */
    protected function _autoFieldPrefix($alias, $fieldName = '', $safe = false) {
        $prefix = $alias . '__' . $fieldName;
        if ($safe) {
            $prefix = "'" . $prefix . "'";
        }
        return $prefix;
    }

/**
 * Apply 'replace' save strategy to linked entities.
 *
 * It will delete all entities associated with $sourceEntity to create new ones.
 *
 * @param \Nata\ORM\Table $modelTable Table instance
 * @param \Nata\ORM\Entity $sourceEntity Source primary key value
 * @return void
 */
    protected function _saveStrategyReplace(Table $modelTable, Entity $sourceEntity): ?bool {
        $saveStrategy = $this->saveStrategy();
        if ($saveStrategy !== 'replace' || $sourceEntity->id === null) {
            return null;
        }

        $conditions = array_merge([$this->foreignKey() => $sourceEntity->id], $this->conditions() ?? []);
        if ($this->_polymorphic) {
            $conditions[$this->foreignModel()] = $sourceEntity->source();
        }

        $counter = $modelTable->query()->where($conditions)->count();
        if ($counter === 0) {
            return true;
        }
        return (bool)$modelTable->deleteAll($conditions);
    }

/**
 * Apply 'compare' save strategy to linked entities.
 *
 * It will delete all entities not present on the $foreignEntities list.
 *
 * @param \Nata\ORM\Table $modelTable Table instance
 * @param \Nata\ORM\Entity $sourceEntity Source primary key value
 * @param string $fieldName Field name to compare
 * @param Collection $foreignEntities Saved primary keys to ignore on delete
 * @return void
 */
    protected function _saveStrategyCompare(Table $modelTable, Entity $sourceEntity, $fieldName, Collection $foreignEntities): ?bool {
        $saveStrategy = $this->saveStrategy();
        if ($saveStrategy !== 'compare' || $sourceEntity->id === null) {
            return null;
        }

        $conditions = array_merge([$this->foreignKey() => $sourceEntity->id], $this->conditions() ?? []);
        if ($this->_polymorphic) {
            $conditions[$this->foreignModel()] = $sourceEntity->source();
        }

        if (!$foreignEntities->isEmpty()) {
            foreach ($foreignEntities as $foreignEntity) {
                $conditions[$fieldName . ' NOT IN'][] = (is_numeric($foreignEntity->id) ? $foreignEntity->id : -1);
            }
        }

        $counter = $modelTable->query()->where($conditions)->count();
        if ($counter === 0) {
            return true;
        }

        return (bool)$modelTable->deleteAll($conditions);
    }

/**
 * Check if $data is a single entity.
 *
 * Assumes $data is single entity if:
 *  - It's a \Nata\ORM\Entity instance
 *  - Is numeric (as an integer ID)
 *  - Or is an array with at least one key that matched an existing field name
 *
 *  Matches as multiple entities if:
 *  - Array of \Nata\ORM\Entity instances
 *  - Array of ID's ([2345, 4556, 4566, ...])
 *  - Array with array values ([['property_name' => 'the_value'], ['property_name' => 'the_other_value']])
 *
 * @param mixed $data Entity|array of data
 * @return bool True if is a single entity, false otherwise
 */
    protected function _isSingle($data) {
        if ($data instanceof Collection) {
            return false;
        }

        if (!($data instanceof Entity) && !is_numeric($data)) {
            $target = $this->target();

            foreach ((array)$data as $property => $value) {
                if (!is_numeric($property) && $target->hasField($property)) {
                    return true;
                } elseif (is_array($value) || (is_numeric($property) && is_numeric($value)) || $value instanceof Entity) {
                    return false;
                }
            }

            return false;
        }

        return true;
    }

/**
 * Normalize single record/entity to many.
 *
 * @param mixed $data Associated data
 * @return array Many data
 */
    protected function _singleToMany($data) {
        if (!$this->_single) {
            $this->_saveStrategy = false;
        }
        return [$data];
    }

/**
 * Normalize single record/entity to many.
 *
 * @param mixed $data Associated data
 * @return mixed Single data
 */
    protected function _manyToSingle(Collection $data) {
        return $data->first();
    }

/**
 * Normalize data.
 *
 * @param mixed $data Associated data
 * @return array Normalized data and is single bool
 */
    protected function _normalizeMany($data) {
        $isNull = $data === null;
        if (empty($data)) {
            $data = [];
        }

        $isSingle = $this->_isSingle($data);
        if ($isSingle) {
            $data = $this->_singleToMany($data);
        }

        return [new Collection($data), $isSingle, $isNull];
    }

/**
 * Set default values to given entity.
 *
 * @param \Nata\ORM\Entity $entity Entity
 * @return void
 */
    protected function _setDefaultValues(Entity $entity) {
        foreach ((array)$this->defaultValues() as $field => $value) {
            $entity->set($field, $value);
        }
    }

/**
 * Based on given conditions, it will try to find existing pesisted entity
 * and obtain the primary keys.
 * If found, will set them into the given entity and set isNew as false.
 *
 * @param \Nata\ORM\Table $modelTable Table instance
 * @param array $conditions Conditions
 * @param Entity $entity Entity
 * @return bool|null
 */
    protected function _evaluateIsNew(Table $table, array $conditions, Entity $entity): ?bool {
        if (!$conditions) {
            return null;
        }
        $primaryKeys = (array)$table->primaryKey();
        $existingKeys = $table->query()->select($primaryKeys)->where($conditions)->hydrate(false)->first();
        if (!$existingKeys) {
            return null;
        }

        foreach ($existingKeys as $prop => $val) {
            $entity->set($prop, $val)->dirty($prop, false);
        }

        $entity->isNew(false);

        $existingKeys = null;
        unset($existingKeys);

        return true;
    }

/**
 * Set error.
 * If setting an error when another error is already set
 * it will convert the property into an array
 *
 * @param string $error Error
 * @return array|string
 */
    protected function _setError($error) {
        if ($this->_errors === null) {
            $this->_errors = $error;
        } else {
            if (!is_array($this->_errors)) {
                $this->_errors = [$this->_errors];
            }
            $this->_errors[] = $error;
        }
        return $this;
    }

/**
 * Consume existing errors.
 *
 * @return array|string
 */
    public function consumeErrors() {
        $errors = $this->_errors;
        $this->_errors = null;
        return $errors;
    }

/**
 * Match source query builder.
 *
 * @param \Nata\ORM\Query $sourceQuery Parent table query builder
 * @param closure|array $associations Children associations or query closure
 * @return \Nata\ORM\Query
 */
    public function matching($sourceQuery, $associations) {
        return $this->_matching(__FUNCTION__, $sourceQuery, $associations);
    }

/**
 * Match source query builder.
 *
 * @param \Nata\ORM\Query $sourceQuery Parent table query builder
 * @param closure|array $associations Children associations or query closure
 * @return \Nata\ORM\Query
 */
    public function orMatching($sourceQuery, $associations) {
        return $this->_matching(__FUNCTION__, $sourceQuery, $associations);
    }

/**
 * Not matching source query builder.
 *
 * @param \Nata\ORM\Query $sourceQuery Parent table query builder
 * @param closure|array $associations Children associations or query closure
 * @return \Nata\ORM\Query
 */
    public function notMatching($sourceQuery, $associations) {
        return $this->_matching(__FUNCTION__, $sourceQuery, $associations);
    }

/**
 * Not matching source query builder.
 *
 * @param \Nata\ORM\Query $sourceQuery Parent table query builder
 * @param closure|array $associations Children associations or query closure
 * @return \Nata\ORM\Query
 */
    public function orNotMatching($sourceQuery, $associations) {
        return $this->_matching(__FUNCTION__, $sourceQuery, $associations);
    }

/**
 * Prepare query before execution.
 *
 * @param \Nata\ORM\Query $sourceQuery Query
 * @param callable $build Callable build
 * @return bool
 */
    public function buildQuery(Query $sourceQuery, $build) {}

/**
 * Prepare query before execution.
 *
 * @param \Nata\ORM\Query $sourceQuery Query
 * @param callable $build Callable build
 * @return \Nata\ORM\Entity|array
 */
    public function mapResult($row, $build) {return $row;}

/**
 * Batch-load this association for a whole result set with a single query.
 *
 * Association types that support batching override this method to collect
 * the relevant key values from all parent rows, run one IN() query and
 * return a lookup map that mapBatchedResult() reads per row. Returning
 * null signals that batching is not possible (unsupported configuration,
 * per-association limit, no usable keys) and the per-row mapResult() path
 * must be used instead.
 *
 * @param array $parentRows Raw parent result rows.
 * @param array $containment Normalized containment configuration.
 * @return array|null Batch data for mapBatchedResult(), or null to fall back.
 */
    public function eagerLoad(array $parentRows, array $containment) {
        return null;
    }

/**
 * Populate a single row from a batch previously built by eagerLoad().
 *
 * The base implementation returns the row unchanged; association types
 * that implement eagerLoad() override this with a lookup into the batch
 * map, assigning the same property name and value shape that the per-row
 * mapResult() path produces.
 *
 * @param \Nata\ORM\Entity|array $row Parent row being prepared.
 * @param array $batch Batch data returned by eagerLoad().
 * @param array $containment Normalized containment configuration.
 * @return \Nata\ORM\Entity|array Row with the association property set.
 */
    public function mapBatchedResult($row, array $batch, array $containment) {
        return $row;
    }

/**
 * Collect the distinct non-empty values of a property across parent rows.
 *
 * Used by eagerLoad() implementations to build the IN() key list. Empty
 * values (null, 0, '') are skipped: rows without a usable key get an
 * empty association property instead of participating in the batch query.
 *
 * @param array $parentRows Raw parent result rows.
 * @param string $property Property/column name to collect.
 * @return array Distinct non-empty values.
 */
    protected function _collectBatchKeys(array $parentRows, $property) {
        $keyValues = [];
        foreach ($parentRows as $parentRow) {
            $keyValue = $this->_extractPropertyValue($parentRow, $property);
            if (!empty($keyValue)) {
                $keyValues[$keyValue] = true;
            }
        }
        return array_keys($keyValues);
    }

/**
 * Check whether a prepared target query can be executed as a batch.
 *
 * A query with a limit, an offset or single-record mode applies those
 * constraints to the whole batch instead of per parent row, which would
 * change results; such queries must run through the per-row path.
 *
 * The same applies to result formatters and mapReduce routines (added by
 * finders such as find('list')/find('threaded'), behaviors or the contain
 * builder): they would run once over the combined batch instead of once
 * per parent row, and may reshape rows into values the batch grouping
 * cannot index by parent key.
 *
 * @param Query $query Target query after the contain builder was applied,
 *   before any internal batch formatter is attached.
 * @return bool True when the query is safe to batch.
 */
    protected function _isBatchableQuery(Query $query) {
        if ($query->single() === true) {
            return false;
        }
        if ($query->limit() !== null) {
            return false;
        }
        if ($query->formatResults() || $query->mapReduce()) {
            return false;
        }
        return !$query->offset();
    }

/**
 * Split a batch key list into executable chunks.
 *
 * Keeps each IN() list bounded so very large parent result sets cannot
 * exceed driver placeholder limits or the maximum packet size.
 *
 * @param array $keyValues Distinct key values.
 * @return array List of key value chunks.
 */
    protected function _batchKeyChunks(array $keyValues) {
        return array_chunk($keyValues, 1000);
    }

/**
 * Populate a parent row with its grouped children from a batch map.
 *
 * Shared by the to-many association types: assigns the plural property
 * with the parent's children wrapped in a prepared ResultSet, matching
 * the per-row mapResult() output shape.
 *
 * @param \Nata\ORM\Entity|array $row Parent row being prepared.
 * @param array $batch Batch data returned by eagerLoad().
 * @return \Nata\ORM\Entity|array Row with the association property set.
 */
    protected function _mapBatchedManyResult($row, array $batch) {
        $sourceId = $this->_extractPropertyValue($row, 'id');

        $children = [];
        if (!empty($sourceId) && isset($batch['map'][$sourceId])) {
            $children = $batch['map'][$sourceId];
        }

        $row[$this->propertyName()] = new ResultSet(null, $children);

        return $row;
    }

/**
 * Proxies property retrieval to the target table. This is handy for getting this
 * association's associations
 *
 * @param string $property the property name
 * @return \Nata\ORM\Association
 * @throws \RuntimeException if no association with such name exists
 */
    public function __get($property) {
        return $this->target()->{$property};
    }

/**
 * Proxies the isset call to the target table. This is handy to check if the
 * target table has another association with the passed name
 *
 * @param string $property the property name
 * @return bool true if the property exists
 */
    public function __isset($property) {
        return isset($this->target()->{$property});
    }

/**
 * Proxies method calls to the target table.
 *
 * @param string $method name of the method to be invoked
 * @param array $argument List of arguments passed to the function
 * @return mixed
 * @throws \BadMethodCallException
 */
    public function __call($method, array $args) {
        return call_user_func_array([$this->target(), $method], $args);
    }

}
