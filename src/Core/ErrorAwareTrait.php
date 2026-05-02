<?php
/**
 * NataPHP Framework.
 *
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

namespace Nata\Core;

use Nata\Log\Log;

/**
 * Trait for classes that want to provide methods to deal with errors.
 */
trait ErrorAwareTrait {

/**
 * Errors.
 *
 * @var array
 */
    protected $_errors = [];

/**
 * Handle multiple errors.
 *
 * @var bool
 */
    protected $_multiple = false;

/**
 * Log the error(s).
 *
 * @todo Implement log.
 * @var bool
 */
    protected $_log = false;


/**
 * Check if has error(s).
 *
 * @return bool
 */
    final public function hasError(): bool {
        return !empty($this->_errors);
    }

/**
 * Get last error message.
 *
 * @return string
 */
    final public function getLastError(): ?string {
        return end($this->_errors);
    }

/**
 * Consume last error.
 *
 * @return string
 */
    final public function consumeLastError(): ?string {
        return array_pop($this->_errors);
    }

/**
 * Get error(s).
 *
 * @return array
 */
    final public function getErrors(): array {
        return $this->_multiple === true ? $this->_errors : end($this->_errors);
    }

/**
 * Consume errors.
 *
 * @return array
 */
    final public function consumeErrors() {
        $errors = $this->_errors;
        $this->_errors = [];
        return $errors;
    }

/**
 * Set error(s).
 *
 * @param array|string $error Error.
 * @return $this
 */
    protected function _setError(array|string $error, ) {
        if ($this->_multiple === false) {
            $this->_errors = [];
        }

        foreach ((array)$error as $e) {
            $this->_errors[] = $e;
            if ($this->_log === true) {
                Log::error($e);
            }
        }
        return $this;
    }

}
