<?php
/**
 * NataPHP Framework
 * 
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
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

namespace Nata\View;

use Nata\Core\App;
use Nata\Core\ObjectRegistry;
use MissingHelperException;

/**
 * Helper registry.
 */
class HelperRegistry extends ObjectRegistry {

/**
 * The controller that this collection was initialized with.
 *
 * @var \Nata\View\View
 */
    protected $_view = null;

/**
 * Constructor.
 *
 * @param \Nata\View\View $view View instance.
 * @return void
 */
    public function __construct(View $view) {
        $this->_view = $view;
    }

/**
 * Resolve a component classname.
 *
 * Part of the template method for Nata\Core\ObjectRegistry::load()
 *
 * @param string $class Partial classname to resolve.
 * @return string|false Either the correct classname or false.
 */
    protected function _resolveClassName($class) {
        return App::className($class, 'View/Helper');
    }

/**
 * Throws an exception when a component is missing.
 *
 * Part of the template method for Nata\Core\ObjectRegistry::load()
 *
 * @param string $class The classname that is missing.
 * @param string $plugin The plugin the component is missing in.
 * @return void
 * @throws \MissingHelperException
 */
    protected function _throwMissingClassError($class, $plugin) {
        throw new MissingHelperException(array(
            'class' => 'View\Helper\\' . $class,
            'plugin' => $plugin
        ));
    }

/**
 * Create the component instance.
 *
 * Part of the template method for Nata\Core\ObjectRegistry::load()
 * Enabled components will be registered with the event manager.
 *
 * @param string $class The classname to create.
 * @param string $alias The alias of the component.
 * @param array $config An array of config to use for the component.
 * @return \Nata\Controller\Component The constructed component class.
 */
    protected function _create($class, $alias, $config) {
        $instance = new $class($this->_view, $config);
        $enable = isset($config['enabled']) ? $config['enabled'] : true;
        if ($enable) {
            // $this->eventManager()->on($instance);
        }
        return $instance;
    }    
    
}
