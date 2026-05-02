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

namespace Nata\View;

use Exception;

/**
 * JSON view.
 */
class Json extends View {

/**
 * Cache instance.
 *
 * @var \Nata\View\Cache
 */
    protected $_cache;


/**
 * Render JSON view.
 *
 * @param string $template Name of the view template to use.
 * @param string $layout Name of the view layout to use.
 * @return string
 */
    public function render($template = null, $layout = null) {
        $this->response->type('json');

        if ($this->_cache && $this->_cache->check()) {
            return $this->_cache->fetch();
        }

        $this->loadHelpers();
        if ($alerts = $this->_getAlerts()) {
            $this->set('alerts', $alerts);
        }

        try {
            $data = json_encode(array_filter($this->vars, function ($val) {
                return $val !== null;
            }));
            if ($this->_cache && $data) {
                $this->_cache->write($data);
            }
            return $data;
        } catch (Exception $exception) {
            if ($exception->getPrevious() && $exception->getPrevious()->getPrevious()) {
                throw $exception->getPrevious()->getPrevious();
            } elseif ($exception->getPrevious()) {
                throw $exception->getPrevious();
            } else {
                throw $exception;
            }
        }

    }

/**
 * Set cache configuration.
 * By default cache key uses current:
 *  - template name
 *  - view path
 *  - theme (if set)
 *  - layout name
 *
 * ### Usage
 *
 * // Simple key and config set
 * $view->cache('my_view_key', 'config_name');
 *
 * // Disable cache
 * $view->cache(false);
 *
 * @param string|bool $key Cache key
 * @param string $config Cache configuration
 * @return \Nata\View\View View instance
 * @throws \RunTimeException
 */
    public function cache($key = null, $config = 'default') {
        if ($key === null) {
            return $this->_cache;
        }

        $cache = false;
        if (is_string($key)) {
            $cache = new Cache($key, $config);
        }

        $this->_cache = $cache;

        return $this;
    }

/**
 * Get alerts.
 *
 * @return array
 */
    private function _getAlerts() {
        if ($this->helpers()->loaded('Alert')) {
            return $this->Alert->get('notifier');
        }
        return [];
    }

}
