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

use Closure;
use Nata\Database\Query\QueryExpression;
use Nata\ORM\Entity;
use Nata\Utility\Hash;

/**
 * Base class for Lazy/Eager data loading from associations.
 */
class Loader {

/**
 * Table instance.
 *
 * @var \Nata\ORM\Table
 */
protected $_table;

/**
 * Array of matching associations map.
 *
 * @var array
 */
    protected $_containments = [];

/**
 * Array of matching associations map.
 *
 * @var array
 */
    protected $_matchings = [];

/**
 * Table instance.
 *
 * @var \Nata\ORM\Table
 */
    protected $_defaultOptions = [
        'associations' => [],
        'builder' => null,
        'entity' => null,
        'target' => null,
        'preventBuilding' => false,
        '_normalized' => true
    ];

/**
 * Table instance.
 *
 * @var \Nata\ORM\Table
 */
    protected $_normalized = [
        '_containments' => null,
        '_matchings' => null
    ];


/**
 * Constructor.
 *
 * @param \Nata\ORM\Table Instance
 * @return void
 */
    public function __construct(Table $table) {
        $this->_table = $table;
    }

/**
 * Load assocations.
 *
 * @param string|array $associations Associations
 * @param closure $builder Callable
 * @return \Nata\ORM\EagerLoader|array
 */
    public function contain($associations = null, callable $builder = null) {
        return $this->_mergeContain($associations, $builder);
    }

/**
 * Prevent buildContainQuery() from being called when doing a simple COUNT().
 *
 * Calling buildContainQuery() automatically is useful because it selects the foreign field everytime
 * a contain association is added, allowing to have the foreign_key field value available to
 * load the respective association entity.
 *
 * If not, it will add the foreign key field to the query,
 * triggering a MySQL sql_mode=only_full_group_by error.
 */
    public function preventQueryBuilding() {
        $this->_defaultOptions['preventBuilding'] = true;
    }

/**
 * Return query results that match this assocations.
 *
 * @param string|array $associations Associations
 * @param closure $builder Callable
 * @return \Nata\ORM\EagerLoader|array
 */
    public function matching($associations = null, callable $builder = null) {
        return $this->_mergeMatching(__FUNCTION__, $associations, $builder, false);
    }

/**
 * Return query results that match this assocations.
 *
 * @param string|array $associations Associations
 * @param closure $builder Callable
 * @return \Nata\ORM\EagerLoader|array
 */
    public function orMatching($associations = null, callable $builder = null) {
        return $this->_mergeMatching('matching', $associations, $builder, false, 'or');
    }

/**
 * Return query results that don't match this assocations.
 *
 * @param string|array $associations Associations
 * @param closure $builder Callable
 * @return \Nata\ORM\EagerLoader|array
 */
    public function notMatching($associations = null, callable $builder = null) {
        return $this->_mergeMatching(__FUNCTION__, $associations, $builder, true);
    }

/**
 * Return query results that don't match this assocations.
 *
 * @param string|array $associations Associations
 * @param closure $builder Callable
 * @return \Nata\ORM\EagerLoader|array
 */
    public function orNotMatching($associations = null, callable $builder = null) {
        return $this->_mergeMatching('notMatching', $associations, $builder, true, 'or');
    }

/**
 * Return query results that don't match this assocations.
 *
 * @param string|array $associations Associations
 * @param closure $builder Callable
 * @return \Nata\ORM\EagerLoader|array
 */
    private function _mergeContain($associations, $builder = null) {
        if ($associations === null) {
            return $this->_containments;
        }

        $associations = $this->_merge($associations, $builder);

        $this->_containments = array_merge($this->_containments, $associations);
    }

/**
 * Return query results that don't match this assocations.
 *
 * @param string|array $associations Associations
 * @param closure $builder Callable
 * @return \Nata\ORM\EagerLoader|array
 */
    private function _mergeMatching($type, $associations, $builder = null, $negation = false, $conjunction = 'and') {
        if ($associations === null) {
            return $this->_matchings;
        }

        if (empty($associations)) {
            return;
        }

        $associations = $this->_merge($associations, $builder);

        $matching = [
            'type' => $type,
            'associations' => $associations,
            'method' => $conjunction . 'Where',
            'negation' => $negation
        ];

        $this->_matchings = array_merge($this->_matchings, [$matching]);
    }

/**
 * Return query results that don't match this assocations.
 *
 * @param string|array $associations Associations
 * @param closure $builder Callable
 * @return \Nata\ORM\EagerLoader|array
 */
    private function _merge($associations, $builder = null) {
        if (!is_array($associations) && !empty($associations)) {
            $config = ['builder' => $builder];
            if ($builder instanceof Entity) {
                $config = ['entity' => $builder];
            }

            $config += $this->_defaultOptions;

            $associations = [$associations => $config];
        }
        return $associations;
    }

/**
 * Return query results that match this assocations.
 *
 * @param string $type Name of property
 * @param string|array $associations Associations
 * @param closure $builder Callable
 * @return array|string
 */
    private function _getNormalized($type) {
        $type = '_' . $type;
        if ($this->_normalized[$type] === null) {
            $this->_normalized[$type] = $this->_normalizer($this->{$type});
        }
        return $this->_normalized[$type];
    }

/**
 * Return query results that match this associations.
 *
 * @param string $type Name of property
 * @param string|array $associations Associations
 * @param closure $builder Callable
 * @return array
 */
    private function _normalizer(array $associations) {
        if (empty($associations)) {
            return [];
        }

        $data = [];
        foreach ($associations as $alias => $association) {
            list($alias, $association) = $this->_normalizeAssociation($alias, $association, false);
            $data[$alias] = $association + $this->_defaultOptions;
        }
        return Hash::expand($data);
    }

/**
 * Return query results that match this associations.
 *
 * @param string $type Name of property
 * @param string|array $associations Associations
 * @return array
 */
    private function _normalizeAssociation($alias, $association, $expand = true) {
        if (is_int($alias)) {
            $alias = $association;
            $association = $this->_defaultOptions;
        }

        if (strpos($alias, '.') !== false) {
            $alias = str_replace('.', '.associations.', $alias);
        }

        if ($association instanceof Closure) {
            $association = ['builder' => $association];
        } elseif ($association instanceof Entity) {
            $association = ['entity' => $association];
        }

        $association += $this->_defaultOptions;

        if ($expand === true) {
            $association = Hash::expand([$alias => $association]);
            list($alias) = explode('.', $alias);
            $association = $association[$alias] + $this->_defaultOptions;
        }

        if (str_contains($alias, ':')) {
            [$alias, $association['target']] = splitter($alias, ':');
        }

        return [$alias, $association];
    }

/**
 * Contain associations.
 *
 * @param \Nata\ORM\Entity|array $row Entity/row
 * @return \Nata\ORM\Entity|array
 */
    public function prepareResult($row) {
        $associations = $this->_table->associations();
        foreach ($this->_getNormalized('containments') as $alias => $containment) {
            $row = $associations->get($alias)->mapResult($row, $containment + $this->_defaultOptions);
        }
        return $row;
    }

/**
 * Get results that match association conditions.
 *
 * @param \Nata\ORM\Query $query Query builder instance
 * @return \Nata\ORM\Query
 */
    public function buildContainQuery(Query $query) {
        if ($this->_defaultOptions['preventBuilding']) {
            return;
        }

        $associations = $this->_table->associations();

        foreach ($this->_getNormalized('containments') as $alias => $containment) {
            $set = $associations->get($alias)->buildQuery($query, $containment + $this->_defaultOptions);

            if ($set) {
                unset($this->_normalized['_containments'][$alias]);
            }

        }

    }

/**
 * Get results that match association conditions.
 *
 * @param \Nata\ORM\Query $query Query builder instance
 * @return \Nata\ORM\Query
 */
    public function buildMatchQuery(Query $query) {

        foreach ($this->_matchings as $index => $matching) {

            $expression = new QueryExpression();
            foreach ($matching['associations'] as $alias => $associations) {

                $conjunction = strtoupper($alias);
                if (in_array($conjunction, ['OR', 'AND'])) {
                    $conditions = $this->_getMatchingConditionsForExpression($query, $matching['type'], $associations);
                    if (empty($conditions)) {
                        continue;
                    }

                    $subExpression = new QueryExpression([$conjunction => $conditions], $conjunction);
                    $expression->add($subExpression);
                } else {
                    $conditions = $this->_getMatchingConditions($query, $matching['type'], $alias, $associations);
                    $expression->add($conditions);
                }

            }

            $query->{$matching['method']}($expression);

            unset($this->_matchings[$index]);
        }

        return $query;
    }

/**
 * Get expression operator and field.
 * If no operator will assume '='.
 *
 * @param string $field Field name with Operator
 * @return array Field name and Operator
 */
    protected function _getMatchingConditionsForExpression(Query $query, $type, $associations) {
        $conditions = [];
        foreach ($associations as $alias => $association) {
            $conditions[] = $this->_getMatchingConditions($query, $type, $alias, $association);
        }
        return $conditions;
    }

/**
 * Get expression operator and field.
 * If no operator will assume '='.
 *
 * @param string $field Field name with Operator
 * @return array Field name and Operator
 */
    protected function _getMatchingConditions(Query $query, $type, $alias, $containment) {
        [$alias, $containment] = $this->_normalizeAssociation($alias, $containment);
        return $this->_table->associations()->get($alias)->{$type}($query, $containment);
    }

}
