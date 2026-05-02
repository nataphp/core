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
use Nata\Utility\Html as HtmlUtility;

/**
 * Html Tag element.
 */
class HtmlTag extends Element {

/**
 * This element is just to be rendered, not processed/validated.
 *
 * @var bool
 */
    protected $_readOnly = true;

/**
 * Html tag.
 *
 * @var string
 */
    protected $_tag;

/**
 * Text.
 *
 * @var string
 */
    protected $_text;

/**
 * HTML.
 *
 * @var string
 */
    protected $_html;


/**
 * Pseudo-constructor.
 *
 * @return void
 */
    public function initialize($config) {
        $config += array(
            'tag' => null,
            'html' => null,
            'text' => null
        );
        if ($config['tag']) {
            $this->_tag = $config['tag'];
        }
        if ($config['text']) {
            $this->_text = $config['text'];
        }
        if ($config['html']) {
            $this->_html = $config['html'];
        }
    }

/**
 * Render.
 *
 * @return string
 */
    public function render() {
        if ($this->_tag) {
            return HtmlUtility::elem($this->_tag, ($this->_text ? $this->_text : $this->_html), $this->attributes());
        }
        return $this->_html;
    }

/**
 * Get/set tag.
 *
 * @param string $tag Tag
 * @return $this|array
 */
    public function tag($tag = null) {
        return $this->_property('_tag', $tag);
    }

/**
 * Get/set text.
 *
 * @param string $text Text
 * @return $this|string
 */
    public function text($text = null) {
        return $this->_property('_text', $text);
    }

/**
 * Get/set html.
 *
 * @param string $html HTML
 * @return $this|string
 */
    public function html($html = null) {
        return $this->_property('_html', $html);
    }

}