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

use Nata\Form\DataSet\Model;
use Nata\Form\DataSet\Request;

/**
 * Manages Data instances.
 */
class DataManager {

/**
 * Model data.
 *
 * @var array|Nata\ORM\Entity
 */
    protected $_model;

/**
 * Request Data.
 *
 * @var array
 */
    protected $_request;

/**
 * Translate.
 *
 * @var bool
 */
    protected $_translate;

/**
 * Duplicate data.
 *
 * @var bool
 */
    protected $_duplicate;


/**
 * Constructor.
 *
 * @param array $data Model and request data
 * @return void
 */
    public function __construct(array $data = []) {
        $data += [
            'translate' => $this->_translate,
            'duplicate' => $this->_duplicate,
            'model' => $this->_model,
            'request' => $this->_request,
        ];
        $this->_translate = $data['translate'];
        $this->_duplicate = $data['duplicate'];
        $this->model($data['model']);
        $this->request($data['request']);
    }

/**
 * Get data instance.
 *
 * @param string $property Property name
 * @return \Nata\Form\ Model data
 */
    public function __get($name) {
        if ($name === 'request') {
            return $this->request();
        } elseif ($name === 'model') {
            return $this->model();
        }
    }

/**
 * Enable translation.
 *
 * @param bool $translate Translate
 * @return bool|$this
 */
    public function translate(?bool $translate = null) {
        if (func_num_args() === 0) {
            return $this->_translate;
        }

        $this->_translate = $translate;

        return $this;
    }

/**
 * Get data instance.
 *
 * @param string $property Property name
 * @return \Nata\Form\ Model data
 */
    public function nested($index) {
        return new self([
            'duplicate' => $this->_duplicate,
            'model' => $this->model()->get($index),
            'request' => $this->request()->get($index)
        ]);
    }

/**
 * Get data instance.
 *
 * @param string $property Property name
 * @return \Nata\Form\ Model data
 */
    public function get($property) {
        return new self([
            'duplicate' => $this->_duplicate,
            'model' => $this->model()->get($property),
            'request' => $this->request()->get($property)
        ]);
    }

/**
 * Get element value instance.
 *
 * @param mixed $property Property name
 * @param mixed $defaultData Default data
 * @return \Nata\Form\ Model data
 */
    public function value($property, $defaultData) {
        $request = $this->request();
        return new ElementValue([
            'model' => ($defaultData === null ? $this->model()->autoTranslate($this->_translate !== false)->get($property) : null),
            'request' => $request->get($property),
            'submitted' => !$request->isEmpty()
        ]);
    }

/**
 * Get data instance.
 *
 * @param string $property Property name
 */
    public function patch($property, $value) {
        $this->model()->set($property, $value);
    }

/**
 * Clear all data sets.
 *
 * @param mixed $default Default value
 * @return void
 */
    public function clear($default = null) {
        $this->model($default);
        $this->request($default);
    }

/**
 * Get/Set model's data.
 *
 * @param \Nata\ORM\Entity $model Model data
 * @return mixed Model data
 */
    public function model($model = null, $duplicate = false) {
        if ($model === null) {
            if (!($this->_model instanceof Model)) {
                $this->_model = new Model($this->_model, $this->_duplicate);
            }
            return $this->_model;
        }
        $this->_model = $model;
        return $this;
    }

/**
 * Get/set form's request data.
 *
 * @param string $requestData Path/key value
 * @return mixed Model data
 */
    public function request($requestData = null) {
        if ($requestData === null) {
            if (!($this->_request instanceof Request)) {
                $this->_request = new Request($this->_request);
            }
            return $this->_request;
        }
        $this->_request = $requestData;
        return $this;
    }

/**
 * Get/set form's request data.
 *
 * @param string $requestData Path/key value
 * @return mixed Model data
 */
    public function requestWithHtml($requestData = null) {
        if ($requestData === null) {
            if (!($this->_request instanceof Request)) {
                $this->_request = new Request($this->_request);
            }
            return $this->_request;
        }
        $this->_request = $requestData;
        return $this;
    }

}