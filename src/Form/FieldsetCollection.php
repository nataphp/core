<?php
/**
 * NataPHP Framework
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
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

namespace Nata\Form;

use Nata\Form\Form;
use Nata\Form\Fieldset;
use FormException;

/**
 * Form's collection of fieldset's instances.
 */
class FieldsetCollection extends BaseCollection {

/**
 * Element's default config.
 *
 * @var array
 */
    protected $_defaultConfig = array(
        'fieldset' => null,
        'title' => null,
        'description' => null,
        'template' => null,
        'elements' => array(),
        'groups' => array()
    );

/**
 * Load fieldset instance.
 *
 * @param string|array $fieldset Fieldset name or array with config
 * @param array $config Fieldset configuration
 * @return \Nata\Form\Fieldset
 */
    public function load($fieldset, array $config = array()) {
        $config += array(
            'index' => $this->count() + 1
        ) + $this->_defaultConfig();

        if (is_array($fieldset)) {
            $config = $fieldset + $config;
            $fieldset = $config['fieldset'];
        } else {
            $config['fieldset'] = $fieldset;
        }
        if (empty($config['fieldset'])) {
            throw new FormException(sprintf('Missing name of fieldset in "%s"', $this->_form->className()));
        }

        $config['data'] = new DataManager([
            'model' => $this->_dataManager->model(),
            'request' => $this->_dataManager->request()
        ]);

        $fieldset = new Fieldset($this->_form, $config);
        return $this->_loaded[$config['fieldset']] = $fieldset;
    }

/**
 * Check if instance data is valid.
 *
 * @return bool True if valid, false otherwise
 */
    public function isValid() {
        if ($this->_isValid === null) {
            $valid = true;
            foreach ($this->_loaded as $index => $instance) {
                $isValid = $instance->isValid();
                if (!$isValid && $valid) {
                    $valid = false;
                }
                $this->_toArray[$instance->attrs()->id()] = $instance->toArray();
            }
            $this->_isValid = $valid;
        }
        return $this->_isValid;
    }

/**
 * Get instance data as array.
 *
 * @return array Instance data as array
 */
    public function toArray() {
        if ($this->_toArray === null) {
            foreach ($this->_loaded as $index => $instance) {
                $this->_toArray[$instance->attrs()->id()] = $instance->toArray();
            }
        }
        return $this->_toArray;
    }

}
