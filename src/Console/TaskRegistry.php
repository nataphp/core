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

namespace Nata\Console;

use Nata\Core\App;
use Nata\Core\ObjectRegistry;
use MissingTaskException;

/**
 * Task instances registry.
 */
class TaskRegistry extends ObjectRegistry {

/**
 * The job that this collection was initialized with.
 *
 * @var \Nata\CCron\Job
 */
    protected $_job = null;


/**
 * Construct task registry.
 *
 * @param \Nata\Console\Job $job Job instance.
 * @return void
 */
    public function __construct(Job $job) {
        $this->_job = $job;
    }

/**
 * Get the controller associated with the collection.
 *
 * @return Controller Controller instance
 */
    public function getTask() {
        return $this->_job;
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
        return App::className($class, 'Cron/Task');
    }

/**
 * Throws an exception when a component is missing.
 *
 * Part of the template method for Nata\Core\ObjectRegistry::load()
 *
 * @param string $class The classname that is missing.
 * @param string $plugin The plugin the component is missing in.
 * @return void
 * @throws \Nata\Controller\Exception\MissingComponentException
 */
    protected function _throwMissingClassError($class, $plugin) {
        throw new MissingTaskException(array(
            'class' => 'Cron\Task\\' . $class,
            'plugin' => $plugin
        ));
    }

/**
 * Create the task instance.
 *
 * Part of the template method for Nata\Core\ObjectRegistry::load()
 * Enabled components will be registered with the event manager.
 *
 * @param string $class The classname to create.
 * @param string $alias The alias of the component.
 * @param array $config An array of config to use for the component.
 * @return \Nata\Console\Task The constructed component class.
 */
    protected function _create($class, $alias, $config) {
        $instance = new $class($this->_job, $config);
        $enable = isset($config['enabled']) ? $config['enabled'] : true;
        if ($enable) {
            // $this->eventManager()->on($instance);
        }
        return $instance;
    }

}
