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

use Nata\Form\Button;
use Nata\View\ViewBuilder;
use Nata\Utility\Hash;

/**
 * Holds the form's collection of buttons.
 */
class ButtonCollection extends BaseCollection {


/**
 * Load button instance.
 *
 * @param string|array $name type of button or config
 * @param array $config Configuration
 * @return \Nata\Form\Button
 */
    public function load($name, $config = array()) {
        if (is_array($name)) {
            $config = $name;
            $name = null;
        }

        $config += array(
            'id' => null,
            'type' => null,
            'name' => null,
            'button' => $name,
            'align' => null,
            'text' => null,
            'url' => null,
            'disabled' => null,
            'block' => null,
            'className' => null,
            'attrs' => array()
        );

        $config = Hash::expand($config);

        $config['className'] = $this->_resolveClassName(($config['className'] ? $config['className'] : $config['type']), 'Button');

        if (!$config['button']) {
            $config['button'] = $config['type'];
        }

        if (!$config['name']) {
            $config['name'] = substr(md5(json_encode($config)), 0, 5);
        }

        return $this->_loaded[$config['name']] = new $config['className']($config);
    }

/**
 * Render buttons.
 *
 * @param string $name Button name
 * @return string
 */
    public function render($name = null) {
        $html = '';
        foreach ($this->_loaded as $button) {
            $html .= $button->render();
        }        
        return $html;
    }

}