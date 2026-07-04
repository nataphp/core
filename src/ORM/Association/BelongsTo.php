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
 * @since         NataPHP 1.0-0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\ORM\Association;

use Nata\Core\App;
use Nata\Database\Query as DatabaseQuery;
use Nata\ORM\Query;
use Nata\ORM\Entity;
use Nata\ORM\Association;
use Nata\Utility\Inflector;
use Nata\ORM\Exception\InvalidEntityException;
use Nata\ORM\Table;
use Closure;
use InvalidArgumentException;
use UnexpectedValueException;

class BelongsTo extends Association {

/**
 * Single record.
 *
 * @var bool
 */
    protected $_single = true;


/**
 * Returns whether or not the passed table is the owning side for this
 * association. This means that rows in the 'target' table would miss important
 * or required information if the row in 'source' did not exist.
 *
 * @param \Nata\ORM\Table $side The potential Table with ownership
 * @return bool
 */
    public function isOwningSide(Table $side): bool {
        return $side === $this->target();
    }

/**
 * Get/Set property name.
 * Defaults to underscored association name alias.
 *
 * @param string $propertyName Property name
 * @return string\$this
 */
    public function propertyName($propertyName = null) {
        if ($propertyName === null) {
            if ($this->_propertyName === null) {
                [$plugin, $alias] = pluginSplit($this->_name);
                $this->_propertyName = Inflector::underscore($alias);
                $this->_propertyName = Inflector::singularize($this->_propertyName);
            }
            return $this->_propertyName;
        }

        $this->_propertyName = $propertyName;

        return $this;
    }

/**
 * Foreign key name (we get foreign key name from target table).
 *
 * @param string $foreignKey Foreign key
 * @return $this|string
 */
    public function foreignKey($foreignKey = null) {
        if ($foreignKey === null && $this->_foreignKey === null) {
            $this->_foreignKey = 'foreign_key';
            if (!$this->polymorphic()) {
                $this->_foreignKey = Inflector::singularize($this->target()->table());
                [$primaryKey] = (array)$this->target()->primaryKey();
                $this->_foreignKey .= '_' . $primaryKey;
            }
        }
        return parent::foreignKey($foreignKey);
    }

/**
 * Save BelongsTo entity in to association table.
 *
 * @param \Nata\ORM\Entity $sourceEntity Source entity
 * @param \Nata\ORM\Entity|array $targetEntity Related data
 * @param array $options Save options
 * @return array Given entity with saved data
 */
    public function beforeSave(Entity $sourceEntity, $targetEntity, $options) {
        $foreignKey = $this->foreignKey();
        $foreignModel = $this->foreignModel();

        if ($this->polymorphic()) {
            $this->_setPolymorphicTarget($sourceEntity, $targetEntity);
        }

        $target = $this->target();
        [$primaryKey] = (array)$target->primaryKey();
        if (isset($targetEntity[$primaryKey]) && empty($targetEntity[$primaryKey])) {
            unset($targetEntity[$primaryKey]);
        }

        if (empty($targetEntity)) {
            $sourceEntity->set($foreignKey, null);
            if ($this->polymorphic()) {
                $sourceEntity->set($foreignModel, null);
            }
            return null;
        }

        $targetEntity = $target->newEntity($targetEntity);
        if ($targetEntity->isNew()) {
            $this->_setDefaultValues($targetEntity);
        }

        $success = $target->save($targetEntity, $options);
        if ($success === false && $options['atomic']) {
            $this->_setError(__('Error saving association %s "%s".', [
                $this->_name,
                App::classShortName($targetEntity)
            ]));
        }

        if ($success) {
            $primaryKeyValue = $targetEntity->get($primaryKey);
            if ($targetEntity && !empty($primaryKeyValue)) {
                if ($this->polymorphic()) {
                    $sourceEntity->set($foreignModel, $targetEntity->source());
                }
                $sourceEntity->set($foreignKey, $primaryKeyValue);
            }
        }

        return $targetEntity;
    }

/**
 * Remove set foreign key after saving.
 *
 * @param \Nata\ORM\Entity $sourceEntity Source entity
 * @param \Nata\ORM\Entity|array $targetEntity Related data
 * @param array $options Save options
 * @return \Nata\ORM\Entity Target entity
 */
    public function afterSave(Entity $sourceEntity, $targetEntity, $options) {
        $sourceEntity->unsetProperty($this->foreignKey());
        return $targetEntity;
    }

/**
 * Set polymorphic target model from target entity.
 *
 * @param \Nata\ORM\Entity $sourceEntity Source entity
 * @param \Nata\ORM\Entity|array $targetEntity Related data
 * @return void
 */
    protected function _setPolymorphicTarget($sourceEntity, $targetEntity) {
        if (!($targetEntity instanceof Entity)) {
            throw new InvalidEntityException(sprintf(
                'Invalid entity. "%s" association in "%s" is polymorphic, entity instance is expected.',
                $this->_name,
                $sourceEntity->source()
            ));
        }
        $this->target($targetEntity->source());
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
 * @param closure|array $associations Children associations or query closure
 * @return \Nata\ORM\Query
 */
    private function _matching($comparison, $sourceQuery, $associations) {
        if ($this->polymorphic()) {
            $target = $associations['target'];
            if ($entity = $associations['entity']) {
                $target = $entity->source();
            }
            $this->target($target);
        }

        if ($this->polymorphic()) {
            // $conditions = $this->_matchingDynamic($comparison, $sourceQuery, $associations);
            // return $conditions;
        }

        $target = $this->target();
        $foreignModel = $this->foreignModel();
        $foreignKey = $this->foreignKey();

        /*
        if (!$associations['builder']) {
            $clause = $comparison === 'notMatching' ? ' IS' : ' IS NOT';
            // @todo
            if ($this->polymorphic()) {
                $sourceQuery->andWhere([$sourceQuery->aliasField($foreignModel, $this->source()->table()) => $target->alias()]);
            }
            return [$sourceQuery->aliasField($foreignKey, $this->source()->table()) . $clause => null];
        }
        */

        $associationAlias = $this->_associationAlias();
        $targetQuery = $this->query();

        $targetQuery
            ->select($this->_associationAliasField('id'))
            ->from($target->table(), $associationAlias);

        $targetQuery = $this->_queryBuilder('matching', $associations, $targetQuery);
        $targetQuery = $targetQuery->eagerLoader()->buildMatchQuery($targetQuery);

        $clause = ' IN';
        if ($comparison === 'notMatching') {
            $clause = ' NOT' . $clause;
        }

        $conditions = ['AND' => []];
        $conditions['AND'][] = [$sourceQuery->aliasField($foreignKey, $this->source()->table()) . $clause => $targetQuery];
        if ($this->polymorphic()) {
            if ($entity && $entity->id) {
                $conditions = ['AND' => []];
                $conditions['AND'][] = [$sourceQuery->aliasField($foreignKey, $this->source()->table()) => $entity->id];
            }
            $conditions['AND'][] = [$sourceQuery->aliasField($foreignModel, $this->source()->table()) => $target->alias()];
        }

        return $conditions;
    }

/**
 * Build query to load associated table results.
 *
 * @param \Nata\ORM\Query $sourceQuery Entity
 * @param closure|array $containment Callable closure
 * @return \Nata\ORM\Query
 */
    public function buildQuery(Query $sourceQuery, $containment) {
        $foreignKey = $this->foreignKey();
        $this->_selectAssociationField($sourceQuery, $foreignKey);
    }

/**
 * Build query to load associated table results.
 *
 * @param \Nata\ORM\Entity|array $row Row
 * @param array $containment Callable closure
 * @return \Nata\ORM\Entity|array
 */
    public function mapResult($row, $containment) {
        extract($containment);

        if ($this->polymorphic()) {
            $targetModel = $this->_extractPropertyValue($row, $this->foreignModel());
            if (empty($targetModel)) {
                return $row;
            }
            $this->target($targetModel);
        }

        $foreignKey = $this->foreignKey();
        $target = $this->target();

        $sourceId = $this->_extractPropertyValue($row, $foreignKey);

        $targetQuery = null;
        if (!empty($sourceId)) {
            $targetQuery = $target->query()->from($target->table(), $this->_associationAlias());
            $targetQuery = $this->_queryBuilder('contain', $containment, $targetQuery)
                ->where([
                    $this->_associationAliasField($target->primaryKey()) => $sourceId
                ])
                ->single(true);

            $this->_selectAssociationField($targetQuery, '*', $this->_associationAlias());
        }

        return $this->_setTargetQuery($row, $targetQuery);
    }

/**
 * Batch-load the association for all parent rows with one IN() query per
 * key chunk.
 *
 * Collects the distinct foreign key values held by the parent rows and
 * fetches every referenced target entity at once, indexed by primary key.
 * Polymorphic associations are grouped by target model first, one query
 * per referenced model.
 *
 * @param array $parentRows Raw parent result rows.
 * @param array $containment Normalized containment configuration.
 * @return array|null Batch data with an entity lookup map, or null to fall back.
 */
    public function eagerLoad(array $parentRows, array $containment) {
        if ($this->polymorphic()) {
            return $this->_eagerLoadPolymorphic($parentRows, $containment);
        }

        $foreignKeyValues = $this->_collectBatchKeys($parentRows, $this->foreignKey());

        $entitiesByPrimaryKey = [];
        if (!empty($foreignKeyValues)) {
            $chunkQueries = $this->_buildBatchQueries($foreignKeyValues, $containment);
            if ($chunkQueries === null) {
                return null;
            }
            $entitiesByPrimaryKey = $this->_runBatchQueries($chunkQueries, $this->target()->primaryKey());
        }

        return ['map' => $entitiesByPrimaryKey];
    }

/**
 * Batch-load a polymorphic association, one query per referenced target model.
 *
 * Parent rows are grouped by their foreign model column and each group is
 * fetched with IN() queries against that model's table. All group queries
 * are built and validated before any of them executes, so an unbatchable
 * containment aborts without issuing partial work. The lookup map is keyed
 * by model name first, then by primary key.
 *
 * @param array $parentRows Raw parent result rows.
 * @param array $containment Normalized containment configuration.
 * @return array|null Batch data with a per-model entity lookup map, or null to fall back.
 */
    protected function _eagerLoadPolymorphic(array $parentRows, array $containment) {
        $foreignKey = $this->foreignKey();
        $foreignModel = $this->foreignModel();

        $keyValuesByModel = [];
        foreach ($parentRows as $parentRow) {
            $targetModel = $this->_extractPropertyValue($parentRow, $foreignModel);
            $foreignKeyValue = $this->_extractPropertyValue($parentRow, $foreignKey);
            if (!empty($targetModel) && !empty($foreignKeyValue)) {
                $keyValuesByModel[$targetModel][$foreignKeyValue] = true;
            }
        }

        $queriesByModel = [];
        $primaryKeysByModel = [];
        foreach ($keyValuesByModel as $targetModel => $keyValues) {
            $this->target($targetModel);
            $chunkQueries = $this->_buildBatchQueries(array_keys($keyValues), $containment);
            if ($chunkQueries === null) {
                return null;
            }
            $queriesByModel[$targetModel] = $chunkQueries;
            $primaryKeysByModel[$targetModel] = $this->target()->primaryKey();
        }

        $entitiesByModel = [];
        foreach ($queriesByModel as $targetModel => $chunkQueries) {
            $entitiesByModel[$targetModel] = $this->_runBatchQueries($chunkQueries, $primaryKeysByModel[$targetModel]);
        }

        return ['map' => $entitiesByModel];
    }

/**
 * Build one batched target query per chunk of key values.
 *
 * Mirrors the per-row mapResult() query construction (target table with
 * the association alias, contain builder applied, association fields
 * selected) but filters by primary key IN() instead of a single value.
 * No query is executed here, so callers can validate batchability across
 * all chunks/groups before issuing any SQL.
 *
 * @param array $foreignKeyValues Distinct foreign key values to fetch.
 * @param array $containment Normalized containment configuration.
 * @return array|null Chunk queries, or null when the builder made the
 *   query unbatchable (limit/offset/single).
 */
    protected function _buildBatchQueries(array $foreignKeyValues, array $containment) {
        $target = $this->target();
        $targetPrimaryKey = $target->primaryKey();

        $chunkQueries = [];
        foreach ($this->_batchKeyChunks($foreignKeyValues) as $keyValueChunk) {
            $targetQuery = $target->query()->from($target->table(), $this->_associationAlias());
            $targetQuery = $this->_queryBuilder('contain', $containment, $targetQuery);

            if (!$this->_isBatchableQuery($targetQuery)) {
                return null;
            }

            $targetQuery->andWhere([
                $this->_associationAliasField($targetPrimaryKey) => $keyValueChunk
            ]);

            $this->_selectAssociationField($targetQuery, '*', $this->_associationAlias());

            $chunkQueries[] = $targetQuery;
        }

        return $chunkQueries;
    }

/**
 * Execute the chunk queries and index the fetched entities by primary key.
 *
 * @param array $chunkQueries Queries built by _buildBatchQueries().
 * @param string $targetPrimaryKey Primary key field of the target table.
 * @return array Entities indexed by primary key value.
 */
    protected function _runBatchQueries(array $chunkQueries, $targetPrimaryKey) {
        $entitiesByPrimaryKey = [];
        foreach ($chunkQueries as $chunkQuery) {
            foreach ($chunkQuery->all() as $targetEntity) {
                $primaryKeyValue = $this->_extractPropertyValue($targetEntity, $targetPrimaryKey);
                $entitiesByPrimaryKey[$primaryKeyValue] = $targetEntity;
            }
        }
        return $entitiesByPrimaryKey;
    }

/**
 * Populate a parent row from the batch lookup map.
 *
 * Assigns the singular association property with a clone of the referenced
 * target entity — cloned because several parents can reference the same
 * target, and the per-row path gave each parent an independent instance.
 * Parents without a (resolvable) foreign key value get null, matching the
 * per-row mapResult() output. Polymorphic rows without a foreign model
 * value are returned untouched, also matching mapResult().
 *
 * @param \Nata\ORM\Entity|array $row Parent row being prepared.
 * @param array $batch Batch data returned by eagerLoad().
 * @param array $containment Normalized containment configuration.
 * @return \Nata\ORM\Entity|array Row with the association property set.
 */
    public function mapBatchedResult($row, array $batch, array $containment) {
        $entityMap = $batch['map'];

        if ($this->polymorphic()) {
            $targetModel = $this->_extractPropertyValue($row, $this->foreignModel());
            if (empty($targetModel)) {
                return $row;
            }
            $entityMap = $entityMap[$targetModel] ?? [];
        }

        $foreignKeyValue = $this->_extractPropertyValue($row, $this->foreignKey());
        $targetEntity = $foreignKeyValue !== null ? ($entityMap[$foreignKeyValue] ?? null) : null;

        $row[Inflector::singularize($this->propertyName())] = $targetEntity !== null ? clone $targetEntity : null;

        return $row;
    }

/**
 * Build query associated results of given entity.
 *
 * @param \Nata\ORM\Entity $entity Entity
 * @param \Closure $queryBuilder Query builder
 * @return \Nata\ORM\Entity
 */
    public function of(Entity $entity, Closure $queryBuilder = null) {
        $foreignKey = $this->foreignKey();
        $sourceId = $this->_extractPropertyValue($entity, $foreignKey);
        if (empty($sourceId)) {
            return;
        }

        if ($this->polymorphic()) {
            if (!$entity->has($this->foreignModel())) {
                if ($entity->isNew()) {
                    return null;
                }
                throw new InvalidArgumentException(sprintf('Missing property "%s" from entity "%s" for polymorphic target model.', $this->foreignModel(), $entity->source()));
            }

            $targetModel = $entity->get($this->foreignModel());
            if ($targetModel === null) {
                return null;
            }

            $this->target($targetModel);
        }

        $target = $this->target();

        $query = $target->query()
            ->select($this->_associationAliasField('*'))
            ->from($target->table(), $this->_associationAlias());

        if ($queryBuilder) {
            $query = $queryBuilder($query);
            if (!($query instanceof DatabaseQuery)) {
                throw new UnexpectedValueException(sprintf('Query instance expected, got %s instead.', gettype($query)));
            }
        }

        $query->andWhere([
            $this->_associationAliasField($target->primaryKey()) => $sourceId
        ]);

        return $query->first();
    }

}
