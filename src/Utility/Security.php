<?php
/**
 * Core Security
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         CakePHP(tm) v .0.10.0.1233
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\Utility;

use Nata\Core\Configure;
use Exception;
use InvalidArgumentException;

/**
 * Security Library contains utility methods related to security.
 */
class Security {

/**
 * Default hash method.
 *
 * @var string
 */
    public static $hashType;

/**
 * Default cost.
 *
 * @var string
 */
    public static $hashCost = '10';


/**
 * Create a hash from string using given method or fallback on next available method.
 *
 * #### Using Blowfish
 *
 * - Creating Hashes: *Do not supply a salt*. Cake handles salt creation for
 * you ensuring that each hashed password will have a *unique* salt.
 * - Comparing Hashes: Simply pass the originally hashed password as the salt.
 * The salt is prepended to the hash and php handles the parsing automagically.
 * For convenience the BlowfishAuthenticate adapter is available for use with
 * the AuthComponent.
 * - Do NOT use a constant salt for blowfish!
 *
 * Creating a blowfish/bcrypt hash:
 *
 * {{{
 *     $hash = Security::hash($password, 'blowfish');
 * }}}
 *
 * @param string $string String to hash
 * @param string $type Method to use (sha1/sha256/md5/blowfish)
 * @param mixed $salt If true, automatically prepends the application's salt
 *     value to $string (Security.salt). If you are using blowfish the salt
 *     must be false or a previously generated salt.
 * @return string Hash
 * @link http://book.cakephp.org/2.0/en/core-utility-libraries/security.html#Security::hash
 */
    public static function hash($string, $type = null, $salt = false) {
        if (empty($type)) {
            $type = static::$hashType;
        }
        $type = strtolower($type);

        if ($type === 'blowfish') {
            return static::_crypt($string, $salt);
        }
        if ($salt) {
            if (!is_string($salt)) {
                $salt = Configure::read('Security.salt');
            }
            $string = $salt . $string;
        }

        if (!$type || $type === 'sha1') {
            if (function_exists('sha1')) {
                return sha1($string);
            }
            $type = 'sha256';
        }

        if ($type === 'sha256' && function_exists('mhash')) {
            return bin2hex(mhash(MHASH_SHA256, $string));
        }

        if (function_exists('hash')) {
            return hash($type, $string);
        }
        return md5($string);
    }

/**
 * Sets the default hash method for the Security object. This affects all objects using
 * Security::hash().
 *
 * @param string $hash Method to use (sha1/sha256/md5/blowfish)
 * @return void
 * @see Security::hash()
 */
    public static function setHash($hash) {
        static::$hashType = $hash;
    }

/**
 * Sets the cost for they blowfish hash method.
 *
 * @param integer $cost Valid values are 4-31
 * @return void
 * @throws Nata\Exception When cost is invalid.
 */
    public static function setCost($cost) {
        if ($cost < 4 || $cost > 31) {
            throw new Exception(vsprintf(
                'Invalid value, cost must be between %s and %s',
                array(4, 31)
            ));
        }
        static::$hashCost = $cost;
    }

/**
 * Get random bytes from a secure source.
 *
 * This method will fall back to an insecure source an trigger a warning
 * if it cannot find a secure source of random data.
 *
 * @param int $length The number of bytes you want.
 * @return string Random bytes in binary.
 */
    public static function randomBytes($length) {
        if (function_exists('random_bytes')) {
            return random_bytes($length);
        }

        if (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes($length, $strongSource);
            if (!$strongSource) {
                trigger_error(
                    'openssl was unable to use a strong source of entropy. ' .
                    'Consider updating your system libraries, or ensuring ' .
                    'you have more available entropy.',
                    E_USER_WARNING
                );
            }
            return $bytes;
        }

        trigger_error(
            'You do not have a safe source of random data available. ' .
            'Install either the openssl extension, or paragonie/random_compat. ' .
            'Falling back to an insecure random source.',
            E_USER_WARNING
        );

        return static::insecureRandomBytes($length);
    }

/**
 * Like randomBytes() above, but not cryptographically secure.
 *
 * @param int $length The number of bytes you want.
 * @return string Random bytes in binary.
 * @see \Nata\Utility\Security::randomBytes()
 */
    public static function insecureRandomBytes($length) {
        $length *= 2;
        $bytes = '';
        $byteLength = 0;
        while ($byteLength < $length) {
            $bytes .= static::hash(Text::uuid() . uniqid(mt_rand(), true), 'sha512', true);
            $byteLength = strlen($bytes);
        }

        $bytes = substr($bytes, 0, $length);

        return pack('H*', $bytes);
    }

/**
 * Generates a pseudo random salt suitable for use with php's crypt() function.
 * The salt length should not exceed 27. The salt will be composed of
 * [./0-9A-Za-z]{$length}.
 *
 * @param integer $length The length of the returned salt
 * @return string The generated salt
 */
    protected static function _salt($length = 22) {
        $salt = str_replace(
            array('+', '='),
            '.',
            base64_encode(sha1(uniqid(Configure::read('Security.salt'), true), true))
        );
        return substr($salt, 0, $length);
    }

/**
 * One way encryption using php's crypt() function. To use blowfish hashing see ``Security::hash()``
 *
 * @param string $password The string to be encrypted.
 * @param mixed $salt false to generate a new salt or an existing salt.
 * @return string The hashed string or an empty string on error.
 * @throws \Nata\Exception on invalid salt values.
 */
    protected static function _crypt($password, $salt = false) {
        if ($salt === false) {
            $salt = static::_salt(22);
            $salt = vsprintf('$2y$%02d$%s', array(static::$hashCost, $salt));
        }

        if ($salt === true || strpos($salt, '$2y$') !== 0 || strlen($salt) < 29) {
            throw new Exception(sprintf(
                'Invalid salt: %s for blowfish Please visit http://www.php.net/crypt and read the appropriate section for building blowfish salts.',
                $salt
            ));
        }

        return crypt($password, $salt);
    }

/**
 * Encrypt a value using AES-256.
 *
 * *Caveat* You cannot properly encrypt/decrypt data with trailing null bytes.
 * Any trailing null bytes will be removed on decryption due to how PHP pads messages
 * with nulls prior to encryption.
 *
 * @param string $plain The value to encrypt.
 * @param string $key The 256 bit/32 byte key to use as a cipher key.
 * @param string $hmacSalt The salt to use for the HMAC process. Leave null to use Security.salt.
 * @param string $method The cipher method.
 * @return string Encrypted data.
 * @throws \Nata\Exception On invalid data or key.
 */
    public static function encrypt($plain, $key, $hmacSalt = null, $method = 'AES-256-OFB') {
        static::_checkKey($key, __METHOD__);

        if (!function_exists('openssl_encrypt')) {
            throw new Exception('openssl is not installed!');
        }

        $method = static::_getNormalizedCipherMethod($method);

        if ($hmacSalt === null) {
            $hmacSalt = Configure::read('Security.salt');
        }

        // Generate the encryption and hmac key.
        $key = substr(hash('sha256', $key . $hmacSalt), 0, 32);

        $ivlen = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertextRaw = openssl_encrypt($plain, $method, $key, OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $ciphertextRaw, $key, true);
        $ciphertext = base64_encode($iv . $hmac . $ciphertextRaw);

        return $ciphertext;
    }

/**
 * Decrypt a value using AES-256.
 *
 * @param string $ciphertext The ciphertext to decrypt.
 * @param string $key The 256 bit/32 byte key to use as a cipher key.
 * @param string $hmacSalt The salt to use for the HMAC process. Leave null to use Security.salt.
 * @return string Decrypted data. Any trailing null bytes will be removed.
 * @throws \Nata\Exception On invalid data or key.
 */
    public static function decrypt($ciphertext, $key, $hmacSalt = null, $method = 'AES-256-OFB') {
        static::_checkKey($key, __METHOD__);

        if (!function_exists('openssl_encrypt')) {
            throw new Exception('openssl is not installed!');
        }

        if (empty($ciphertext)) {
            throw new InvalidArgumentException('The data to decrypt cannot be empty.');
        }

        $method = static::_getNormalizedCipherMethod($method);

        if ($hmacSalt === null) {
            $hmacSalt = Configure::read('Security.salt');
        }

        // Generate the encryption and hmac key.
        $sha2length = 32;
        $key = substr(hash('sha256', $key . $hmacSalt), 0, $sha2length);

        $ciphertext = base64_decode($ciphertext);
        $ivlen = openssl_cipher_iv_length($method);
        $iv = substr($ciphertext, 0, $ivlen);
        $hmac = substr($ciphertext, $ivlen, $sha2length);
        $ciphertextRaw = substr($ciphertext, $ivlen + $sha2length);
        $plaintext = openssl_decrypt($ciphertextRaw, $method, $key, OPENSSL_RAW_DATA, $iv);
        $calcmac = hash_hmac('sha256', $ciphertextRaw, $key, true);

        // PHP 5.6+ timing attack safe comparison
        if (!hash_equals($hmac, $calcmac)) {
            return;
        }

        return $plaintext;
    }

/**
 * Check the encryption key for proper length.
 *
 * @param string $method The method the key is being checked for.
 * @return string Normalized method according to the case being used
 * @throws \Exception When invalid method is passed
 */
    protected static function _getNormalizedCipherMethod($method) {
        list($sample) = $methods = openssl_get_cipher_methods();

        // Remove numbers before case checking
        $sample = str_replace(['-', '_'], '', preg_replace('/\d+/u', '', $sample));
        if (ctype_lower($sample)) {
            $method = strtolower($method);
        } else {
            $method = strtoupper($method);
        }

        if (!in_array($method, $methods)) {
            throw new Exception(sprintf('Cipher %s is not supported!', $method));
        }

        return $method;
    }

/**
 * Check the encryption key for proper length.
 *
 * @param string $key
 * @param string $method The method the key is being checked for.
 * @return void
 * @throws \Exception When key length is not 256 bit/32 bytes
 */
    protected static function _checkKey($key, $method) {
        if (strlen($key) < 32) {
            throw new Exception(sprintf('Invalid key for Security::%s(), key must be at least 256 bits (32 bytes) long.', $method));
        }
    }

}
