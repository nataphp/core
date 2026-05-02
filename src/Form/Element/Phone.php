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

namespace Nata\Form\Element;

use Nata\Event\Event;
use Nata\Form\OptionRegistry;
use Nata\Utility\Validation;

/**
 * Phone element.
 */
class Phone extends Input {

/**
 * List of prefixes
 *
 * @var Element
 */
    protected $_prefix;


/**
 * Pseudo-constructor.
 *
 * @return void
 */
    public function initialize($config) {
        $config += [
            'prefix' => null
        ];

        if ($config['prefix'] !== null) {
            $this->prefix($config['prefix']);
        }

        parent::initialize($config);
    }

/**
 * Get/set phone's prefixes.
 *
 * @param OptionRegistry|array $prefixes
 * @return \Nata\Form\OptionRegistry
 */
    public function prefix(array $prefix = null) {
        if (func_num_args() === 0) {
            return $this->_prefix;
        }

        $id = $this->id();
        $custom = array_pop($id);

        $prefix += [
            'name' => $custom . '_prefix',
            'options' => []
        ];

        if (!($prefix['options'] instanceof OptionRegistry)) {
            $options = $prefix['options'];
            $prefix['options'] = new OptionRegistry($this);
            $prefix['options']->loadAll($options);
            $prefix['options']->select($this->_dataManager->model()->get($prefix['name']));
        }

        $container = $this->_form;
        if ($this->_groupRow) {
            $container = $this->_groupRow;
        }

        $this->_prefix = $container->elements()->load([
            'dataManager' => $this->_dataManager->get('mobile_phone_country.id', ''),
            'type' => 'select',
            'data' => $this->_dataManager->value($prefix['name'], $prefix['value']),
            'enableChosen' => true,
            'required' => $this->required(),
            'empty' => function () {
                return $this->isEmpty();
            },
            'render' => false
        ] + $prefix);

        if (!$this->_prefix->attrs()->name()) {
            $this->_prefix->attrs()->name($this->_prefix->id());
        }

        $this->_prefix->attrs()->addClass('form-control form-control-phone-prefixes');

        return $this;
    }

/**
 * Validation.
 *
 * @param string $data Data to be validated
 * @return boolean True if valid, false otherwise
 */
    protected function _valid($data) {
        // normalize data
        $data = str_replace(' ', '', $data);
        $regex = $this->_match;
        if ($regex) {
            if (!Validation::phone($data, $regex)) {
                $this->_error = __('Must be a valid phone number.');
            }
        } elseif (!Validation::numeric($data)) {
            $this->_error = __('Must be a valid phone number.');
        }
        $this->data($data);
        return $this->_error === null;
    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 */
    public function beforeRender($event) {
        parent::beforeRender($event);
        if ($this->_prefix) {
            $event = new Event($this->_prefix->name() . '.beforeRender', $this->_prefix);
            $this->_prefix->eventManager()->dispatch($event);
        }
    }

}
