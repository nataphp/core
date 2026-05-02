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

use Nata\Form\Element;

/**
 * Radio element.
 */
class Radio extends Element {

/**
 * Inline options.
 *
 * @var bool
 */
    protected $_inline = false;


/**
 * Pseudo-constructor.
 * This method is called after user and defaults are setup in class.
 *
 * @return void
 */
    public function initialize($config) {
        $config += array(
            'inline' => null
        );

        if ($config['inline']) {
            $this->_inline = $config['inline'];
        }
    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 * @return \Nata\Event\Event
 */
    public function beforeRender($event) {
        $id = $this->id();
        $name = $this->_flattenData === true ? $this->name() : $id;
        $this->attributes()
            ->id($id)
            ->name($name);
    }

/**
 * Get/set inline option.
 *
 * @param bool $inline Inline options
 * @return $this|bool
 */
    public function inline($inline = null) {
        return $this->_property('_inline', $inline);
    }

/**
 * Element to string.
 *
 * @return string
 */
    public function __toString() {
        $value = $this->value();
        if ($value) {
            $options = $this->options();
            if ($options->has($value)) {
                return $options->get($value)->label();
            }
        }
    }

}
