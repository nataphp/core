<?php
/**
 * NataPHP Framework
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

use Nata\View\CellBuilder;

/**
 * Smarty plugin
 * -------------------------------------------------------------
 * File:     function.Cell.php
 * Type:     function
 * Name:     eightball
 * Purpose:  outputs a random magic answer
 * -------------------------------------------------------------
 */
    function smarty_function_Cell($params, Smarty_Internal_Template $template) {
        $cell = $params['cell'] ?? null;
        $data = $params['data'] ?? [];
        $options = $params['options'] ?? [];
        unset($params['cell'], $params['data'], $params['options']);

        // Remaining attributes become named args, matched by parameter name in Cell::render().
        if (!empty($params)) {
            $data['namedArgs'] = $params + ($data['namedArgs'] ?? []);
        }

        return CellBuilder::build($cell, $data, $options)->render();
    }
