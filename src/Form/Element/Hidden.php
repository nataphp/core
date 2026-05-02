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
 * Hidden element.
 */
class Hidden extends Input {


/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 * @return \Nata\Event\Event
 */
    public function beforeRender($event) {
        if ($this->multiple() && $this->isEmpty()) {
            $this->value(['']);
        }

        $this->_layout = null;

        $attributes = $this->attributes();
        $attributes
            ->addClass('form-control')
            ->set('value', $this->value());

        if (!$attributes->get('name')) {
            $id = $this->id();
            $name = $this->_flattenData === true ? $this->name() : $id;
            $attributes->name($name);
        }

    }

}