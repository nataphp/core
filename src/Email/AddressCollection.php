<?php
/**
 * NataPHP Framework.
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

use ArrayIterator;
use InvalidArgumentException;
use JsonSerializable;

/**
 * Collection of email addresses.
 */
class AddressCollection extends ArrayIterator implements JsonSerializable {

/**
 * List of Address instances.
 *
 * @var array
 */
    protected $_addresses = [];

/**
 * List of email addresses.
 * This prevents duplicate emails
 *
 * @var array
 */
    protected $_emailList = [];

/**
 * Strict addresses.
 *
 * If 'true', invalid email addresses throws an Error/Exception.
 *
 * @var bool
 */
    protected $_strict = false;

/**
 * Iterator index position.
 *
 * @var bool
 */
    protected $_index = 0;


/**
 * Constructor.
 *
 * @param string|array $addresses Email addresses (with or without personal).
 * @return void
 */
    public function __construct($addresses = [], array $options = []) {
        $options += [
            'strict' => false
        ];

        $this->_appendAddresses($addresses);
    }

/**
 * Add/append email address(es).
 *
 * @param string|array $addresses Email addresses (with or without personal).
 * @return $this
 */
    public function add($addresses) {
        $this->_appendAddresses($addresses);
        return $this;
    }

/**
 * Get first address.
 *
 * @return Address Email address
 */
    public function first(): ?Address {
        return isset($this->_addresses[0]) ? $this->_addresses[0] : null;
    }

/**
 * Get last address.
 *
 * @return Address Email address
 */
    public function last(): ?Address {
        return $this->_addresses ? $this->_addresses[count($this->_addresses) - 1] : null;
    }

/**
 * Consume by given email address(es) or their respective indexes.
 *
 * @param string|int|Address|array<string|int|Address> $addresses Email addresses to consume.
 * @return AddressCollection
 */
    public function consume($addresses) {
        if (!is_iterable($addresses)) {
            $addresses = [$addresses];
        }

        $consumedAddresses = [];
        foreach ($addresses as $address) {
            if ($address instanceof Address) {
                $address = $address->email();
            }

            if (is_string($address)) {
                $address = isset($this->_emailList[$address]) ? $this->_emailList[$address] : null;
            }

            if (!is_int($address) || !isset($this->_addresses[$address])) {
                continue;
            }

            $consumedAddress = $this->_addresses[$address];
            unset($this->_addresses[$address], $this->_emailList[$consumedAddress->email()]);
            $consumedAddresses[] = $consumedAddress;
        }

        $this->_addresses = array_values($this->_addresses);

        return new AddressCollection($consumedAddresses);
    }

/**
 * Remove given email address(es) or their respective indexes.
 *
 * @param string|int|Address|array $address Email address to consume.
 * @return void
 */
    public function remove($addresses) {
        if (!is_iterable($addresses)) {
            $addresses = [$addresses];
        }

        foreach ($addresses as $address) {
            if ($address instanceof Address) {
                $address = $address->email();
            }

            if (is_string($address)) {
                $address = isset($this->_emailList[$address]) ? $this->_emailList[$address] : null;
            }

            if (!is_int($address) || !isset($this->_addresses[$address])) {
                continue;
            }

            $consumedAddress = $this->_addresses[$address];
            unset($this->_addresses[$address], $this->_emailList[$consumedAddress->email()]);
        }

        $this->_addresses = array_values($this->_addresses);
    }

/**
 * Add/append email address(es).
 *
 * @param string|array|Address $addresses Email addresses (with or without personal).
 * @param int $key Key offset.
 * @return void
 */
    protected function _appendAddresses($addresses, int $key = null) {
        if (is_string($addresses) && (strpos($addresses, '[') === 0 || strpos($addresses, '{') === 0)) {
            $addresses = json_decode($addresses, true);
        }

        if (is_string($addresses)) {
            $addresses = strpos($addresses, ',') === false ? [$addresses] : str_getcsv($addresses);
        } elseif (!is_array($addresses)) {
            $addresses = [$addresses];
        }

        foreach ($addresses as $address => $personal) {
            if (is_int($address)) {
                $address = $personal;
                $personal = null;
            } elseif (str_contains($address, '@')) {
                $address = [$address => $personal];
            }

            if (!($address instanceof Address)) {
                $address = new Address($address);
            }

            if ($address->isEmpty()) {
                if ($this->_strict === true) {
                    throw new InvalidArgumentException('Empty email address.');
                }
                continue;
            }

            if ($this->_strict === true && !$address->isValid()) {
                throw new InvalidArgumentException(sprintf('Invalid email address "%s".', $address->email));
            }

            $email = $address->email();
            if (isset($this->_emailList[$email])) {
                continue;
            }

            if (is_int($key)) {
                // Unset existing email list position
                if (isset($this->_addresses[$key])) {
                    $existingAddress = $this->_addresses[$key];
                    unset($this->_emailList[$existingAddress->email]);
                }

                $this->_addresses[$key] = $address;
                $this->_emailList[$email] = $key;
                continue;
            }

            $this->_emailList[$email] = count($this->_addresses);
            $this->_addresses[] = $address;
        }

    }

/**
 * Set/Get strict option.
 *
 * @param bool $strict Strict
 * @return $this|bool
 */
    public function strict($strict = null) {
        if ($strict === null) {
            return $this->_strict;
        }

        $this->_strict = $strict;

        return $this;
    }

/**
 * RFC822 addresses.
 *
 * @return string RFC 822 addresses ()
 * @see https://tools.ietf.org/html/rfc5322#appendix-A.1
 */
    public function getRfc822($htmlEncoded = false) {
        return implode(', ', array_map(function ($address) use ($htmlEncoded) {
            return $address->getRfc822($htmlEncoded);
        }, $this->toArray()));
    }

/**
 * __toString.
 *
 * @return string RFC 822 addresses ()
 * @see https://tools.ietf.org/html/rfc5322#appendix-A.1
 */
    public function __toString() {
        return $this->getRfc822();
    }

/**
 * To array.
 *
 * @return array
 */
    public function toArray() {
        return $this->_addresses;
    }

/**
 * Returns the properties that will be serialized as JSON
 *
 * @return array
 */
    public function jsonSerialize(): mixed {
        return $this->toArray();
    }

/**
 * Check if collection contains given email address.
 *
 * @param string $address Email address.
 * @return bool True if contains, false otherwise
 */
    public function contains($checkAddress) {
        foreach ($this->_addresses as $address) {
            if ($address->email() === $checkAddress) {
                return true;
            }
        }
        return false;
    }

/**
 * Check if collection is empty.
 *
 * @return bool True if empty, false otherwise
 */
    public function isEmpty(): bool {
        foreach ($this->_addresses as $address) {
            if (!$address->isEmpty()) {
                return false;
            }
        }
        return true;
    }

/**
 * \Iterator implementation.
 *
 * @return void
 */
    public function rewind(): void {
        $this->_index = 0;
    }

/**
 * \Iterator implementation.
 *
 * @return bool
 */
    public function valid(): bool {
        return isset($this->_addresses[$this->_index]);
    }

/**
 * \Iterator implementation.
 *
 * @return int
 */
    public function key(): int {
        return $this->_index;
    }

/**
 * \Iterator implementation.
 *
 * @return Address
 */
    public function current(): mixed {
        return $this->_addresses[$this->_index];
    }

/**
 * \Iterator implementation.
 *
 * @return void
 */
    public function next(): void {
        $this->_index++;
    }

/**
 * Implements isset($collection['email@address.com']) or isset($collection[0]);
 *
 * @param int|string $offset The offset to check.
 * @return bool Success
 */
    public function offsetExists($offset): bool {
        if (is_string($offset)) {
            return isset($this->_emailList[$offset]);
        }
        return isset($this->_addresses[$offset]);
    }

/**
 * Implements $collection[$offset];
 *
 * @param int|string $offset The offset to get.
 * @return mixed
 */
    public function &offsetGet($offset): mixed {
        if (is_string($offset)) {
            $offset = $this->_emailList[$offset];
        }
        return $this->_addresses[$offset];
    }

/**
 * Implements $collection[$offset] = $value;
 *
 * @param int|string $offset The offset to set.
 * @param mixed $value The value to set.
 * @return void
 */
    public function offsetSet($offset, $value): void {
        $this->_appendAddresses($value, $offset);
    }

/**
 * Implements unset($result[$offset);
 *
 * @param int|string $offset The offset to remove.
 * @return void
 */
    public function offsetUnset($offset): void {
        if (is_string($offset)) {
            $offset = $this->_emailList[$offset];
        }

        $address = $this->_addresses[$offset];
        unset($this->_emailList[$address->email]);
        // Reset email list
        $this->_emailList = array_flip(
            array_values(
                array_flip($this->_emailList)
            )
        );

        $this->_addresses[$offset] = null;
        unset($this->_addresses[$offset]);
        // Reset array
        $this->_addresses = array_values($this->_addresses);
    }

}
