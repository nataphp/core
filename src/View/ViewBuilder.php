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

use Nata\Core\App;
use Nata\Http\Request;
use Nata\Http\Response;
use Nata\Event\Manager;
use Nata\Utility\Inflector;
use MissingViewException;

/**
 * View builder.
 */
class ViewBuilder {


/**
 * Build view instance.
 *
 * @param string $viewClass View class name.
 * @param \Nata\Http\Request $request Request instance.
 * @param \Nata\Http\Response $response Request instance.
 * @param array $options View options
 * @return \Nata\View\View
 * @throws \NataException
 */
    public static function build($viewClass = null, Request $request = null, Response $response = null, Manager $eventManager = null, array $options = array()) {
        if ($request instanceof Response) {
            $response = $request;
        }

        if ($viewClass instanceof Request) {
            $request = $viewClass;
            $viewClass = null;
        }

        if ($viewClass === null) {
            $viewClass = 'View';
        } else {
            $viewClass = Inflector::camelize($viewClass);
        }

        $className = App::className($viewClass, 'View');
        if (!$className) {
            throw new MissingViewException(array($viewClass));
        }

        return new $className($request, $response, $eventManager, $options);
    }

}
