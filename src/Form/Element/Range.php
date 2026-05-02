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

use Nata\Form\Element\Input;

/**
 * Range element.
 */
class Range extends Input {

/**
 * Min.
 *
 * @var int
 */
    protected $_min;

/**
 * Max.
 *
 * @var int
 */
    protected $_max;

/**
 * Step.
 *
 * @var int
 */
    protected $_step;

/**
 * Label.
 *
 * @var string
 */
    protected $_label;


/**
 * Pseudo-constructor.
 *
 * @return void
 */
    public function initialize($config) {
        $config += array(
            'min' => null,
            'max' => null,
            'step' => null,
            'label' => null
        );

        if ($config['min']) {
            $this->_min = $config['min'];
        }

        if ($config['max']) {
            $this->_max = $config['max'];
        }

        if ($config['step']) {
            $this->_step = $config['step'];
        }

    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 */
    public function beforeRender($event) {
        parent::beforeRender($event);
        $this->_getView()->extend(array('input'));

        $this->attrs()
            ->set('min', $this->_min)
            ->set('max', $this->_max)
            ->set('step', $this->_step);

    }

}