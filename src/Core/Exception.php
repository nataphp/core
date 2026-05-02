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

namespace Nata\Core;

use Error;
use JsonSerializable;
use RuntimeException;
use Throwable;

/**
 * Nata\Core\Exception\Nata is used a base class for NataPHP's internal exceptions.
 * In general framework errors are interpreted as 500 code errors.
 */
class Exception extends RuntimeException implements JsonSerializable {

/**
 * Array of attributes that are passed in from the constructor, and
 * made available in the view when a development error is displayed.
 *
 * @var array
 */
    protected $_attributes = [];

/**
 * Template string that has attributes sprintf()'ed into it.
 *
 * @var string
 */
    protected $_templateMessage = '';

/**
 * Constructor.
 *
 * Allows you to create exceptions that are treated as framework errors and disabled
 * when debug = 0.
 *
 * @param string|array $message Either the string of the error message, or an array of attributes
 *   that are made available in the view, and sprintf()'d into NataException::$_messageTemplate
 * @param string $code The code of the error, is also the HTTP status code for the error.
 * @param \Throwable|null $previous the previous exception.
 */
    public function __construct($message, $code = 500, ?Throwable $previous = null) {
        if (is_array($message)) {
            $attributes = $message;
            if (isset($attributes['message'])) {
                $message = $attributes['message'];
                unset($attributes['message']);
            } else {
                $message = vsprintf($this->_templateMessage, array_values($attributes));
            }

            $this->_attributes = $attributes;
        }
        parent::__construct($message, $code, $previous);
    }

/**
 * Get the passed in attributes.
 *
 * @return array
 */
    public function getAttributes() {
        return $this->_attributes;
    }

/**
 * Get the attribute value with given key name.
 *
 * @param string $name Attribute name
 * @return mixed
 */
    public function getAttribute(string $name) {
        return $this->_attributes[$name] ?? null;
    }

/**
 * Check if given attribute name exists.
 *
 * @param string $name Attriibute name to check
 * @return bool True if is set, false otherwise
 */
    public function hasAttribute($name) {
        return isset($this->_attributes[$name]);
    }

/**
 * Get defined attribute value.
 * If is not set, it will throw a undefined method Error.
 *
 * @param string $method Method name
 * @param array $args Method arguments
 * @return mixed Attribute value
 * @throws Error
 */
    public function __call(string $method, array $args) {
        if (stripos($method, 'get') === 0 && $this->_attributes) {
            $attribute = lcfirst(substr($method, 3));
            if (isset($this->_attributes[$attribute])) {
                return $this->_attributes[$attribute];
            }
        }
        throw new Error(sprintf('Call to undefined method %s::%s', __CLASS__, $method));
    }

/**
 * __toString.
 *
 * @return string Message
 */
    public function __toString() {
        return parent::getMessage();
    }

/**
 * jsonSerialize.
 *
 * @return array Get array for JSON
 */
    public function jsonSerialize(): mixed {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTrace()
        ];
    }
}
