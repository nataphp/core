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

namespace Nata\ORM\Behavior;

use Nata\ORM\Behavior;
use Nata\ORM\Entity;
use Nata\ORM\Query;
use Nata\Event\Event;
use RuntimeException;
use InvalidArgumentException;
use RecordNotFoundException;

/**
 * Makes the table to which this is attached to behave like a nested set and
 * provides methods for managing and retrieving information out of the derived
 * hierarchical structure.
 *
 * Tables attaching this behavior are required to have a column referencing the
 * parent row, and two other numeric columns (lft and rght) where the implicit
 * order will be cached.
 *
 * For more information on what is a nested set and a how it works refer to
 * http://www.sitepoint.com/hierarchical-data-database-2/
 */
class Tree extends Behavior {

/**
 * Cached copy of the first column in a table's primary key.
 *
 * @var string
 */
    protected $_primaryKey;

/**
 * Default config.
 *
 * These are merged with user-provided configuration when the behavior is used.
 *
 *  - 'parent' Field name where parent id is saved (Defaults to 'parent_id')
 *  - 'rootParentId' Default value defined for root parent id (Defaults to null)
 *  - 'left' Field name for where left value will be stored (Defaults to 'lft')
 *  - 'right' Field name for where right value will be stored (Defaults to 'rght')
 *  - 'level' Field name for where level value will be stored (Defaults to null, so won't be stored)
 *  - 'scope' Query conditions for tree. (Defaults to null)
 *      This allows to have many distinct trees in one table
 *  - 'recoverOrder' Allows you to specify the sorting conditions in which tree recovery should follow
 *
 * @var array
 */
    protected $_defaultConfig = [
        'implementedFinders' => [
            'path' => 'findPath',
            'children' => 'findChildren',
            'tree' => 'findTree',
            'treeList' => 'findTreeList'
        ],
        'implementedMethods' => [
            'childCount' => 'childCount',
            'isChild' => 'isChild',
            'hasChildren' => 'hasChildren',
            'moveUp' => 'moveUp',
            'moveDown' => 'moveDown',
            'recover' => 'recover',
            'removeFromTree' => 'removeFromTree',
            'getLevel' => 'getLevel',
            'formatTreeList' => 'formatTreeList'
        ],
        'primaryKey' => null,
        'parent' => 'parent_id',
        'rootParentId' => null,
        'left' => 'lft',
        'right' => 'rght',
        'level' => null,
        'scope' => null,
        'scopeByField' => null,
        //'scopeByAssociation' => 'Boxes',
        'recoverOrder' => null,
        'existingNodeRequired' => false
    ];


/**
 * Before save listener.
 * Transparently manages setting the lft and rght fields if the parent field is
 * included in the parameters to be saved.
 *
 * @param \Nata\Event\Event $event The beforeSave event that was fired
 * @param \Nata\ORM\Entity $entity the entity that is going to be saved
 * @return void
 * @throws \RuntimeException if the parent to set for the node is invalid
 */
    public function beforeSave(Event $event, Entity $entity) {
        $isNew = $entity->isNew();
        $config = $this->config();
        $hasParent = $entity->has($config['parent']);
        $parent = $this->_getParent($entity, $config['parent']);
        $parentIsDirty = $entity->isDirty($config['parent']);
        $primaryKey = $this->_getPrimaryKey();
        $levelProperty = $config['level'];

        if ($hasParent) {
            if (empty($parent)) {
                $parent = $config['rootParentId'];
                $entity->set($config['parent'], $parent);
            } elseif ($entity->get($primaryKey) == $parent) {
                throw new RuntimeException("Cannot set a node's parent as itself");
            }
        }

        if ($isNew) {
            $level = 0;
            if ($parent) {
                $parentNode = $this->_getNode($parent);
                $edge = $parentNode->get($config['right']);
                $entity->set($config['left'], $edge);
                $entity->set($config['right'], $edge + 1);

                $this->_sync(2, '+', ">= {$edge}");

                if ($levelProperty) {
                    $level = $parentNode->get($levelProperty) + 1;
                }
            } else {
                $edge = $this->_getMax();

                $entity->set($config['left'], $edge + 1);
                $entity->set($config['right'], $edge + 2);
            }

            if ($levelProperty) {
                $entity->set($levelProperty, $level);
            }

            return;
        }

        if ($parentIsDirty && $parent) {
            $this->_setParent($entity, $parent);

            if ($levelProperty) {
                $parentNode = $this->_getNode($parent);
                $entity->set($levelProperty, $parentNode->get($levelProperty) + 1);
            }

            return;
        }

        if ($parentIsDirty && !$parent) {
            $this->_setAsRoot($entity);

            if ($levelProperty) {
                $entity->set($levelProperty, 0);
            }
        }

    }

/**
 * After save listener.
 *
 * Manages updating level of descendents of currently saved entity.
 *
 * @param \Nata\Event\Event $event The beforeSave event that was fired
 * @param \Nata\ORM\Entity $entity the entity that is going to be saved
 * @return void
 */
    public function afterSave(Event $event, Entity $entity) {
        if (!$this->_config['level'] || $entity->isNew()) {
            return;
        }

        $this->_setChildrenLevel($entity);

        // Clear/reset scope
        if ($this->config('scopeByField')) {
            $this->config('scope', null);
        }
    }

/**
 * Set level for descendents.
 *
 * @param \Nata\ORM\Entity $entity The entity whose descendents need to be updated.
 * @return void
 */
    protected function _setChildrenLevel($entity) {
        $config = $this->config();

        if (!$entity->has($config['level']) || !$entity->has($config['left']) || !$entity->has($config['right'])) {
            return false;
        }

        if (($entity->get($config['left']) + 1) === $entity->get($config['right'])) {
            return;
        }

        $primaryKey = $this->_getPrimaryKey();
        $primaryKeyValue = (string)$entity->get($primaryKey);
        $depths = [$primaryKeyValue => (int)$entity->get($config['level'])];
        $fields = [$primaryKey, $config['parent'], $config['level']];

        $children = $this->_table->find('children', [
            'for' => $primaryKeyValue,
            'fields' => $fields,
            'order' => $config['left']
        ]);

        foreach ($children as $node) {
            $parentIdValue = (string)$this->_getParent($node, $config['parent']);
            if (!isset($depths[$parentIdValue])) {
                $depths[$parentIdValue] = 0;
            }
            $depth = $depths[$parentIdValue] + 1;
            $nodePrimaryKeyValue = (string)$node->get($primaryKey);
            $depths[$nodePrimaryKeyValue] = $depth;

            $this->_table->updateAll(
                [$config['level'] => $depth],
                [$primaryKey => $nodePrimaryKeyValue]
            );
        }

    }

/**
 * Also deletes the nodes in the subtree of the entity to be delete
 *
 * @param \Nata\Event\Event $event The beforeDelete event that was fired
 * @param \Nata\ORM\Entity $entity The entity that is going to be saved
 * @return void
 */
    public function beforeDelete(Event $event, Entity $entity) {
        $config = $this->config();
        $this->_ensureFields($entity);
        $left = $entity->get($config['left']);
        $right = $entity->get($config['right']);
        $diff = $right - $left + 1;

        if ($diff > 2) {
            $this->_table->deleteAll([
                "{$config['left']} >=" => $left + 1,
                "{$config['left']} <=" => $right - 1
            ]);
        }

        $this->_sync($diff, '-', "> {$right}");
    }

/**
 * Sets the correct left and right values for the passed entity so it can be
 * updated to a new parent. It also makes the hole in the tree so the node
 * move can be done without corrupting the structure.
 *
 * @param \Nata\ORM\Entity $entity The entity to re-parent
 * @param mixed $parent the id of the parent to set
 * @return void
 * @throws \RuntimeException if the parent to set to the entity is not valid
 */
    protected function _setParent($entity, $parent) {
        $config = $this->config();
        $parentNode = $this->_getNode($parent);
        $this->_ensureFields($entity);
        $parentLeft = $parentNode->get($config['left']);
        $parentRight = $parentNode->get($config['right']);
        $right = $entity->get($config['right']);
        $left = $entity->get($config['left']);

        if ($parentLeft > $left && $parentLeft < $right) {
            throw new RuntimeException(sprintf(
                'Cannot use node "%s" as parent for entity "%s"',
                $parent,
                $entity->get($this->_getPrimaryKey())
            ));
        }

        // Values for moving to the left
        $diff = $right - $left + 1;
        $targetLeft = $parentRight;
        $targetRight = $diff + $parentRight - 1;
        $min = $parentRight;
        $max = $left - 1;

        if ($left < $targetLeft) {
            // Moving to the right
            $targetLeft = $parentRight - $diff;
            $targetRight = $parentRight - 1;
            $min = $right + 1;
            $max = $parentRight - 1;
            $diff *= -1;
        }

        if ($right - $left > 1) {
            // Correcting internal subtree
            $internalLeft = $left + 1;
            $internalRight = $right - 1;
            $this->_sync($targetLeft - $left, '+', "BETWEEN {$internalLeft} AND {$internalRight}", true);
        }

        $this->_sync($diff, '+', "BETWEEN {$min} AND {$max}");

        if ($right - $left > 1) {
            $this->_unmarkInternalTree();
        }

        // Allocating new position
        $entity->set($config['left'], $targetLeft);
        $entity->set($config['right'], $targetRight);
    }

/**
 * Updates the left and right column for the passed entity so it can be set as
 * a new root in the tree. It also modifies the ordering in the rest of the tree
 * so the structure remains valid
 *
 * @param Entity $entity The entity to set as a new root
 * @return void
 */
    protected function _setAsRoot(Entity $entity) {
        $config = $this->config();
        $edge = $this->_getMax();

        $this->_ensureFields($entity);

        $right = $entity->get($config['right']);
        $left = $entity->get($config['left']);

        $diff = $right - $left;

        if ($right - $left > 1) {
            // Correcting internal subtree
            $internalLeft = $left + 1;
            $internalRight = $right - 1;
            $this->_sync($edge - $diff - $left, '+', "BETWEEN {$internalLeft} AND {$internalRight}", true);
        }

        $this->_sync($diff + 1, '-', "BETWEEN {$right} AND {$edge}");

        if ($right - $left > 1) {
            $this->_unmarkInternalTree();
        }

        $entity->set($config['left'], $edge - $diff);
        $entity->set($config['right'], $edge);
    }

/**
 * Helper method used to invert the sign of the left and right columns that are
 * less than 0. They were set to negative values before so their absolute value
 * wouldn't change while performing other tree transformations.
 *
 * @return void
 */
    protected function _unmarkInternalTree() {
        $config = $this->config();
        $query = $this->_table->query();

        $left = sprintf("-1 * %s", $config['left']);
        $right = sprintf("-1 * %s", $config['right']);
        $set = sprintf('%s = %s, %s = %s', $config['left'], $left, $config['right'], $right);
        $where = sprintf('%s < 0', $config['left']);

        $sql = sprintf("UPDATE %s SET %s WHERE %s", $this->_table->table(), $set, $where);

        $query->execute($sql);

        return;

        // ORM version (not working well apparently)
        $this->_table->updateAll([
            "{$config['left']}" => sprintf("-1 * %s", $config['left']),
            "{$config['right']}" => sprintf("-1 * %s", $config['right'])
        ], [$config['left'] . ' <' => 0]);

    }

/**
 * Alias to 'threaded' finder. It will order by left column.
 *
 * @param \Nata\ORM\Query $query Query.
 * @param array $options Array of options.
 * @return \Nata\ORM\Query
 */
    public function findTree(Query $query, array $options): Query {
        return $this->_scope($query)
            ->find('threaded', [
                'parentField' => $this->config('parent'),
                'order' => [$this->config('left') => 'ASC']
            ] + $options);
    }

/**
 * Custom finder method which can be used to return the list of nodes from the root
 * to a specific node in the tree. This custom finder requires that the key 'for'
 * is passed in the options containing the id of the node to get its path for.
 *
 * @param \Nata\ORM\Query $query The constructed query to modify
 * @param array $options the list of options for the query
 * @return \Nata\ORM\Query
 * @throws \InvalidArgumentException If the 'for' key is missing in options
 */
    public function findPath(Query $query, array $options): Query {
        $options += [
            'for' => null
        ];

        if (empty($options['for'])) {
            throw new InvalidArgumentException("The 'for' key is required for find('path')");
        }

        $config = $this->config();

        [$left, $right] = array_map(
            function ($field) {
                return $this->_table->aliasField($field);
            },
            [$config['left'], $config['right']]
        );

        $node = $this->_table->get($options['for'], ['fields' => [$left, $right]]);

        return $this->_scope($query)
            ->andWhere([
                "{$left} <=" => $node->get($config['left']),
                "{$right} >=" => $node->get($config['right'])
            ])
            ->order([$left => 'ASC']);
    }

/**
 * Get the number of children nodes.
 *
 * @param \Nata\ORM\Entity $node The entity to count children for
 * @param bool $direct whether to count all nodes in the subtree or just
 * direct children
 * @return int Number of children nodes.
 */
    public function childCount(Entity $node, $direct = false): int {
        $config = $this->config();
        $parent = $this->_table->aliasField($config['parent']);

        if ($direct) {
            return $this->_scope($this->_table->find())
                ->andWhere([$parent => $node->get($this->_getPrimaryKey())])
                ->count();
        }

        $this->_ensureFields($node);

        return ($node->get($config['right']) - $node->get($config['left']) - 1) / 2;
    }

/**
 * Check if given $node is child or has parents.
 *
 * If $parentNode is given, it will check if given entity
 * is $node's parent.
 *
 * @param \Nata\ORM\Entity $node The entity to check if has parents
 * @param \Nata\ORM\Entity $parentNode The parent entity to check
 * @return bool True if $parentNode is parent of $node, false otherwise
 */
    public function isChild(Entity $node, Entity $parentNode = null): bool {
        if (!($node->id > 0)) {
            throw new InvalidArgumentException("The primary key is required for isChild");
        }

        $path = $this->_table->find('path', [
            'for' => $node->id
        ])->all()->combine('id', 'id')->toArray();

        unset($path[$node->id]);

        if ($parentNode !== null) {
            $path = array_intersect([$parentNode->id], $path);
        }

        return !empty($path);
    }

/**
 * Check if given $node has children.
 *
 * @param \Nata\ORM\Entity $node The entity to check if has parents
 * @return bool
 */
    public function hasChildren(Entity $node): bool {
        if (!($node->id > 0)) {
            throw new InvalidArgumentException("The primary key is required for isChild");
        }

        return $this->_table->find('children', [
            'for' => $node->id
        ])->count();
    }

/**
 * Get the children nodes of the current model
 *
 * Available options are:
 *
 * - for: The id of the record to read.
 * - direct: Boolean, whether to return only the direct (true), or all (false) children,
 *   defaults to false (all children).
 *
 * If the direct option is set to true, only the direct children are returned (based upon the parent_id field)
 *
 * @param \Nata\ORM\Query $query Query.
 * @param array $options Array of options as described above
 * @return \Nata\ORM\Query
 * @throws \InvalidArgumentException When the 'for' key is not passed in $options
 */
    public function findChildren(Query $query, array $options): Query {
        $config = $this->config();
        $options += ['for' => null, 'direct' => false, 'includeParent' => false];

        [$parent, $left, $right] = array_map(
            function ($field) {
                return $this->_table->aliasField($field);
            },
            [$config['parent'], $config['left'], $config['right']]
        );

        [$for, $direct] = [$options['for'], $options['direct']];
        if (empty($for)) {
            throw new InvalidArgumentException("The 'for' key is required for find('children')");
        }

        if ($query->clause('order') === null) {
            $query->order([$left => 'ASC']);
        }

        if ($direct) {
            return $this->_scope($query)->andWhere([$parent => $for]);
        }

        $node = $this->_getNode($for);

        $query = $this->_scope($query)
            ->andWhere([
                "{$right} <" => $node->get($config['right']),
                "{$left} >" => $node->get($config['left'])
            ]);

        if ($options['includeParent']) {
            $query->orWhere([
                $right => $node->get($config['right']),
                $left => $node->get($config['left'])
            ]);
        }

        return $query;
    }

/**
 * Gets a representation of the elements in the tree as a flat list where the keys are
 * the primary key for the table and the values are the display field for the table.
 * Values are prefixed to visually indicate relative depth in the tree.
 *
 * ### Options
 *
 * - keyPath: A dot separated path to fetch the field to use for the array key, or a closure to
 *   return the key out of the provided row.
 * - valuePath: A dot separated path to fetch the field to use for the array value, or a closure to
 *   return the value out of the provided row.
 * - spacer: A string to be used as prefix for denoting the depth in the tree for each item
 *
 * @param \Nata\ORM\Query $query Query.
 * @param array $options Array of options as described above.
 * @return \Nata\ORM\Query
 */
    public function findTreeList(Query $query, array $options): Query {
        $results = $this->_scope($query)
            ->find('threaded', [
                'parentField' => $this->config('parent'),
                'order' => [$this->config('left') => 'ASC']
            ]);
        return $this->formatTreeList($results, $options);
    }

/**
 * Formats query as a flat list where the keys are the primary key for the table
 * and the values are the display field for the table. Values are prefixed to visually
 * indicate relative depth in the tree.
 *
 * ### Options
 *
 * - keyPath: A dot separated path to the field that will be the result array key, or a closure to
 *   return the key from the provided row.
 * - valuePath: A dot separated path to the field that is the array's value, or a closure to
 *   return the value from the provided row.
 * - spacer: A string to be used as prefix for denoting the depth in the tree for each item.
 *
 * @param \Nata\ORM\Query $query The query object to format.
 * @param array $options Array of options as described above.
 * @return \Nata\ORM\Query Augmented query.
 */
    public function formatTreeList(Query $query, array $options = []) {
        return $query->formatResults(function ($results) use ($options) {
            $options += [
                'keyPath' => $this->_getPrimaryKey(),
                'valuePath' => $this->_table->displayField(),
                'spacer' => '_'
            ];

            return $results
                ->listNested()
                ->printer($options['valuePath'], $options['keyPath'], $options['spacer']);
        });
    }

/**
 * Removes the current node from the tree, by positioning it as a new root
 * and re-parents all children up one level.
 *
 * Note that the node will not be deleted just moved away from its current position
 * without moving its children with it.
 *
 * @param \Nata\ORM\Entity $node The node to remove from the tree
 * @return \Nata\ORM\Entity|false the node after being removed from the tree or
 * false on error
 */
    public function removeFromTree(Entity $node) {
        return $this->_table->connection()->transactional(function () use ($node) {
            $this->_ensureFields($node);
            return $this->_removeFromTree($node);
        });
    }

/**
 * Helper function containing the actual code for removeFromTree
 *
 * @param \Nata\ORM\Entity $node The node to remove from the tree
 * @return \Nata\ORM\Entity|false the node after being removed from the tree or
 * false on error
 */
    protected function _removeFromTree($node) {
        $config = $this->config();
        $left = $node->get($config['left']);
        $right = $node->get($config['right']);
        $parent = $this->_getParent($node, $config['parent']);
        $node->set($config['parent'], null);

        if ($right - $left == 1) {
            return $this->_table->save($node);
        }

        $primary = $this->_getPrimaryKey();

        $this->_table->updateAll(
            [$config['parent'] => $parent],
            [$config['parent'] => $node->get($primary)]
        );

        $this->_sync(1, '-', 'BETWEEN ' . ($left + 1) . ' AND ' . ($right - 1));
        $this->_sync(2, '-', "> {$right}");

        $edge = $this->_getMax();

        $node->set($config['left'], $edge + 1);
        $node->set($config['right'], $edge + 2);

        $fields = [$config['parent'], $config['left'], $config['right']];

        $this->_table->updateAll($node->extract($fields), [$primary => $node->get($primary)]);

        foreach ($fields as $field) {
            $node->dirty($field, false);
        }

        return $node;
    }

/**
 * Returns the depth level of a node in the tree.
 *
 * @param int|string|\Nata\ORM\Entity $entity The entity or primary key get the level of.
 * @return int|bool Integer of the level or false if the node does not exist.
 */
    public function getLevel($entity) {
        $config = $this->config();

        $this->_ensureFields($entity);

        if ($entity === null) {
            return false;
        }

        $query = $this->_table->find('all')->andWhere([
            $config['left'] . ' <' => $entity[$config['left']],
            $config['right'] . ' >' => $entity[$config['right']]
        ]);

        return $this->_scope($query)->count();
    }

/**
 * Reorders the node without changing its parent.
 *
 * If the node is the first child, or is a top level node with no previous node
 * this method will return false
 *
 * @param \Nata\ORM\Entity $node The node to move
 * @param int|bool $number How many places to move the node, or true to move to first position
 * @throws \Nata\Datasource\Exception\RecordNotFoundException When node was not found
 * @return \Nata\ORM\Entity|bool $node The node after being moved or false on failure
 */
    public function moveUp(Entity $node, $number = 1) {
        if ($number < 1) {
            return false;
        }

        return $this->_table->connection()->transactional(function () use ($node, $number) {
            $this->_ensureFields($node);
            return $this->_moveUp($node, $number);
        });
    }

/**
 * Helper function used with the actual code for moveUp
 *
 * @param \Nata\ORM\Entity $node The node to move
 * @param int|bool $number How many places to move the node, or true to move to first position
 * @throws \RecordNotFoundException When node was not found
 * @return \Nata\ORM\Entity|bool $node The node after being moved or false on failure
 */
    protected function _moveUp($node, $number) {
        $config = $this->config();
        [$parent, $left, $right] = [$config['parent'], $config['left'], $config['right']];
        [$nodeParent, $nodeLeft, $nodeRight] = array_values($node->extract([$parent, $left, $right]));
        $targetNode = null;

        if ($number !== true) {
            $targetNode = $this->_scope($this->_table->find())
                ->select([$left, $right])
                ->andWhere(["$parent IS" => $nodeParent, "$right <" => $nodeLeft])
                ->order([$left => 'DESC'])
                ->offset($number - 1)
                ->limit(1)
                ->first();
        }

        if (!$targetNode) {
            $targetNode = $this->_scope($this->_table->find())
                ->select([$left, $right])
                ->andWhere(["$parent IS" => $nodeParent, "$right <" => $nodeLeft])
                ->order([$left => 'ASC'])
                ->limit(1)
                ->first();

            if (!$targetNode) {
                return $node;
            }
        }

        [$targetLeft] = array_values($targetNode->extract([$left, $right]));

        $edge = $this->_getMax();

        $leftBoundary = $targetLeft;
        $rightBoundary = $nodeLeft - 1;
        $nodeToEdge = $edge - $nodeLeft + 1;
        $shift = $nodeRight - $nodeLeft + 1;
        $nodeToHole = $edge - $leftBoundary + 1;

        $this->_sync($nodeToEdge, '+', "BETWEEN {$nodeLeft} AND {$nodeRight}");
        $this->_sync($shift, '+', "BETWEEN {$leftBoundary} AND {$rightBoundary}");
        $this->_sync($nodeToHole, '-', "> {$edge}");

        $node->set($left, $targetLeft);
        $node->set($right, $targetLeft + ($nodeRight - $nodeLeft));

        $node->dirty($left, false);
        $node->dirty($right, false);

        return $node;
    }

/**
 * Reorders the node without changing the parent.
 *
 * If the node is the last child, or is a top level node with no subsequent node
 * this method will return false
 *
 * @param \Nata\ORM\Entity $node The node to move
 * @param int|bool $number How many places to move the node or true to move to last position
 * @throws \Nata\Datasource\Exception\RecordNotFoundException When node was not found
 * @return \Nata\ORM\Entity|bool the entity after being moved or false on failure
 */
    public function moveDown(Entity $node, $number = 1) {
        if ($number < 1) {
            return false;
        }

        return $this->_table->connection()->transactional(function () use ($node, $number) {
            $this->_ensureFields($node);
            return $this->_moveDown($node, $number);
        });
    }

/**
 * Helper function used with the actual code for moveDown
 *
 * @param \Nata\ORM\Entity $node The node to move
 * @param int|bool $number How many places to move the node, or true to move to last position
 * @throws \Nata\Datasource\Exception\RecordNotFoundException When node was not found
 * @return \Nata\ORM\Entity|bool $node The node after being moved or false on failure
 */
    protected function _moveDown($node, $number) {
        $config = $this->config();
        list($parent, $left, $right) = [$config['parent'], $config['left'], $config['right']];
        list($nodeParent, $nodeLeft, $nodeRight) = array_values($node->extract([$parent, $left, $right]));
        $targetNode = null;

        if ($number !== true) {
            $targetNode = $this->_scope($this->_table->find())
                ->select([$left, $right])
                ->andWhere(["$parent IS" => $nodeParent, "$left >" => $nodeRight])
                ->order([$left => 'ASC'])
                ->offset($number - 1)
                ->limit(1)
                ->first();
        }

        if (!$targetNode) {
            $targetNode = $this->_scope($this->_table->find())
                ->select([$left, $right])
                ->andWhere(["{$parent} IS" => $nodeParent, "$left >" => $nodeRight])
                ->order([$left => 'DESC'])
                ->limit(1)
                ->first();

            if (!$targetNode) {
                return $node;
            }
        }

        list(, $targetRight) = array_values($targetNode->extract([$left, $right]));

        $edge = $this->_getMax();
        $leftBoundary = $nodeRight + 1;
        $rightBoundary = $targetRight;
        $nodeToEdge = $edge - $nodeLeft + 1;
        $shift = $nodeRight - $nodeLeft + 1;
        $nodeToHole = $edge - $rightBoundary + $shift;

        $this->_sync($nodeToEdge, '+', "BETWEEN {$nodeLeft} AND {$nodeRight}");
        $this->_sync($shift, '-', "BETWEEN {$leftBoundary} AND {$rightBoundary}");
        $this->_sync($nodeToHole, '-', "> {$edge}");

        $node->set($left, $targetRight - ($nodeRight - $nodeLeft));
        $node->set($right, $targetRight);

        $node->dirty($left, false);
        $node->dirty($right, false);

        return $node;
    }

/**
 * Returns a single node from the tree from its primary key
 *
 * @param mixed $id Record id.
 * @return \Nata\ORM\Entity
 * @throws \Nata\Datasource\Exception\RecordNotFoundException When node was not found
 */
    protected function _getNode($id) {
        $config = $this->config();
        [$parent, $left, $right] = [$config['parent'], $config['left'], $config['right']];
        $primaryKey = $this->_getPrimaryKey();
        $fields = [$parent, $left, $right];

        if ($config['level']) {
            $fields[] = $config['level'];
        }

        if ($config['scopeByField']) {
            $fields[] = $config['scopeByField'];
        }

        $node = $this->_scope($this->_table->query())
            ->select($fields)
            ->andWhere([$this->_table->aliasField($primaryKey) => $id])
            ->first();

        if ($node) {
            return $node;
        }

        if (!$config['existingNodeRequired']) {
            return $this->_table->newEntity([
                $config['left'] => -1000000000,
                $config['right'] => -10000000000
            ]);
        }

        // @todo
        // $this->_setDynamicScope($node);

        $scope = $config['scope'];
        if (is_array($scope)) {
            $scope = json_encode($scope);
        }

        throw new RecordNotFoundException(sprintf(
            'Node "%d" was not found in the tree. Scope: %s',
            $id,
            $scope ? $scope : 'disabled'
        ));
    }

/**
 * Recovers the lft and right column values out of the hierarchy defined by the
 * parent column.
 *
 * @return void
 */
    public function recover() {
        $this->_table->connection()->transactional(function () {
            $this->_recoverTree();
        });
    }

/**
 * Recursive method used to recover a single level of the tree
 *
 * @param int $counter The Last left column value that was assigned
 * @param mixed $parentId the parent id of the level to be recovered
 * @param int $level Node level
 * @return int The next value to use for the left column
 */
    protected function _recoverTree($counter = 0, $parentId = null, $level = -1) {
        $config = $this->config();
        [$parent, $left, $right] = [$config['parent'], $config['left'], $config['right']];

        if ($parentId === null) {
            $parentId = $config['rootParentId'];
        }

        $primaryKey = $this->_getPrimaryKey();
        $aliasedPrimaryKey = $this->_table->aliasField($primaryKey);

        $order = $config['recoverOrder'] ?: $aliasedPrimaryKey;

        $operator = $config['rootParentId'] === null ? ' IS' : '';

        $query = $this->_scope($this->_table->query())
            ->select([$aliasedPrimaryKey])
            ->andWhere([$this->_table->aliasField($parent) . $operator => $parentId])
            ->order($order)
            ->hydrate(false);

        if ($config['scopeByField']) {
            $query->addSelect([$config['scopeByField']]);
        }

        $leftCounter = $counter;

        $nextLevel = $level + 1;
        foreach ($query as $row) {
            // @todo
            // $this->_setDynamicScope($row);

            $counter++;
            $counter = $this->_recoverTree($counter, $row[$primaryKey], $nextLevel);
        }

        if ($parentId === $config['rootParentId']) {
            return $counter;
        }

        $fields = [$left => $leftCounter, $right => $counter + 1];

        if ($config['level']) {
            $fields[$config['level']] = $level;
        }

        $this->_table->updateAll(
            $fields,
            [$primaryKey => $parentId]
        );

        return $counter + 1;
    }

/**
 * Returns the maximum index value in the table.
 *
 * @return int
 */
    protected function _getMax() {
        $config = $this->config();
        $field = $config['right'];

        $edge = $this->_scope($this->_table->find())
            ->select([$field])
            ->order([$field => 'DESC'])
            ->first();

        if (empty($edge->{$field})) {
            return 0;
        }

        return $edge->{$field};
    }

/**
 * Auxiliary function used to automatically alter the value of both the left and
 * right columns by a certain amount that match the passed conditions
 *
 * @param int $shift the value to use for operating the left and right columns
 * @param string $dir The operator to use for shifting the value (+/-)
 * @param string $conditions a SQL snipped to be used for comparing left or right
 * against it.
 * @param bool $mark whether to mark the updated values so that they can not be
 * modified by future calls to this function.
 * @return void
 */
    protected function _sync($shift, $dir, $conditions, $mark = false) {
        $config = $this->config();

        foreach ([$config['left'], $config['right']] as $field) {
            $query = $this->_scope($this->_table->query());

            $mark = $mark ? '*-1' : '';

            $template = sprintf('%s = (%s %s %s)%s', $field, $field, $dir, $shift, $mark);

            $query->update()
                ->add('set', $template)
                ->andWhere("{$field} {$conditions}")
                ->execute();
        }

    }

/**
 * Returns the the parent primary key value.
 *
 * @param int|string|\Nata\ORM\Entity $entity The entity to extract the parent from.
 * @return int|string Parent primary key
 */
    protected function _getParent($entity, $parentProp) {
        $parent = $entity->get($parentProp);
        if ($parent instanceof Entity) {
            $parent = $entity->get($this->_getPrimaryKey());
        }
        return $parent;
    }

/**
 * Alters the passed query so that it only returns scoped records as defined
 * in the tree configuration.
 *
 * @param \Nata\ORM\Query $query the Query to modify
 * @return \Nata\ORM\Query
 */
    protected function _scope($query) {
        $config = $this->config();

        if (empty($config['scope']) && $config['scopeByField']) {
            throw new RuntimeException(sprintf("Missing dynamic scope for %s"));
        }

        if (is_array($config['scope'])) {
            return $query->andWhere($config['scope']);
        } elseif (is_callable($config['scope'])) {
            return $config['scope']($query);
        }

        return $query;
    }

/**
 * Alters the passed query so that it only returns scoped records as defined
 * in the tree configuration.
 *
 * @param \Nata\ORM\Query $query the Query to modify
 * @return \Nata\ORM\Query
 */
    protected function _setDynamicScope($node) {
        $config = $this->config();
        $field = $config['scopeByField'];
        if (!is_string($field)) {
            return null;
        }

        if (!isset($node[$field])) {
            throw new InvalidArgumentException(sprintf('Missing property value for scope field %s.', $field));
        }

        $value = $node[$field];
        $this->config('scope', [$field => $value]);
    }

/**
 * Ensures that the provided entity contains non-empty values for the left and
 * right fields
 *
 * @param \Nata\ORM\Entity $entity The entity to ensure fields for
 * @return void
 */
    protected function _ensureFields($entity) {
        $config = $this->config();
        $fields = [$config['left'], $config['right']];
        $values = array_filter($entity->extract($fields));

        if (count($values) === count($fields)) {
            return;
        }

        $fresh = $this->_table->get($entity->get($this->_getPrimaryKey()), $fields);
        $entity->set($fresh->extract($fields), ['guard' => false]);

        foreach ($fields as $field) {
            $entity->dirty($field, false);
        }
    }

/**
 * Returns a single string value representing the primary key of the attached table.
 *
 * @return string
 */
    protected function _getPrimaryKey() {
        if ($primaryKey = $this->config('primaryKey')) {
            $this->_primaryKey = $primaryKey;
        }
        if (!$this->_primaryKey) {
            [$this->_primaryKey] = (array)$this->_table->primaryKey();
        }
        return $this->_primaryKey;
    }

}
