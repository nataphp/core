<?php
/**
 * NataPHP Framework
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
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

namespace Nata\Controller;

use NotFoundException;
use Nata\Controller\Controller;

/**
 * JSON Web Token dispenser Controller.
 * Controller used by NataJS to obtain the JWT.
 */
class Jwt extends Controller {


/**
 * Startup.
 *
 * It does some checks to determine if is the NataJS making
 * the request.
 */
    public function startup() {
        if (!$this->request->is('ajax') || !$this->request->is('json') || !$this->request->is('get')) {
            throw new NotFoundException;
        }
    }

/**
 * Get new JSON.
 */
    public function index() {
        if (!$this->request->is('ajax') || !$this->request->is('json') || !$this->request->is('get')) {
            throw new NotFoundException;
        }

        $this->set('token', $this->JsonWebToken->get());
    }

/**
 * Validate given JWT.
 *
 * @param string $token Token
 */
    public function validate($token) {}

}