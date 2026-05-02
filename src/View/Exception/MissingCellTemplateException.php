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

namespace Nata\View\Exception;

use Nata\Core\Exception;

/**
 * Nata\Error\Exception is used a base class for NataPHP's internal exceptions.
 * In general framework errors are interpreted as 500 code errors.
 */
class MissingCellTemplateException extends Exception {

/**
 * Exception message.
 *
 * @var string
 */
    protected $_templateMessage = 'Cell template "%s" is missing.';

}
