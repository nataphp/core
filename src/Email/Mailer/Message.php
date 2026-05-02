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
use Nata\Core\ConfigAwareTrait;
use Nata\Email\Address;
use Nata\Email\AddressCollection;
use Nata\Email\Client\Message as ClientMessage;
use Nata\FilesystemManager\File;
use Nata\FilesystemManager\FileFactory;
use Nata\Routing\Router;
use Nata\Utility\Html;
use Nata\Utility\Text;

/**
 * Email message.
 */
class Message {

    use ConfigAwareTrait;

/**
 * Type HTML.
 *
 * @var string
 */
    const HTML = 'html';

/**
 * Type TEXT.
 *
 * @var string
 */
    const TEXT = 'text';

/**
 * Email types.
 *
 * @var array
 */
    protected $_bodyTypes = ['html', 'text'];

/**
 * Email charset.
 *
 * @var string
 */
    protected $_charset = 'utf-8';

/**
 * Email priority.
 * 1 = High, 3 = Normal, 5 = Low.
 *
 * @var integer
 */
    protected $_priority = 3;

/**
 * Email priority list.
 * 1 = High, 3 = Normal, 5 = Low.
 *
 * @var array
 */
    protected $_priorityList = [
        'high' => 1,
        'normal' => 3,
        'low' => 5
    ];

/**
 * Email header.
 *
 * @var array
 */
    protected $_header = [];

/**
 * FROM address.
 *
 * @var Address
 */
    protected $_from;

/**
 * SENDER addresses.
 *
 * @var Address
 */
    protected $_sender;

/**
 * Recipient of the email.
 *
 * @var array
 */
    protected $_to = [];

/**
 * Reply to email addresses.
 *
 * @var array
 */
    protected $_replyTo = [];

/**
 * CC addresses.
 *
 * @var array
 */
    protected $_cc = [];

/**
 * BCC addresses.
 *
 * @var array
 */
    protected $_bcc = [];

/**
 * List of files that should be attached to the email.
 * Only absolute paths.
 *
 * @var array
 */
    protected $_attachments = [];

/**
 * The read receipt email.
 *
 * @var array
 */
    protected $_readReceipt;

/**
 * The read receipt email.
 *
 * @var array
 */
    protected $_returnPath;

/**
 * Email subject.
 *
 * @var string
 */
    protected $_subject;

/**
 * Email body messages (HTML/Plain).
 *
 * @var array
 */
    protected $_body;

/**
 * Error information.
 *
 * @var string
 */
    protected $_error;

/**
 * Set domain/host to be used on generated message-id.
 *
 * @var string
 */
    protected $_domain;

/**
 * Default properties values.
 *
 * @var array
 */
    protected $_defaultProperties = [];


/**
 * Constructor.
 *
 * @param array $parts Email parts.
 */
    public function __construct(array $config = []) {
        $this->_defaultProperties = get_object_vars($this);
        $this->config($config);
    }

/**
 * Get/Set charset.
 *
 * @param string $charset Null to get, String with charset.
 * @return string|$this
 */
    public function charset($charset = null) {
        if ($charset === null) {
            return $this->_charset;
        }
        $this->_charset = $charset;
        return $this;
    }

/**
 * Get/Set priority.
 *
 * @param string|int $priority Null to get, Priority integer,
 *  String in priority list or the priority integer
 * @return string|$this
 */
    public function priority($priority = null) {
        if ($priority === null) {
            return $this->_priority;
        }

        if (is_string($priority)) {
            $priority = (isset($this->_priorityList[$priority]) ? $this->_priorityList[$priority] : 3);
        }

        $this->_priority = $priority;
        return $this;
    }

/**
 * Set/Get header.
 *
 * @param array|string $name Null to get, String with header name,
 *  Array to reset header
 * @param string $value Header value, Null to remove header
 * @return array|$this
 */
    public function header($name = null, $value = null) {
        if ($name === null) {
            return $this->_header;
        }

        if (is_array($name)) {
            $this->_header = $name;
        } elseif (func_num_args() === 1) {
            return isset($this->_header[$name]) ? $this->_header[$name] : null;
        } elseif ($value === null) {
            unset($this->_header[$name]);
        } else {
            $this->_header[$name] = $value;
        }

        return $this;
    }

/**
 * Get/Set from.
 *
 * @param string $email Null to get, String with email,
 * @param string|null $name Name
 * @return Address|self
 */
    public function from($email = null, $name = null) {
        if ($email === null) {
            return $this->_from;
        }
        return $this->_setEmailSingle('_from', $email, $name, 'From requires only 1 email address.');
    }

/**
 * Get/Set sender.
 *
 * @param string $email Null to get, String with email,
 * @param string|null $name Name
 * @return Address|self
 */
    public function sender($email = null, $name = null) {
        if ($email === null) {
            return $this->_sender;
        }
        return $this->_setEmailSingle('_sender', $email, $name, 'Sender requires only 1 email address.');
    }

/**
 * Get/Set to.
 *
 * @param string $email Set recipient email address
 * @param string $name Name
 * @return AddressCollection|self
 */
    public function to($email = null, $name = null) {
        if ($email === null) {
            return $this->_to;
        }
        return $this->_setEmail('_to', $email, $name);
    }

/**
 * Add To.
 *
 * @param string|array $email Null to get, String with email,
 *   Array with email as key, name as value or email as value (without name)
 * @param string|null $name Name
 * @return $this
 */
    public function addTo($email, $name = null) {
        return $this->_setEmail('_to', $email, $name, true);
    }

/**
 * Set cc.
 *
 * @param string $email Set recipient email address
 * @param string $name Name
 * @return AddressCollection|self
 */
    public function cc($email = null, $name = null) {
        if ($email === null) {
            return $this->_cc;
        }
        return $this->_setEmail('_cc', $email, $name);
    }

/**
 * Add Cc.
 *
 * @param string|array $email Null to get, String with email,
 *   Array with email as key, name as value or email as value (without name)
 * @param string|null $name Name
 * @return $this
 */
    public function addCc($email, $name = null) {
        return $this->_setEmail('_cc', $email, $name, true);
    }

/**
 * Set Bcc.
 *
 * @param string $email Set recipient email address
 * @param string $name Name
 * @return AddressCollection|self
 */
    public function bcc($email = null, $name = null) {
        if ($email === null) {
            return $this->_bcc;
        }
        return $this->_setEmail('_bcc', $email, $name);
    }

/**
 * Add Bcc.
 *
 * @param string|array $email Null to get, String with email,
 *   Array with email as key, name as value or email as value (without name)
 * @param string|null $name Name
 * @return $this
 */
    public function addBcc($email, $name = null) {
        return $this->_setEmail('_bcc', $email, $name, true);
    }

/**
 * Set reply to.
 *
 * @param string $email Set recipient email address
 * @param string $name Name
 * @return AddressCollection|self
 */
    public function replyTo($email = null, $name = null) {
        if ($email === null) {
            return $this->_replyTo;
        }
        return $this->_setEmail('_replyTo', $email, $name);
    }

/**
 * Add Bcc.
 *
 * @param string|array $email Null to get, String with email,
 *   Array with email as key, name as value or email as value (without name)
 * @param string|null $name Name
 * @return $this
 */
    public function addReplyTo($email, $name = null) {
        return $this->_setEmail('_replyTo', $email, $name, true);
    }

/**
 * Read Receipt (Disposition-Notification-To header).
 *
 * @param string|array|null $email Null to get, String with email,
 *   Array with email as key, name as value or email as value (without name)
 * @param string|null $name Name
 * @return AddressCollection|self
 * @throws \InvalidArgumentException
 */
    public function readReceipt($email = null, $name = null) {
        if ($email === null) {
            return $this->_readReceipt;
        }
        return $this->_setEmail('_readReceipt', $email, $name, 'Disposition-Notification-To requires only 1 email address.');
    }

/**
 * Read Receipt (Disposition-Notification-To header).
 *
 * @param string|array|null $email Null to get, String with email,
 *   Array with email as key, name as value or email as value (without name)
 * @param string|null $name Name
 * @return array|$this
 * @throws \InvalidArgumentException
 */
    public function returnPath($email = null, $name = null) {
        if ($email === null) {
            return $this->_returnPath;
        }
        return $this->_setEmailSingle('_returnPath', $email, $name, 'Disposition-Notification-To requires only 1 email address.');
    }

/**
 * Set email.
 *
 * @param string $varName Property name
 * @param string|array $email String with email,
 *   Array with email as key, name as value or email as value (without name)
 * @param string $name Name
 * @param bool $append Append addresses
 * @return $this
 * @throws \InvalidArgumentException
 */
    protected function _setEmail($varName, $email, $name, $append = false) {
        if (!$this->{$varName} || $append === false) {
            $this->{$varName} = new AddressCollection();
        }

        $this->{$varName}->add($email, $name);

        return $this;
    }

/**
 * Set only 1 email.
 *
 * @param string $varName Property name
 * @param string|array $email String with email,
 *   Array with email as key, name as value or email as value (without name)
 * @param string $name Name
 * @param string $throwMessage Exception message
 * @return $this
 * @throws \InvalidArgumentException
 */
    protected function _setEmailSingle($varName, $email, $name, $throwMessage) {
        if (is_array($email) && count($email) !== 1) {
            throw new InvalidArgumentException($throwMessage);
        }

        $address = $email;
        if (!($email instanceof Address)) {
            $address = new Address($email, $name);
        }

        if (!$address->isValid()) {
            throw new InvalidArgumentException(sprintf('Invalid email: "%s" (%s)', $address->email(), $varName));
        }

        $this->{$varName} = $address;

        return $this;
    }

/**
 * Add In-Reply-To.
 * It will also set the References if any found
 * on \Nata\Email\Client\Email instance.
 *
 * @param \Nata\Email\Client\Email|string $inReplyTo Message ID,
 * @return $this
 */
    public function inReplyTo($inReplyTo = null) {
        if ($inReplyTo === null) {
            return $this->header('In-Reply-To');
        }

        $references = '';
        if ($inReplyTo instanceof ClientMessage) {
            $references = (string)$inReplyTo->getReferences();
            $inReplyTo = $inReplyTo->getMessageId();

            if ($references) {
                $this->references($references);
            }
        }

        if ($this->references() === null) {
            $this->addReference($inReplyTo);
        }

        return $this->header('In-Reply-To', $inReplyTo);
    }

/**
 * Set/Get References.
 *
 * @param \Nata\Email\Client\Email|string|array $references References,
 * @return $this|string
 */
    public function references($references = null) {
        if ($references === null) {
            return $this->header('References');
        }

        if ($references instanceof ClientMessage) {
            $references = $references->getReferences();
        }

        if (is_array($references)) {
            $references = trim(implode("\n", $references));
        }

        return $this->header('References', $references);
    }

/**
 * Add reference.
 *
 * @param \Nata\Email\Client\Email|string $reference Reference to add,
 * @return $this|string
 */
    public function addReference($reference) {
        if ($reference instanceof ClientMessage) {
            $reference = $reference->getMessageId();
        }

        $references = trim($this->references());
        $references = array_filter(explode("\n", $references));

        $references[] = $reference;

        return $this->references($references);
    }

/**
 * Set/Get attachments.
 *
 * Attach a file datasource and specify alternative filename:
 *
 * ```
 * $email->attachments('path/to/file', 'custom_name.png');
 * ```
 *
 * Attach an array of files datasources and specify alternative filenames:
 *
 * ```
 * $email->attachments([
 *     'path/to/file' => 'custom_name.png',
 *     'path/to/file2' => 'another_custom_name.jpg',
 *     'natafs://storename/path/to/file3' => 'yet_another_custom_name.pdf',
 * ]);
 * ```
 *
 * @param string|array $attachments Null to get, String with datasource,
 *   Array with file datasource as key, alternative file name as value or
 *   file datasource as value (without alternative name).
 * @param string $name Alternative file name
 * @return array|$this
 */
    public function attachments(string|array $attachments = null, string $name = null) {
        if ($attachments === null) {
            return $this->_attachments;
        }
        $this->_attachments = $this->_prepareAttachments($attachments, $name);
        return $this;
    }

/**
 * Add attachments.
 *
 * @param string|array $attachments Null to get, String with path,
 *   Array with file path as key, alternative file name as value or file path as value (without alternative name)
 * @param string $name Alternative file name
 * @return $this
 */
    public function addAttachments(string|array $attachments, string $name = null) {
        $this->_attachments += $this->_prepareAttachments($attachments, $name);
        return $this;
    }

/**
 * Prepare attachements arguments.
 *
 * @param string|array $attachments Attachments to prepare
 * @param string|null $name Name
 * @return array Prepared attachments
 * @see Email::attachments
 * @see Email::addAttachments
 */
    protected function _prepareAttachments(string|array $attachments, string|null $name): array {
        $default = [
            'file' => null,
            'name' => null,
            'contentId' => null,
            'disposition' => 'attachment' // 'attachment' or 'inline'
        ];

        if ($attachments instanceof File) {
            $attachments = [['file' => $attachments, 'name' => $name ?? $attachments->basename()]];
        } elseif (is_string($attachments)) {
            $attachments = FileFactory::build($attachments);
            $attachments = [['file' => $attachments, 'name' => $name ?? $attachments->basename()]];
        } elseif (isset($attachments['file'])) {
            $attachments = [$attachments];
        }

        $attach = [];
        foreach ($attachments as $path => $attachment) {
            if (is_int($path) && is_string($attachment)) {
                $path = $attachment;
                $attachment = $default;
            } elseif (is_string($path) && is_string($attachment)) {
                $attachment = [
                    'file' => $path,
                    'name' => $attachment
                ];
            } elseif ($attachment instanceof File) {
                $attachment = ['file' => $attachment];
            }

            $attachment += $default;
            if (is_string($attachment['file'])) {
                $attachment['file'] = FileFactory::build($attachment['file']);
            }

            if (!$attachment['file']->exists()) {
                throw new InvalidArgumentException(sprintf('File not found: "%s"', $attachment['file']->metadata('storageuri')));
            }

            if (!$attachment['name']) {
                $attachment['name'] = $attachment['file']->basename();
            }

            $attach[$attachment['file']->sha1()] = $attachment + $default;
        }

        return $attach;
    }

/**
 * Get/Set email subject.
 *
 * @param string $subject Email subject
 * @return mixed False if no error, Error message otherwise
 */
    public function subject($subject = null) {
        if ($subject === null) {
            return $this->_subject;
        }
        $this->_subject = $subject;
        return $this;
    }

/**
 * Get/set TEXT/HTML message content.
 *
 * @param array|string $content Message content.
 * @param string $type Content type.
 * @return array|$this
 */
    public function body($content = null, $type = null) {
        if ($content === null) {
            return $this->_body;
        }

        if (!is_array($content)) {
            if ($type === null) {
                $type = Html::in($content) ? static::HTML : static::TEXT;
            }
            $content = [$type => $content];
        }

        $this->_body = array_merge([
            static::HTML => null,
            static::TEXT => null
        ], $content);

        if ($this->_body[static::HTML]) {
            $this->_body[static::HTML] = $this->_prepareInlineAttachments($this->_body[static::HTML]);
        }

        return $this;
    }

/**
 * Prepare inline attachments extracted from base64 encoded files.
 *
 * @param string $htmlMessage HTML.
 * @return array HTML message with content ID's and inline attachments
 */
    public function extractInlineAttachments($htmlMessage) {
        if (!$htmlMessage || !preg_match_all('/src="(data:image\/[^;]+;base64[^"]+)"/i', $htmlMessage, $matches)) {
            return [$htmlMessage, []];
        }

        $inlineAttachments = [];
        $hashRepeatCounter = [];
        foreach ($matches[1] as $match) {
            if (empty($match)) {
                continue;
            }

            [$dataUri, $params] = splitter($match, '?');
            if ($params) {
                $params = Router::queryAsArray($params);
            }

            $attachment = FileFactory::build($dataUri);

            $hash = $attachment->sha1();
            if (!isset($hashRepeatCounter[$hash])) {
                $hashRepeatCounter[$hash] = 0;
            }
            $hashRepeatCounter[$hash]++;

            $hash1 = substr($hash, 0, 10);
            $hash2 = substr(sha1($hash), 0, 10);
            $contentId = sprintf('%s%s@%s', $hash1, $attachment->extension(true), $hash2);
            $htmlMessage = str_replace($match, 'cid:' . $contentId, $htmlMessage);

            $inlineAttachments[$contentId] = [
                'file' => $attachment,
                'dataUri' => $dataUri,
                'contentId' => $contentId,
                'disposition' => 'inline',
                'params' => $params
            ];
        }

        return [$htmlMessage, $inlineAttachments];
    }

/**
 * Prepare inline attachments extracted from base64 encoded files.
 *
 * @param string $htmlMessage HTML.
 * @return string HTML message with content ID's
 */
    protected function _prepareInlineAttachments($htmlMessage) {
        [$htmlMessage, $inlineAttachments] = $this->extractInlineAttachments($htmlMessage);

        if ($inlineAttachments) {
            $this->addAttachments(array_values($inlineAttachments));
        }

        return $htmlMessage;
    }

/**
 * Get/set TEXT message.
 *
 * @param string $content Message content.
 * @return string
 */
    public function textMessage($content = null) {
        if ($content === null) {
            $body = $this->body();
            return $body[static::TEXT] ?? null;
        }

        $this->body($content, static::TEXT);

        return $this;
    }

/**
 * Get/set HTML message.
 *
 * @param string $content Message content.
 * @return string
 */
    public function htmlMessage($content = null) {
        if ($content === null) {
            $body = $this->body();
            return $body[static::HTML] ?? null;
        }

        $this->body($content, static::HTML);

        return $this;
    }

/**
 * Get/Set body content types.
 *
 * @param string|array $types Body content types.
 * @return string|array|$this
 */
    public function bodyTypes($types = null) {
        if ($types === null) {
            return $this->_bodyTypes;
        }
        $this->_bodyTypes = $types;
        return $this;
    }

/**
 * Get/Set message ID.
 *
 * @param string|bool $messageId Null to get, String with message Id, true to generate.
 * @return string|$this
 */
    public function messageId($messageId = null) {
        if ($messageId === null) {
            return $this->header('Message-Id');
        }

        if ($messageId === true) {
            $messageId = $this->_generateMessageId();
        }

        $this->header('Message-Id', $messageId);

        return $this;
    }

/**
 * Generate message ID.
 *
 * @return string Generated message id
 */
    protected function _generateMessageId() {
        $domain = $this->_domain;
        if ($domain === null) {
            $domain = env('HTTP_HOST');
            if (empty($domain)) {
                $domain = gethostname();
            }
        }

        if (empty($domain)) {
            throw new InvalidArgumentException('Missing domain name to generate message ID.');
        }

        return '<' . str_replace('-', '', Text::uuid()) . '@' . $domain . '>';
    }

/**
 * Get/Set domain.
 *
 * @param string $domain Domain.
 * @return string|$this
 */
    public function domain($domain = null) {
        if ($domain === null) {
            return $this->_domain;
        }
        $this->_domain = $domain;
        return $this;
    }

/**
 * Get message estimated size, in bytes.
 *
 * Please take note that this is the message size estimation, not the real
 * final message size.
 *
 * @return int Current message size in bytes
 */
    public function getSize() {
        $size = 0;

        foreach ($this->body() as $content) {
            $size += strlen($content);
        }

        $size += $this->getAttachmentsSize();

        foreach ($this->header() as $header) {
            $size += strlen($header);
        }

        return $size;
    }

/**
 * Get base64 encoded message estimated size, in bytes.
 *
 * Please take note that this is the message size estimation, not the real
 * final message size.
 *
 * @return int Current base64 encoded message size in bytes
 */
    public function getBase64Size() {
        $size = 0;

        foreach ($this->body() as $content) {
            $size += strlen(base64_encode($content));
        }

        $size += $this->getAttachmentsBase64Size();

        foreach ($this->header() as $header) {
            $size += strlen($header);
        }

        return $size;
    }

/**
 * Get message attachments estimated size, in bytes.
 *
 * @return int Attachments size in bytes
 */
    public function getAttachmentsSize() {
        $size = 0;
        foreach ($this->attachments() as $attachment) {
            $file = $attachment['file'];
            $size += $file->size();
        }
        return $size;
    }

/**
 * Get message attachments estimated size, in bytes.
 * It will convert to base64
 *
 * @return int Attachments base64 encoded size in bytes
 */
    public function getAttachmentsBase64Size() {
        $size = 0;
        foreach ($this->attachments() as $attachment) {
            $file = $attachment['file'];
            $size += strlen($file->getBase64());
        }
        return $size;
    }

/**
 * Reset properties to default values.
 *
 * @return $this
 */
    public function reset() {
        foreach ($this->_defaultProperties as $varName => $value) {
            $this->{$varName} = $value;
        }
        return $this;
    }

}
