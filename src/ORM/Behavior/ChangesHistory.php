<?php
/**
 * NataPHP Framework.
 *
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * Copyright 2015 - 2019, Cake Development Corporation (http://cakedc.com)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * This behavior was inspired based on the work of Cake Development Corporation.
 *
 * @copyright     Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @link          http://nataphp.com NataPHP Project
 * @link          https://github.com/CakeDC/Enum
 * @since         NataPHP 1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\ORM\Behavior;

use Nata\Core\ConfigAwareTrait;
use Nata\Event\Event;
use Nata\ORM\Behavior;
use Nata\ORM\Entity;

/**
 * Changes history behavior.
 * This behavior is used to log changes in the tables that have
 * this behavior attached.
 */
class ChangesHistory extends Behavior {

    use ConfigAwareTrait;

/**
 * Default configuration.
 *
 * @var array
 */
    protected $_defaultConfig = [
        'table' => 'nata_changes_history',
        'implementedEvents' => [
            'Table.afterSave' => 'afterSave'
        ],
        'trackAssociations' => true, // Enable/disable association tracking globally
        'trackedAssociations' => [], // List of specific associations to track (empty means track all)
        'ignoredAssociations' => [], // List of associations to ignore
        'trackedFields' => [], // List of specific fields to track (empty means track all)
        'ignoredFields' => ['updated'] // List of fields to ignore
    ];


/**
 * Initialize hook.
 *
 * If events are specified - do *not* merge them with existing events,
 * overwrite the events to listen on
 *
 * @param array $config The config for this behavior.
 * @return void
 */
    public function initialize($config) {
        parent::initialize($config);
    }

/**
 * After save event.
 *
 * @param Event $event The event object.
 * @param Entity $entity The entity object.
 * @param array $options The options array.
 * @return void
 */
    public function afterSave(Event $event, Entity $entity, array $options) {
        $table = $event->getSubject();
        $changes = [];

        // Track property changes
        if ($entity->cleaned()) {
            foreach ($entity->cleaned() as $field) {
                if ($this->shouldTrackField($field) && $entity->hasChanged($field)) {
                    $changes[] = [
                        'field_name' => $field,
                        'old_value' => $entity->getOriginal($field),
                        'new_value' => $entity->get($field),
                        'foreign_model' => $entity->source(),
                        'foreign_id' => $entity->id,
                        'changed_by_model' => 'User', // You may want to customize this
                        'changed_by_id' => 1, // You may want to customize this
                        'changed_at' => date('Y-m-d H:i:s')
                    ];
                }
            }
        }

        // Track association changes if enabled
        if ($this->config('trackAssociations')) {
            foreach ($table->associations() as $association) {
                $associationName = $association->getName();
                // Skip if this association should be ignored
                if (!$this->shouldTrackAssociation($associationName)) {
                    continue;
                }

                /*
                $property = $association->getProperty();
                if ($entity->has($property)) {
                    $associatedData = $entity->get($property);

                    if (is_array($associatedData)) {
                        foreach ($associatedData as $associatedEntity) {
                            if ($associatedEntity->cleaned()) {
                                foreach ($associatedEntity->cleaned() as $field) {
                                    if ($this->shouldTrackField($field) && $associatedEntity->hasChanged($field)) {
                                        $changes[] = [
                                            'field_name' => $associationName . '.' . $field,
                                            'old_value' => $associatedEntity->getOriginal($field),
                                            'new_value' => $associatedEntity->get($field),
                                            'foreign_model' => $entity->source(),
                                            'foreign_id' => $entity->id,
                                            'changed_by_model' => 'User', // You may want to customize this
                                            'changed_by_id' => 1, // You may want to customize this
                                            'changed_at' => date('Y-m-d H:i:s')
                                        ];
                                    }
                                }
                            }
                        }
                    } elseif ($associatedData instanceof Entity && $associatedData->isDirty()) {
                        foreach ($associatedData->cleaned() as $field) {
                            if ($this->shouldTrackField($field) && $associatedData->hasChanged($field)) {
                                $changes[] = [
                                    'field_name' => $associationName . '.' . $field,
                                    'old_value' => $associatedData->getOriginal($field),
                                    'new_value' => $associatedData->get($field),
                                    'foreign_model' => $entity->source(),
                                    'foreign_id' => $entity->id,
                                    'changed_by_model' => 'User', // You may want to customize this
                                    'changed_by_id' => 1, // You may want to customize this
                                    'changed_at' => date('Y-m-d H:i:s')
                                ];
                            }
                        }
                    }
                }
                */
            }
        }

        if (empty($changes)) {
            return;
        }

        $table->connection()->insert($this->config('table'), $changes);


        // Save changes to history table
        /*
        if (!empty($changes)) {
            $historyTable = $table->connection()->schema()->describe($this->config('table'));
            $table->connection()->insert($historyTable->name(), $changes);
        }
        */
    }

/**
 * Check if a field should be tracked based on configuration
 *
 * @param string $field The field name to check
 * @return bool
 */
    protected function shouldTrackField($field) {
        $trackedFields = $this->config('trackedFields');
        $ignoredFields = $this->config('ignoredFields');

        // If specific fields are listed, only track those
        if (!empty($trackedFields)) {
            return in_array($field, $trackedFields);
        }

        // Otherwise, track all fields except ignored ones
        return !in_array($field, $ignoredFields);
    }

/**
 * Check if an association should be tracked based on configuration
 *
 * @param string $associationName The association name to check
 * @return bool
 */
    protected function shouldTrackAssociation($associationName) {
        $trackedAssociations = $this->config('trackedAssociations');
        $ignoredAssociations = $this->config('ignoredAssociations');

        // If specific associations are listed, only track those
        if (!empty($trackedAssociations)) {
            return in_array($associationName, $trackedAssociations);
        }

        // Otherwise, track all associations except ignored ones
        return !in_array($associationName, $ignoredAssociations);
    }

}

