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

use Nata\Email\Mailer\Transport;
use Nata\Core\ClassLoader;
use Nata\Core\Configure;
use Nata\Email\Mailer\Message;
use PHPMailer\PHPMailer\PHPMailer as PHPMailerLib;

ClassLoader::registerPackage('PHPMailer\\PHPMailer\\', [
    'baseDir' => 'PHPMailer/PHPMailer/src',
    'core' => true
]);

/**
 * PHPMailer email transport.
 */
class PhpMailer extends Transport {

/**
 * SMTP configuration.
 *
 * 'mailer'   Transport type: 'smtp', 'mail' or 'sendmail'.
 * 'sendmail' Path to sendmail binary (used when mailer = 'sendmail').
 * 'host'     Either a single hostname or multiple semicolon-delimited hostnames.
 *            You can also specify a different port for each host by using this format: [hostname:port]
 *            "smtp1.example.com:25;smtp2.example.com"
 *            You can also specify encryption type, for example:
 *            "tls://smtp1.example.com:587;ssl://smtp2.example.com:465"
 *            Hosts will be tried in order.
 * 'port'     The default SMTP server port.
 * 'secure'   What kind of encryption to use on the SMTP connection:
 *            '', 'ssl' or 'tls'
 * 'auth'     Whether to use SMTP authentication.
 *            Uses the Username and Password properties.
 * 'username' SMTP username.
 * 'password' SMTP password.
 * 'timeout'  The SMTP server timeout in seconds.
 *            Default of 5 minutes (300sec) is from RFC2821 section 4.5.3.2
 *
 * @var array
 */
    protected $_defaultConfig = [
        'mailer' => 'smtp',
        'sendmail' => '/usr/sbin/sendmail',
        'host' => 'localhost',
        'port' => 25,
        'secure' => '',
        'auth' => true,
        'username' => null,
        'password' => null,
        'timeout' => 300
    ];

/**
 * PHPMailer instance.
 *
 * @var PHPMailerLib|null
 */
    protected $_phpMailer = null;


/**
 * Send.
 *
 * @param Message $message Email message to send
 * @return bool True if successful, false otherwise.
 */
    public function send(Message $message): bool {
        parent::_uniquifyAddresses($message);

        $phpMailer = $this->_send($message);

        $sent = $phpMailer->send();

        if (!$sent) {
            $this->_errors[] = $phpMailer->ErrorInfo;
            return false;
        }

        $transportId = trim($phpMailer->getLastMessageID(), '<>');

        if (!$message->messageId()) {
            $message->messageId($phpMailer->getLastMessageID());
        }

        $this->_id = $transportId;
        $this->_smtpReply = '250 OK';

        return true;
    }

/**
 * Initialize PHPMailer and populate it from the message.
 *
 * @param Message $message Email message.
 * @return PHPMailerLib Configured PHPMailer instance ready to send.
 */
    protected function _send(Message $message): PHPMailerLib {
        $phpMailer = $this->_loadPhpMailer();

        $phpMailer->CharSet = $message->charset();
        $phpMailer->Encoding = PHPMailerLib::ENCODING_QUOTED_PRINTABLE;
        $phpMailer->Priority = $message->priority();
        $phpMailer->Mailer = $this->config('mailer');
        $phpMailer->Sendmail = $this->config('sendmail');

        if ($phpMailer->Mailer === 'smtp') {
            $phpMailer->Host = $this->config('host');
            $phpMailer->Port = $this->config('port');
            $phpMailer->Timeout = $this->config('timeout');
            $phpMailer->SMTPSecure = $this->config('secure');
            $phpMailer->SMTPAuth = $this->config('auth');
            $phpMailer->Username = $this->config('username');
            $phpMailer->Password = $this->config('password');
        }

        if ($message->from() && !$message->from()->isEmpty()) {
            $phpMailer->From = $message->from()->email();
            $phpMailer->FromName = (string)$message->from()->personal();
        }

        if ($message->to() && !$message->to()->isEmpty()) {
            foreach ($message->to() as $address) {
                $phpMailer->addAddress($address->email(), (string)$address->personal());
            }
        }

        if ($message->cc() && !$message->cc()->isEmpty()) {
            foreach ($message->cc() as $address) {
                $phpMailer->addCC($address->email(), (string)$address->personal());
            }
        }

        if ($message->bcc() && !$message->bcc()->isEmpty()) {
            foreach ($message->bcc() as $address) {
                $phpMailer->addBCC($address->email(), (string)$address->personal());
            }
        }

        if ($message->replyTo() && !$message->replyTo()->isEmpty()) {
            foreach ($message->replyTo() as $address) {
                $phpMailer->addReplyTo($address->email(), (string)$address->personal());
            }
        }

        if ($message->readReceipt() && !$message->readReceipt()->isEmpty()) {
            $phpMailer->ConfirmReadingTo = $message->readReceipt()->first()->email();
        }

        if (!empty($message->attachments())) {
            foreach ($message->attachments() as $attachment) {
                $file = $attachment['file'];
                $content = base64_decode($file->getBase64());
                $mime = $file->mime();
                $name = $attachment['name'];

                if ($attachment['disposition'] === 'inline' && $attachment['contentId']) {
                    $phpMailer->addStringEmbeddedImage($content, $attachment['contentId'], $name, PHPMailerLib::ENCODING_BASE64, $mime);
                } else {
                    $phpMailer->addStringAttachment($content, $name, PHPMailerLib::ENCODING_BASE64, $mime);
                }
            }
        }

        if ($message->header()) {
            foreach ($message->header() as $name => $value) {
                $phpMailer->addCustomHeader($name, $value);
            }
        }

        if (!empty($this->_hostname)) {
            $phpMailer->Hostname = $this->_hostname;
        }

        if ($message->messageId()) {
            $phpMailer->MessageID = $message->messageId();
        }

        $phpMailer->Subject = $message->subject();
        $phpMailer->isHTML(!empty($message->htmlMessage()));

        if ($message->htmlMessage()) {
            $phpMailer->Body = $message->htmlMessage();
            $phpMailer->AltBody = (string)$message->textMessage();
        } else {
            $phpMailer->Body = (string)$message->textMessage();
        }

        return $phpMailer;
    }

/**
 * Initialize PHPMailer.
 *
 * @return PHPMailerLib
 */
    protected function _loadPhpMailer(): PHPMailerLib {
        if ($this->_phpMailer === null) {
            $this->_phpMailer = new PHPMailerLib(Configure::read('debug'));
        }
        return $this->_phpMailer;
    }

}
