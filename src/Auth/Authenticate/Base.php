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

use Nata\Core\App;
use Nata\Core\NataObject;
use Nata\Http\Request;
use Nata\Http\Response;
use Nata\ORM\TableRegistry;
use Nata\Auth\PasswordHasherFactory;

/**
 * Base for authenticate classes.
 */

abstract class Base extends NataObject {

/**
 * Default config for this object.
 *
 * - `fields` The fields to use to identify a user by.
 * - `userModel` The alias for users table, defaults to Users.
 * - `scope` Additional conditions to use when looking up and authenticating users,
 *    i.e. `['Users.is_active' => 1].`
 * - `contain` Extra models to contain and store in session.
 * - `passwordHasher` Password hasher class. Can be a string specifying class name
 *    or an array containing `className` key, any other keys will be passed as
 *    config to the class. Defaults to 'Default'.
 *
 * @var array
 */
    protected $_defaultConfig = array(
        'fields' => array(
            'username' => array(
                'username',
                'email'
            ),
            'password' => 'password'
        ),
        'userModel' => 'Users',
        'scope' => array(),
        'contain' => null,
        'passwordHasher' => 'Php'
    );

/**
 * Password hasher instance.
 *
 * @var AbstractPasswordHasher
 */
    protected $_passwordHasher;

/**
 * Whether the password needs to be rehashed.
 *
 * @var bool
 */
    protected $_needsPasswordRehash = false;

/**
 * Base authenticate construct.
 *
 * @param array $config Authenticate configuration
 * @return void
 */
    public function __construct(array $config = array()) {
        $this->config($config + $this->_defaultConfig);
    }

/**
 * Get user that matches the data.
 *
 * @param string $username Username value from request data
 * @param string $password Plain text password from request data
 * @return array|bool Array with user data or false if not found
 */
    protected function _findUser($username, $password = null) {
        $passwordFieldName = $this->_config['fields']['password'];
        $result = $this->_query($username);

        $passwordHasher = $this->passwordHasher();
        if ($result && $passwordHasher->check($password, $result->get($passwordFieldName))) {
            $result->unsetProperty($passwordFieldName);
            $data = $result->toArray();
            $data['model'] = $this->_config['userModel'];
            return $data;
        }

        return false;
    }

/**
 * Get query result.
 *
 * @param string $username Username value from request data
 * @return \Nata\ORM\Entity|null Query result
 */
    protected function _query($username) {
        $conditions = [];
        $table = $this->_getTable();

        $fields = (array)$this->_config['fields']['username'];
        foreach ($fields as $field) {
            if ($table->hasField($field)) {
                $conditions[$field] = $username;
            }
        }

        if (!empty($this->_config['scope'])) {
            $conditions = array_merge(
                $conditions,
                $this->_config['scope']
            );
        }

        return $table
            ->find()
            ->where($conditions)
            ->contain($this->_config['contain'])
            ->hydrate(true)
            ->first();
    }

/**
 * Return password hasher object
 *
 * @return AbstractPasswordHasher Password hasher instance
 * @throws \RuntimeException If password hasher class not found or
 *   it does not extend AbstractPasswordHasher
 */
    public function passwordHasher() {
        if ($this->_passwordHasher) {
            return $this->_passwordHasher;
        }

        $passwordHasher = $this->_config['passwordHasher'];
        return $this->_passwordHasher = PasswordHasherFactory::build($passwordHasher);
    }

/**
 * Returns whether or not the password stored in the repository for the logged in user
 * requires to be rehashed with another algorithm
 *
 * @return bool
 */
    public function needsPasswordRehash() {
        return $this->_needsPasswordRehash;
    }

/**
 * Authenticate a user based on the request information.
 *
 * @param \Nata\Http\Request $request Request to get authentication information from.
 * @param \Nata\Http\Response $response A response object that can have headers added.
 * @return mixed Either false on failure, or an array of user data on success.
 */
    abstract public function authenticate(Request $request, Response $response);

/**
 * Get a user based on information in the request. Primarily used by stateless authentication
 * systems like basic and digest auth.
 *
 * @param \Nata\Http\Request $request Request object.
 * @return mixed Either false or an array of user information
 */
    protected function _getTable() {
        return TableRegistry::get($this->config('userModel'));
    }

/**
 * Get a user based on information in the request. Primarily used by stateless authentication
 * systems like basic and digest auth.
 *
 * @param \Nata\Http\Request $request Request object.
 * @return mixed Either false or an array of user information
 */
    public function getUser(Request $request) {
        return false;
    }

/**
 * Handle unauthenticated access attempt. In implementation valid return values
 * can be:
 *
 * - Null - No action taken, AuthComponent should return appropriate response.
 * - Nata\Http\Response - A response object, which will cause AuthComponent to
 *   simply return that response.
 *
 * @param \Nata\Http\Request $request A request object.
 * @param \Nata\Http\Response $response A response object.
 * @return void
 */
    public function unauthenticated(Request $request, Response $response) {}

/**
 * Returns a list of all events that this authenticate class will listen to.
 *
 * An authenticate class can listen to following events fired by AuthComponent:
 *
 * - `Auth.afterIdentify` - Fired after a user has been identified using one of
 *   configured authenticate class. The callback function should have signature
 *   like `afteIndentify(Event $event, array $user)` when `$user` is the
 *   identified user record.
 *
 * - `Auth.logout` - Fired when AuthComponent::logout() is called. The callback
 *   function should have signature like `logout(Event $event, array $user)`
 *   where `$user` is the user about to be logged out.
 *
 * @return array List of events this class listens to. Defaults to `[]`.
 */
    public function implementedEvents() {
        return array();
    }

}
