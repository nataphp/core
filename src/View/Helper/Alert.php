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

namespace Nata\View\Helper;

use Nata\View\ViewBuilder;
use Nata\View\Helper;

/**
 * View helper library that handles the render of alerts.
 */
class Alert extends Helper {

/**
 * Used to render the message set in Controller.
 *
 * @param string $key The [Message.]key you are rendering in the view.
 * @param array $attrs Additional attributes to use for the creation of this flash message.
 *    Supports the 'params', and 'element' keys that are used in the helper.
 * @return string
 */
    public function get($params = array()) {
        $name = '';
        $session = $this->_view->request->getSession();

        $alerts = array();

        if (isset($params['key'])) {
            $name .= $params['key'];
        }

        if (!empty($name)) {
            $name = '.' . $name;
        }

        if ($session->check('Message.inline' . $name)) {
            $alerts = $session->read('Message.inline' . $name);
            $session->delete('Message.inline' . $name);
        }

        return $alerts;
    }

/**
 * Render each alert.
 *
 * @param array $params Parameters for selecting witch
 * @return string
 */
    public function render($params) {
        $alerts = $this->get($params);
        $html = '';

        if (!empty($alerts)) {
            if (isset($alerts['type'])) {
                $alerts = array($alerts);
            }

            $html .= $this->_render($alerts);

        }

        return $html;
    }

/**
 * Render each alert.
 *
 * @param array $params Parameters for selecting witch
 * @return string
 */
    protected function _render($alerts) {
        $render = '';

        $view = ViewBuilder::build();
        $view->compileCheck(false);

        foreach ($alerts as $alert) {
            if (is_array($alert) && !isset($alert['type'])) {
                $render .= $this->_render($alert);
                continue;
            }

            // This is temporary, this should place the default icons
            if (is_bool($alert['icon'])) {
                $alert['icon'] = null;
            }

            $view->set('alert', $alert);
            $render .= $view->render('/Alert/' . $alert['type']);

        }

        return $render;
    }

}
