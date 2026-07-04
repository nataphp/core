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
use Nata\Collection\Collection;
use Nata\Database\Query as DatabaseQuery;
use Nata\ORM\Query;
use Nata\ORM\Association;
use Nata\ORM\Entity;
use Nata\ORM\ResultSet;
use Nata\ORM\Table;
use Nata\Utility\Inflector;
use UnexpectedValueException;
use Closure;
use RuntimeException;

class HasMany extends Association {

/**
 * Is target table dependent on the source.
 *
 * This allows the correct managment of save strategy.
 *
 * @var bool
 */
    protected $_dependent;


/**
 * Constructor.
 *
 * @param string $alias Association alias name
 * @param array $options Association options
 */
    public function __construct($alias, array $options = array()) {
        parent::__construct($alias, $options);

        $options += array(
            'dependent' => null
        );

        if ($options['dependent'] !== null) {
            $this->_dependent = $options['dependent'];
        }

    }

/**
 * Check if target table is dependent on the source. If not set (null),
 * it will try to guess by convention.
 *
 * If target table is prefixed by the source table name, singularized,
 * it assumes it is dependent.
 *
 * @param bool $dependent Dependent
 * @return bool True if dependent, false otherwise
 */
    public function dependent($dependent = null) {
        if ($dependent === null) {
            if ($this->_dependent === null) {
                $sourceTable = Inflector::singularize($this->source()->table());
                $targetTable = $this->target()->table();

                $this->_dependent = stripos($targetTable, $sourceTable . '_') === 0;
            }

            return $this->_dependent;
        }

        $this->_dependent = $dependent;

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
        return $side === $this->source();
    }

/**
 * Save entities of values in to database.
 *
 * @param \Nata\ORM\Entity $sourceEntity Source entity
 * @param \Nata\ORM\Entity|array $data Related data
 * @param array $options Save options
 * @return \Nata\ORM\Entity|array Data
 */
    public function afterSave(Entity $sourceEntity, $targetEntities, $options) {
        [$targetEntities, $isSingle, $isNull] = $this->_normalizeMany($targetEntities);

        $target = $this->target();
        $foreignKey = $this->foreignKey();
        [$sourcePrimaryKey] = (array)$this->source()->primaryKey();
        [$targetPrimaryKey] = (array)$target->primaryKey();

        // Replace
        if ($this->_saveStrategyReplace($target, $sourceEntity) === false) {
            $this->_setError(__('Error comparing records for "%s" on association "%s".', [
                App::classShortName($sourceEntity),
                $this->_name
            ]));
        }

        $newTargetEntities = [];
        foreach ($targetEntities as $index => $targetEntity) {
            $targetEntity = $target->newEntity($targetEntity);

            if ($targetEntity->isNew()) {
                $this->_setDefaultValues($targetEntity);
            }

            $targetEntity->set($foreignKey, $sourceEntity->get($sourcePrimaryKey));
            if ($this->polymorphic()) {
                $targetEntity->set($this->foreignModel(), $sourceEntity->source());
                if ($this->_foreignAssociationName) {
                    $targetEntity->set($this->_foreignAssociationName, $this->_name);
                }
            }

            $saved = $target->save($targetEntity, $options);
            if ($saved === false) {
                $this->_setError(__('Error saving "%s" on association "%s" (at index: %s).', [
                    App::classShortName($targetEntity),
                    $this->_name,
                    $index
                ]));
            }

            $newTargetEntities[] = $targetEntity;
        }

        $targetEntities = new Collection($newTargetEntities);
        $newTargetEntities = null;

        // Compare
        if ($this->_saveStrategyCompare($target, $sourceEntity, $targetPrimaryKey, $targetEntities) === false) {
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
 * Apply 'compare' save strategy to linked entities.
 *
 * @param \Nata\ORM\Table $modelTable Table instance
 * @param \Nata\ORM\Entity $sourceEntity Source primary key value
 * @param string $fieldName Field name to compare
 * @param Collection $foreignEntities Saved primary keys to ignore on delete
 * @return void
 */
    protected function _saveStrategyCompare(Table $modelTable, Entity $sourceEntity, $fieldName, Collection $foreignEntities): ?bool {
        if ($this->saveStrategy() !== 'compare') {
            return null;
        }

        // If dependent, delete all
        if ($this->dependent() === true) {
            return parent::_saveStrategyCompare($modelTable, $sourceEntity, $fieldName, $foreignEntities);
        }

        // Update/Clear instead of deleting the record
        $update = [$this->foreignKey() => null];
        $conditions = [$this->foreignKey() => $sourceEntity->id];
        if ($this->polymorphic()) {
            $update[$this->foreignModel()] = null;
            $conditions[$this->foreignModel()] = $sourceEntity->source();
            if ($this->_foreignAssociationName) {
                $update[$this->_foreignAssociationName] = null;
                $conditions[$this->_foreignAssociationName] = $this->_name;
            }
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

        $foreignKeyColumn = $modelTable->schema()->column($this->foreignKey());
        if ($foreignKeyColumn->getNotNull()) {
            throw new RuntimeException(sprintf(
                "Foreign key '%s' doesn't allow NULL. Allow NULL or set association '%s' as dependent or simply exclude '%s' from 'associated' option in Table::save().",
                $this->foreignKey(),
                $this->_name,
                $this->_name,
            ));
        }

        return (bool)$modelTable->updateAll($update, $conditions);
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
        $source = $this->source();
        $target = $this->target();
        $foreignKey = $this->foreignKey();

        $targetQuery = $target->query()
            ->select($this->_associationAliasField($foreignKey))
            ->from($target->table(), $this->_associationAlias());

        if ($this->polymorphic()) {
            $targetQuery->andWhere([
                $this->_associationAliasField($this->foreignModel()) => $source->registryAlias()
            ]);
            if ($this->_foreignAssociationName) {
                $targetQuery->andWhere([
                    $this->_associationAliasField($this->_foreignAssociationName) => $this->_name
                ]);
            }
        }

        $targetQuery = $this->_queryBuilder('matching', $associations, $targetQuery)
            ->eagerLoader()
            ->buildMatchQuery($targetQuery);

        $clause = ' IN';
        if ($comparison === 'notMatching') {
            $clause = ' NOT' . $clause;
        }

        return [
            $sourceQuery->aliasField('id', $source->table()) . $clause => $targetQuery
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
    public function mapResult($entity, $builder) {
        $foreignKey = $this->foreignKey();
        $source = $this->source();
        $target = $this->target();

        $query = $this->find()
            ->select($this->_associationAliasField('*'))
            ->from($target->table(), $this->_associationAlias());

        $sourceId = $this->_extractPropertyValue($entity, 'id');
        if (empty($sourceId)) {
            return $this->_setTargetQuery($entity, new ResultSet($query, []));
        }

        $query = $this->_queryBuilder('contain', $builder, $query)
            ->andWhere(array($this->_associationAliasField($foreignKey) => $sourceId));

        if ($this->polymorphic()) {
            $query->andWhere([$this->_associationAliasField($this->foreignModel()) => $source->registryAlias()]);
            if ($this->_foreignAssociationName) {
                $query->andWhere([$this->_associationAliasField($this->_foreignAssociationName) => $this->_name]);
            }
        }

        return $this->_setTargetQuery($entity, $query);
    }

/**
 * Batch-load the association for all parent rows with one IN() query per
 * key chunk.
 *
 * Runs the same target query mapResult() builds per row, but filtered by
 * every parent id at once, then groups the children by foreign key value.
 * Associations flagged 'single' => true fall back to the per-row path,
 * which handles the singularized single-entity property assignment.
 *
 * @param array $parentRows Raw parent result rows.
 * @param array $containment Normalized containment configuration.
 * @return array|null Batch data with children grouped by foreign key, or null to fall back.
 */
    public function eagerLoad(array $parentRows, array $containment) {
        if ($this->_single) {
            return null;
        }

        $foreignKey = $this->foreignKey();
        $parentIds = $this->_collectBatchKeys($parentRows, 'id');

        $childrenByForeignKey = [];
        foreach ($this->_batchKeyChunks($parentIds) as $parentIdChunk) {
            $targetQuery = $this->_buildBatchChunkQuery($parentIdChunk, $containment);
            if ($targetQuery === null) {
                return null;
            }

            foreach ($targetQuery->all() as $targetEntity) {
                $foreignKeyValue = $this->_extractPropertyValue($targetEntity, $foreignKey);
                $childrenByForeignKey[$foreignKeyValue][] = $targetEntity;
            }
        }

        return ['map' => $childrenByForeignKey];
    }

/**
 * Build the batched target query for one chunk of parent ids.
 *
 * Mirrors the per-row mapResult() query construction (association finder,
 * conditions and sort via find(), contain builder applied, polymorphic
 * conditions) but filters by foreign key IN() instead of a single value.
 *
 * @param array $parentIdChunk Chunk of distinct parent id values.
 * @param array $containment Normalized containment configuration.
 * @return \Nata\ORM\Query|null Chunk query, or null when the builder made
 *   the query unbatchable (limit/offset/single).
 */
    private function _buildBatchChunkQuery(array $parentIdChunk, array $containment) {
        $foreignKey = $this->foreignKey();
        $source = $this->source();
        $target = $this->target();

        $targetQuery = $this->find()
            ->select($this->_associationAliasField('*'))
            ->from($target->table(), $this->_associationAlias());

        $targetQuery = $this->_queryBuilder('contain', $containment, $targetQuery);

        if (!$this->_isBatchableQuery($targetQuery)) {
            return null;
        }

        $targetQuery->andWhere([$this->_associationAliasField($foreignKey) => $parentIdChunk]);

        if ($this->polymorphic()) {
            $targetQuery->andWhere([$this->_associationAliasField($this->foreignModel()) => $source->registryAlias()]);
            if ($this->_foreignAssociationName) {
                $targetQuery->andWhere([$this->_associationAliasField($this->_foreignAssociationName) => $this->_name]);
            }
        }

        return $targetQuery;
    }

/**
 * Populate a parent row from the batch lookup map.
 *
 * Assigns the plural association property with the parent's children as a
 * prepared ResultSet (or an empty one), matching the per-row mapResult()
 * output shape.
 *
 * @param \Nata\ORM\Entity|array $row Parent row being prepared.
 * @param array $batch Batch data returned by eagerLoad().
 * @param array $containment Normalized containment configuration.
 * @return \Nata\ORM\Entity|array Row with the association property set.
 */
    public function mapBatchedResult($row, array $batch, array $containment) {
        return $this->_mapBatchedManyResult($row, $batch);
    }

/**
 * Build query associated results of given entity.
 *
 * @param \Nata\ORM\Entity|array $entity Entity
 * @param \Closure $builder Query builder
 * @return \Nata\ORM\ResultSet
 */
    public function of($entity, Closure $queryBuilder = null) {
        $foreignKey = $this->foreignKey();
        $source = $this->source();
        $target = $this->target();

        $query = $this->find()
            ->select($this->_associationAliasField('*'))
            ->from($target->table(), $this->_associationAlias());

        $sourceId = $this->_extractPropertyValue($entity, 'id');
        if (empty($sourceId)) {
            return new ResultSet($query, []);
        }
        if ($queryBuilder) {
            $query = $queryBuilder($query);
            if (!($query instanceof DatabaseQuery)) {
                throw new UnexpectedValueException(sprintf('Query instance expected, got %s instead.', gettype($query)));
            }
        }

        $query->andWhere([$this->_associationAliasField($foreignKey) => $sourceId]);
        if ($this->polymorphic()) {
            $query->andWhere([$this->_associationAliasField($this->foreignModel()) => $source->registryAlias()]);
            if ($this->_foreignAssociationName) {
                $query->andWhere([$this->_associationAliasField($this->_foreignAssociationName) => $this->_name]);
            }
        }

        return $query->all();
    }

}