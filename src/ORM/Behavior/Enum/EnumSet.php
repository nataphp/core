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

namespace Nata\ORM\Behavior\Enum;

use JsonSerializable;
use ArrayIterator;
use IteratorIterator;

/**
 * Holds set of enumeration.
 */
class EnumSet extends IteratorIterator implements JsonSerializable {

/**
 * Alias name.
 *
 * @var string
 */
    protected $_alias;

/**
 * Enum set.
 *
 * @var array
 */
    protected $_values = [];

/**
 * Enum set.
 *
 * @var array
 */
    protected $_index = 0;


/**
 * Constructor.
 *
 * @param string $alias List alias
 * @param array $values Enum list values
 * @return void
 */
    public function __construct(string $alias, array $values) {
        $this->_alias = $alias;
        foreach ($values as $value => $text) {
            $values[$value] = new EnumItem($value, $text);
        }
        $this->_values = $values;
        parent::__construct(new ArrayIterator($values));
    }

/**
 * Get enum set alias/field name.
 *
 * @return string
 */
    public function getAlias() {
        return $this->_alias;
    }

/**
 * Get enum values or value, if given.
 *
 * @param string $value Value to get from set
 * @return array|EnumItem
 */
    public function __get($value) {
        return $this->get($value);
    }

/**
 * Get enum values or value, if given.
 *
 * @param string $value Value to get from set
 * @return array|EnumItem
 */
    public function get($value = null) {
        if ($value === null) {
            return $this->_values;
        }
        return isset($this->_values[$value]) ? $this->_values[$value] : null;
    }

/**
 * Return a new EnumSet containing only matching items.
 *
 * Pass an array for an allowlist, or a callable that receives an EnumItem and returns bool.
 *
 * @param array|callable $filter Allowlist of values, or a callable predicate.
 * @return EnumSet New EnumSet with only the matching values.
 */
    public function filter(array|callable $filter) {
        if (is_callable($filter)) {
            return new EnumSet($this->_alias, array_filter($this->_values, $filter));
        }
        $index = array_flip($filter);
        return new EnumSet($this->_alias, array_intersect_key($this->_values, $index));
    }

/**
 * Return a new EnumSet with the given values removed (denylist).
 *
 * @param array $values Values to exclude.
 * @return EnumSet New EnumSet without the excluded values.
 */
    public function except(array $values) {
        $index = array_flip($values);
        return new EnumSet($this->_alias, array_diff_key($this->_values, $index));
    }

/**
 * Check if given value is valid.
 *
 * @param string $value Value to check
 * @param bool $strict Strict check
 * @return bool True if value if valid, false otherwise
 */
    public function isValid($value, $strict = false) {
        return in_array($value, array_keys($this->_values), $strict);
    }

/**
 * jsonSerialize.
 *
 * @return mixed
 */
    public function jsonSerialize(): mixed {
        return $this->_values;
    }

}