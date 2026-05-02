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

namespace Nata\ORM\Association;

use Nata\Core\App;
use Nata\ORM\Query;
use Nata\ORM\Table;
use Nata\ORM\Entity;
use Nata\ORM\Association;
use Nata\ORM\TableRegistry;
use Nata\Utility\Inflector;
use Nata\Collection\Collection;
use Nata\Collection\CollectionInterface;
use Nata\Database\Query as DatabaseQuery;
use Nata\ORM\Exception\InvalidEntityException;
use Nata\ORM\ResultSet;
use Closure;
use Exception;
use InvalidArgumentException;
use UnexpectedValueException;

class BelongsToMany extends Association {

/**
 * Join table instance.
 *
 * @var \Nata\ORM\Table
 */
    protected $_join;

/**
 * Join table default values.
 *
 * @var array
 */
    protected $_joinDefaultValues;

/**
 * Join table instance.
 *
 * @var \Nata\ORM\Table
 */
    protected $_joinTableExists;

/**
 * Property name to be used in result set.
 *
 * @var string
 */
    protected $_joinTable;

/**
 * Through association.
 *
 * @var string
 */
    protected $_through;

/**
 * Mutual association/relation.
 *
 * If 'true', the association will created simultaneously
 * between source > target and target > source.
 *
 * @var bool
 */
    protected $_mutual;

/**
 * Enable dynamic target model.
 *
 * @var bool
 */
    protected $_dynamicTarget;

/**
 * Enable polymorphic target model.
 *
 * @var bool
 */
    protected $_polymorphicTarget;

/**
 * Target foreign key.
 *
 * @var string
 */
    protected $_targetForeignKey;

/**
 * Target foreign model.
 *
 * @var string
 */
    protected $_targetForeignModel;

/**
 * Through associations set.
 *
 * @var bool
 */
    protected $_throughAssociations;

/**
 * Junction table instance.
 *
 * @var \Nata\ORM\Table
 */
    protected $_junctionTable;


/**
 * Constructor.
 *
 * @param string $alias Association alias name
 * @param array $options Association options
 * @return void
 */
    public function __construct(string $alias, array $options = []) {
        parent::__construct($alias, $options);

        $options += [
            'through' => null,
            'mutual' => null,
            'joinTable' => null,
            'dynamicTarget' => null,
            'polymorphicTarget' => null,
            'targetForeignKey' => null,
            'targetForeignModel' => null,
            'joinDefaultValues' => null,
        ];

        if ($options['joinTable']) {
            $this->_joinTable = $options['joinTable'];
        }

        if ($options['through']) {
            $this->_through = $options['through'];
        }

        if ($options['mutual'] !== null) {
            $this->mutual($options['mutual']);
        }

        if ($options['dynamicTarget'] !== null) {
            $this->_dynamicTarget = $options['dynamicTarget'];
        }
        if ($options['polymorphicTarget'] !== null) {
            $this->_polymorphicTarget = $options['polymorphicTarget'];
        }

        if ($options['targetForeignKey']) {
            $this->_targetForeignKey = $options['targetForeignKey'];
        }

        if ($options['targetForeignModel']) {
            $this->_targetForeignModel = $options['targetForeignModel'];
        }

        if ($options['joinDefaultValues']) {
            $this->_joinDefaultValues = $options['joinDefaultValues'];
        }

    }

/**
 * Get/Set through association.
 *
 * @param \Nata\ORM\Table|string $through Through association
 * @return \Nata\ORM\Table|$this|string
 */
    public function through($through = null) {
        if ($through === null) {
            return $this->_through;
        }
        $this->_through = $through;
        return $this;
    }

/**
 * Get/Set mutual association.
 *
 * @param \Nata\ORM\Table|string $through Through association
 * @return \Nata\ORM\Table|$this|string
 */
    public function mutual($mutual = null) {
        if ($mutual === null) {
            return $this->_mutual;
        }

        $this->_mutual = $mutual;

        return $this;
    }

/**
 * Get/Set join table default values.
 *
 * @param array $joinDefaultValues Join table default values
 * @return array|$this
 */
    public function joinDefaultValues(array $joinDefaultValues = null) {
        if ($joinDefaultValues === null) {
            return $this->_joinDefaultValues;
        }

        $this->_joinDefaultValues = $joinDefaultValues;
        return $this;
    }

/**
 * Returns whether or not the passed table is the owning side for this
 * association. This means that rows in the 'target' table would miss important
 * or required information if the row in 'source' did not exist.
 *
 * @param \Nata\ORM\Table $side The potential Table with ownership
 * @return bool
 */
    public function isOwningSide(Table $side): bool {
        return true;
    }

/**
 * Target foreign key name.
 *
 * @param string $targetForeignKey Target foreign key
 * @return $this|string
 */
    public function targetForeignKey($targetForeignKey = null) {
        if ($targetForeignKey === null) {
            if ($this->_targetForeignKey === null) {
                $this->_targetForeignKey = !$this->_dynamicTarget ? Inflector::singularize($this->target()->table()) . '_id' : 'target_foreign_key';
            }
            return $this->_targetForeignKey;
        }

        $this->_targetForeignKey = $targetForeignKey;
        return $this;
    }

/**
 * Target foreign model.
 *
 * @param string $targetForeignModel Target foreign model field name
 * @return $this|string
 */
    public function targetForeignModel($targetForeignModel = null) {
        if ($targetForeignModel === null) {
            if ($this->_targetForeignModel === null && $this->_dynamicTarget === true) {
                $this->_targetForeignModel = 'target_foreign_model';
            }
            return $this->_targetForeignModel;
        }

        $this->_targetForeignModel = $targetForeignModel;

        return $this;
    }

/**
 * Set/get dynamic target table/model for foreign key(s).
 *
 * If 'targetForeignKey' is 'target_foreign_key', it will assume that field 'model'
 * exists in target/join table and save the model's registry alias
 * along with it.
 *
 * @param bool $dynamicTarget Dynamic source model for foreign key.
 * @return $this|bool
 */
    public function dynamicTarget($dynamicTarget = null) {
        if ($dynamicTarget === null) {
            return $this->_dynamicTarget;
        }
        $this->_dynamicTarget = $dynamicTarget;
        return $this;
    }

/**
 * Set/get dynamic target table/model for foreign key(s).
 *
 * If 'targetForeignKey' is 'target_foreign_key', it will assume that field 'model'
 * exists in target/join table and save the model's registry alias
 * along with it.
 *
 * @param bool $dynamicTarget Dynamic source model for foreign key.
 * @return $this|bool
 */
    public function polymorphicTarget($polymorphicTarget = null) {
        if ($polymorphicTarget === null) {
            return $this->_polymorphicTarget ?? $this->dynamicTarget();
        }
        $this->_polymorphicTarget = $polymorphicTarget;
        return $this;
    }

/**
 * Get/Set join/pivot table name.
 * If table's name differ's from Nata's convention, you can change it.
 *
 * @param string $joinTable Table name
 * @return $this|string
 */
    public function joinTable($joinTable = null) {
        if ($joinTable === null) {
            if ($this->_joinTable === null) {
                [$p, $target] = pluginSplit($this->_name);
                $this->_joinTable = Inflector::singularize($this->source()->table()) . '_' . Inflector::underscore($target);
            }
            return $this->_joinTable;
        }

        $this->_joinTable = $joinTable;

        return $this;
    }

/**
 * Get the junction table instance.
 *
 * @return \Nata\ORM\Table Junction table instance
 */
    public function junction() {
        if ($this->_junctionTable === null) {
            $table = $this->_through;
            if ($table === null) {
                $table = $this->joinTable();
            }

            if (!($table instanceof Table)) {
                $table = Inflector::camelize($table);
                $table = TableRegistry::get($table);
            }

            $this->_junctionTable = $table;
        }

        return $this->_junctionTable;
    }

/**
 * Save associated entities in to database.
 *
 * @param \Nata\ORM\Entity $sourceEntity Source entity
 * @param array|Collection $targetEntities Related data
 * @param array $options Save options
 * @return Entity|array Saved entity(ies)
 */
    public function afterSave(Entity $sourceEntity, mixed $targetEntities, $options) {
        $junctionTable = $this->junction();
        if ($junctionTable === null) {
            throw new Exception(sprintf(
                'Unable to initialize join table "%s".',
                $this->joinTable()
            ));
        }

        // This is a workaround to avoid errors when the target entities is a string
        // and not an array.
        // TODO: Remove this when the issue is fixed.
        if (is_string($targetEntities) && !empty($targetEntities)) {
            $this->_setError(__('String value for association "%s" is not allowed on "%s".', [
                App::classShortName($sourceEntity),
                $this->_name
            ]));
            return null;
        }

        [$targetEntities, $isSingle, $isNull] = $this->_normalizeMany($targetEntities);

        // Replace
        if ($this->_saveStrategyReplace($junctionTable, $sourceEntity) === false) {
            $this->_setError(__('Error replacing records for "%s" on association "%s".', [
                App::classShortName($sourceEntity),
                $this->_name
            ]));
        }

        if ($this->_single && $targetEntities->isEmpty()) {
            $original = $sourceEntity->getOriginal($this->propertyName());
            if ($original instanceof Entity) {
                $this->unlink($sourceEntity, $original);
            }
        }

        // Save junction entities
        $junctionEntities = $this->dynamicTarget()
            ? $this->_dynamicTargetSaveAll($sourceEntity, $targetEntities, $options)
            : $this->_saveAll($sourceEntity, $targetEntities, $options);

        // Mutual mode
        if ($this->_mutual) {
            // @todo Check mutual associations that should be removed
            // $this->_clearRemovedMutual($sourceEntity, $junctionEntities);
        }

        // Compare
        if ($this->_saveStrategyCompare($junctionTable, $sourceEntity, $junctionTable->primaryKey(), new Collection($junctionEntities)) === false) {
            $this->_setError(__('Error comparing records for "%s" on association "%s".', [
                App::classShortName($sourceEntity),
                $this->_name
            ]));
        }

        if ($isSingle) {
            $targetEntities = $this->_manyToSingle($targetEntities);
        }

        if ($isNull) {
            return null;
        }

        return $targetEntities;
    }

/**
 * Save all junction entities and target entities, if dirty.
 *
 * @param \Nata\ORM\Entity $sourceEntity Source entity
 * @param array|Collection $targetEntities Related data
 * @param array $options Save options
 * @return array Junction entities
 */
    protected function _saveAll(Entity $sourceEntity, &$targetEntities, $options) {
        $target = $this->target();

        $junctionEntities = [];
        $newTargetEntities = [];
        foreach ($targetEntities as $index => $targetEntity) {
            $targetEntity = $target->newEntity($targetEntity);
            $success = $target->save($targetEntity, $options);
            if ($success === false) {
                $this->_setError(__('Error saving "%s" at index "%s".', [
                    App::classShortName($targetEntity),
                    $index
                ]));
            }

            $junctionEntity = $this->link($sourceEntity, $targetEntity, $options);

            $newTargetEntities[] = $targetEntity;
            $junctionEntities[] = $junctionEntity;
        }

        $targetEntities = new Collection($newTargetEntities);
        $newTargetEntities = null;

        return $junctionEntities;
    }

/**
 * Save all junction entities and dynamic target entities, if dirty.
 *
 * @param \Nata\ORM\Entity $sourceEntity Source entity
 * @param array|Collection $targetEntities Related data
 * @param array $options Save options
 * @return array Junction entities
 */
    protected function _dynamicTargetSaveAll(Entity $sourceEntity, &$targetEntities, $options) {
        $junctionEntities = [];
        $newTargetEntities = [];
        foreach ($targetEntities as $index => $targetEntity) {
            if (!($targetEntity instanceof Entity)) {
                $this->_setError(__('Dynamic record index "%s" not saved because it\'s missing.', [
                    $index
                ]));
                continue;
            }

            // Set dynamic target
            $this->target($targetEntity->source());

            if ($this->target()->save($targetEntity, $options) === false) {
                $this->_setError(__('Error saving dynamic entity "%s" at index "%s".', [
                    App::classShortName($targetEntity),
                    $index
                ]));
            }

            $junctionEntity = $this->link($sourceEntity, $targetEntity, $options);

            $newTargetEntities[] = $targetEntity;
            $junctionEntities[] = $junctionEntity;
        }

        $targetEntities = new Collection($newTargetEntities);
        $newTargetEntities = null;

        return $junctionEntities;
    }

/**
 * Clear mutual.
 *
 * @param Entity $sourceEntity Source entity
 * @param array $foreignEntities Foreign keys
 * @return void
 */
    protected function _clearRemovedMutual(Entity $sourceEntity, array $foreignEntities) {
        $saveStrategy = $this->saveStrategy();
        if ($saveStrategy !== 'compare') {
            return;
        }

        /*
        if (!empty($foreignEntities)) {
            $delete[$this->junction()->primaryKey() . ' NOT IN'] = array_map(function ($entity) {
                return ($entity->_mutualEntity && is_numeric($entity->_mutualEntity->id) ? $entity->_mutualEntity->id : -1);
            }, $foreignEntities);
        }

        print_a($delete);die;
        */

        // $this->junction()->deleteAll($delete);
    }

/**
 * Link given associated entities.
 *
 * @param \Nata\ORM\Entity|int $source Source entity
 * @param \Nata\ORM\Entity|int $target Target entity
 * @param array $options Options
 * @return \Nata\ORM\Entity Junction entity
 */
    public function link($sourceEntity, $targetEntity, array $options = []) {
        // Dynamic target
        if ($this->dynamicTarget()) {
            if (!($targetEntity instanceof Entity)) {
                throw new InvalidEntityException(sprintf(
                    'Invalid entity. "%s" association is dynamic, entity instance is expected.',
                    $this->_name
                ));
            }
            $this->target($targetEntity->source());
        }

        $source = $this->source();
        $target = $this->target();
        $sourceEntity = $source->newEntity($sourceEntity);
        $targetEntity = $target->newEntity($targetEntity);

        $junctionEntity = $this->_prepareJunctionEntityLink($sourceEntity, $targetEntity);
        $success = $this->junction()->save($junctionEntity, $options);

        if ($success === false) {
            [$sourcePrimaryKey] = (array)$source->primaryKey();
            [$targetPrimaryKey] = (array)$target->primaryKey();

            $this->_setError(__('Unable to link "%s::%s" with "%s::%s".', [
                App::classShortName($sourceEntity),
                $sourceEntity->get($sourcePrimaryKey),
                App::classShortName($targetEntity),
                $targetEntity->get($targetPrimaryKey)
            ]));
        }

        return $junctionEntity;
    }

/**
 * Unlink associated entities.
 *
 * @param \Nata\ORM\Entity|int $source Source entity
 * @param \Nata\ORM\Entity|int $target Target entity
 * @return boolean True if successful, false otherwise
 */
    public function unlink($sourceEntity, $targetEntity) {
        // Dynamic target
        if ($this->dynamicTarget()) {
            if (!($targetEntity instanceof Entity)) {
                throw new InvalidEntityException(sprintf(
                    'Invalid entity. "%s" association is dynamic, entity instance is expected.',
                    $this->_name
                ));
            }
            $this->target($targetEntity->source());
        }

        $source = $this->source();
        $target = $this->target();
        [$sourcePrimaryKey] = (array)$source->primaryKey();
        [$targetPrimaryKey] = (array)$target->primaryKey();

        $sourceEntity = $source->newEntity($sourceEntity);
        $targetEntity = $target->newEntity($targetEntity);
        if (!($sourceEntity->get($sourcePrimaryKey) > 0) || !($targetEntity->get($targetPrimaryKey) > 0)) {
            return false;
        }

        $data = [
            $this->foreignKey() => $sourceEntity->id,
            $this->targetForeignKey() => $targetEntity->id
        ];

        return $this->junction()->deleteAll($data);
    }

/**
 * Prepare and return the junction entity.
 *
 * @param Entity $sourceEntity Source entity
 * @param Entity $targetEntity Target entity
 * @return Entity Junction entity
 */
    protected function _prepareJunctionEntityLink(Entity $sourceEntity, Entity $targetEntity): Entity {
        $junctionTable = $this->junction();
        $foreignKey = $this->foreignKey();
        $foreignModel = $this->foreignModel();
        $targetForeignKey = $this->targetForeignKey();
        $targetForeignModel = $this->targetForeignModel();
        [$sourcePrimaryKey] = (array)$this->source()->primaryKey();
        [$targetPrimaryKey] = (array)$this->target()->primaryKey();

        // Through association
        if ($this->through()) {
            $junctionEntity = $this->_prepareThroughEntity($targetEntity);
        } else {
            $junctionEntity = $junctionTable->newEntity();
        }

        // Set join table default values
        if ($junctionEntity->isNew() && $this->_joinDefaultValues) {
            foreach ($this->_joinDefaultValues as $field => $value) {
                $junctionEntity->set($field, $value);
            }
        }

        // (Re)set source primary key if it's missing
        $sourcePrimaryKeyValue = $junctionEntity->get($foreignKey);
        if (empty($sourcePrimaryKeyValue)) {
            $junctionEntity->set($foreignKey, $sourceEntity->get($sourcePrimaryKey));
        }

        // (Re)set target primary key if it's missing
        $targetPrimaryKeyValue = $junctionEntity->get($targetForeignKey);
        if (empty($targetPrimaryKeyValue)) {
            $junctionEntity->set($targetForeignKey, $targetEntity->get($targetPrimaryKey));
        }

        // Dynamic source
        if ($this->polymorphic()) {
            $junctionEntity->set($foreignModel, $sourceEntity->source());
        }

        // Dynamic target
        if ($this->dynamicTarget()) {
            $junctionEntity->set($targetForeignModel, $targetEntity->source());
        }

        // Mutual association
        $mutual = $this->_mutual;
        if ($mutual === true || ($mutual instanceof Closure && $mutual($targetEntity, $this) === true)) {
            $junctionEntity->_mutualEntity = $this->_linkMutual($junctionEntity, $sourceEntity, $targetEntity);
        }

        return $junctionEntity;
    }

/**
 * Prepare a through junction entity.
 *
 * @param \Nata\ORM\Entity $targetEntity Target entity
 * @return Entity Junction entity
 */
    private function _prepareThroughEntity(Entity $targetEntity): Entity {
        $joinData = $targetEntity->_joinData;
        if ($joinData instanceof Entity) {
            return $joinData;
        }

        if ($joinData === null) {
            $joinData = [];
        }

        $junctionEntity = $this->junction()->newEntity($joinData);

        return $targetEntity->_joinData = $junctionEntity;
    }

/**
 * Handle mutual entity.
 *
 * @param \Nata\ORM\Entity $junctionEntity Junction entity
 * @return \Nata\ORM\Entity Mutual entity
 */
    protected function _linkMutual($junctionEntity) {
        $junctionTable = $this->junction();
        $foreignKey = $this->foreignKey();
        $targetForeignKey = $this->targetForeignKey();

        // Reverse foreign association fields
        $conditions = [
            $foreignKey => $junctionEntity->get($targetForeignKey),
            $targetForeignKey => $junctionEntity->get($foreignKey)
        ];

        if ($this->_dynamicTarget || $this->_dynamic) {
            throw new InvalidArgumentException('Mutual flag with dynamic models is not currently supported.');
        }

        $mutualJunctionEntity = $junctionTable->query()->where($conditions)->first();
        if ($mutualJunctionEntity instanceof Entity) {
            return $mutualJunctionEntity;
        }

        // Add mutual association
        $mutualJunctionEntity = clone $junctionEntity;
        $mutualJunctionEntity->isNew(true);
        $mutualJunctionEntity->unsetProperty(['id', 'created', 'updated']);

        return $junctionTable->save($mutualJunctionEntity);
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
 * Matching or not source query builder.
 *
 * @param string $comparison Matching or Not Matching
 * @param \Nata\ORM\Query $sourceQuery Parent table query builder
 * @param closure|array $joins Children joins or query closure
 * @return \Nata\ORM\Query
 */
    private function _matching($comparison, $sourceQuery, $associations) {
        if (!$junctionTable = $this->junction()) {
            if ($comparison === 'matching') {
                $sourceQuery->andWhere([
                    $this->source()->aliasField('id') => '-1'
                ]);
            }
            return $sourceQuery;
        }

        $target = $this->target();
        if ($associations['target']) {
            $target = $this->target($associations['target']);
        }
        $foreignKey = $this->foreignKey();
        $targetForeignKey = $this->targetForeignKey();

        $junctionTableQuery = $junctionTable
            ->query()
            ->select($junctionTable->aliasField($foreignKey));

        // Entity
        if ($associations['entity']) {
            $conditions = [$junctionTable->aliasField($this->targetForeignKey()) => $associations['entity']->id];
            if ($this->dynamicTarget()) {
                $conditions[$junctionTable->aliasField($this->targetForeignModel())] = $associations['entity']->source();
            }
            $junctionTableQuery->andWhere($conditions);
        }

        // Add subquery only if query builder is being used or nested associations are referenced
        if ($associations['builder'] || $associations['associations']) {
            $targetQuery = $target->query()
                ->select($this->_associationAliasField('id'))
                ->from($target->table(), $this->_associationAlias());

            $targetQuery = $this->_queryBuilder('matching', $associations, $targetQuery)
                ->eagerLoader()
                ->buildMatchQuery($targetQuery);

            $junctionTableQuery->andWhere([
                $junctionTable->aliasField($targetForeignKey) . ' IN' => $targetQuery
            ]);
        }

        $clause = ' IN';
        if ($comparison === 'notMatching') {
            $clause = ' NOT' . $clause;
        }

        return [
            $sourceQuery->aliasField('id', $this->source()->table()) . $clause => $junctionTableQuery
        ];
    }

/**
 * Build query to load associated table results.
 *
 * @param \Nata\ORM\Query $sourceQuery Entity
 * @param closure|array $builder Callable closure
 * @return \Nata\ORM\Query
 */
    public function buildQuery(Query $sourceQuery, $containment) {
        $this->_selectAssociationField($sourceQuery, 'id');
    }

/**
 * Build query to load associated table results.
 *
 * @param \Nata\ORM\Entity|array $entity Entity
 * @param closure|array $builder Callable closure
 * @return \Nata\ORM\Entity|array
 */
    public function mapResult($entity, $containment) {
        if ($this->polymorphicTarget() === true) {
            return $this->_setTargetQuery($entity, $this->_loadFromDynamicTarget($entity));
        }

        $junctionTable = $this->junction();
        if (!$junctionTable) {
            return $entity;
        }

        $target = $this->target();
        $foreignKey = $this->foreignKey();
        $targetForeignKey = $this->targetForeignKey();

        $query = $this->find()
            ->select($this->_associationAliasField('*'))
            ->from($target->table(), $this->_associationAlias());

        $sourceId = $this->_extractPropertyValue($entity, 'id');
        if (empty($sourceId)) {
            return new ResultSet($query, []);
        }

        $query = $this->_queryBuilder('contain', $containment, $query)
            ->leftJoin([
                'table' => $junctionTable->table(),
                'alias' => $junctionTable->alias(),
                'conditions' => $junctionTable->aliasField($foreignKey) . ' = ' . $sourceId
            ])->andWhere($this->_associationAliasField('id') . ' = ' . $junctionTable->aliasField($targetForeignKey));

        if ($this->polymorphicTarget()) {
            $query->andWhere([$junctionTable->aliasField($this->targetForeignModel()) => $target->registryAlias()]);
        }

        if ($this->_through) {
            $prefixedFields = $this->_autoFields($junctionTable, $query, $junctionTable->registryAlias());

            [$p, $registryAlias] = pluginSplit($junctionTable->registryAlias());

            $query->formatResults(function (CollectionInterface $resultSet) use ($junctionTable, $registryAlias, $prefixedFields) {
                foreach ($resultSet as $index => $entity) {
                    if ($entity instanceof Entity) {
                        $joinEntity = $junctionTable->newEntity();

                        foreach ($entity->extract($prefixedFields, false) as $field => $value) {
                            [$m, $_field] = explode('__', $field);
                            $joinEntity[$_field] = $value;
                            unset($entity[$field]);
                        }
                        $entity->set('_joinData', $joinEntity);

                        $joinEntity->isNew(false);
                        $joinEntity->clean();
                    }
                }
                return $resultSet;
            });
        }

        $entity = $this->_setTargetQuery($entity, $query);
        return $entity;
    }

/**
 * Build query associated results of given entity.
 *
 * @param \Nata\ORM\Entity|array $entity Entity
 * @param \Closure $builder Query builder
 * @return \Nata\ORM\ResultSet
 */
    public function of($entity, Closure $queryBuilder = null) {
        if ($this->polymorphicTarget() === true) {
            return $this->_loadFromDynamicTarget($entity, $queryBuilder);
        }

        $junctionTable = $this->junction();
        if (!$junctionTable) {
            return;
        }

        $target = $this->target();
        $foreignKey = $this->foreignKey();
        $targetForeignKey = $this->targetForeignKey();

        $query = $this->find()
            ->select($this->_associationAliasField('*'))
            ->from($target->table(), $this->_associationAlias());

        $sourceId = $this->_extractPropertyValue($entity, 'id');
        if (empty($sourceId)) {
            return new ResultSet($query, []);
        }

        $query->leftJoin([
            'table' => $junctionTable->table(),
            'alias' => $junctionTable->alias(),
            'conditions' => $junctionTable->aliasField($foreignKey) . ' = ' . $sourceId
        ])->andWhere($this->_associationAliasField('id') . ' = ' . $junctionTable->aliasField($targetForeignKey));

        if ($this->_through) {
            $prefixedFields = $this->_autoFields($junctionTable, $query, $junctionTable->registryAlias());

            [$p, $registryAlias] = pluginSplit($junctionTable->registryAlias());
            $query->formatResults(function (CollectionInterface $resultSet) use ($junctionTable, $registryAlias, $prefixedFields) {
                foreach ($resultSet as $index => $entity) {
                    if ($entity instanceof Entity) {
                        $joinEntity = $junctionTable->newEntity();

                        foreach ($entity->extract($prefixedFields, false) as $field => $value) {
                            [$m, $_field] = explode('__', $field);
                            $joinEntity[$_field] = $value;
                            unset($entity[$field]);
                        }
                        $entity->set('_joinData', $joinEntity);

                        $joinEntity->isNew(false);
                        $joinEntity->clean();
                    }
                }
                return $resultSet;
            });
        }

        if ($queryBuilder) {
            $query = $queryBuilder($query);
            if (!($query instanceof DatabaseQuery)) {
                throw new UnexpectedValueException(sprintf('Query instance expected, got %s instead.', gettype($query)));
            }
        }

        return $query->all();
    }

/**
 * Build query associated results of given entity.
 *
 * @param \Nata\ORM\Entity|array $entity Entity
 * @param \Closure $builder Query builder
 * @return \Nata\ORM\ResultSet
 */
    protected function _loadFromDynamicTarget($entity, Closure $queryBuilder = null) {
        $junctionTable = $this->junction();
        if (!$junctionTable) {
            return;
        }

        if ($this->_through) {
            $query = $junctionTable->find()->hydrate(false);
        } else {
            $query = $junctionTable->find()
                ->hydrate(false)
                ->select([
                    $this->targetForeignModel(),
                    $this->targetForeignKey()
                ]);
        }

        $sourceId = $this->_extractPropertyValue($entity, 'id');
        if (empty($sourceId)) {
            return new ResultSet($query, []);
        }

        $foreignKey = $this->foreignKey();
        $query->where([
            $junctionTable->aliasField($foreignKey) => $sourceId
        ]);

        $targetModels = $query->all()->groupBy($this->targetForeignModel());

        $results = [];
        foreach ($targetModels as $targetAlias => $primaryKeys) {
            $target = $this->target($targetAlias);

            $query = $target->query();
            if ($queryBuilder) {
                $query = $queryBuilder($query, $target);
                if (!($query instanceof DatabaseQuery)) {
                    throw new UnexpectedValueException(sprintf('Query instance expected, got %s instead.', gettype($query)));
                }
            }

            if ($this->_through) {
                $entities = $this->_loadEntitiesThrough($target, $query, $primaryKeys);
            } else {
                $entities = $this->_loadEntities($target, $query, $primaryKeys);
            }

            $results = array_merge($results, $entities);
        }

        unset($entities);

        return new Collection($results);
    }

/**
 * Build query associated results of given entity.
 *
 * @param \Nata\ORM\Entity|array $entity Entity
 * @param \Closure $builder Query builder
 * @return array
 */
    protected function _loadEntities($target, $query, $primaryKeys) {
        $primaryKeys = array_map(function ($junctionEntity) {
            return $junctionEntity[$this->targetForeignKey()];
        }, $primaryKeys);

        return $query->andWhere([
            $target->aliasField('id') => $primaryKeys
        ])->all()->toArray();
    }

/**
 * Build query associated results of given entity.
 *
 * @param \Nata\ORM\Entity|array $entity Entity
 * @param \Closure $builder Query builder
 * @return array
 */
    protected function _loadEntitiesThrough($target, $query, $primaryKeys) {
        $junctionTable = $this->junction();
        $targetForeignKey = $this->targetForeignKey();

        $junctionByTargetId = [];
        $targetIds = [];
        foreach ($primaryKeys as $junctionRow) {
            $targetId = $junctionRow[$targetForeignKey];
            $targetIds[] = $targetId;
            $junctionByTargetId[$targetId] = $junctionRow;
        }

        $entities = $query->andWhere([
            $target->aliasField('id') => $targetIds
        ])->all()->toArray();

        foreach ($entities as $entity) {
            if ($entity instanceof Entity) {
                $targetId = $entity->id;
                if (isset($junctionByTargetId[$targetId])) {
                    $joinEntity = $junctionTable->newEntity($junctionByTargetId[$targetId]);
                    $joinEntity->isNew(false);
                    $joinEntity->clean();
                    $entity->set('_joinData', $joinEntity);
                }
            }
        }

        return $entities;
    }

}