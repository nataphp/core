<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.0.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Nata\Controller\Component;

use Nata\Controller\Component;
use Nata\Http\Response;
use Nata\Http\Request;
use InvalidCsrfTokenException;
use Nata\Utility\Security;

/**
 * Provides CSRF protection & validation.
 *
 * This component adds a CSRF token to a cookie. The cookie value is compared to
 * request data, or the X-CSRF-Token header on each PATCH, POST,
 * PUT, or DELETE request.
 *
 * If the request data is missing or does not match the cookie data,
 * an InvalidCsrfTokenException will be raised.
 *
 * This component integrates with the \Nata\Form\Form automatically and when
 * used together your forms will have CSRF tokens automatically added
 * when \Component\Form is used in a controller.
 */
class Csrf extends Component {

/**
 * Default config for the CSRF handling.
 *
 *  - cookieName = The name of the cookie to send.
 *  - expiry = How long the CSRF token should last. Defaults to browser session.
 *  - secure = Whether or not the cookie will be set with the Secure flag.
 *    When set to null, it will be enabled automatically on HTTPS requests.
 *  - httpOnly = Whether or not the cookie will be set with the HttpOnly flag. Defaults to false.
 *  - sameSite = SameSite cookie policy. Defaults to 'strict'.
 *  - field = The form field to check. Changing this will also require configuring
 *    FormHelper.
 *
 * @var array
 */
    protected $_defaultConfig = [
        'cookieName' => 'csrfToken',
        'expiry' => 0,
        'secure' => null,
        'httpOnly' => false,
        'sameSite' => 'strict',
        'field' => '_csrfToken',
    ];


/**
 * Startup callback.
 *
 * @param \Nata\Event\Event $event Event instance.
 * @return void
 */
    public function startup($event) {
        $controller = $event->getSubject();
        $request = $controller->request;
        $response = $controller->response;

        $cookie = $request->cookies($this->_config['cookieName']);
        if ($cookie) {
            $request->params['_csrfToken'] = $cookie;
        }

        if ($request->is('requested')) {
            return;
        }

        if ($request->is('get') && $cookie === null) {
            $this->_setCookie($request, $response);
            return;
        }

        if ($request->is(['put', 'post', 'delete', 'patch'])) {
            $this->_validateToken($request);
            $request->data($this->_config['field'], null);
        }
    }

/**
 * Set the cookie in the response.
 *
 * Also sets the request->params['_csrfToken'] so the newly minted
 * token is available in the request data.
 *
 * @param \Nata\Http\Request $request The request object.
 * @param \Nata\Http\Response $response The response object.
 * @return void
 */
    protected function _setCookie(Request $request, Response $response) {
        $value = hash('sha512', Security::randomBytes(16), false);
        $request->params['_csrfToken'] = $value;

        $secure = $this->_config['secure'];
        if ($secure === null) {
            $secure = $request->is('ssl');
        }
        $response->cookie([
            'name' => $this->_config['cookieName'],
            'value' => $value,
            'expire' => $this->_config['expiry'],
            'path' => $request->webroot,
            'secure' => $secure,
            'sameSite' => $this->_config['sameSite'],
            'httpOnly' => $this->_config['httpOnly'],
        ]);
    }

/**
 * Validate the request data against the cookie token.
 *
 * @param \Nata\Http\Request $request The request to validate against.
 * @throws \InvalidCsrfTokenException when the CSRF token is invalid or missing.
 * @return void
 */
    protected function _validateToken(Request $request) {
        $cookie = $request->cookies($this->_config['cookieName']);
        $post = $request->data($this->_config['field']);
        $header = $request->getHeaderLine('X-CSRF-Token');

        if (!$cookie) {
            throw new InvalidCsrfTokenException('Missing CSRF token cookie');
        }

        $postMatches = is_string($post) && hash_equals($cookie, $post);
        $headerMatches = is_string($header) && hash_equals($cookie, $header);
        if (!$postMatches && !$headerMatches) {
            throw new InvalidCsrfTokenException('CSRF token mismatch.');
        }
    }

}
