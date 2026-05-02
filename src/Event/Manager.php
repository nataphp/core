<?php
/**
 * NataPHP Framework.
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

namespace Nata\Event;

use Nata\Event\Listener;
use InvalidArgumentException;

/**
 * The event manager is responsible for keeping track of event listeners and pass the correct
 * data to them, and fire them in the correct order, when associated events are triggered. You
 * can create multiple instances of this objects to manage local events or keep a single instance
 * and pass it around to manage all events in your app.
 */
class Manager {

/**
 * The default priority queue value for new attached listeners
 *
 * @var int
 */
    public static $defaultPriority = 10;

/**
 * The globally available instance, used for dispatching events attached from any scope.
 *
 * @var \Nata\Event\Manager
 */
    protected static $_generalManager;

/**
 * List of listener callbacks associated to.
 *
 * @var object $Listeners
 */
    protected $_listeners = [];

/**
 * Internal flag to distinguish a common manager from the singleton.
 *
 * @var boolean
 */
    protected $_isGlobal = false;

/**
 * Returns the globally available instance of a \Nata\Event\Manager
 * this is used for dispatching events attached from outside the scope
 * other managers were created. Usually for creating hook systems or inter-class
 * communication
 *
 * If called with a first params, it will be set as the globally available instance
 *
 * @param \Nata\Event\Manager $manager
 * @return \Nata\Event\Manager The global event manager
 */
    public static function instance(Manager $manager = null): Manager {
        if ($manager instanceof Manager) {
            static::$_generalManager = $manager;
        }

        if (static::$_generalManager === null) {
            static::$_generalManager = new Manager;
        }

        static::$_generalManager->_isGlobal = true;

        return static::$_generalManager;
    }

/**
 * Adds a new listener to an event. Listeners
 *
 * @param string $eventKey The event unique identifier name to with the callback will be associated. If $callable
 * is an instance of \Nata\Event\Listener this argument will be ignored
 *
 * @param callback|\Nata\Event\Listener $callable PHP valid callback type or instance of \Nata\Event\Listener to be called
 * when the event named with $eventKey is triggered. If a \Nata\Event\Listener instances is passed, then the `implementedEvents`
 * method will be called on the object to register the declared events individually as methods to be managed by this class.
 * It is possible to define multiple event handlers per event name.
 *
 * @param array $options used to set the `priority` and `passParams` flags to the listener.
 * Priorities are handled like queues, and multiple attachments into the same priority queue will be treated in
 * the order of insertion. `passParams` means that the event data property will be converted to function arguments
 * when the listener is called. If $called is an instance of \Nata\Event\Listener, this parameter will be ignored
 *
 * @return $this
 * @throws InvalidArgumentException When event key is missing or callable is not an
 *   instance of \Nata\Event\Listener.
 */
    public function on($eventKey, $callable = null, $options = []) {
        if ($eventKey instanceof Listener) {
            return $this->_attachSubscriber($eventKey);
        }

        $options += ['priority' => static::$defaultPriority, 'passParams' => false];

        $this->_listeners[$eventKey][$options['priority']][] = [
            'callable' => $callable,
            'passParams' => $options['passParams'],
        ];

        return $this;
    }

/**
 * Auxiliary function to attach all implemented callbacks of a \Nata\Event\Listener class instance
 * as individual methods on this manager.
 *
 * @param \Nata\Event\Listener $subscriber
 * @return void
 */
    protected function _attachSubscriber(Listener $subscriber) {
        foreach ($subscriber->implementedEvents() as $eventKey => $function) {
            $options = [];
            $method = $function;

            if (is_array($function) && isset($function['callable'])) {
                [$method, $options] = $this->_extractCallable($function, $subscriber);
            } elseif (is_array($function) && is_numeric(key($function))) {
                foreach ($function as $f) {
                    [$method, $options] = $this->_extractCallable($f, $subscriber);
                    $this->on($eventKey, $method, $options);
                }
                continue;
            }

            if (is_string($method)) {
                $method = [$subscriber, $function];
            }

            $this->on($eventKey, $method, $options);
        }
    }

/**
 * Auxiliary function to extract and return a PHP callback type out of the callable definition
 * from the return value of the `implementedEvents` method on a \Nata\Event\Listener.
 *
 * @param array $function the array taken from a handler definition for a event
 * @param \Nata\Event\Listener $object The handler object
 * @return callback
 */
    protected function _extractCallable($function, $object) {
        $method = $function['callable'];
        $options = $function;
        unset($options['callable']);
        if (is_string($method)) {
            $method = [$object, $method];
        }
        return [$method, $options];
    }

/**
 * Removes a listener from the active listeners.
 *
 * @param \Nata\Event\Listener $callable any valid PHP callback type or an instance of \Nata\Event\Listener
 * @param callback $callable any valid PHP callback type or an instance of \Nata\Event\Listener
 * @return $this
 */
    public function off($eventKey, $callable = null) {
        if ($eventKey instanceof Listener) {
            return $this->_detachSubscriber($eventKey, $callable);
        }

        if (empty($eventKey)) {
            foreach (array_keys($this->_listeners) as $eventKey) {
                $this->off($eventKey, $callable);
            }
            return;
        }

        if (empty($this->_listeners[$eventKey])) {
            return;
        }

        foreach ($this->_listeners[$eventKey] as $priority => $callables) {
            foreach ($callables as $k => $callback) {
                if ($callback['callable'] === $callable) {
                    unset($this->_listeners[$eventKey][$priority][$k]);
                    break;
                }
            }
        }
        return $this;
    }

/**
 * Auxiliary function to help detach all listeners provided by an object implementing \Nata\Event\Listener.
 *
 * @param \Nata\Event\Listener $subscriber the subscriber to be detached
 * @return void
 */
    protected function _detachSubscriber(Listener $subscriber) {
        $events = $subscriber->implementedEvents();
        foreach ($events as $key => $function) {
            if (is_array($function)) {
                if (is_numeric(key($function))) {
                    foreach ($function as $handler) {
                        $handler = isset($handler['callable']) ? $handler['callable'] : $handler;
                        $this->off($key, array($subscriber, $handler));
                    }
                    continue;
                }
                $function = $function['callable'];
            }
            $this->off($key, array($subscriber, $function));
        }
    }

/**
 * Dispatches a new event to all configured listeners.
 *
 * @param \Nata\Event\Event\string $event the event key name or instance of Event
 * @return \Nata\Event\Event
 */
    public function dispatch($event) {
        if (is_string($event)) {
            $event = new Event($event);
        }

        if (!$this->_isGlobal) {
            static::instance()->dispatch($event);
        }

        if (empty($this->_listeners[$event->getName()])) {
            return;
        }

        foreach ($this->listeners($event->getName()) as $listener) {
            if ($event->isStopped()) {
                break;
            }

            $result = $this->_callListener($listener['callable'], $event);

            if ($result === false) {
                $event->stopPropagation();
            }

            if ($result !== null) {
                $event->result = $result;
            }

            continue;
        }

        return $event;
    }

/**
 * Calls a listener.
 *
 * Direct callback invocation is up to 30% faster than using call_user_func_array.
 * Optimize the common cases to provide improved performance.
 *
 * @param callable $listener The listener to trigger.
 * @param \Nata\Event\Event $event Event instance.
 * @return mixed The result of the $listener function.
 */
    protected function _callListener(callable $listener, Event $event) {
        $data = $event->data();

        if (!is_array($data)) {
            $data = [$data];
        }

        $length = count($data);

        if ($length) {
            $data = array_values($data);
        }

        switch ($length) {
            case 0:
                return $listener($event);
            case 1:
                return $listener($event, $data[0]);
            case 2:
                return $listener($event, $data[0], $data[1]);
            case 3:
                return $listener($event, $data[0], $data[1], $data[2]);
            default:
                array_unshift($data, $event);
                return call_user_func_array($listener, $data);
        }

    }

/**
 * Returns a list of all listeners for a eventKey in the order they should be called.
 *
 * @param string $eventKey
 * @return array
 */
    public function listeners($eventKey) {
        if (empty($this->_listeners[$eventKey])) {
            return [];
        }

        ksort($this->_listeners[$eventKey]);

        $result = [];
        foreach ($this->_listeners[$eventKey] as $priorityQ) {
            $result = array_merge($result, $priorityQ);
        }

        return $result;
    }

}
