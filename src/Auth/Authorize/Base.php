<?php
/**
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         2.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
namespace Nata\Auth\Authorize;

use Nata\Core\NataObject;
use Nata\Http\Request;
use Nata\Controller\Component\Auth;

/**
 * Abstract base authorization adapter for AuthComponent.
 *
 * @see Component\Auth::$authenticate
 */
abstract class Base extends NataObject {

/**
 * Component\Auth instance for getting more components.
 *
 * @var \Nata\Controller\Component\Auth
 */
    protected $_auth;

/**
 * Default config for authorize objects.
 *
 * @var array
 */
    protected $_defaultConfig = array();


/**
 * Constructor
 *
 * @param ComponentRegistry $registry The controller for this request.
 * @param array $config An array of config. This class does not use any config.
 */
    public function __construct(Auth $auth, array $config = array()) {
        $this->_auth = $auth;
        $this->config($config + $this->_defaultConfig);
    }

/**
 * Checks user authorization.
 *
 * @param array $user Active user data
 * @param \Nata\Http\Request $request Request instance.
 * @return bool
 */
    abstract public function authorize($user, Request $request);

}
