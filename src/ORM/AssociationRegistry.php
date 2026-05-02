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
use Nata\ORM\Table;
use Nata\ORM\Association;
use Nata\Collection\Collection;
use MissingAssociationException;
use Nata\ORM\Association\BelongsToMany;
use Nata\ORM\Association\HasMany;
use Nata\Utility\Inflector;

/**
 * Table association instances registry.
 */
class AssociationRegistry {

/**
 * Array of associations instances.
 *
 * @var array
 */
    protected $_associations = [];

/**
 * Array of associations instances.
 *
 * @var array
 */
    protected $_map = [];

/**
 * Source repository alias.
 *
 * @var string
 */
    protected $_sourceAlias;

/**
 * Table repository instance.
 *
 * @var Table
 */
    protected $_repository;

/**
 * Normalized associated aliases cache.
 *
 * This is used as cache to normalized \Nata\ORM\Table::save() options
 * 'associated' list of aliases.
 *
 * @var array
 */
    protected $_normalizedAssociations;


/**
 * Constructor.
 *
 * @param \Nata\ORM\Table $repository Repository instance
 */
    public function __construct(Table $repository) {
        $this->_repository = $repository;
        $this->_sourceAlias = $repository->registryAlias();
    }

/**
 * Add Association instance.
 *
 * @param string $alias Association alias
 * @param \Nata\ORM\Association $association Association instance
 * @return \Nata\ORM\Association
 */
    public function add($alias, Association $association) {
        [$p, $alias] = pluginSplit($alias);
        $associationName = App::classShortName($association);

        $this->_map[$associationName][] = $alias;

        return $this->_associations[strtolower($alias)] = $association;
    }

/**
 * Get Association instance.
 *
 * @param string $alias Name of the table to check
 * @return \Nata\ORM\Association|array
 * @throws \MissingAssociationException
 */
    public function get($alias = null) {
        [$p, $alias] = pluginSplit($alias);

        if (func_num_args() === 0) {
            return array_keys($this->_associations);
        }

        $_alias = strtolower($alias);
        if (isset($this->_associations[$_alias])) {
            return $this->_associations[$_alias];
        }

        throw new MissingAssociationException(sprintf(
            'Model "%s" is not associated with "%s".',
            $this->_sourceAlias,
            $alias
        ));

    }

/**
 * Save associated properties in given entity before given entity is saved.
 *
 * @param \Nata\ORM\Entity $entity Entity
 * @param array $options Save options
 * @return void
 */
    public function beforeSave($entity, $options) {
        return $this->_save(__FUNCTION__, $entity, $options);
    }

/**
 * Save associated properties in given entity after given entity is saved.
 *
 * @param \Nata\ORM\Entity $entity Entity
 * @param array $options Save options
 * @return void
 */
    public function afterSave($entity, $options) {
        return $this->_save(__FUNCTION__, $entity, $options);
    }

/**
 * Save associated properties in given entity.
 *
 * @param string $method Save method
 * @param \Nata\ORM\Entity $entity Entity
 * @param array $options Save options
 * @return void
 */
    protected function _save($method, $entity, $options) {
        $associated = $options['associated'];
        if ($associated === false) {
            return null;
        }

        $atomic = $options['atomic'];
        $associated = $this->normalizeAssociations($associated);
        foreach ($this->_associations as $alias => $association) {
            // Check if association is present
            if (!method_exists($association, $method)
                || (is_array($associated) && !in_array($alias, array_keys($associated)))) {
                continue;
            }

            $dirtyProperties = [];

            // Check if associated entity with default property name is dirty
            $propertyName = $association->propertyName();
            if ($entity->isDirty($propertyName) === true) {
                $dirtyProperties[] = $propertyName;
            }
            // On these association types, check also the existence of the singular property name if
            if ($association instanceof HasMany || $association instanceof BelongsToMany) {
                $singularPropertyName = $association->singularPropertyName();
                // Property name it's already set and it's dirty, ignore the singular...
                if ($entity->isDirty($singularPropertyName) === true && !$dirtyProperties) {
                    $dirtyProperties[] = $singularPropertyName;
                }
            }

            foreach ($dirtyProperties as $dirtyProperty) {
                if (is_array($associated) && isset($associated[$alias])) {
                    $options = $associated[$alias];
                }

                $options['__parent'] = false;
                $options += [
                    'atomic' => $atomic
                ];

                $data = $entity->get($dirtyProperty);
                if (is_object($data) && !($data instanceof Collection) && !($data instanceof Entity)) {
                    continue;
                }

                $data = $association->{$method}($entity, $data, $options);

                // This should be removed in the future, association class should be the only
                // responsible setting the entities into source entity.
                //
                // Although this will mean that the association class will need to have the propertyName
                // in use, I don't think there's a problem? Is this current solution more DRY?
                $entity->set($dirtyProperty, $data);

                if (!$errors = $association->consumeErrors()) {
                    continue;
                }

                $entity->errors($dirtyProperty, $errors);
            }

        }
        return true;
    }

/**
 * Normalize associations.
 *
 * @param string|array $associated Array of associations to normalize
 * @return array Normalized associated save options
 */
    public function normalizeAssociations($associated) {
        $normalized = [];
        $default = ['associated' => false];

        $key = md5($this->_sourceAlias . json_encode($associated));
        if (isset($this->_normalizedAssociations[$key])) {
            return $this->_normalizedAssociations[$key];
        }

        if ($associated === true) {
            $associated = $this->get();
        } elseif (is_array($associated) && (in_array('*', $associated) || isset($associated['*']))) {
            unset($associated['*']);
            $associated = array_merge($associated, $this->get());
        }

        foreach ((array)$associated as $alias => $options) {
            if (!is_string($alias) && !is_array($options) && !is_string($options)) {
                continue;
            }

            if (is_int($alias)) {
                $alias = $options;
                $options = $default;
            }

            if ($alias === '*') {
                continue;
            }

            if (!isset($options['associated'])) {
                $options = ['associated' => $options];
            }

            // This is for dot separated aliases
            // (e.g. ['associated' => ['Comments.Author']])
            [$alias, $nestedAssoc] = splitter($alias, '.');
            if ($nestedAssoc) {
                $options = ['associated' => [$nestedAssoc => $options]];
            }

            $alias = strtolower($alias);

            if (isset($normalized[$alias])) {
                $options['associated'] = $this->_mergeAssociated($options['associated'], $normalized[$alias]['associated']);
            }

            $normalized[$alias] = $options;
        }

        return $this->_normalizedAssociations[$key] = $normalized;
    }

/**
 * Normalize associations.
 *
 * @param Entity $entity Entity to check
 * @param string|array $associated Array of associations to normalize
 * @return bool
 */
    public function hasDirtyProperties(Entity $entity, array $options = []): bool {
        $associated = $options['associated'] ?? false;
        if ($associated === false) {
            return false;
        }

        $associated = $this->normalizeAssociations($associated);
        $dirtyProperties = [];
        foreach ($this->_associations as $alias => $association) {
            // Check if associated entity with default property name is dirty
            $propertyName = $association->propertyName();
            if ($entity->isDirty($propertyName) === true) {
                $dirtyProperties[] = $propertyName;
            }
            // On these association types, check also the existence of the singular property name if
            if ($association instanceof HasMany || $association instanceof BelongsToMany) {
                $singularPropertyName = $association->singularPropertyName();
                // Property name it's already set and it's dirty, ignore the singular...
                if ($entity->isDirty($singularPropertyName) === true && !$dirtyProperties) {
                    $dirtyProperties[] = $singularPropertyName;
                }
            }

            if (!empty($dirtyProperties)) {
                return true;
            }

        }
        return false;
    }

/**
 * Merge associated data into existing associated data.
 * If associated data to merge is bool 'false', it will return the
 *
 * @param array|bool $newAssociatedData Associated data to merge
 * @param array|bool $associated Existing associated aliases
 * @return array|bool Associated data
 */
    protected function _mergeAssociated($newAssociated, $associated) {
        if (empty($newAssociated) && empty($associated)) {
            return false;
        }

        if (empty($newAssociated)) {
            return $associated;
        }

        if (empty($associated)) {
            return $newAssociated;
        }

        return array_merge($associated, (array)$newAssociated);
    }

/**
 * Get type of association for model alias.
 *
 * @param string $alias Model alias
 * @return bool
 */
    public function type($alias) {
        $alias = strtolower($alias);

        if (!$this->has($alias)) {
            return;
        }

        return App::classShortName($this->_associations[strtolower($alias)]);
    }

/**
 * Check if current table's instance has a association with
 * given table name.
 *
 * @param string $alias Name of the table to check
 * @return bool
 */
    public function has($alias) {
        return isset($this->_associations[strtolower($alias)]);
    }

/**
 * Remove association.
 *
 * @param string $alias Association alias
 * @return void
 */
    public function remove($alias) {
        unset($this->_associations[strtolower($alias)]);
    }

/**
 * List associations where current repository is associated with.
 *
 * @todo If polymorphic associations are found, search in the records for references
 * to the model being checked for missing associations.
 *
 * @return array
 */
    public function getMissing() {
        $setAssociations = $this->_listSourceSetAssociations($this->_repository);

        [$associations, $throughList] = $this->_loadAssociations();

        $usedAliases = [];
        foreach ($associations as $association) {
            if (in_array($association->source()->registryAlias(), $throughList)) {
                continue;
            }

            $columnName = $association->getType() === 'BelongsToMany'
                ? $association->targetForeignKey() : $association->foreignKey();

            $alias = $association->source()->table();
            if ($association->getType() === 'BelongsToMany') {
                $alias = $association->junction()->table();
            }

            $key = $alias . ':' . $columnName;
            if (in_array($key, $setAssociations) || isset($referencedKey[$key])) {
                continue;
            }

            $referencedKey[$key] = $key;

            $missingAssociation = [];
            if ($association->getType() === 'BelongsToMany') {
                $missingAssociation['joinTable'] = Inflector::camelize($association->junction()->table());
                if ($association->through()) {
                    $missingAssociation['through'] = $association->through();
                }
            }

            $usedAliases[] = $missingAssociation['suggestedAlias'] = $this->_getSuggestedAlias($association, $usedAliases);

            $missingAssociation += [
                'className' => $association->source()->registryAlias(),
                'foreignAssociation' => $association->polymorphic(),
                'foreignModel' => $association->foreignModel(),
                'foreignKey' => $columnName,
                'through' => ''
            ];

            $type = $this->_getSuggestedType($association);
            $missingAssociations[$type][$key] = $missingAssociation;
            //$missingAssociations[$type] = array_values($missingAssociations[$type]);
        }

        return $missingAssociations;
    }

/**
 * Check missing associations from tables that references.
 *
 * @return array
 */
    protected function _loadAssociations() {
        $tableObjects = [
            'App' => App::objects('Model/Table', null, false)
        ];
        foreach (App::objects('Plugin', null, false) as $plugin) {
            $tableObjects[$plugin] = App::objects($plugin . '.Model/Table', null, false);
        }

        $modelName = $this->_repository->registryAlias();

        $associationsList = [];
        $throughList = [];
        foreach ($tableObjects as $path => $tables) {
            foreach ($tables as $alias) {
                if ($path !== 'App') {
                    $alias = $path . '.' . $alias;
                }

                $associatedTable = TableRegistry::get($alias);

                $associations = $associatedTable->associations()->get();
                foreach ($associations as $association) {
                    $association = $associatedTable->associations()->get($association);
                    if (!$association->className() || $association->className() !== $modelName) {
                        continue;
                    }

                    if ($association->getType() === 'BelongsToMany' && $association->through()) {
                        $throughList[] = $association->through();
                    }

                    $associationsList[] = $association;
                }

            }

        }

        return [$associationsList, $throughList];
    }

/**
 * Check missing associations from tables that references.
 *
 * @return array
 */
    protected function _getSuggestedAlias($association, $usedAliases) {
        $name = Inflector::camelize($association->source()->table());
        if ($association->getType() === 'BelongsToMany') {
            $name = Inflector::camelize($association->junction()->table());
        }

        $associationName = $association->getName();
        if (in_array($name, $usedAliases) && !str_contains($name, $associationName)) {
            $name .= $association->getName();
        }

        return $name;
    }

/**
 * Check missing associations from tables that references.
 *
 * @return string
 */
    protected function _getSuggestedType($association) {
        $type = '';

        switch ($association->getType()) {
            case 'HasOne':
            case 'HasMany':
                $type = 'BelongsTo';
                break;
            case 'BelongsTo':
                $type = 'HasMany';
                break;
            case 'BelongsToMany':
                $type = 'BelongsToMany';
                break;
        }

        return $type;
    }

/**
 * Check missing associations from tables that references.
 *
 * @return array
 */
    protected function _listSourceSetAssociations($table) {
        $keys = [];
        $associations = $table->associations()->get();
        foreach ($associations as $association) {
            $assoc = $table->associations()->get($association);

            $key = $assoc->target()->table();
            if ($assoc->getType() === 'BelongsToMany') {
                $key = $assoc->junction()->table();
            }

            $columnName = $assoc->foreignKey();
            $key .= ':' . $columnName;

            $keys[$key] = $key;
        }
        return array_values($keys);
    }

}
