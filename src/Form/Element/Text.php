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
use Nata\I18n\I18n;

/**
 * Text element.
 */
class Text extends Input {

/**
 * Translate-able field.
 *
 * @var bool
 */
    protected $_translate;


/**
 * Pseudo-constructor.
 *
 * @return void
 */
    public function initialize($config) {
        parent::initialize($config);
        $config += [
            'translate' => null
        ];

        if ($config['translate']) {
            $this->_translate = $config['translate'];
        }

    }

/**
 * Get/set translate-able.
 *
 * @param bool $translate Multilanguage value
 * @return $this|bool
 */
    public function translate($translate = null) {
        if ($translate === null) {
            return $this->_translate;
        }
        $this->_translate = $translate;
        return $this;
    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 */
    public function beforeRender($event) {
        parent::beforeRender($event);
        $this->_getView()->extend(['input']);
    }

/**
 * Element to string.
 *
 * @return string
 */
    public function __toString() {
        $value = $this->value();
        if ($this->translate() && is_array($value)) {
            $value = $value[I18n::locale()];
        }
        return $value;
    }

}