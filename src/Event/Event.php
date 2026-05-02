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

namespace Nata\Event;

use Nata\Utility\Hash;

/**
 * Represent the transport class of events across the system, it receives a name, and subject and an optional
 * payload. The name can be any string that uniquely identifies the event across the application, while the subject
 * represents the object that the event is applying to.
 */
class Event {

/**
 * Name of the event.
 *
 * @var string $name
 */
    protected $_name;

/**
 * The object this event applies to
 * (usually the same object that generates the event).
 *
 * @var object
 */
    protected $_subject;

/**
 * Custom data for the method that receives the event.
 *
 * @var array
 */
    protected $_data;

/**
 * Property used to retain the result value of the event listeners.
 *
 * @var mixed $result
 */
    public $result;

/**
 * Flags an event as stopped or not, default is false.
 *
 * @var boolean
 */
    protected $_stopped = false;

/**
 * Constructor.
 *
 * @param string $name Name of the event
 * @param object $subject the object that this event applies to (usually the object that is generating the event)
 * @param mixed $data any value you wish to be transported with this event to it can be read by listeners
 *
 * ## Examples of usage:
 *
 * {{{
 *    $event = new \Nata\Event\Event('Order.afterBuy', $this, array('buyer' => $userData));
 *    $event = new \Nata\Event\Event('User.afterRegister', $UserModel);
 * }}}
 *
 */
    public function __construct($name, $subject = null, $data = null) {
        $this->_name = $name;
        $this->_data = $data;
        $this->_subject = $subject;
    }

/**
 * Dynamically returns the name and subject if accessed directly.
 *
 * @param string $attribute
 * @return mixed
 */
    public function __get($attribute) {
        if ($attribute === 'name') {
            return $this->getName();
        } elseif ($attribute === 'subject') {
            return $this->getSubject();
        }
    }

/**
 * Returns the name of this event.
 * This is usually used as the event identifier.
 *
 * @return string
 */
    public function getName() {
        return $this->_name;
    }

/**
 * Returns the subject of this event.
 *
 * @return mixed
 */
    public function getSubject() {
        return $this->_subject;
    }

/**
 * Get/Set data.
 *
 * @param string $path Path to value
 * @param mixed $value Value to insert
 * @return mixed|$this Path's value
 */
    public function data($path = null, $value = null) {
        if (func_num_args() === 0) {
            return $this->_data;
        }

        if ($value === null) {
            return Hash::get($this->_data, $path);
        }

        $this->_data = Hash::insert($this->_data, $path, $value);

        return $this;
    }

/**
 * Stops the event from being used anymore.
 *
 * @return $this
 */
    public function stopPropagation() {
        $this->_stopped = true;
        return $this;
    }

/**
 * Check if the event is stopped.
 *
 * @return boolean True if the event is stopped
 */
    public function isStopped() {
        return $this->_stopped;
    }

}
