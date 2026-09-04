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

use Nata\I18n\I18n;

/**
 * Holds element data.
 */
class ElementValue {

/**
 * Model data.
 *
 * @var array|Nata\ORM\Entity
 */
    protected $_model;

/**
 * Model data.
 *
 * @var array|Nata\ORM\Entity
 */
    protected $_dirty = false;

/**
 * Request Data.
 *
 * @var array|string
 */
    protected $_request;

/**
 * Translate.
 *
 * @var bool
 */
    protected $_translate;

/**
 * Unsanitized/unfiltered request Data.
 *
 * @var array|string
 */
    protected $_unsafeRequest;

/**
 * Check if data was submitted.
 *
 * @var bool
 */
    protected $_submitted = false;

/**
 * HTML is allowed.
 *
 * @var bool
 */
    protected $_allowHtml = false;

/**
 * Value type that should be enforced.
 * By default
 *
 * @var string
 */
    protected $_type;


/**
 * Constructor.
 *
 * @param array $data Model and request data
 * @return void
 */
    public function __construct(array $data = []) {
        $data += [
            'model' => $this->_model,
            'request' => $this->_request,
            'submitted' => $this->_submitted
        ];

        $this->_model = $data['model'];
        $this->request($data['request']);
        $this->_submitted = $data['submitted'];
    }

/**
 * Get/Set translate.
 *
 * @param bool $translate Translate
 * @return bool|$this
 */
    public function translate($translate = null) {
        if ($translate === null) {
            return $this->_translate;
        }

        $this->_translate = $translate;

        return $this;
    }

/**
 * Get/set var type that should be enforced.
 *
 * @param string $type Var type
 * @return mixed Model data
 */
    public function type($type = null) {
        if ($type === null) {
            if ($this->_type === null && $this->_model !== null) {
                $type = gettype($this->_model);
                if (!in_array($type, ['array', 'object'])) {
                    $this->_type = $type;
                }
            }
            return $this->_type;
        }

        $this->_type = $type;

        return $this;
    }

/**
 * Get/Set model's data.
 *
 * @param \Nata\ORM\Entity $model Model data
 * @return mixed Model data
 */
    public function model($model = null) {
        if (func_num_args() === 0) {
            return $this->_getData($this->_model);
        }

        $this->_dirty = true;
        $this->_model = $model;

        return $this;
    }

/**
 * Get model's data based on translate setting.
 *
 * @param mixed $modelData Model data
 * @return mixed Model data
 */
    protected function _getData($modelData) {
        if ($this->_translate === null || $this->_translate === true || !is_array($modelData)) {
            return $modelData;
        }

        $locale = I18n::locale();
        if (is_string($this->_translate)) {
            $locale = $this->_translate;
        }

        return isset($modelData[$locale]) ? $modelData[$locale] : $modelData;
    }

/**
 * Get/set form's request data.
 *
 * @param string $requestData Path/key value
 * @return mixed Model data
 */
    public function request($requestData = null) {
        if ($requestData === null) {
            return $this->_request;
        }

        $this->_request = $requestData;
        return $this;
    }

/**
 * Type cast the (request) value.
 *
 * @param mixed $data Data to typecast
 * @return mixed Typecasted value
 */
    public function typecast($data = null) {
        if ($data === null) {
            $data = $this->_request;
        }
        return $this->_typeCast($data);
    }

/**
 * Type cast variable if needed.
 *
 * @param string $value Value to type cast
 * @return mixed Value
 */
    public function _typeCast($value) {
        if (!$this->type()) {
            return $value;
        }

        $type = gettype($value);

        /*
         * Nothing is cast into or out of a composite. Checked BEFORE the
         * integer test below, which calls strlen() and therefore raises a
         * TypeError on an array rather than falling through to here.
         */
        if (in_array($type, ['array', 'object'])) {
            return $value;
        }

        if ($this->_type === 'integer' && strlen((string)$value) === 0) {
            return null;
        }

        if ($type === $this->_type) {
            return $value;
        }

        settype($value, $this->_type);

        return $value;
    }

/**
 * Value submitted.
 *
 * @return bool true if submitted
 */
    public function submitted() {
        return $this->_submitted;
    }

/**
 * Get/Set model's data.
 *
 * @return boolean Model data
 */
    public function dirty() {
        return $this->_dirty;
    }

}