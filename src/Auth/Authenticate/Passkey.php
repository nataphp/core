<?php
/**
 * NataPHP Framework
 *
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 *
 * @copyright     Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @link          http://nataphp.com NataPHP Project
 * @since         NataPHP 1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\Auth\Authenticate;

use Nata\Core\App;
use Nata\Http\Request;
use Nata\Http\Response;
use Nata\ORM\TableRegistry;
use Exception;

/**
 * Passkey (WebAuthn) authentication.
 *
 * Authenticates when the request contains assertion data from a passkey
 * (e.g. POST body with `assertion` key from navigator.credentials.get()).
 *
 * Config:
 * - `userModel` The alias for users table, defaults to Users.
 * - `scope` Additional conditions when loading the user (e.g. status).
 * - `webauthnManager` WebAuthn\Manager instance or callable that returns it.
 *   Required for verification. Set by the controller when configuring Auth, e.g.:
 *   'Passkey' => ['webauthnManager' => $this->loadService('WebAuthn/Manager')]
 */
class Passkey extends Base {

/**
 * Default config (no password/username fields).
 *
 * @var array
 */
    protected $_defaultConfig = [
        'userModel' => 'Users',
        'scope' => [],
        'contain' => null,
        'webauthnManager' => null,
    ];

/**
 * Authenticate using passkey assertion in the request.
 *
 * @param \Nata\Http\Request $request Request instance
 * @param \Nata\Http\Response $response Response instance
 * @return array|false User data array on success, false otherwise
 */
    public function authenticate(Request $request, Response $response) {
        $assertionData = $request->data('assertion');
        if (empty($assertionData) || !is_array($assertionData)) {
            return false;
        }

        $manager = $this->_getWebAuthnManager();
        if (!$manager) {
            return false;
        }

        try {
            $passkey = $manager->verifyAuthenticationResponse($assertionData);
        } catch (Exception $e) {
            return false;
        }

        $user = $this->_getUserById($passkey->user_id);
        if (!$user) {
            return false;
        }

        return $user;
    }

/**
 * Get the WebAuthn Manager instance (from config).
 *
 * @return \App\Service\WebAuthn\Manager|null
 */
    protected function _getWebAuthnManager() {
        $manager = $this->config('webauthnManager');
        if ($manager === null) {
            return null;
        }
        if (is_callable($manager)) {
            $manager = $manager();
        }
        return $manager;
    }

/**
 * Load user by ID and return as array (respecting scope).
 *
 * @param int|string $userId User ID
 * @return array|false User data or false
 */
    protected function _getUserById($userId) {
        $table = TableRegistry::get($this->config('userModel'));
        $conditions = [$table->aliasField('id') => $userId];
        if (!empty($this->_config['scope'])) {
            $conditions = array_merge($conditions, $this->_config['scope']);
        }
        $entity = $table
            ->find()
            ->where($conditions)
            ->contain($this->_config['contain'])
            ->hydrate(true)
            ->first();
        if (!$entity) {
            return false;
        }
        return $entity->toArray();
    }

}
