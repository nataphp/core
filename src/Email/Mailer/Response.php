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

use InvalidArgumentException;

/**
 * Email response.
 *
 * THIS IS NOT BEING USED AT THE MOMENT.
 *
 * @property string $_transportId Transport ID.
 * @property string $_smtpReply SMTP Reply.
 * @property int $_statusCode Status code.
 * @property string $_messageId Message ID.
 */
class Response {

/**
 * Transport ID.
 *
 * @var string
 */
    protected string $_transportId;

/**
 * SMTP Reply.
 *
 * @var string
 */
    protected string $_smtpReply;

/**
 * Status code.
 *
 * @var int
 */
    protected int $_statusCode;

/**
 * Message ID.
 *
 * @var string
 */
    protected string $_messageId;

/**
 * Constructor.
 *
 * @param array $config Config.
 */
    public function __construct(array $properties = []) {
        $this->transportId($properties['transportId'] ?? null);
        $this->smtpReply($properties['smtpReply'] ?? null);
        $this->messageId($properties['messageId'] ?? null);
    }

/**
 * Get/set transport ID.
 *
 * @param string $transportId Transport ID.
 * @return string|self Transport ID.
 */
    public function transportId(string $transportId = null) {
        if ($transportId === null) {
            return $this->_transportId;
        }

        if ($this->_transportId !== null) {
            throw new InvalidArgumentException('Transport ID already set.');
        }

        $this->_transportId = $transportId;
        return $this;
    }

/**
 * Get/set SMTP Reply.
 *
 * @param string $smtpReply SMTP Reply.
 * @return string|self SMTP Reply.
 */
    public function smtpReply(string $smtpReply = null) {
        if ($smtpReply === null) {
            return $this->_smtpReply;
        }

        if ($this->_smtpReply !== null) {
            throw new InvalidArgumentException('SMTP Reply already set.');
        }

        $this->_smtpReply = $smtpReply;
        return $this;
    }

/**
 * Get/set status code.
 *
 * @param int $statusCode Status code.
 * @return int|self Status code.
 */
    public function statusCode(int $statusCode = null) {
        if ($statusCode === null) {
            if ($this->_statusCode === null) {
                $this->_statusCode = preg_match('/^2\d\d$/', $this->_smtpReply) ? 200 : 500;
            }
            return $this->_statusCode;
        }

        if ($this->_statusCode !== null) {
            throw new InvalidArgumentException('Status code already set.');
        }

        $this->_statusCode = $statusCode;
        return $this;
    }

/**
 * Get/set message ID.
 *
 * @param string $messageId Message ID.
 * @return string|self Message ID.
 */
    public function messageId(string $messageId = null) {
        if ($messageId === null) {
            return $this->_messageId;
        }

        if ($this->_messageId !== null) {
            throw new InvalidArgumentException('Message ID already set.');
        }

        $this->_messageId = $messageId;
        return $this;
    }

/**
 * Check if message was sent.
 *
 * @return bool True if message was sent, false otherwise.
 */
    public function wasSent() {
        return stripos($this->_smtpReply, 'OK') !== false;
    }

}