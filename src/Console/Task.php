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

use Nata\Console\Job;
use Nata\Core\NataObject;
use Nata\Event\Event;
use Nata\Event\Listener;
use Nata\Event\Manager;

/**
 * Adds common methods across all job tasks.
 */
class Task extends NataObject implements Listener {

/**
 * Job.
 *
 * @var \Nata\Console\Job
 */
    protected $_job;

/**
 * Default component configuration.
 *
 * @var array
 */
    protected $_defaultConfig = array();


/**
 * Component constructor.
 *
 * @param \Nata\Controller\Controller $controller Controller instance.
 * @param array $config Component configuration
 * @return void
 */
    public function __construct(Job $job, $config = array()) {
        $this->_job = $job;
        $this->config($config + $this->_defaultConfig);
        $this->initialize($config);
    }

/**
 * Initialization hook method.
 *
 * Implement this method to avoid having to overwrite
 * the constructor and call parent.
 *
 * @param array $config Configuration array
 * @return void
 */
    public function initialize(array $config) {}

/**
 * Startup.
 *
 * @return void
 */
    public function startup($event) {}

/**
 * Called on controller shutdown.
 *
 * @return void
 */
    public function shutdown() {}

/**
 * Returns a list of all events that will fire in the controller during it's lifecycle.
 * You can override this function to add you own listener callbacks
 *
 * @return array
 */
    public function implementedEvents() {
        return array(
            'Job.startup' => 'startup',
            'Job.shutdown' => 'shutdown'
        );
    }

}
