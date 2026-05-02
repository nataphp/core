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

namespace Nata\Http\Exception;

use Nata\I18n\Time;

/**
 * Nata\Error\Exception is used a base class for NataPHP's internal exceptions.
 * In general framework errors are interpreted as 500 code errors.
 */
class UnderMaintenanceException extends HttpException {

/**
 * Default attributes.
 *
 * - 'until': Date/Time that is expected to be back online.
 *
 * @var array
 */
    protected $_attributes = [
        'until' => null
    ];

/**
 * Default message.
 *
 * @var string
 */
    protected $_message = 'Website currently under maintenance. We promise to return soon. Thank you.';

/**
 * Constructor
 *
 * @param string $message If no message is given 'Internal Server Error' will be the message
 * @param string $code Status code, defaults to 500
 */
    public function __construct($message = null, $code = 503) {
        if ($message instanceof Time) {
            $this->_attributes['until'] = $message;
        } elseif (is_array($message)) {
            $this->_attributes = array_merge($this->_attributes, $message);
            if (isset($this->_attributes['until']) && $this->_attributes['until'] && !($this->_attributes['until'] instanceof Time)) {
                $this->_attributes['until'] = new Time($this->_attributes['until']);
            }
        }

        $displayMessage = $this->_attributes['message'] ?? null;
        if (!$displayMessage && $this->_attributes['until']) {
            $displayMessage = sprintf('Website currently under maintenance. We promise to return in %s. Thank you.', $this->_attributes['until']->timeAgoInWords());
        }
        if (!$displayMessage) {
            $displayMessage = $this->_message;
        }

        parent::__construct($displayMessage, $code);
    }

}
