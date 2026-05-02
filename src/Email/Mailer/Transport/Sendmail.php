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

namespace Nata\Email\Mailer\Transport;

use Nata\Email\Mailer\Message;

/**
 * Sendmail email transport.
 * It uses PHPMailer library to send message.
 */
class Sendmail extends PhpMailer {


/**
 * Send.
 *
 * @param Message $message Email message to send
 * @return bool Sendgrid message key if successful, false otherwise.
 */
    public function send(Message $message) {
        return parent::send($message);
    }

}
