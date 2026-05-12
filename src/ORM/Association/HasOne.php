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
use Nata\Database\Query as DatabaseQuery;
use Nata\ORM\Query;
use Nata\ORM\Association;
use Nata\ORM\Entity;
use Nata\ORM\Table;
use Nata\Utility\Inflector;
use UnexpectedValueException;
use Closure;

class HasOne extends Association {

/**
 * Single record.
 *
 * @var bool
 */
    protected $_single = true;

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
    public function __construct(string $alias, array $options = []) {
        parent::__construct($alias, $options);

        $options += [
            'dependent' => null
        ];

        if ($options['dependent'] !== null) {
            $this->_dependent = $options['dependent'];
        }
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
                $this->_propertyName = Inflector::singularize($this->_propertyName);
            }
            return $this->_propertyName;
        }

        $this->_propertyName = $propertyName;

        return $this;
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
 * Save entities of values in to database.
 *
 * @param \Nata\ORM\Entity $sourceEntity Source entity
 * @param \Nata\ORM\Entity|array $targetEntity Related data
 * @param array $options Save options
 * @return \Nata\ORM\Entity Persisted entity
 */
    public function afterSave(Entity $sourceEntity, $targetEntity, $options) {
        $target = $this->target();
        $source = $this->source();

        if (empty($targetEntity)) {
            $this->_deleteTargetEntity($sourceEntity);
            return $targetEntity;
        }

        $targetEntity = $target->newEntity($targetEntity);

        if ($targetEntity->isNew()) {
            $this->_setDefaultValues($targetEntity);
        }

        $this->_checkIsNew($sourceEntity, $targetEntity);

        [$sourcePrimaryKey] = (array)$source->primaryKey();
        $targetEntity->set($this->foreignKey(), $sourceEntity->get($sourcePrimaryKey));
        if ($this->polymorphic()) {
            $targetEntity->set($this->foreignModel(), $source->registryAlias());
            if ($this->_foreignAssociationName) {
                $targetEntity->set($this->_foreignAssociationName, $this->_name);
            }
        }

        $success = $target->save($targetEntity, $options);
        if ($success === false) {
            $this->_setError(__('Error saving record for "%s" on association "%s".', [
                App::classShortName($sourceEntity),
                $this->_name
            ]));
        }

        return $targetEntity;
    }

/**
 * Check if target entity already exists and sets its respective
 * primary key to prevent duplicates.
 *
 * @param \Nata\ORM\Entity $sourceEntity Source entity
 * @param \Nata\ORM\Entity $targetEntity Target entity
 * @return void
 */
    protected function _checkIsNew(Entity $sourceEntity, Entity $targetEntity) {
        if (!$targetEntity->isNew()) {
            return null;
        }

        $target = $this->target();
        $source = $this->source();

        [$sourcePrimaryKey] = (array)$source->primaryKey();
        [$targetPrimaryKey] = (array)$target->primaryKey();

        $targetPrimaryKeyValue = $targetEntity->get($targetPrimaryKey);
        if (!empty($targetPrimaryKeyValue)) {
            $targetEntity->isNew(false);
            return null;
        }

        $conditions = [$this->foreignKey() => $sourceEntity->get($sourcePrimaryKey)];
        if ($this->polymorphic()) {
            $conditions[$this->foreignModel()] = $source->registryAlias();
            if ($this->_foreignAssociationName) {
                $conditions[$this->_foreignAssociationName] = $this->_name;
            }
        }

        parent::_evaluateIsNew($target, $conditions, $targetEntity);

        return true;
    }

/**
 * Delete target entity.
 *
 * @param \Nata\ORM\Entity $sourceEntity Source entity
 * @return void
 */
    protected function _deleteTargetEntity(Entity $sourceEntity) {
        $conditions = [$this->foreignKey() => $sourceEntity->id];
        if ($this->polymorphic()) {
            $conditions[$this->foreignModel()] = $this->source()->registryAlias();
            if ($this->_foreignAssociationName) {
                $conditions[$this->_foreignAssociationName] = $this->_name;
            }
        }
        $this->target()->deleteAll($conditions);
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
        if ($associations['entity']) {
            $this->target($associations['entity']->source());
        }

        $associationAlias = $this->_associationAlias();
        $source = $this->source();
        $target = $this->target();
        $foreignKey = $this->foreignKey();
        $targetQuery = $this->query();

        $targetQuery
            ->select($this->_associationAliasField($foreignKey))
            ->from($target->table(), $associationAlias);

        if ($this->polymorphic()) {
            $targetQuery->andWhere([
                $this->_associationAliasField($this->foreignModel()) => $source->registryAlias()
            ]);
        }

        $targetQuery = $this->_queryBuilder('matching', $associations, $targetQuery);
        $targetQuery = $targetQuery->eagerLoader()->buildMatchQuery($targetQuery);

        $clause = ' IN';
        if ($comparison === 'notMatching') {
            $clause = ' NOT' . $clause;
        }
        return array(
            $sourceQuery->aliasField('id', $this->source()->table()) . $clause => $targetQuery
        );
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
 * @param closure|array $containment Callable closure
 * @return \Nata\ORM\Entity|array
 */
    public function mapResult($row, $containment) {
        extract($containment);

        $foreignKey = $this->foreignKey();
        $source = $this->source();
        $target = $this->target();

        $sourceId = $this->_extractPropertyValue($row, 'id');

        $query = $this->_queryBuilder(
                'contain',
                $containment,
                $target->query()->from($target->table(),
                $this->_associationAlias())
            )
            ->where(array($this->_associationAliasField($foreignKey) => $sourceId))
            ->single(true);

        if ($this->polymorphic()) {
            $query->andWhere(array($this->_associationAliasField($this->foreignModel()) => $source->registryAlias()));
        }

        $this->_selectAssociationField($query, '*', $this->_associationAlias());

        return $this->_setTargetQuery($row, $query);
    }

/**
 * Build query associated results of given entity.
 *
 * @param \Nata\ORM\Entity|array $entity Entity
 * @param \Closure $builder Query builder
 * @return \Nata\ORM\Entity
 */
    public function of($entity, Closure $queryBuilder = null) {
        $foreignKey = $this->foreignKey();
        $source = $this->source();
        $target = $this->target();

        $sourceId = $this->_extractPropertyValue($entity, 'id');
        if (!($sourceId > 0)) {
            return;
        }

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
            $this->_associationAliasField($foreignKey) => $sourceId
        ]);

        if ($this->polymorphic()) {
            $query->andWhere([
                $this->_associationAliasField($this->foreignModel()) => $source->registryAlias()
            ]);
            if ($this->_foreignAssociationName) {
                $query->andWhere([
                    $this->_associationAliasField($this->_foreignAssociationName) => $this->_name
                ]);
            }
        }

        return $query->first();
    }

}
