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

namespace Nata\Controller\Exception;

use Nata\Http\Exception\HttpException;

/**
 * Nata\Error\Exception is used a base class for NataPHP's internal exceptions.
 * In general framework errors are interpreted as 500 code errors.
 */
class MissingControllerException extends HttpException {

/**
 * Template string that has attributes sprintf()'ed into it.
 *
 * @var string
 */
    protected $_templateMessage = 'Controller class %s could not be found for %s.';


/**
 * Constructor.
 *
 * Allows you to create exceptions that are treated as framework errors and disabled
 * when debug = 0.
 *
 * @param string|array $message Either the string of the error message, or an array of attributes
 *   that are made available in the view, and sprintf()'d into NataException::$_messageTemplate
 * @param string $code The code of the error, is also the HTTP status code for the error.
 * @param \Throwable|null $previous the previous exception.
 */
    public function __construct($message = null, $code = 404) {
        parent::__construct($message, $code);
    }

}
