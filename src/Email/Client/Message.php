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

namespace Nata\Email\Client;

use Nata\Email\Address;
use Nata\I18n\Time;
use Exception;
use Nata\Core\Configure;
use Nata\Email\AddressCollection;
use Nata\Filesystem\Mimetype;
use Nata\Utility\Html;
use Nata\Utility\Text;
use IMAP\Connection;
use stdClass;

/**
 * Message.
 */
class Message {

/**
 * IMAP stream.
 *
 * @var Connection
 */
    protected $_stream;

/**
 * Folder name.
 *
 * @var string
 */
    protected $_folder;

/**
 * Email provider name.
 *
 * @var string
 */
    protected $_providerName;

/**
 * Email overview data.
 *
 * @var object
 */
    protected $_overview;

/**
 * Message parts.
 *
 * @var array
 */
    protected $_parts;

/**
 * True iif parts have been assigned.
 *
 * @var bool
 */
    protected $_partsAssigned = false;

/**
 * Size.
 *
 * @var int
 */
    protected $_size;

/**
 * From.
 *
 * @var \Nata\Email\Address
 */
    protected $_from;

/**
 * Sender.
 *
 * @var \Nata\Email\Address
 */
    protected $_sender;

/**
 * To.
 *
 * @var \Nata\Email\AddressCollection
 */
    protected $_to;

/**
 * CC.
 *
 * @var \Nata\Email\AddressCollection
 */
    protected $_cc;

/**
 * BCC.
 *
 * @var \Nata\Email\AddressCollection
 */
    protected $_bcc;

/**
 * Reply To.
 *
 * @var \Nata\Email\Address
 */
    protected $_replyTo;

/**
 * Subject.
 *
 * @var string
 */
    protected $_subject;

/**
 * Date.
 *
 * @var \Nata\I18n\Time
 */
    protected $_date;

/**
 * Message ID.
 *
 * @var string
 */
    protected $_messageId;

/**
 * In Reply To.
 *
 * @var string
 */
    protected $_inReplyTo;

/**
 * References.
 *
 * @var array
 */
    protected $_references;

/**
 * UID.
 *
 * @var int
 */
    protected $_uid;

/**
 * Message number.
 *
 * @var int
 */
    protected $_msgno;

/**
 * Email flag(s).
 *
 * @var array
 */
    protected $_flags;

/**
 * Email supported flag(s).
 *
 * @var array
 */
    protected $_supportedFlags = [
        'seen', 'answered', 'draft', 'deleted', 'recent', 'flagged'
    ];

/**
 * HTML Message.
 *
 * @var string
 */
    protected $_htmlMessage;

/**
 * Text/Plain Message.
 *
 * @var string
 */
    protected $_plainMessage;

/**
 * Message parts.
 *
 * @var array
 */
    protected $_messageParts;

/**
 * Inline disposition attachments.
 *
 * @var array
 */
    protected $_inlineAttachments;

/**
 * Not-inline disposition attachments.
 *
 * @var array
 */
    protected $_notInlineAttachments;

/**
 * Attachments.
 *
 * @var array
 */
    protected $_attachments;

/**
 * Header.
 *
 * @var array
 */
    protected $_header;

/**
 * Header map.
 *
 * @var array
 */
    protected $_headerMap;

/**
 * Raw MIME Message.
 *
 * @var string
 */
    protected $_raw;

/**
 * Assigned uniqid hash for this message.
 *
 * It hashes 'host', 'username', 'folder' and message UID
 *
 * @var string
 */
    protected $_uniqid;


/**
 * Constructor.
 *
 * @param array $parts Email parts.
 */
    public function __construct($stream, int $uid, array $data = []) {
        $this->_stream = $stream;
        $this->_uid = $uid;
        $this->_properties($data);
    }

/**
 * Get overview data.
 *
 * @return object Overview
 */
    private function _getOverview() {
        if ($this->_overview === null) {
            $this->_overview = imap_fetch_overview($this->_stream, $this->_uid, FT_UID);
            if ($this->_overview) {
                [$this->_overview] = $this->_overview;
            }
        }
        return $this->_overview;
    }

/**
 * Get folder name.
 *
 * @return string Folder
 */
    public function getFolder(): string {
        return $this->_folder;
    }

/**
 * Get name of email service provider.
 *
 * @return string Service provider name
 */
    public function getProviderName(): string {
        return $this->_providerName;
    }

/**
 * Get complete MIME message.
 *
 * @return string MIME Message
 */
    public function getRaw(): string {
        if ($this->_raw === null) {
            $this->_raw = imap_fetchbody($this->_stream, $this->_uid, '', FT_UID);
        }
        return $this->_raw;
    }

/**
 * Get message UID.
 *
 * @return int Message UID
 */
    public function getUid(): int {
        return $this->_uid;
    }

/**
 * Gets the message sequence number.
 *
 * @return int Message number
 */
    public function getMsgno(): ?int {
        if ($this->_msgno === null) {
            if ($this->_overview && isset($this->_overview->msgno)) {
                $this->_msgno = $this->_overview->msgno;
            } elseif ($this->_uid > 0) {
                $this->_msgno = imap_msgno($this->_stream, $this->_uid);
            }
        }
        return $this->_msgno;
    }

/**
 * Get message number.
 *
 * @return int Message number
 */
    public function getMsgNumber(): ?int {
        return $this->getMsgno();
    }

/**
 * Get message ID.
 *
 * @return string Message ID
 */
    public function getMessageId(): ?string {
        if ($this->_messageId === null) {
            $this->_messageId = $this->getHeader('Message-ID');
        }
        return $this->_messageId;
    }

/**
 * Get message size, in bytes.
 *
 * @return int Message size
 */
    public function getSize(): ?int {
        if (!$overview = $this->_getOverview()) {
            return null;
        }
        return $overview->size;
    }

/**
 * Get header.
 *
 * @param string $field Optional header field to obtain the value
 * @return string|array Header/value
 */
    public function getHeader($field = null) {
        if ($this->_header === null) {
            $this->_header = $this->_parseHeader();
            $keys = array_keys($this->_header);
            $this->_headerMap = array_combine(array_map('strtolower', $keys), $keys);
        }

        if ($field === null) {
            return $this->_header;
        }

        $field = strtolower($field);
        return isset($this->_headerMap[$field]) ? $this->_header[$this->_headerMap[$field]] : null;
    }

/**
 * Get and parse MIME message header.
 *
 * @return array Parsed message header
 */
    protected function _parseHeader(): ?array {
        // return $this->_parseHeaderAlt();
        $header = imap_fetchheader($this->_stream, $this->_uid, FT_UID);
        if (!$header) {
            return [];
        }

        preg_match_all('/([^: ]+): (.+?(?:\r\n\s(?:.+?))*)\r\n/m', $header, $matches);
        if (count($matches) < 3) {
            return [];
        }

        return array_combine(array_map('trim', $matches[1]), $matches[2]);
    }

/**
 * Get and parse MIME message header.
 *
 * This is in alternative to the header from imap_fetchheader
 * because when TO, or any other have more than 100 addresses
 * it truncates it.
 *
 * @return array Parsed message header
 */
    protected function _parseHeaderAlt(): ?array {
        $headerLines = [];
        $lastHeader = null;
        foreach (explode(PHP_EOL, $this->getRaw()) as $line) {
            if ($line === '') {
                break;
            }

            if (!str_contains($line, ': ') || substr($line, 0, 1) === ' ') {
                $headerLines[$lastHeader] .= PHP_EOL . trim($line);
                continue;
            }

            [$header, $value] = splitter($line, ': ', false, 2);
            $header = trim($header);
            $lastHeader = $header;
            $headerLines[$header] = trim($value);
        }

        return $headerLines;
    }

/**
 * Check flag.
 *
 * @return bool
 */
    public function isRecent() {
        return $this->_checkFlag('recent');
    }

/**
 * Check flag.
 *
 * @return bool
 */
    public function isSeen() {
        return $this->_checkFlag('seen');
    }

/**
 * Check flag.
 *
 * @return bool
 */
    public function isUnseen() {
        return !$this->_checkFlag('seen');
    }

/**
 * Check flag.
 *
 * @return bool
 */
    public function isAnswered() {
        return $this->_checkFlag('answered');
    }

/**
 * Check flag.
 *
 * @return bool
 */
    public function isFlagged() {
        return $this->_checkFlag('flagged');
    }

/**
 * Check flag.
 *
 * @return bool
 */
    public function isDraft() {
        return $this->_checkFlag('draft');
    }

/**
 * Check flag.
 *
 * @return bool
 */
    public function isDeleted() {
        return $this->_checkFlag('deleted');
    }

/**
 * Check flag (for BC).
 *
 * @deprecated Use isDeleted() instead
 * @return bool
 */
    public function wasDeleted() {
        return $this->isDeleted();
    }

/**
 * Get flags.
 *
 * @return array Email flags
 */
    public function getFlags() {
        if ($this->_flags === null) {
            $this->_flags = [];
            $overview = (array)$this->_getOverview();
            foreach ($this->_supportedFlags as $supportedFlag) {
                if (!isset($overview[$supportedFlag]) || !$overview[$supportedFlag]) {
                    continue;
                }
                $this->_flags[] = $supportedFlag;
            }
        }
        return $this->_flags;
    }

/**
 * Set references as an array.
 *
 * @param string $references References
 * @return void
 */
    public function getInReplyTo() {
        if ($this->_inReplyTo === null) {
            $this->_inReplyTo = $this->getHeader('In-Reply-To');
        }
        return $this->_inReplyTo;
    }

/**
 * Set references as an array.
 *
 * @param string $references References
 * @return void
 */
    public function getReferences() {
        if ($this->_references === null) {
            $references = trim($this->getHeader('References'));
            if ($references) {
                $this->_references = array_map('trim', explode(' ', $references));
            }
        }
        return $this->_references;
    }

/**
 * Get FROM.
 *
 * @return \Nata\Email\Client\Address
 */
    public function getFrom() {
        if ($this->_from === null) {
            $this->_from = $this->_getAddress('From');
        }
        return $this->_from;
    }

/**
 * Get SENDER.
 *
 * @return \Nata\Email\Client\Address
 */
    public function getSender() {
        if ($this->_sender === null) {
            $this->_sender = $this->_getAddress('Sender');
        }
        return $this->_sender;
    }

/**
 * Get Reply to.
 *
 * @return \Nata\Email\Client\Address
 */
    public function getReplyTo() {
        if ($this->_replyTo === null) {
            $this->_replyTo = $this->_getAddresses('Reply-To');
        }
        return $this->_replyTo;
    }

/**
 * Set TO.
 *
 * @return \Nata\Email\Client\AddressCollection
 */
    public function getTo() {
        if ($this->_to === null) {
            $this->_to = $this->_getAddresses('To');
        }
        return $this->_to;
    }

/**
 * Get CC.
 *
 * @return \Nata\Email\Client\AddressCollection
 */
    public function getCc() {
        if ($this->_cc === null) {
            $this->_cc = $this->_getAddresses('Cc');
        }
        return $this->_cc;
    }

/**
 * Get BCC.
 *
 * @return \Nata\Email\Client\AddressCollection
 */
    public function getBcc() {
        if ($this->_bcc === null) {
            $this->_bcc = $this->_getAddresses('Bcc');
        }
        return $this->_bcc;
    }

/**
 * Get address.
 *
 * @return \Nata\Email\Client\Address|null
 */
    protected function _getAddress($field): ?Address {
        if (!$value = $this->getHeader($field)) {
            return null;
        }
        return new Address($value);
    }

/**
 * Get addresses.
 *
 * @return \Nata\Email\Client\AddressCollection
 */
    protected function _getAddresses($field): ?AddressCollection {
        if (!$value = $this->getHeader($field)) {
            return null;
        }
        return new AddressCollection($value);
    }

/**
 * Get date as an \Nata\I18n\Time instance.
 *
 * @param string|Time $defaultDate Default/fallback date
 * @return Time
 */
    public function getDate($defaultDate = null) {
        if ($this->_date === null) {
            $date = $this->getHeader('Date');
            if ($date) {
                $date = $this->_fixTimezone($date);
                try {
                    $this->_date = new Time($date);
                    $this->_date->timezone('UTC');
                } catch (Exception $error) {
                    if (Configure::read('debug')) {
                        throw new $error;
                    }
                    if ($defaultDate !== null && !($defaultDate instanceof Time)) {
                        $defaultDate = new Time($defaultDate);
                    }
                    $this->_date = $defaultDate;
                }
            }
        }
        return $this->_date;
    }

/**
 * Fix timezone.
 * This is rare, but has happen.
 *
 * @param string $date Date
 * @return string
 */
    protected function _fixTimezone($date) {
        $parts = explode(' ', $date);
        $timezone = array_pop($parts);
        if ($timezone === 'UT' || $timezone === '(UT)') {
            $date = str_replace('UT', 'UTC', $date);
        } elseif ($timezone === 'CE' || $timezone === '(CE)') {
            $date = str_replace('CE', 'CET', $date);
        }
        return $date;
    }

/**
 * Get date as an unix timestamp.
 *
 * @param string|Time $defaultDate Default/fallback date
 * @return int Unixtimestamp
 */
    public function getUdate($defaultDate = null) {
        return ($this->getDate($defaultDate) instanceof Time ? $this->_date->timestamp() : null);
    }

/**
 * Get subject.
 *
 * @return string Message subject
 */
    public function getSubject() {
        if ($this->_subject === null) {
            $this->_subject = '';
            $subject = $this->_overview && isset($this->_overview->subject) ? $this->_overview->subject : $this->getHeader('Subject');
            $decoded = imap_mime_header_decode($subject);
            if ($decoded) {
                $decoded = implode('', array_map(function ($val) {
                    return $val->text;
                }, $decoded));

                $this->_subject = Text::toUtf8($decoded);
            }
        }
        return $this->_subject;
    }

/**
 * Get plain message.
 *
 * @return string Plain message
 */
    public function getPlainMessage() {
        if ($this->_plainMessage === null) {
            $this->_assignParts();

            if (isset($this->_messageParts['plain'])) {
                $plain = '';
                foreach ($this->_messageParts['plain'] as $part) {
                    $message = $this->_getPart($this->_stream, $this->_uid, $part['section'], $part['encoding']);
                    $message = Text::toUtf8($message);

                    // Some HTML messages comes set as a text/plain part (:facepalm)
                    if ($this->_checkIfItsHtml($message)) {
                        if (!isset($this->_messageParts['html']) && !$this->_htmlMessage) {
                            $this->_htmlMessage = $message;
                        }
                        continue;
                    }

                    $plain .= $message;
                }

                unset($this->_messageParts['plain']);

                if (!$plain) {
                    return null;
                }

                $this->_plainMessage = $plain;
            }
        }
        return $this->_plainMessage;
    }

/**
 * Get HTML message.
 *
 * @return string HTML message
 */
    public function getHtmlMessage() {
        if ($this->_htmlMessage === null) {
            $this->_assignParts();
            if (isset($this->_messageParts['html'])) {
                $this->_htmlMessage = '';
                foreach ($this->_messageParts['html'] as $part) {
                    $message = $this->_getPart(
                        $this->_stream,
                        $this->_uid,
                        $part['section'],
                        $part['encoding']
                    );

                    $this->_htmlMessage .= Text::toUtf8($message);
                }
                unset($this->_messageParts['html']);
            }
        }
        return $this->_htmlMessage;
    }

/**
 * Get message's inline attachments.
 *
 * @return array Inline attachments
 */
    public function getInlineAttachments() {
        if ($this->_inlineAttachments === null) {
            $this->_assignParts();

            $this->_inlineAttachments = [];
            $htmlMessage = $this->getHtmlMessage();
            if (str_contains($htmlMessage, 'cid:')) {
                foreach ($this->_attachments as $index => $attachment) {
                    if (!$attachment->getId()) {
                        continue;
                    }

                    $cid = $attachment->getId();
                    if (!str_contains($htmlMessage, 'cid:' . $cid)) {
                        continue;
                    }

                    $this->_inlineAttachments[] = $attachment;
                }
            }

        }
        return $this->_inlineAttachments;
    }

/**
 * Check if there's inline attachments.
 *
 * @return bool True if has inline attachments
 */
    public function hasInlineAttachments() {
        return count($this->getInlineAttachments()) > 0;
    }

/**
 * Get message's not-inline attachments.
 *
 * @return array Not-inline attachments
 */
    public function getNotInlineAttachments() {
        if ($this->_notInlineAttachments === null) {
            $this->_assignParts();

            $this->_notInlineAttachments = [];
            $inlineAttachments = $this->getInlineAttachments();
            foreach ($this->getAttachments() as $attachment) {
                foreach ($inlineAttachments as $inline) {
                    if ($attachment === $inline) {
                        continue 2;
                    }
                }

                $this->_notInlineAttachments[] = $attachment;
            }

        }
        return $this->_notInlineAttachments;
    }

/**
 * Check if there's attachments other than inline.
 *
 * @return bool True if has other than inline attachments
 */
    public function hasNotInlineAttachments() {
        return count($this->getAttachments()) > count($this->getInlineAttachments());
    }

/**
 * Get message attachments (without contents, just the reference to the parts).
 *
 * @param string $filename Optionally get just a file
 * @return array Attachments
 */
    public function getAttachments($filename = null) {
        if ($this->_attachments === null) {
            $this->_assignParts();

            if ($filename !== null) {
                foreach ($this->_attachments as $index => $attachment) {
                    if ((is_int($filename) && $index === $filename) || $attachment->basename() === $filename || $attachment->name() === $filename) {
                        return $attachment;
                    }
                }
                return null;
            }

        }
        return $this->_attachments;
    }

/**
 * Check if there's attachments.
 *
 * @return bool True if has attachments
 */
    public function hasAttachments() {
        return count($this->getAttachments()) > 0;
    }

/**
 * Assign the messages and attachments parts (if any) to the respective
 * properties.
 *
 * @return bool True if assigned, false otherwise.
 */
    protected function _assignParts() {
        if ($this->_partsAssigned === true) {
            return true;
        }

        $this->_attachments = [];

        $parts = $this->getParts();
        if (!$parts) {
            return $this->_checkForNoPartsBody();
        }

        foreach ($parts as $section => $part) {
            // Type 0: Message(s) (HTML and plain)
            // Type 1: Multi-part headers, can be ignored
            // Type 2: Attached message headers
            // Type 3: application
            // Type 4: audio
            // Type 5: image
            // Type 6: video
            // Type 7: other
            if ($part->type === 1) {
                continue;
            }

            [$p, $subSection] = splitter($section, '.', 2);
            if ($subSection === null) {
                $subSection = $p;
            }

            // Extract the message(s)
            // the HTML and/or plain text part of the email
            if ($part->type === 0 && ($part->ifdisposition === 0 || ($part->ifdisposition === 1 && strtolower($part->disposition) === 'inline'))) {
                $messageType = strtolower($part->subtype);
                if (!in_array($messageType, ['html', 'plain'])) {
                    continue;
                }

                $this->_messageParts[$messageType][] = [
                    'section' => $subSection,
                    'encoding' => $part->encoding
                ];

                continue;
            }

            $filename = $this->_getFilenameFromPart($part);
            if (!$filename) {
                continue;
            }

            // Prepare attachments
            $info = new stdClass;
            $info->source_part = $part;
            $info->id = null;
            $info->filename = $filename->text;
            $info->charset = $filename->charset;
            $info->section = $subSection;
            $info->encoding = $part->encoding;
            $info->size = $part->bytes ?? null;
            $info->uid = $this->_uid;
            $info->mime = $part->mimetype;
            $info->type = null;
            $info->disposition = 'inline';
            $info->uniqid = $this->_uniqid . '-' . substr(sha1($section), 0, 5);
            if ($part->ifsubtype == 1) {
                $info->type = strtolower($part->subtype);
            }

            if ($part->ifdisposition == 1) {
                $info->disposition = strtolower($part->disposition);
            }

            $info->extension = strtolower(pathinfo($info->filename, PATHINFO_EXTENSION));

            $mime = Mimetype::get($info->extension) ?? Mimetype::get($info->type);
            if ($mime) {
                [$info->mime] = $mime;
            } elseif ($info->type) {
                $mime = str_contains($part->type, '/') ? $part->type : 'application/' . $part->type;
            }

            if (str_contains($info->mime, 'rfc822')) {
                $info->disposition = 'attachment';

                if (empty($info->extension)) {
                    $info->extension = Mimetype::getExtension($info->mime);
                }

            }

            if ($part->ifid == 1) {
                $info->id = str_replace(['<', '>'], '', $part->id);
            }

            $this->_attachments[] = new Attachment($this->_stream, $info);
        }

        // Parts assigned!
        return $this->_partsAssigned = true;
    }

/**
 * Check for a no parts email body.
 *
 * If one found, set it to the respective message type.
 *
 * @return void
 */
    protected function _checkForNoPartsBody() {
        $message = imap_body($this->_stream, $this->_uid, FT_PEEK | FT_UID);
        if (!$message) {
            return null;
        }

        $message = Text::toUtf8($message);
        $type = Html::in($message) ? 'plain' : 'html';
        $message = mb_decode_mimeheader($message);
        $this->{'_' . $type . 'Message'} = Text::toUtf8($message);

        return true;
    }

/**
 * Get filename from part.
 *
 * @param object $part Email part
 * @return object Filename charset and text
 */
    protected function _getFilenameFromPart($part) {
        $filename = '';
        if ($part->ifdparameters) {
            foreach ($part->dparameters as $object) {
                if (strtolower($object->attribute) === 'filename') {
                    $filename = $object->value;
                }
            }
        }

        if (!$filename && $part->ifparameters) {
            foreach ($part->parameters as $object) {
                if(strtolower($object->attribute) === 'name') {
                    $filename = $object->value;
                }
            }
        }

        $decodedFilename = new stdClass;
        $decodedFilename->charset = null;
        $decodedFilename->text = '';

        array_map(function ($part) use (&$decodedFilename) {
            if ($decodedFilename->charset === null) {
                $decodedFilename->charset = $part->charset;
            }

            $decodedFilename->text .= $part->text;

        }, imap_mime_header_decode($filename));

        return $decodedFilename;
    }

/**
 * Get message parts.
 *
 * @return array Message parts
 */
    public function getParts() {
        if ($this->_parts === null) {
            $structure = imap_fetchstructure($this->_stream, $this->_uid, FT_UID);

            if ($structure && isset($structure->type)) {
                $structure = json_decode(json_encode([
                    'parts' => [$structure]
                ]));
            }

            $this->_parts = [];
            if (isset($structure->parts)) {
                $this->_parts = $this->_flattenParts($structure->parts);
            }

        }
        return $this->_parts;
    }

/**
 * Flatten structure parts.
 *
 * @param array $messageParts Message parts
 * @return array Flattened parts
 */
    protected function _flattenParts($messageParts, $flattenedParts = [], $prefix = '', $index = 1, $fullPrefix = true) {
        foreach($messageParts as $part) {
            $part->mimetype = $this->_getMimeType($part);

            $flattenedParts[$prefix . $index] = $part;

            if (isset($part->parts)) {
                // For attached email/mime message
                if ($part->mimetype === 'message/rfc822') {
                    $flattenedParts[$prefix . $index]->parts = $this->_flattenParts($part->parts, $flattenedParts, $prefix . $index . '.', 1, false);
                } elseif ($part->type === TYPEMESSAGE) {
                    $flattenedParts = $this->_flattenParts($part->parts, $flattenedParts, $prefix . $index . '.', 1, false);
                    unset($flattenedParts[$prefix . $index]->parts);
                } elseif ($fullPrefix) {
                    $flattenedParts = $this->_flattenParts($part->parts, $flattenedParts, $prefix . $index . '.');
                    unset($flattenedParts[$prefix . $index]->parts);
                } else {
                    $flattenedParts = $this->_flattenParts($part->parts, $flattenedParts, $prefix);
                    unset($flattenedParts[$prefix . $index]->parts);
                }

            }

            $index++;
        }

        return $flattenedParts;
    }

/**
 * Fetch body part from given section.
 *
 * @param resource $stream Connection stream
 * @param int $uid Email UID
 * @param string $partNumber Section to search the message
 * @return string Fetch body part decoded
 */
    protected function _getPart($connection, $uid, $partNumber, $encoding) {
        $data = imap_fetchbody($connection, $uid, $partNumber, FT_PEEK | FT_UID);
        switch ($encoding) {
            // 7 BIT
            case 0:
                break;
            // 8 BIT
            case 1:
                $data = quoted_printable_decode(imap_8bit($data));
                break;
            // BINARY
            case 2:
                $data = imap_binary($data);
                break;
            // BASE64
            case 3:
                $data = imap_base64($data);
                break;
            // QUOTED_PRINTABLE
            case 4:
                $data = quoted_printable_decode($data);
                break;
            // OTHER
            case 5:
                break;
        }
        return $data;
    }

/**
 * Fetch body part from given section.
 *
 * @param resource $stream Connection stream
 * @param int $uid Email UID
 * @param string $partNumber Section to search the message
 * @return string Message
 */
    protected function _getMimeType($part) {
        switch ($part->type) {
            case TYPETEXT:
                $type = 'text';
                break;
            case TYPEMULTIPART:
                $type = 'multipart';
                break;
            case TYPEMESSAGE:
                $type = 'message';
                break;
            case TYPEAPPLICATION:
                $type = 'application';
                break;
            case TYPEAUDIO:
                $type = 'audio';
                break;
            case TYPEIMAGE:
                $type = 'image';
                break;
            case TYPEVIDEO:
                $type = 'video';
                break;
            case TYPEMODEL:
                $type = 'model';
                break;
            case TYPEOTHER:
                $type = 'other';
                break;
        }
        return $type . '/' . $this->_getMimeSubtype($part);
    }

/**
 * Fetch body part from given section.
 *
 * @param resource $stream Connection stream
 * @param int $uid Email UID
 * @param string $partNumber Section to search the message
 * @return string Message
 */
    protected function _getMimeSubtype($part) {
        return strtolower($part->subtype);
    }

/**
 * Check if given message is HTML.
 *
 * @param string $message Message to check
 * @return bool True if it contains, false otherwise
 */
    protected function _checkIfItsHtml(string $message): bool {
        return str_contains($message, '<img>')
            || str_contains($message, '</body>')
            || str_contains($message, '</html>')
            || str_contains($message, '</div>')
            || str_contains($message, '</table>');
    }

/**
 * Check flag status.
 *
 * @return array Email flags
 */
    protected function _checkFlag($flag) {
        return in_array($flag, $this->getFlags());
    }

/**
 * Get/Set instance data/properties.
 *
 * @param array Data Email data/properties
 * @return array Email data/properties
 */
    protected function _properties(array $data = null) {
        $properties = get_object_vars($this);
        $list = array_keys($properties);
        if ($data === null) {
            $vars = [];
            foreach (get_object_vars($this) as $name => $value) {
                if ($value === null || in_array($name, ['_supportedFlags', '_stream', '_overview'])) {
                    continue;
                }
                $vars[$name] = $value;
            }
            return $vars;
        }

        foreach ($data as $name => $value) {
            $var = '_' . $name;
            if (!in_array($var, $list)) {
                continue;
            }
            $this->{$var} = $value;
        }

    }

/**
 * __destruct.
 *
 * @return void
 */
    public function getMessageParts() {
        $this->_assignParts();
        return $this->_messageParts;
    }

/**
 * __destruct.
 *
 * @return void
 */
    public function toArray() {
        return [
            'deliveredTo' => $this->getHeader('Delivered-To'),
            'uid' => $this->getUid(),
            'messageId' => $this->getMessageId(),
            'inReplyTo' => $this->getInReplyTo(),
            'references' => $this->getReferences(),
            'from' => $this->getFrom(),
            'sender' => $this->getSender(),
            'replyTo' => $this->getReplyTo(),
            'subject' => $this->getSubject(),
            'to' => $this->getTo(),
            'cc' => $this->getCc(),
            'bcc' => $this->getBcc(),
            'date' => $this->getDate(),
            'header' => $this->getHeader(),
            'flags' => $this->getFlags(),
            'plainMessage' => $this->getPlainMessage(),
            'htmlMessage' => $this->getHtmlMessage(),
            'attachments' => $this->getAttachments()
        ];
    }

}
