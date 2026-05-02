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

use Nata\Utility\Validation;

/**
 * Month element.
 */
class Month extends Input {

/**
 * Default date format.
 *
 * @var string
 */
    protected $_format = 'Y-m';
    

/**
 * Pseudo-constructor.
 *
 * @return void
 */
    public function initialize($config) {
        parent::initialize($config);
        if (!$this->_prepend) {
             $this->prepend('<i class="fa fa-calendar"></i>');
        }
    }

/**
 * Validation.
 *
 * @param string $data Data to be validated
 * @return boolean True if valid, false otherwise
 */
    protected function _valid($data) {
        if (!Validation::date($data, $this->_format, $this->_match)) {
            $this->_error = __('Must be a valid date in the format "%s".', array($this->_format));
            return false;
        }
        return true;
    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 */
    public function beforeRender($event) {
        parent::beforeRender($event);
        $this->_getView()->extend(array('input'));
    }

}
