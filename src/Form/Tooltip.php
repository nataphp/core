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
use Nata\Event\Listener;
use Nata\Event\Manager;

/**
 * Tooltip utility object.
 */
class Tooltip extends NataObject implements Listener {

/**
 * Tooltip id.
 *
 * @var array
 */
    protected $_id;

/**
 * Tooltip content.
 *
 * @var string
 */
    protected $_content = '';

/**
 * Tooltip list of HTML attributes.
 *
 * @var array
 */
    protected $_attributes;

/**
 * Event manager.
 *
 * @var \Nata\Event\Manager
 */
    protected $_eventManager;


/**
 * Construtor.
 *
 * @param array $config Options
 */
    public function __construct(array $config = []) {
        $config += [
            'eventManager' => null,
            'content' => null
        ];

        if ($config['eventManager']) {
            $this->_eventManager = $config['eventManager'];
            $this->_eventManager->on($this);
        }

        if ($config['content']) {
            $this->_content = $config['content'];
        }

        $this->initialize($config);
        $this->startup();

    }

/**
 * Pseudo-constructor.
 * This method is called after user and defaults are setup in class.
 *
 * @param array $config Element configuration
 * @return void
 */
    public function initialize($config) {
        $this->config($config);
    }

/**
 * Startup is called after all element data is set.
 *
 * @return void
 */
    public function startup() {}

/**
 * Returns the \Nata\Event\Manager manager instance that is handling any callbacks.
 * You can use this instance to register any new listeners or callbacks to the
 * controller events, or create your own events and trigger them at will.
 *
 * @param \Nata\Event\Manager $eventManager Event manager instance
 * @return \Nata\Event\Manager
 */
    public function eventManager($eventManager = null) {
        if ($eventManager === null) {
            if ($this->_eventManager === null) {
                $this->_eventManager = new Manager();
                $this->_eventManager->on($this);
            }
            return $this->_eventManager;
        }
        $this->_eventManager = $eventManager;
        return $this;
    }

/**
 * Returns a list of all events that will fire in the model during it's lifecycle.
 * You can override this function to add you own listener callbacks
 *
 * @return array
 */
    public function implementedEvents() {
        return array(
            $this->name() . '.beforeRender' => 'beforeRender',
            $this->name() . '.afterRender' => 'afterRender'
        );
    }

/**
 * Get tooltip's id.
 *
 *
 * @return array
 */
    public function id($id = null) {
        if ($id === null) {
            if ($this->_id === null) {
                $this->_id = 'tooltip-' . substr(sha1($this->_content), 0, 10);
            }
            return $this->_id;
        }

        $this->_id = $id;

        return $this;
    }

/**
 * Get/set tooltip's content.
 *
 * @param string $content Tooltip content
 * @return $this|string
 */
    public function content($content = null) {
        if ($content === null) {
            return $this->_content;
        }
        $this->_content = $content;
        return $this;
    }

/**
 * Get/set content's attributes.
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
 * Array of some properties.
 *
 * @return array
 */
    public function toArray() {
        return [
            'id' => $this->attributes()->id(),
            'content' => $this->_content
        ];
    }

/**
 * __toString.
 *
 * @return string Tooltip content
 */
    public function __toString() {
        return $this->_content;
    }

}
