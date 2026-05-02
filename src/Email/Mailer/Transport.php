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

namespace Nata\Email\Mailer;

use Nata\Core\App;
use Nata\Core\ConfigAwareTrait;
use Nata\Email\Exception\TransportError;
use Nata\Utility\Inflector;

/**
 * Email transport adapter base class.
 */
abstract class Transport {

    use ConfigAwareTrait;

/**
 * Mailer.
 * 'mail', 'sendmail' or 'smtp'.
 *
 * @var string
 */
    protected $_mailer = 'mail';

/**
 * Sendmail path.
 *
 * @var string
 */
    protected $_sendmail = '/usr/sbin/sendmail';

/**
 * The hostname to use in the Message-ID header and as default HELO string.
 *
 * @var string
 */
    protected $_hostname;

/**
 * Message transport ID.
 *
 * This is used by transport API's that might
 * return a special ID to track the message across
 * the transport provider's service (like sendgrid).
 *
 * @var string
 */
    protected $_id;

/**
 * SMTP Reply.
 *
 * @var string
 */
    protected $_smtpReply;

/**
 * Error.
 *
 * @var array
 */
    protected $_errors = [];


/**
 * Constructor.
 *
 * @param array $parts Email parts.
 */
    public function __construct(array $config = []) {
        $this->config($config);
    }

/**
 * Send.
 *
 * @param Message $message Email message to send
 * @return bool True if successful, false otherwise.
 */
    abstract public function send(Message $message): bool;

/**
 * Check if has error.
 *
 * @return bool True if there's an error, false otherwise.
 */
    public function getId() {
        if ($this->_id === null) {
            return null;
        }
        return $this->_id . ':' . Inflector::dasherize(App::classShortName($this));
    }

/**
 * Get SMTP Reply.
 *
 * @return string SMTP Reply.
 */
    public function getSmtpReply() {
        return $this->_smtpReply;
    }

/**
 * Uniquify email addresses in To, Cc and Bcc to prevent problems.
 * Sendgrid API, for example, doesn't allow same email address on multiple parts.
 *
 * @param Message $message Email message to send
 * @return void
 */
    protected function _uniquifyAddresses(Message $message) {
        if ($message->cc() || $message->bcc()) {
            foreach ($message->to() as $emailAddress) {
                if ($message->cc()) {
                    $message->cc()->remove($emailAddress);
                }
                if ($message->bcc()) {
                    $message->bcc()->remove($emailAddress);
                }
            }

            if ($message->bcc()) {
                foreach ($message->cc() as $emailAddress) {
                    $message->bcc()->remove($emailAddress);
                }
            }

        }
    }

/**
 * Check if has error.
 *
 * @return bool True if there's an error, false otherwise.
 */
    public function hasError() {
        return !empty($this->_errors);
    }

/**
 * Get errors.
 *
 * @return array Errors
 */
    public function getErrors() {
        return $this->_errors;
    }

}
