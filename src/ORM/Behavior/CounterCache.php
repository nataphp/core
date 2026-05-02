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
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://nataphp.com NataPHP Project
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\ORM\Behavior;

use ArrayObject;
use Nata\ORM\Entity;
use Nata\Event\Event;
use Nata\ORM\Association;
use Nata\ORM\Behavior;
use Closure;
use Error;

/**
 * CounterCache behavior
 *
 * Enables models to cache the amount of connections in a given relation.
 *
 * Examples with Post model belonging to User model
 *
 * Regular counter cache
 * ```
 * [
 *     'Users' => [
 *         'post_count'
 *     ]
 * ]
 * ```
 *
 * Counter cache with scope
 * ```
 * [
 *     'Users' => [
 *         'posts_published' => [
 *             'conditions' => [
 *                 'published' => true
 *             ]
 *         ]
 *     ]
 * ]
 * ```
 *
 * Counter cache using custom find
 * ```
 * [
 *     'Users' => [
 *         'posts_published' => [
 *             'finder' => 'published' // Will be using findPublished()
 *         ]
 *     ]
 * ]
 * ```
 *
 * Counter cache using lambda function returning the count
 * This is equivalent to example #2
 *
 * ```
 * [
 *     'Users' => [
 *         'posts_published' => function (Event $event, Entity $entity, Table $table) {
 *             $query = $table->find('all')->where([
 *                 'published' => true,
 *                 'user_id' => $entity->get('user_id')
 *             ]);
 *             return $query->count();
 *          }
 *     ]
 * ]
 * ```
 *
 * When using a lambda function you can return `false` to disable updating the counter value
 * for the current operation.
 *
 * Ignore updating the field if it is dirty
 * ```
 * [
 *     'Users' => [
 *         'posts_published' => [
 *             'ignoreDirty' => true
 *         ]
 *     ]
 * ]
 * ```
 *
 * You can disable counter updates entirely by sending the `ignoreCounterCache` option
 * to your save operation:
 *
 * ```
 * $this->Articles->save($article, ['ignoreCounterCache' => true]);
 * ```
 */
class CounterCache extends Behavior {

/**
 * Store the fields which should be ignored
 *
 * @var array
 */
    protected $_ignoreDirty = [];


/**
 * beforeSave callback.
 *
 * Check if a field, which should be ignored, is dirty
 *
 * @param \Nata\Event\Event $event The beforeSave event that was fired
 * @param \Nata\ORM\Entity $entity The entity that is going to be saved
 * @param \ArrayObject $options The options for the query
 * @return void
 */
    public function beforeSave(Event $event, Entity $entity, array $options) {
        if (isset($options['ignoreCounterCache']) && $options['ignoreCounterCache'] === true) {
            return;
        }

        $associations = $this->_table->associations();
        foreach ($this->_config as $assoc => $settings) {
            $assoc = $associations->get($assoc);
            foreach ($settings as $field => $config) {
                if (is_int($field)) {
                    continue;
                }

                $registryAlias = $assoc->target()->registryAlias();
                $entityAlias = $assoc->propertyName();

                if (
                    !is_callable($config) &&
                    isset($config['ignoreDirty']) &&
                    $config['ignoreDirty'] === true &&
                    $entity->$entityAlias->isDirty($field)
                ) {
                    $this->_ignoreDirty[$registryAlias][$field] = true;
                }
            }
        }
    }

/**
 * afterSave callback.
 *
 * Makes sure to update counter cache when a new record is created or updated.
 *
 * @param \Nata\Event\Event $event The afterSave event that was fired.
 * @param \Nata\ORM\Entity $entity The entity that was saved.
 * @param \ArrayObject $options The options for the query
 * @return void
 */
    public function afterSave(Event $event, Entity $entity, array $options): void {
        if (isset($options['ignoreCounterCache']) && $options['ignoreCounterCache'] === true) {
            return;
        }

        $this->_processAssociations($event, $entity);
        $this->_ignoreDirty = [];
    }

/**
 * afterDelete callback.
 *
 * Makes sure to update counter cache when a record is deleted.
 *
 * @param \Nata\Event\Event $event The afterDelete event that was fired.
 * @param \Nata\ORM\Entity $entity The entity that was deleted.
 * @param \ArrayObject $options The options for the query
 * @return void
 */
    public function afterDelete(Event $event, Entity $entity, array $options) {
        if (isset($options['ignoreCounterCache']) && $options['ignoreCounterCache'] === true) {
            return;
        }

        $this->_processAssociations($event, $entity);
    }

/**
 * Iterate all associations and update counter caches.
 *
 * @param \Nata\Event\Event $event Event instance.
 * @param \Nata\ORM\Entity $entity Entity.
 * @return void
 */
    protected function _processAssociations(Event $event, Entity $entity): void {
        $associations = $this->_table->associations();
        foreach ($this->_config as $assoc => $settings) {
            $assoc = $associations->get($assoc);
            $this->_processAssociation($event, $entity, $assoc, $settings);
        }
    }

/**
 * Updates counter cache for a single association.
 *
 * @param \Nata\Event\Event $event Event instance.
 * @param \Nata\ORM\Entity $entity Entity
 * @param \Nata\ORM\Association $assoc The association object
 * @param array $settings The settings for for counter cache for this association
 * @return void
 * @throws \RuntimeException If invalid callable is passed.
 */
    protected function _processAssociation(Event $event, Entity $entity, Association $assoc, array $settings): void {
        if ($assoc->polymorphic()) {
            throw new Error("CounterCache behavior doesn't support polymorphic associations.");
        }

        $foreignKeys = (array)$assoc->foreignKey();
        $countConditions = $entity->extract($foreignKeys);

        foreach ($countConditions as $field => $value) {
            if ($value === null) {
                $countConditions[$field . ' IS'] = $value;
                unset($countConditions[$field]);
            }
        }

        $primaryKeys = (array)$assoc->bindingKey();
        $updateConditions = array_combine($primaryKeys, $countConditions);

        $countOriginalConditions = $entity->extractOriginal($foreignKeys, true);
        if ($countOriginalConditions !== []) {
            $updateOriginalConditions = array_combine($primaryKeys, $countOriginalConditions);
        }

        foreach ($settings as $field => $config) {
            if (is_int($field)) {
                $field = $config;
                $config = [];
            }

            $registryAlias = $assoc->target()->registryAlias();
            if (isset($this->_ignoreDirty[$registryAlias][$field]) && $this->_ignoreDirty[$registryAlias][$field] === true) {
                continue;
            }

            if ($this->_shouldUpdateCount($updateConditions)) {
                if ($config instanceof Closure) {
                    $count = $config($event, $entity, $this->_table, false);
                } else {
                    $count = $this->_getCount($config, $countConditions);
                }
                if ($count !== false) {
                    $assoc->target()->updateAll([$field => $count], $updateConditions);
                }
            }

            if (isset($updateOriginalConditions) && $this->_shouldUpdateCount($updateOriginalConditions)) {
                if ($config instanceof Closure) {
                    $count = $config($event, $entity, $this->_table, true);
                } else {
                    $count = $this->_getCount($config, $countOriginalConditions);
                }
                if ($count !== false) {
                    $assoc->target()->updateAll([$field => $count], $updateOriginalConditions);
                }
            }
        }
    }

/**
 * Checks if the count should be updated given a set of conditions.
 *
 * @param array $conditions Conditions to update count.
 * @return bool True if the count update should happen, false otherwise.
 */
    protected function _shouldUpdateCount(array $conditions) {
        return !empty(array_filter($conditions, function ($value) {
            return $value !== null;
        }));
    }

/**
 * Fetches and returns the count for a single field in an association
 *
 * @param array $config The counter cache configuration for a single field
 * @param array $conditions Additional conditions given to the query
 * @return int The number of relations matching the given config and conditions
 */
    protected function _getCount(array $config, array $conditions): int {
        $finder = 'all';
        if (!empty($config['finder'])) {
            $finder = $config['finder'];
            unset($config['finder']);
        }

        if (!isset($config['conditions'])) {
            $config['conditions'] = [];
        }
        $config['conditions'] = array_merge($conditions, $config['conditions']);
        $query = $this->_table->find($finder, $config);

        return $query->count();
    }

}
