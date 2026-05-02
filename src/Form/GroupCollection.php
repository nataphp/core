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

use Nata\Form\Group;
use FormException;

/**
 * Holds the form's collection of group of elements.
 */
class GroupCollection extends BaseCollection {

/**
 * Group's default config.
 *
 * @var array
 */
    protected $_defaultConfig = array(
        'group' => null,
        'legend' => null,
        'duplicate' => null,
        'defaultTitle' => null,
        'addRowText' => null,
        'title' => null,
        'max' => null,
        'required' => false,
        'template' => null,
        'elements' => []
    );


/**
 * Load group of elements instance.
 *
 * @param string|array $group Group name of array of config
 * @param array $config Group configuration
 * @return \Nata\Form\Element
 */
    public function load($group, array $config = array()) {
        $config += $this->_defaultConfig();
        if (is_array($group)) {
            $config = $group + $config;
            $group = $config['group'];
        } else {
            $config['group'] = $group;
        }
        if (empty($config['group'])) {
            throw new FormException(sprintf('Missing name of group'));
        }
        $config['data'] = $this->_dataManager->get($config['group']);
        $config['submitted'] = $this->_submitted;
        $group = new Group($this->_form, $config);
        return $this->_loaded[$config['group']] = $group;
    }

}