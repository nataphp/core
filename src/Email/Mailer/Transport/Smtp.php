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
 * SMTP mailer transport.
 * It uses PHPMailer/SMTP library to send message.
 */
class Smtp extends PhpMailer {

/**
 * SMTP configuration.
 *
 * 'host' Either a single hostname or multiple semicolon-delimited hostnames.
 *        You can also specify a different port for each host by using this format: [hostname:port]
 *        "smtp1.example.com:25;smtp2.example.com"
 *        You can also specify encryption type, for example:
 *        "tls://smtp1.example.com:587;ssl://smtp2.example.com:465"
 *        Hosts will be tried in order.
 * 'port' The default SMTP server port.
 * 'secure' What kind of encryption to use on the SMTP connection:
 *          '', 'ssl' or 'tls'
 * 'auth' Whether to use SMTP authentication.
 *        Uses the Username and Password properties.
 * 'username' SMTP username.
 * 'password' SMTP password.
 * 'timeout' The SMTP server timeout in seconds.
 *           Default of 5 minutes (300sec) is from RFC2821 section 4.5.3.2
 *
 * @var array
 */
    private $_defaultConfig = [
        'host' => 'localhost',
        'port' => 25,
        'secure' => '',
        'auth' => true,
        'keepAlive' => false,
        'username' => null,
        'password' => null,
        'timeout' => 300
    ];


/**
 * Send.
 *
 * @return bool True if successful, false otherwise.
 */
    public function send(Message $message) {
        return parent::send($message);
    }

}
