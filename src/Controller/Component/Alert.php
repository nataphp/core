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

namespace Nata\Controller\Component;

use Nata\Controller\Component;
use Nata\I18n\Time;
use Nata\Routing\Router;
use Nata\Utility\Html;
use Nata\Utility\Inflector;

/**
 * Alert Component.
 */
class Alert extends Component {

/**
 * Default config.
 *
 * @var array
 */
    protected $_defaultConfig = [
        'title' => '',
        'body' => null,
        'key' => null,
        'url' => null,
        'icon' => true,
        'handler' => '*',
        'type' => 'info',
        'textAlign' => 'left',
        'dismissable' => true,
        'duration' => 10000,
        'dismissTimeout' => 0,
        // Only on growl-like notifications
        'align' => 'center'
    ];

/**
 * List of handlers available.
 *
 * @var array
 */
    protected $_handlers = [
        'notifier',
        'inline'
    ];

/**
 * FontAwesome icon collection.
 *
 * @var array
 */
    protected $_icons = [
        'fontawesome4' => [
            'danger' => 'fa fa-times fa-lg',
            'success' => 'fa fa-check fa-lg',
            'info' => 'fa fa-info-circle fa-lg',
            'warning' => 'fa fa-exclamation-triangle fa-lg'
        ],
    ];


/**
 * Initialization hook method.
 *
 * @param array $config Configuration array
 * @return void
 */
    public function initialize(array $config) {
        $this->_controller->viewBuilder()->loadHelper('Alert');
    }

/**
 * Used to get set messages.
 *
 * In your controller: $this->Alert->get('alert');
 *
 * In your controller: $this->Alert->get('alert.success');
 *
 * @param string $key Message key, default is 'alert'
 * @return array
 */
    public function get($key = 'alert') {
        return $this->_read($key);
    }

/**
 * Consume the alert(s).
 * It will delete the alert after reading.
 *
 * @param string $key Message key, default is 'alert'
 * @see Alert::get()
 * @return array
 */
    public function consume($key = 'alert') {
        $alerts = $this->_read($key, true);
        $this->_request->getSession()->delete($key);
        return $alerts;
    }

/**
 * Read message(s).
 *
 * @param string $key Message key, default is 'alert'
 * @param bool $delete Delete message(s) after reading
 * @return array
 */
    protected function _read($key, $delete = false) {
        $messageKey = 'Message';
        if ($key !== null) {
            $messageKey .= '.' . $key;
        }

        $messages = $this->_request->getSession()->read($messageKey);

        if ($delete) {
            $this->_request->getSession()->delete($messageKey);
        }

        return $messages;
    }

/**
 * Used to set a session variable that can be used to output messages in the view.
 *
 * In your controller: $this->Alert->success('This has been saved');
 *
 * In your controller: $this->Alert->success('This has been saved', 'alert.success');
 *
 * In your controller:
 *  $this->Alert->success([
 *      'title' => 'This has been saved',
 *      'body' => 'Thanks!'
 *  ], 'alert.success');
 *
 * Additional params below can be passed to customize the output, or the Message.[key].
 * You can also set additional parameters when rendering flash messages. See SessionHelper::alert()
 * for more information on how to do that.
 *
 * @param string|array $options Title to be flashed or array of options
 * @param string $key Message key, default is 'alert'
 * @return void
 */
    public function set($options, $key = 'alert') {
        // BC purposes
        $options = $this->_normalizeArguments($options, $key);

        if (empty($options['key'])) {
            $options['key'] = $key;
        }

        $options['hash'] = substr(md5($options['title'] . json_encode((array)$options['body']) . $key), 0, 5);

        $options['id'] = Inflector::slug($options['key'], '-') . '-' . $options['type'] . '-' . $options['hash'];

        if ($options['dismissTimeout'] && !is_numeric($options['dismissTimeout'])) {
            $timeout = new Time($options['dismissTimeout']);
            $options['dismissTimeout'] = $timeout->timestamp();
        }

        if ($options['url']) {
            $options['url'] = Router::url($options['url'], true);
        }

        $options['icon'] = $this->_getIcon($options);

        foreach ($this->_handlers as $handler) {
            if ($options['handler'] !== '*' && $options['handler'] !== $handler) {
                continue;
            }

            $key = $handler . '.' . $options['key'];

            $this->_request->getSession()->write('Message.' . $key, $options);
        }

    }

/**
 * Set success flash alert.
 *
 * @see Alert::set(); for more information
 */
    protected function _normalizeArguments($options, $key) {
        $_options = $this->_defaultConfig;

        if (is_string($options)) {
            $_options['title'] = $options;
        } else {
            $_options['key'] = $key;
        }

        if (is_array($options) && (!isset($options[0]) || isset($options['title']) || isset($options['type']))) {
            $_options = array_merge($_options, $options);
        } else {
            $options = (array)$options;
            $_options['title'] = $options[0];

            if (isset($options[1])) {
                $_options['body'] = $options[1];
            }
        }

        return $_options;
    }

/**
 * Get alert .
 *
 * @see Alert::set(); for more information
 */
    protected function _getIcon(&$options) {
        if (is_bool($options['icon']) || Html::in($options['icon'])) {
            return $options['icon'];
        }

        // URL to icon
        if (strpos($options['icon'], '/') !== false) {
            return Html::elem('<img>', [
                'src' => $options['icon'],

            ]);
        // FontAwesome collection
        } elseif (stripos($options['icon'], 'fontawesome') !== false) {
            $options['icon'] = $this->_getFontAwesomeIcon($options);
        }

        return Html::elem('<i>', '', ['class' => $options['icon']]);
    }

/**
 * Set success flash alert.
 *
 * @see Alert::set(); for more information
 */
    protected function _getFontAwesomeIcon($options) {
        $icon = $options['icon'];

        return isset($this->_icons[$icon][$options['type']]) ? $this->_icons[$icon][$options['type']] : null;
    }

/**
 * Set success flash alert.
 *
 * @see Alert::set(); for more information
 */
    public function success($options, $key = 'alert') {
        // BC purposes
        $options = $this->_normalizeArguments($options, $key);
        $options['type'] = __FUNCTION__;
        $this->set($options, $key);
    }

/**
 * Alias for Alert::danger().
 *
 * @see Alert::set(); for more information
 */
    public function error($options, $key = 'alert') {
        $this->danger($options, $key);
    }

/**
 * Set danger flash alert.
 *
 * @see Alert::set(); for more information
 */
    public function danger($options, $key = 'alert') {
        // BC purposes
        $options = $this->_normalizeArguments($options, $key);
        $options['type'] = __FUNCTION__;
        $this->set($options, $key);
    }

/**
 * Set warning flash alert.
 *
 * @see Alert::set(); for more information
 */
    public function warning($options, $key = 'alert') {
        // BC purposes
        $options = $this->_normalizeArguments($options, $key);
        $options['type'] = __FUNCTION__;
        $this->set($options, $key);
    }

/**
 * Set information flash alert.
 *
 * @see Alert::set(); for more information
 */
    public function info($options, $key = 'alert') {
        // BC purposes
        $options = $this->_normalizeArguments($options, $key);
        $options['type'] = __FUNCTION__;
        $this->set($options, $key);
    }

/**
 * Alias for Alert::info().
 *
 * @see Alert::set(); for more information
 */
    public function information($options, $key = 'alert') {
        $this->info($options, $key);
    }

/**
 * Set cookie with alerts before render.
 *
 * @param \Nata\Event\Event $event Event
 * @return void
 */
    public function beforeRender($event) {
        if (!$this->_controller->request->is('ajax') || !$this->_controller->request->is('json')) {
            $this->_setNotifierCookie();
        }
    }

/**
 * Set cookie with alerts if there's a redirect.
 *
 * @see \Nata\Controller\Controller::beforeRedirect(); for more information
 * @return void
 */
    public function beforeRedirect($event, $url, $status = null, $exit = true) {
        $this->_setNotifierCookie();
    }

/**
 * Set cookie with alerts.
 *
 * @return void
 */
    protected function _setNotifierCookie() {
        $cookie = $this->_controller->loadComponent('Cookie', [
            'encryption' => false
        ]);

        $prevAlerts = [];
        if ($cookie->check('natalerts')) {
            $storedAlerts = $cookie->read('natalerts');
            if ($storedAlerts) {
                $prevAlerts = $storedAlerts;
            }
        }

        $alerts = $this->consume('notifier');
        if (!$alerts) {
            return;
        }

        $cookie->configKey('natalerts', 'expires', '+30 seconds');
        $cookie->write('natalerts', array_merge($prevAlerts, $alerts));
    }

}
