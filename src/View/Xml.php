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
 * XML view.
 */
class Xml extends View {

/**
 * Xml extension.
 *
 * @var string
 */
    protected $_ext = '.xml';


/**
 * Render XML view.
 * Uses Smarty as rendering engine.
 *
 * @param string $template Name of the view template to use.
 * @param string $layout Name of the view layout to use.
 * @return string
 */
    public function render($template = null, $layout = null) {
        if ($this->response) {
            $this->response->type('xml');
        }
        return parent::render($template, $layout);
    }
    
}
