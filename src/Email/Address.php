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

namespace Nata\Email;

use JsonSerializable;
use Nata\Utility\Validation;
use Transliterator;

/**
 * Email address.
 */
class Address implements JsonSerializable {

/**
 * Address.
 *
 * @var string
 */
    protected $_address;

/**
 * Email.
 *
 * @var string
 */
    protected $_email;

/**
 * Personal/Name.
 *
 * @var string
 */
    protected $_personal;

/**
 * Mailbox.
 *
 * @var string
 */
    protected $_mailbox;

/**
 * Host.
 *
 * @var string
 */
    protected $_host;


/**
 * Constructor.
 *
 * ### Example:
 *
 * Any of the email address formats given below are supported:
 *
 * ```
 * new Address('john.doe@provider.com');
 * new Address('john.doe@provider.com', 'John Doe');
 * new Address(['john.doe@provider.com' => 'John Doe']);
 * new Address('John Doe <john.doe@provider.com>');
 * new Address('John Doe <john.doe@provider.com>');
 * new Address(['email' => 'john.doe@provider.com', 'name' => 'John Doe']);
 * ```
 *
 * MIME encoded address:
 *
 * ```
 * new Address('=?UTF-8?B?Sm9obiBEb2UgPGpvaG4uZG9lQHByb3ZpZGVyLmNvbT4=?=');
 * ```
 *
 * @param string|array $address Email address (with or without personal).
 * @param string $personal (optional) Add personal part to given address for the given address.
 * @return void
 */
    public function __construct($address = null, string $personal = null) {
        $this->_personal = $personal;
        $this->_prepareAddress($address);
    }

/**
 * Prepare address given the different formats.
 *
 * @param array|string $address Address
 * @return void
 */
    protected function _prepareAddress($address): void {
        if (empty($address)) {
            return;
        }

        $address = $this->_jsonDecode($address);
        if (is_string($address)) {
            $address = str_replace(PHP_EOL, '', $address);
            $address = trim($address);

            if (str_contains($address, '=?')) {
                $address = $this->_decodeMime($address);
            }

            if (strpos($address, '<') === false) {
                $this->_setEmailParts($address);
            } else {
                $this->_extractRfc822($address);
            }
            return;
        }

        if (is_array($address) && !empty($address)) {
            if (isset($address['email'])) {
                $this->_setEmailParts($address['email']);
                if (isset($address['name'])) {
                    $this->personal($address['name']);
                }
                return;
            }

            [$email] = array_keys($address);
            $this->_setEmailParts($email);
            $this->personal($address[$email]);
            return;
        }

        if (is_object($address) && isset($address->host)) {
            $this->_mailbox = $address->mailbox;
            $this->_host = $address->host;
            if (isset($address->personal)) {
                $this->personal($address->personal);
            }
        }
    }

/**
 * Decode MIME string.
 *
 * @param string $address MIME encoded
 * @return string Address decoded
 */
    protected function _decodeMime(string $address): string {
        if (!preg_match_all("/\=\?(.*)\?=/", $address, $matches)) {
            return $address;
        }

        foreach ($matches[0] as $match) {
            $decoded = iconv_mime_decode($match, 0, "UTF-8");
            $address = str_replace($match, $decoded, $address);
        }

        return $address;
    }

/**
 * Decode JSON string.
 *
 * @param array|string $address Address
 * @return array|string Addresses decoded
 */
    protected function _jsonDecode($address) {
        if (is_string($address) && (strpos($address, '{') === 0 || strpos($address, '[') === 0)) {
            return json_decode($address, true);
        }
        return $address;
    }

/**
 * Extract RFC 822 address.
 *
 * @param string $address RFC 822 address
 */
    protected function _extractRfc822($address) {
        if (preg_match("/(.*[^\s])?\s*<\s*(.*[^\s])\s*>/", $address, $matches)) {
            if (count($matches) !== 3) {
                return;
            }

            $this->_setEmailParts($matches[2]);
            $this->personal($matches[1]);
        }
    }

/**
 * Split and set respective address parts.
 *
 * @param string $address Email address.
 */
    public function _setEmailParts($address) {
        if (stripos($address, '@') === false) {
            $this->personal($address);
            return $this->_personal;
        }
        $this->email($address);
    }

/**
 * __get.
 *
 * @param string $name Property name.
 * @return mixed Property value
 */
    public function __get($name) {
        return $this->{$name}();
    }

/**
 * __set.
 *
 * @param string $name Property name.
 * @param mixed $value Property value.
 * @return $this Property value
 */
    public function __set($name, $value) {
        return $this->{$name}($value);
    }

/**
 * Get/set personal.
 *
 * @param string $personal Personal part
 * @return string|$this Personal
 */
    public function personal(string $personal = null) {
        if ($personal === null) {
            return $this->_personal;
        }

        if (str_contains($personal, '=?') && str_contains($personal, '?=')) {
            $personal = iconv_mime_decode($personal, 0, "UTF-8");
        }

        $personal = str_replace(['"', '\\', "'"], '', $personal);
        $this->_personal = trim($personal);

        return $this;
    }

/**
 * Alias for personal.
 *
 * @param string $name Name part
 * @return string|$this Name
 */
    public function name(string $name = null) {
        return $this->personal($name);
    }

/**
 * Get/set the address mailbox.
 *
 * @param string $mailbox Mailbox
 * @return string|$this Personal
 */
    public function mailbox(string $mailbox = null) {
        if ($mailbox === null) {
            return $this->_mailbox;
        }
        $this->_mailbox = $mailbox;
        return $this;
    }

/**
 * Get/set the host.
 *
 * @param string $host Host
 * @return string|$this Host
 */
    public function host(string $host = null) {
        if ($host === null) {
            return $this->_host;
        }
        $this->_host = $host;
        return $this;
    }

/**
 * Get/set email address.
 *
 * @param string $email Email address
 * @return string|$this Email address
 */
    public function email(string $email = null) {
        if ($email === null) {
            return $this->_mailbox && $this->_host ? $this->_mailbox . '@' . $this->_host : null;
        }

        $email = trim($email);
        $email = strtolower($email);
        [$this->_mailbox, $this->_host] = explode('@', $email);

        return $this;
    }

/**
 * Get email address with transliterated characters.
 *
 * @return string Transliterated email address
 */
    public function getTransliteratedEmail(): string {
        $transliterator = Transliterator::createFromRules(
            ':: NFD; :: [:Nonspacing Mark:] Remove; :: Lower(); :: NFC;',
            Transliterator::FORWARD
        );
        return $transliterator->transliterate($this->email());
    }

/**
 * Check if address is valid.
 *
 * @return bool True if email address is valid
 */
    public function isValid(): bool {
        return Validation::email($this->email());
    }

/**
 * Check if address is empty.
 *
 * @return bool True if email address is empty
 */
    public function isEmpty(): bool {
        return empty($this->email());
    }

/**
 * Get as RFC 822 address.
 *
 * @param bool $htmlEncoded True if html encoded
 * @return string RFC 822 address
 */
    public function getRfc822(bool $htmlEncoded = false): string {
        $address = '';
        if (!$this->email()) {
            return $address;
        }

        if (!empty($this->_personal)) {
            $address .= preg_match("/[\'^£$%&*()}{@#~?><>,\/|=_+¬-]/ui", $this->_personal) === 0
                 ? $this->_personal : '"' . $this->_personal . '"';
            $address .= ' <' . $this->email() . '>';
        } else {
            $address .= $this->email();
        }

        return $htmlEncoded ? htmlspecialchars($address) : $address;
    }

/**
 * __toString.
 *
 * @return string RFC 822 address
 */
    public function __toString(): string {
        return $this->getRfc822();
    }

/**
 * To array.
 *
 * @return array
 */
    public function toArray(): array {
        return [$this->email() => $this->_personal];
    }

/**
 * Returns the properties that will be serialized as JSON.
 *
 * @return array
 */
    public function jsonSerialize(): array {
        return ['email' => $this->email(), 'name' => (string)$this->personal()];
    }

}
