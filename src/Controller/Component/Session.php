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
use phpDocumentor\Reflection\Types\Boolean;

/**
 * The CakePHP SessionComponent provides a way to persist client data between
 * page requests. It acts as a wrapper for the `$_SESSION` as well as providing
 * convenience methods for several `$_SESSION` related functions.
 */

class Session extends Component {

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
    public function write($name, $value = null, $timeout = null): void {
        $this->_request->getSession()->write($name, $value, $timeout);
    }

/**
 * Used to read a session values for a key or return values for all keys.
 *
 * In your controller: $this->Session->read('Controller.sessKey');
 * Calling the method without a param will return all session vars
 *
 * @param string $name the name of the session key you want to read
 * @return mixed value from the session vars
 */
    public function read($name = null) {
        return $this->_request->getSession()->read($name);
    }

/**
 * Wrapper for SessionComponent::del();
 *
 * In your controller: $this->Session->delete('Controller.sessKey');
 *
 * @param string $name the name of the session key you want to delete
 * @return boolean true is session variable is set and can be deleted, false is variable was not set.
 */
    public function delete($name): void {
        $this->_request->getSession()->delete($name);
    }

/**
 * Used to check if a session variable is set.
 *
 * In your controller: $this->Session->check('Controller.sessKey');
 *
 * @param string $name the name of the session key you want to check
 * @return boolean true is session variable is set, false if not
 */
    public function check($name): bool {
        return $this->_request->getSession()->check($name);
    }

/**
 * Used to renew a session id
 *
 * In your controller: $this->Session->renew();
 *
 * @return void
 */
    public function renew(): void {
        $this->_request->getSession()->renew();
    }

/**
 * Used to destroy sessions
 *
 * In your controller: $this->Session->destroy();
 *
 * @return void
 */
    public function destroy(): void {
        $this->_request->getSession()->destroy();
    }

/**
 * Get/Set the session id.
 *
 * When fetching the session id, the session will be started
 * if it has not already been started.  When setting the session id,
 * the session will not be started.
 *
 * @param string $id Id to use (optional)
 * @return string The current session id.
 */
    public function id($id = null): string {
        return $this->_request->getSession()->id($id);
    }

/**
 * Returns a bool, whether or not the session has been started.
 *
 * @return boolean
 */
    public function started() {
        return $this->_request->getSession()->started();
    }

}
