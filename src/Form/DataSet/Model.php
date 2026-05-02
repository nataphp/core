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
 * @copyright   Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @link        http://nataphp.com NataPHP Project
 * @since       NataPHP 1.0.0
 * @license     http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\Form\DataSet;

use Nata\Form\DataSet;
use Nata\ORM\Query;
use Nata\ORM\Entity;
use Nata\ORM\TableRegistry;
use Nata\Collection\Collection;

/**
 * Form's data from model.
 */
class Model extends DataSet {

/**
 * If enabled, it will check from model
 * if Translate behaivour is enabled and property is on of the fields to translate.
 *
 * @var bool
 */
    protected $_autoTranslate = true;

/**
 * Model translations data.
 *
 * @var array
 */
    protected $_translations;

/**
 * Holds the primary key.
 *
 * @var int
 */
    protected $_primaryKey;


/**
 * Constructor.
 *
 * @param mixed $data Model Data
 * @param bool $duplicate Duplicate model
 * @return void
 */
    public function __construct($data, $duplicate = false) {
        parent::__construct($data);

        $this->_primaryKey = $this->get('id');

        if ($duplicate) {
            $this->_duplicate();
        }

        $this->_translations($data);
    }

/**
 * If enabled, it will set 'translate' config automatically.
 *
 * @param bool $autoTranslate Translation is enabled
 * @return bool Translation
 */
    public function autoTranslate(?bool $autoTranslate = null) {
        if (func_num_args() === 0) {
            return $this->_autoTranslate;
        }

        $this->_autoTranslate = $autoTranslate;

        return $this;
    }

/**
 * Remove primary key from model data and dirtify all data.
 * @return void
 */
    protected function _duplicate() {
        if (!$this->has('id')) {
            return;
        }

        unset($this->_data['id']);

        if ($this->_data instanceof Entity) {
            $this->_data->accessible('id', true);
            $this->_data->isNew(true);
        }

    }

/**
 * Returns the value of a property by name.
 *
 * @param string $property the name of the property to retrieve
 * @return mixed
 * @throws \InvalidArgumentException if an empty property name is passed
 */
    public function get($property) {
        if ($this->_isHiddenProperty($property)) {
            return;
        }

        if ($this->isTranslatable($property)) {
            $value = $this->_translation($property);
        } else {
            $value = parent::get($property);
        }

        if ($value instanceof Query) {
            $value = $value->all();
        } elseif ($value instanceof Collection) {
            $value = $value->toArray();
            $this->_data[$property] = $value;
        }

        return $value;
    }

/**
 * Returns translated property by name.
 *
 * @param string $property the name of the property to retrieve translation
 * @return mixed
 */
    private function _translation($property) {
        return $this->_autoTranslate === true && $this->_translations && isset($this->_translations[$property]) ? $this->_translations[$property] : null;
    }

/**
 * Get translated data.
 *
 * @return \Nata\Form\DataSet Translated data
 */
    private function _translations($model) {
        if ($this->_autoTranslate === true && $this->_translations === null && $model instanceof Entity && $model->source()) {
            $repository = TableRegistry::get($model->source());
            $translateBehavior = $repository->behaviors()->get('Translate');
            if ($translateBehavior) {
                $this->_translations = array_map(function () {
                    return [];
                }, array_flip($translateBehavior->config('fields')));

                if ($this->_primaryKey) {
                    if ($model->has('_locale')) {
                        $locale = $model->get('_locale');
                        foreach ($this->_translations as $field => $_translation) {
                            $this->_translations[$field][$locale] = $model->get($field);
                        }
                    }

                    $translations = $translateBehavior->translationTable()
                        ->find()
                        ->select(['locale', 'field', 'content'])
                        ->andWhere([
                            $translateBehavior->foreignKey() => $this->_primaryKey
                        ]);

                    foreach ($translations as $translation) {
                        $field = $translation->get('field');
                        $locale = $translation->get('locale');

                        if (isset($this->_translations[$field][$locale])) {
                            continue;
                        }
                        $this->_translations[$field][$locale] = $translation->get('content');
                    }

                }

            }
        }
        return $this->_translations;
    }

/**
 * Check if property is currently in an hidden state.
 *
 * @param string $property Property name to check
 * @return \Nata\Form\ Model data
 */
    private function _isHiddenProperty($property) {
        return false;
        if (!($this->_data instanceof Entity)) {
            return false;
        }

        $hiddenProperties = $this->_data->hiddenProperties();

        return in_array($property, $hiddenProperties);
    }

/**
 * Check if field is translatable.
 *
 * @param string $property the name of the property to retrieve
 * @return bool
 */
    public function isTranslatable($property) {
        return $this->_autoTranslate && in_array($property, array_keys((array)$this->_translations));
    }

/**
 * Returns whether this data set is empty.
 *
 * When passing on the property is checks if property is empty.
 *
 * @param string|array $property The property or properties to check.
 * @return bool
 */
    public function virtualProperties($property = null) {
        if ($this->_data instanceof Entity) {
            return $this->_data->virtualProperties($property);
        }
        return;
    }

/**
 * Returns whether this data set is empty.
 *
 * When passing on the property is checks if property is empty.
 *
 * @param string|array $property The property or properties to check.
 * @return bool
 */
    public function visibleProperties($property = null) {
        if ($this->_data instanceof Entity) {
            return $this->_data->visibleProperties($property);
        }
        return;
    }

/**
 * Get data.
 *
 * @param null|array $properties Either an array of properties to treat as visible or null to get properties
 * @return $this|array Instance or list of properties
 */
    public function extract() {
        return $this->_data;
    }

/**
 * Clear data.
 */
    public function clear() {
        $this->_data = [];
    }

}