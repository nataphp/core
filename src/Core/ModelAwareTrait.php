<?php
/**
 * NataPHP Framework.
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

namespace Nata\Core;

use Nata\ORM\Table;
use Nata\ORM\TableRegistry;

/**
 * Provides ORM model loading capabilities to any class without requiring
 * inheritance from NataObject.
 */
trait ModelAwareTrait {

/**
 * Loads and returns a model instance without assigning it to a property.
 *
 * Short-hand for TableRegistry::get(). Use when you need a one-off reference
 * to a model and do not want it stored on $this.
 *
 * @param string $modelClass Name of the model class to load, optionally plugin-prefixed (e.g. 'Shop.Orders').
 * @return \Nata\ORM\Table The model instance.
 */
    public function loadModel(string $modelClass): Table {
        [$plugin, $modelClass] = pluginSplit($modelClass, true);
        return TableRegistry::get($plugin . $modelClass);
    }

/**
 * Loads a model and assigns it to a property on the current instance.
 *
 * If the property is already set it is returned immediately, making repeated
 * calls safe. The property name defaults to the unprefixed class name.
 *
 * @param string $modelClass Name of the model class to load, optionally plugin-prefixed.
 * @param string|null $propertyName Property name to assign the instance to. Defaults to the class portion of $modelClass.
 * @return \Nata\ORM\Table The model instance.
 */
    public function loadModelIn(string $modelClass, ?string $propertyName = null): Table {
        if ($propertyName === null) {
            [, $propertyName] = pluginSplit($modelClass, true);
        }

        if (isset($this->{$propertyName})) {
            return $this->{$propertyName};
        }

        $this->{$propertyName} = TableRegistry::get($modelClass);

        return $this->{$propertyName};
    }

/**
 * Alias for loadModelIn kept for backwards compatibility.
 *
 * @deprecated Use loadModelIn() instead.
 * @param string $modelClass Name of the model class to load, optionally plugin-prefixed.
 * @param string|null $propertyName Property name to assign the instance to.
 * @return \Nata\ORM\Table The model instance.
 */
    public function loadInModel(string $modelClass, ?string $propertyName = null): Table {
        return $this->loadModelIn($modelClass, $propertyName);
    }

}
