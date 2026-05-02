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

namespace Nata\Auth\Authenticate;

use Nata\Http\Request;
use Nata\Http\Response;

/**
 * Form based authentication.
 */
class Form extends Base {

/**
 * Checks the fields to ensure they are supplied.
 *
 * @param \Nata\Http\Request $request The request that contains login information.
 * @param array $fields The fields to be checked.
 * @return bool False if the fields have not been supplied. True if they exist.
 */
    protected function _checkFields(Request $request, array $fields) {
        foreach (['username', 'password'] as $field) {
            $value = $request->data($field);
            if (empty($value) || !is_string($value)) {
                return false;
            }
        }
        return true;
    }

/**
 * Authenticate form data.
 *
 * @param \Nata\Http\Request $request Request instance
 * @param \Nata\Http\Response $response Response instance
 * @return boolean true if is authenticates, false otherwise
 */
    public function authenticate(Request $request, Response $response) {
        $fields = $this->_config['fields'];

        if (!$this->_checkFields($request, $fields)) {
            return false;
        }

        return $this->_findUser(
            $request->data('username'),
            $request->data('password')
        );
    }

}
