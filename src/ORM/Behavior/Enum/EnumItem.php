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

use Error;
use JsonSerializable;

/**
 * Holds set of enumeration.
 */
class EnumItem implements JsonSerializable {

/**
 * Enum set.
 *
 * @var array
 */
    protected $_value;

/**
 * Enum set.
 *
 * @var array
 */
    protected $_text;


/**
 * Constructor.
 *
 * @param string $value Value
 * @param \Nata\ORM\Table $table Table instance
 * @return void
 */
    public function __construct(string $value, string $text) {
        $this->_value = $value;
        $this->_text = $text;
    }

/**
 * Get value.
 *
 * @return string
 */
    public function getValue() {
        return $this->_value;
    }

/**
 * Get text.
 *
 * @return string
 */
    public function getText() {
        return $this->_text;
    }

/**
 * __getter.
 *
 * @param string $name Var name
 * @return string
 */
    public function __get(string $name) {
        if (!in_array($name, ['text', 'value'])) {
            throw new Error(sprintf('Property "%s" is not defined.', $name));
        }
        return $this->{'_' . $name};
    }

/**
 * jsonSerialize.
 *
 * @return array
 */
    public function jsonSerialize(): mixed {
        return [
            'value' => $this->_value,
            'text' => $this->_text
        ];
    }

/**
 * __toString.
 *
 * @return string Value
 */
    public function __toString() {
        return $this->_value;
    }

}