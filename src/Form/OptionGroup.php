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

/**
 * Represents an <optgroup> containing a set of Option instances.
 */
class OptionGroup {

/**
 * Group label (rendered as the <optgroup label="…"> attribute).
 *
 * @var string
 */
    protected string $_label;

/**
 * Options within this group, keyed by their value.
 *
 * @var array<string, \Nata\Form\Option>
 */
    protected array $_options = [];


/**
 * Construct.
 *
 * @param string $label Group label
 */
    public function __construct(string $label) {
        $this->_label = $label;
    }

/**
 * Get group label.
 *
 * @return string
 */
    public function label(): string {
        return $this->_label;
    }

/**
 * Add an option to this group.
 *
 * @param \Nata\Form\Option $option Option instance
 * @return void
 */
    public function add(Option $option): void {
        $this->_options[$option->value()] = $option;
    }

/**
 * Get all options in this group.
 *
 * @return array<string, \Nata\Form\Option>
 */
    public function options(): array {
        return $this->_options;
    }

/**
 * To array.
 *
 * @return array
 */
    public function toArray(): array {
        $array = [];
        foreach ($this->_options as $value => $option) {
            $array[$value] = $option->toArray();
        }
        return $array;
    }

}
