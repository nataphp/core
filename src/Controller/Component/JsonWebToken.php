<?php
/**
 * SessionComponent. Provides access to Sessions from the Controller layer
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       Cake.Controller.Component
 * @since         CakePHP(tm) v 0.10.0.1232
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

namespace Nata\Controller\Component;

use Nata\Controller\Component;
use Nata\Service\Service;

/**
 * The JsonWebToken Component provides a way to generate/renew JWT tokens .
 */

class JsonWebToken extends Component {

/**
 * Used to write a value to a session key.
 *
 * In your controller: $this->Session->write('Controller.sessKey', 'session value');
 *
 * @param string $name The name of the key your are setting in the session.
 *                             This should be in a Controller.key format for better organizing
 * @param string $value The value you want to store in a session.
 * @param integer|string $timeout Time out for when the data being writen should be deleted
 * @return boolean Success
 */
    public function get($payload = []): string {
        return $this->_loadService()->payload($payload)->generate();
    }

/**
 * Used to write a value to a session key.
 *
 * In your controller: $this->Session->write('Controller.sessKey', 'session value');
 *
 * @param string $name The name of the key your are setting in the session.
 *                             This should be in a Controller.key format for better organizing
 * @param string $value The value you want to store in a session.
 * @param integer|string $timeout Time out for when the data being writen should be deleted
 * @return boolean Success
 */
    public function decodePayload(string $token): ?array {
        return $this->_loadService()->decodePayload($token);
    }

/**
 * Used to write a value to a session key.
 *
 * In your controller: $this->Session->write('Controller.sessKey', 'session value');
 *
 * @param string $name The name of the key your are setting in the session.
 *                             This should be in a Controller.key format for better organizing
 * @param string $value The value you want to store in a session.
 * @param integer|string $timeout Time out for when the data being writen should be deleted
 * @return boolean Success
 */
    public function valid(string $token): bool {
        return $this->_loadService()->valid($token);
    }

/**
 * Used to write a value to a session key.
 *
 * In your controller: $this->Session->write('Controller.sessKey', 'session value');
 *
 * @param string $name The name of the key your are setting in the session.
 *                             This should be in a Controller.key format for better organizing
 * @param string $value The value you want to store in a session.
 * @param integer|string $timeout Time out for when the data being writen should be deleted
 * @return boolean Success
 */
    public function refresh(string $token): string {
        return $this->_loadService()->refresh($token);
    }

/**
 * Used to write a value to a session key.
 *
 * In your controller: $this->Session->write('Controller.sessKey', 'session value');
 *
 * @param string $name The name of the key your are setting in the session.
 *                             This should be in a Controller.key format for better organizing
 * @param string $value The value you want to store in a session.
 * @param integer|string $timeout Time out for when the data being writen should be deleted
 * @return boolean Success
 */
    public function needsRenewal(string $token): bool {
        return $this->_loadService()->needsRenewal($token);
    }

/**
 * Load JWT service.
 *
 * @return Service
 */
    protected function _loadService(): Service {
        return $this->loadService('Auth/JsonWebToken', $this->config());
    }

}
