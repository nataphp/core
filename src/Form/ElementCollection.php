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

use Nata\Utility\Hash;
use Nata\Form\BaseCollection;
use Nata\Form\Element;
use Nata\Form\Exception\ElementConfigurationException;
use Nata\View\ViewBuilder;

/**
 * Holds the form's collection of elements.
 */
class ElementCollection extends BaseCollection {

/**
 * Element type to name map.
 *
 * @var array
 */
    protected $_typeMap = [];

/**
 * Map of elements per row.
 *
 * @var array
 */
    protected $_rowMap = [];

/**
 * Element's default config.
 *
 * @var array
 */
    protected $_defaultConfig = [
        'className' => null,
        'dataManager' => null,
        'name' => null,
        'data' => null,
        'row' => null,
        'type' => null,
        'translate' => null
    ];


/**
 * Load element instance.
 *
 * @param string|array $name Element name of array of config
 * @param array $config Element configuration
 * @return \Nata\Form\Element
 */
    public function load($name, array $config = []) {
        $config += parent::_defaultConfig();

        if (is_array($name)) {
            $config = $name + $config;
            $name = $config['name'];
        } elseif (!$config['name']) {
            $config['name'] = $name;
        }

        $config = Hash::expand($config);
        $config = $this->_compatibility($config);

        if (!$config['className'] && !$config['element']) {
            throw new ElementConfigurationException(sprintf(
                'Missing "className" or "element" parameter in configuration for name "%s"',
                $config['name']
            ), $config);
        }

        $config['className'] = $this->_resolveClassName(($config['className'] ? $config['className'] : $config['element']), 'Element');

        if (!$config['name']) {
            throw new ElementConfigurationException(sprintf(
                'Empty element "name" parameter for %s.',
                $config['className']
            ));
        }

        $config['dataManager'] = $this->_dataManager;
        if (!$config['data']) {
            $config['data'] = $this->_dataManager->translate($config['translate'])->value($config['name'], $config['data']);
        }
        if ($config['translate'] === null) {
            $config['translate'] = $this->_dataManager->model()->isTranslatable($config['name']);
        }

        if ($config['translate'] !== null) {
            $config['data']->translate($config['translate']);
        }

        $config += [
            'disabled' => $this->_form->disable()
        ];

        $this->_setMap($config);

        $element = new $config['className']($this->_form, $config, $this->_container);
        return $this->_loaded[$config['name']] = $element;
    }

/**
 * Map element.
 *
 * @return array
 */
    public function _setMap($config) {
        $this->_typeMap[$config['element']][] = $config['name'];
        if (!$config['row'] && $config['element'] !== 'hidden') {
            $config['row'] = count($this->_rowMap) + 1;
        }
        $this->_rowMap[$config['row']][] = $config['name'];
    }

/**
 * Set element instance.
 *
 * @return array
 */
    public function set(Element $element) {
        $this->_setMap([
            'element' => $element->element(),
            'name' => $element->name(),
            'row' => null
        ]);
        $this->_set($element->name(), $element);
    }

/**
 * For compatibility with older convention.
 *
 * @param array $config Element configuration.
 * @return array
 */
    private function _compatibility($config) {
        if ($config['type']) {
            $config['element'] = $config['type'];
        }
        if (isset($config['valid'])) {
            $config['validation'] = $config['valid'];
        }
        if (!empty($config['config'])) {
            $config = array_merge($config, $config['config']);
        }
        if (!empty($config['rules'])) {
            $config = array_merge($config, $config['rules']);
        }
        return $config;
    }

/**
 * Get complete list of element names mapped by type.
 *
 * @param string $type Valid type of element.
 * @return array
 */
    public function typeMap($type = null) {
        if ($type === null) {
            return $this->_typeMap;
        }
        return isset($this->_typeMap[$type]) ? $this->_typeMap[$type] : null;
    }

/**
 * Get map of elements per row.
 *
 * @return array
 */
    public function rowMap() {
        return $this->_rowMap;
    }

/**
 * Unload instance(s).
 *
 * @param string|array $names Instances alias
 * @return $this
 */
    public function unload($names) {
        if (!is_array($names)) {
            $names = array($names);
        }
        foreach ($names as $name) {
            if ($this->has($name)) {
                unset($this->_loaded[$name]);
                foreach ($this->_typeMap as $type => $list) {
                    $key = array_search($name, $list);
                    if ($key !== false) {
                        unset($this->_typeMap[$type][$key]);
                    }
                }
                foreach ($this->_rowMap as $row => $list) {
                    $key = array_search($name, $list);
                    if ($key !== false) {
                        unset($this->_rowMap[$row][$key]);
                    }
                }
            }
        }
        return $this;
    }

/**
 * @deprecated Use unload() instead
 */
    public function remove($names) {
        return $this->unload($names);
    }

/**
 * Clear all loaded instances.
 *
 * @return $this
 */
    public function clear() {
        $this->_typeMap = $this->_rowMap = $this->_loaded = array();
        return $this;
    }

/**
 * Render loaded elements.
 *
 * @return string
 */
    public function render() {
        $html = '';
        $view = ViewBuilder::build()->compileCheck(false);
        $theme = $this->_theme;

        foreach ($this->_rowMap as $index => $elements) {
            $view->set('collection', $this);
            $view->set('elements', $elements);
            $view->set('element_count', count($elements));
            $html .= $view->templatePath('Form/' . $theme)->render('/Form/' . $theme . '/row');
        }

        return $html;
    }

}
