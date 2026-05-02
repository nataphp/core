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
use Nata\Utility\Sanitize;

/**
 * Static text element.
 */
class StaticText extends Element {

/**
 * This element is just to be rendered, not processed/validated.
 *
 * @var bool
 */
    protected $_readOnly = true;

/**
 * Text.
 *
 * @var string
 */
    protected $_text;


/**
 * Pseudo-constructor.
 *
 * @return void
 */
    public function initialize($config) {
        $config += array(
            'text' => null,
            'value' => null
        );
        if ($config['text']) {
            $this->_text = $config['text'];
        }
        $this->_required = false;
    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 * @return \Nata\Event\Event
 */
    public function beforeRender($event) {
        $this->attributes()->id($this->id());
    }

/**
 * Get/set text.
 *
 * @param string $text Text
 * @return $this|string
 */
    public function text($text = null) {
        return parent::value($text);
    }

}