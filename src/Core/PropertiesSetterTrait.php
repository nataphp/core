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

use Nata\Utility\Hash;

/**
 * Class properties setter.
 */
trait PropertiesSetterTrait {

/**
 * Allows setting of multiple properties of the object in a single line of code. Will only set
 * properties that are part of a class declaration.
 *
 * @param array $properties An associative array containing properties and corresponding values.
 * @param bool $overwrite overwrite
 */
    protected function _set(array $properties = [], bool $overwrite = false): void {
        if (!$properties) {
            return;
        }

        $vars = get_object_vars($this);

        foreach ($properties as $key => $val) {
            if (array_key_exists($key, $vars) && ($overwrite === true || !$this->{$key})) {
                $this->{$key} = $val;
            }

            $altKey = '_' . $key;
            if (array_key_exists($altKey, $vars) && ($overwrite === true || !$this->{$altKey})) {
                $this->{$altKey} = $val;
            }
        }
    }

/**
 * Merges this objects $property with the property in $class' definition.
 * This classes value for the property will be merged on top of $class'
 *
 * This provides some of the DRY magic CakePHP provides. If you want to shut it off, redefine
 * this method as an empty function.
 *
 * @param array $properties The name of the properties to merge.
 * @param string $class The class to merge the property with.
 * @param boolean $normalize Set to true to run the properties through Hash::normalize() before merging.
 */
    protected function _mergeVars($properties, $class, bool $normalize = true): void {
        $classProperties = get_class_vars($class);

        foreach ($properties as $var) {
            if (
                isset($classProperties[$var]) &&
                !empty($classProperties[$var]) &&
                is_array($this->{$var}) &&
                $this->{$var} != $classProperties[$var]
            ) {
                if ($normalize) {
                    $classProperties[$var] = Hash::normalize($classProperties[$var]);
                    $this->{$var} = Hash::normalize($this->{$var});
                }
                $this->{$var} = Hash::merge($classProperties[$var], $this->{$var});
            }
        }

    }

}
