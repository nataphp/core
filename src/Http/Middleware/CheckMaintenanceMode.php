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

namespace Nata\Http\Middleware;

use Nata\Core\ConfigAwareTrait;
use Nata\Http\Runner;
use Nata\Http\Request;
use Nata\Http\Response;
use Nata\Core\Configure;
use Nata\Http\Server\MiddlewareInterface;
use Nata\Http\Exception\UnderMaintenanceException;
use Nata\Utility\Inflector;
use Nata\I18n\Time;

/**
 * Check request if application is under maintenance mode.
 */
class CheckMaintenanceMode implements MiddlewareInterface {

    use ConfigAwareTrait;

/**
 * Config directory.
 *
 * @var string
 */
    protected $_defaultConfig = [
        'enabled' => false,
        'level' => 1,
        'until' => null,
        'message' => null,
        'allowedIp' => [],
        'controllers' => '*'
    ];

/**
 * Constructor.
 *
 * Processes an incoming server request in order to produce a response.
 * If unable to produce the response itself, it may delegate to the provided
 * request handler to do so.
 *
 * @param array $options Options
 */
    public function __construct(array $options = []) {
        $options += (array)Configure::read('maintenance');

        if (isset($options['until']) && !empty($options['until'])) {
            $options['until'] = new Time($options['until']);
        }

        $this->config($options);
    }

/**
 * Process an incoming server request.
 *
 * Processes an incoming server request in order to produce a response.
 * If unable to produce the response itself, it may delegate to the provided
 * request handler to do so.
 */
    public function process(Request $request, Response $response, Runner $next) {
        if ($this->_isEnabled($request, $response)) {
            throw new UnderMaintenanceException($this->config());
        }
        return $next->process($request, $response, $next);
    }

/**
 * Check if maintenance mode is enabled.
 *
 * Processes an incoming server request in order to produce a response.
 * If unable to produce the response itself, it may delegate to the provided
 * request handler to do so.
 */
    protected function _isEnabled(Request $request, Response $response) {
        if (!$this->config('enabled')) {
            return false;
        }

        if (!$this->_enabledUntilTime()) {
            return false;
        }

        if (!$this->_matchesController($request)) {
            return false;
        }

        if ($this->_allowedIp($request)) {
            return false;
        }

        return true;
    }

/**
 * Check if maintenance mode is enabled.
 *
 * Processes an incoming server request in order to produce a response.
 * If unable to produce the response itself, it may delegate to the provided
 * request handler to do so.
 */
    protected function _enabledUntilTime() {
        $until = $this->config('until');
        if (!$until) {
            return true;
        }
        return (new Time($until))->isFuture();
    }

/**
 * Check if maintenance mode is enabled.
 *
 * Processes an incoming server request in order to produce a response.
 * If unable to produce the response itself, it may delegate to the provided
 * request handler to do so.
 */
    protected function _matchesController(Request $request) {
        $controllers = $this->config('controllers');
        if ($controllers === '*') {
            return true;
        }

        foreach ($controllers as $controller => $actions) {
            if (is_int($controller)) {
                [$controller, $action] = splitter($actions, '::');
                $actions = [$action ? $action : '*'];
            }

            [$plugin, $controller] = pluginSplit($controller);
            if ($plugin) {
                $plugin = Inflector::dasherize($plugin);
                if ($plugin && $request->getParams('plugin') && $plugin !== $request->getParams('plugin')) {
                    continue;
                }
            }

            [$prefix, $controller] = splitter($controller, '/');
            if (!$controller) {
                $controller = $prefix;
                $prefix = '*';
            }

            if ($prefix) {
                $prefix = Inflector::dasherize($prefix);
                if ($prefix !== '*' && $request->getParams('prefix') !== $prefix) {
                    continue;
                }
            }

            $controller = Inflector::dasherize($controller);
            if ($controller !== '*' && $request->getParams('controller') !== $controller) {
                continue;
            }

            $actions = (array)$actions;
            if (!in_array('*', $actions) && !in_array($request->getParams('action'), $actions)) {
                continue;
            }

            return true;
        }

        return false;
    }

/**
 * Check if maintenance mode is enabled.
 *
 * Processes an incoming server request in order to produce a response.
 * If unable to produce the response itself, it may delegate to the provided
 * request handler to do so.
 */
    protected function _allowedIp(Request $request) {
        $ips = (array)$this->config('allowedIp');
        if (empty($ips)) {
            return false;
        }
        return in_array($request->clientIp(), $ips);
    }

}
