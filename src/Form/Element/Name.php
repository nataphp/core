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

namespace Nata\Form\Element;

use Nata\Form\Element\Text;
use Nata\Utility\Validation;

/**
 * (Human) Name element.
 */
class Name extends Text {

/**
 * Template.
 *
 * @var string
 */
    protected $_template = 'text';


/**
 * Validation.
 *
 * @param string $data Data to be validated
 * @return boolean True if valid, false otherwise
 */
    protected function _valid($data) {
        if (!Validation::humanName($data)) {
            $this->_error = __('Invalid characters. Only letters, whitespace and hyphens (-) are allowed.');
            return false;
        }
        return true;
    }

}