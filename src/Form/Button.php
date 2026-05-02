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

use Nata\Core\NataObject;
use Nata\Utility\Html;
use Nata\Routing\Router;

/**
 * Form button instance.
 */
class Button extends NataObject {

/**
 * Button ID.
 *
 * @var string
 */
    protected $_id;

/**
 * Button text.
 *
 * @var string
 */
    protected $_text;

/**
 * Button type.
 *
 * @var string
 */
    protected $_type = 'button';

/**
 * Link as button.
 *
 * @var bool
 */
    protected $_link = false;

/**
 * Button align.
 * 'left', 'right'
 *
 * @var string
 */
    protected $_align = 'left';

/**
 * Button name.
 *
 * @var string
 */
    protected $_name;

/**
 * Button url.
 *
 * @var string
 */
    protected $_url;

/**
 * Button disabled.
 *
 * @var bool
 */
    protected $_disabled = false;

/**
 * Button label.
 *
 * @var string
 */
    protected $_block = false;

/**
 * Attributes instance.
 *
 * @var \Nata\Form\Attributes
 */
    protected $_attributes;


/**
 * Construct.
 *
 * @param \Nata\Form\Form $form Form instance
 * @return void
 */
    public function __construct($config = array()) {
        if ($config['type']) {
            $this->type($config['type']);
        }
        if ($config['text']) {
            $this->_text = $config['text'];
        }
        if ($config['id']) {
            $this->_id = $config['id'];
        }
        if ($config['align']) {
            $this->_align = $config['align'];
        }        
        if ($config['url']) {
            $this->url($config['url']);
        }
        if ($config['disabled']) {
            $this->_disabled = $config['disabled'];
        }        
        if ($config['name']) {
            $this->_name = $config['name'];
        }
        if ($config['block']) {
            $this->_block = $config['block'];
        }
        if (!empty($config['attrs'])) {
            $this->attributes()->set($config['attrs']);
        }
    }

/**
 * Get/set option label.
 *
 * @param string $label Button label
 * @return $this|string
 */
    public function type($type = null) {
        if ($type === null) {
            return $this->_type;
        }
        if ($type === 'link') {
            $this->_link = true;
        } else {
            $this->_type = $type;
        }
        return $this;
    }

/**
 * Get/set option label.
 *
 * @param string $label Button label
 * @return $this|string
 */
    public function text($text = null) {
        return $this->_property('text', $text);
    }

/**
 * Get/set option value.
 *
 * @param string $value Button value
 * @return $this|string
 */
    public function align($align = null) {
        return $this->_property('align', $align);
    }

/**
 * Get/set button id.
 *
 * @param string $id Button id
 * @return $this|string
 */
    public function id($id = null) {
        return $this->_property('id', $id);
    }

/**
 * Get/set option id.
 *
 * @param string $id Button id
 * @return $this|string
 */
    public function disabled($disabled = null) {
        return $this->_property('disabled', $disabled);
    }

/**
 * Get/set option id.
 *
 * @param string $id Button id
 * @return $this|string
 */
    public function url($url = null, $full = false) {
        if ($url === null) {
            return $this->_url;
        }
        $this->_url = Router::url($url, $full);
        return $this;
    }

/**
 * Get/set button attributes.
 *
 * @return \Nata\Form\Attributes
 */
    public function attributes() {
        if ($this->_attributes === null) {
            $this->_attributes = new Attributes($this);
        }
        return $this->_attributes;
    }

/**
 * Alias/short hand for attributes.
 * 
 * @return \Nata\Form\Attributes
 */
    public function attrs() {
        return $this->attributes();
    }

/**
 * Get/set option id.
 *
 * @param string $id Button id
 * @return $this|string
 */
    private function _property($property, $value = null) {
        $var = '_' . $property;
        if ($value === null) {
            return $this->{$var};
        }
        $this->{$var} = $value;
        return $this;
    }

/**
 * Alias/short hand for attributes.
 * 
 * @return \Nata\Form\Attributes
 */
    public function isLink() {
        return $this->_link || $this->_url;
    }

/**
 * Get/set option id.
 *
 * @param string $id Button id
 * @return $this|string
 */
    public function render() {
        $attr = $this->attributes()
            ->set(array(
                'id' => $this->_id
            ));

        $class = $attr->get('class');

        if (empty($class)) {
            $attr->addClass('btn');
            if ($this->_block) {
                $attr->addClass('btn-block');
            }
            if (!$this->_link) {
                $attr->addClass('btn-primary');
            }
        }

        if ($this->isLink()) {
            $attr->set('href', $this->_url);
            $element = '<a>';
        } else {
            $attr->set('type', $this->_type);
            $element = '<button>';
        }

        return Html::elem($element, $this->_text, $attr->get());
    }

/**
 * Get/set option id.
 *
 * @param string $id Button id
 * @return $this|string
 */
    public function toArray() {
        return array(
            'text' => $this->_text,
            'disabled' => $this->_disabled,
            'url' => $this->_url,
            'id' => $this->_id
        );
    }

}