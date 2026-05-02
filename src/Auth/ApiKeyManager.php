<?php
/**
 * NataPHP Framework.
 *
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

namespace Nata\Auth;

/**
 * API keys management and switching API key when quota is exceeded.
 *
 * THIS IS STILL A WORK IN PROGRESS (it's not ready for production yet).
 */
class ApiKeyManager {

/**
 * List of API keys.
 *
 *
 * @var array
 */
    protected $_apiKeys = [];

/**
 * List of API keys.
 *
 *
 * @var array
 */
    protected $_defaultOptions = [
        'quotaRenewalInterval' => 'daily', // daily, weekly, monthly
    ];


/**
 * Constructor.
 *
 * @param array $apiKeys API keys
 * @return void
 */
    public function __construct(string|array $apiKeys) {
        $this->_apiKeys = (array)$apiKeys;
    }

/**
 * Constructor.
 *
 * @param array $apiKeys API keys
 * @return void
 */
    public function _normalizeKeys(array $apiKeys) {

        $this->_apiKeys = $apiKeys;
    }

/**
 * Constructor.
 *
 * @return void
 */
    public function quotaExceeded(string $apiKey) {
        return false;
    }




}
