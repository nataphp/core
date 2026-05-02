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

namespace Nata\Service\Auth;

use Nata\Service\Service;
use Nata\I18n\Time;
use Exception;
use Nata\ORM\Entity;

/**
 * Service for creation and validation of JSON Web Tokens.
 *
 * $token = $jwt->payload(['authUser' => 1])->get();
 *
 * @link https://jwt.io/
 */
class JsonWebToken extends Service {

/**
 * Default config.
 *
 * @var array
 */
    protected $_defaultConfig = [
        'algo' => 'HS256',
        'type' => 'JWT',
        'subject' => null,
        'issuer' => null,
        'audience' => null,
        'expirationTime' => '1 day',
        'secret' => null // 'your-256-bit-secret'
    ];

/**
 * Algorithm map.
 *
 * @var array
 */
    protected $_algoMap = [
        'HS256' => 'sha256',
        'HS384' => 'sha384',
        'HS512' => 'sha512'
    ];

/**
 * JWT header used.
 *
 * @var array
 */
    protected $_header;

/**
 * JWT payload used.
 *
 * @var array
 */
    protected $_payload;


/**
 * Generates and returns a JSON Web Token.
 *
 * @param array|Entity $payload Payload
 * @return string Generated JWT
 */
    public function generate(): string {
        $payload = $this->_encodePayload();
        $header = $this->_encodeHeader();
        $signature = $this->_generateSignature($header, $payload);
        return $header . '.' . $payload . '.' . $signature;
    }

/**
 * Takes a JWT token and decodes it into the respective parts.
 *
 * @param string $token JWT token
 * @return array JWT header and payload
 */
    public function decode(string $token): self {
        return $this;
    }

/**
 * Get/set the header.
 *
 * @param array $header Header
 * @return array Header
 */
    public function header(array $header = null) {
        if (func_num_args() === 0) {
            return $this->_header;
        }

        $this->_header = $header;

        return $this;
    }

/**
 * Get/set the payload.
 *
 * @param array $payload Payload
 * @return array Payload
 */
    public function payload(array $payload = null) {
        if (func_num_args() === 0) {
            return $this->_payload;
        }

        $this->_payload = $payload;

        return $this;
    }

/**
 * Get/set the subject.
 *
 * @param string|int|Entity $subject Subject
 * @return $this|string Subject
 */
    public function subject($subject = null) {
        if (func_num_args() === 0) {
            return $this->config('subject');
        }

        if ($subject instanceof Entity) {
            $subject = $subject->source() . '::' . $subject->id;
        }

        $this->config('subject', $subject);

        return $this;
    }

/**
 * Check if given token is valid.
 *
 * @param string $token Token
 * @return bool True if valid, false otherwise
 */
    public function valid($token) {
        [$header, $payload, $signature] = splitter($token, '.', 3);
        return $this->_generateSignature($header, $payload) === $signature && $this->_checkIssuer($token);
    }

/**
 * Refresh/renew token.
 * Only possible if has not expired yet.
 *
 * @param string $token Token
 * @return string Refreshed token
 */
    public function refresh($token) {
        if (!$this->valid($token) || $this->expired($token)) {
            return null;
        }

        [$h, $payload, $s] = splitter($token, '.', 3);
        $payload = $this->_decode($payload);
        return $this->get($payload);
    }

/**
 * Check if given token needs renewal/refresh
 * because it's valid, but has expired.
 *
 * @param string $token Token
 * @return bool True if needs renewal, false otherwise
 */
    public function needsRenewal($token) {
        return $this->valid($token) && $this->expired($token);
    }

/**
 * Check if given token has expired.
 *
 * @param string $token Token
 * @return bool True if expired, false otherwise
 */
    public function expired($token) {
        if (!$this->valid($token)) {
            return false;
        }

        [$h, $payload, $s] = splitter($token, '.', 3);
        $payload = $this->_decode($payload);
        return (new Time($payload['exp']))->isPast();
    }

/**
 * Get the header.
 *
 * @return string Base64url encoded header
 */
    protected function _encodeHeader() {
        $header = (array)$this->header();

        $header['alg'] = $header['alg'] ?? $this->config('algo');
        if (!$this->_isAlgoSupported($header)) {
            throw new Exception(sprintf('JWT algoritm "%s" is not supported at the moment.', $header['alg']));
        }

        $header['typ'] = $header['typ'] ?? $this->config('type');

        $this->_header = $header;

        return $this->_encode($header);
    }

/**
 * Decode the payload.
 *
 * @param string $token Token
 * @return array Payload
 */
    protected function _decodeHeader(string $token) {
        [$header] = splitter($token, '.', 1);
        return $this->_decode($header);
    }

/**
 * Encode the payload.
 *
 * It will had the 'iat' (issued at) timestamp.
 *
 * @param array $payload Payload
 * @return string Base64url encoded payload
 */
    protected function _encodePayload() {
        $payload = (array)$this->payload();

        // Issued by...
        if (!isset($payload['iss']) && $issuer = $this->config('issuer')) {
            $payload['iss'] = $issuer;
        }

        $payload['iat'] = Time::now()->timestamp();

        // Expiration time...
        if ($expirationTime = $this->config('expirationTime')) {
            $expirationTime = (new Time())->modify($expirationTime)->timestamp();
            $payload['exp'] = $expirationTime;
        }

        // Audience by...
        if (!isset($payload['iss']) && $audience = $this->config('audience')) {
            $payload['aud'] = $audience;
        }

        $this->_payload = $payload;

        return $this->_encode($payload);
    }

/**
 * Decode the payload.
 *
 * @param string $token Token
 * @return array Payload
 */
    protected function _decodePayload(string $token) {
        [$h, $payload, $s] = splitter($token, '.', 3);
        return $this->_decode($payload);
    }

/**
 * Check if alg present in given header
 * is supported.
 *
 * @param array|string $header Header
 * @return bool True if supported, false otherwise
 */
    protected function _checkIssuer($token) {
        $payload = $this->_decodePayload($token);
        $issuer = $this->config('issuer');

        if (!$issuer && !isset($payload['iss'])) {
            return true;
        }

        if (!isset($payload['iss'])) {
            return false;
        }

        return $issuer === $payload['iss'];
    }

/**
 * Generate the hash/signature based on given header and payload.
 *
 * @param array|string $header Header
 * @param array|string $payload Payload
 * @return string Base64 URL encoded hash
 */
    protected function _generateSignature($header, $payload) {
        $algo = $this->_getAlgo($header);
        if ($algo === null) {
            return false;
        }

        if (is_array($header)) {
            $header = $this->_encode($header);
        }

        if (is_array($payload)) {
            $payload = $this->_encode($payload);
        }

        $signature = hash_hmac($algo, $header . '.' . $payload, $this->config('secret'), true);

        return base64url_encode($signature);
    }

/**
 * Get algoritm in header.
 *
 * @param array|string $header Header
 * @return string Algo
 */
    protected function _getAlgo($header) {
        if (is_string($header)) {
            $header = $this->_decode($header);
        }

        $algo = $header['alg'] ?? $this->config('algo');
        $algo = strtoupper($algo);

        return isset($this->_algoMap[$algo]) ? $this->_algoMap[$algo] : null;
    }

/**
 * Check if alg present in given header
 * is supported.
 *
 * @param array|string $header Header
 * @return bool True if supported, false otherwise
 */
    protected function _isAlgoSupported($header) {
        return $this->_getAlgo($header) !== null;
    }

/**
 * Encode (JSON + Base64 URL) the data.
 *
 * @param array $data Data to encode
 * @return string Encoded data
 */
    protected function _encode(array $data) {
        return base64url_encode(json_encode($data));
    }

/**
 * Decode (Base64 URL + JSON) the data.
 *
 * @param string $data Data to decode
 * @return array Decoded data
 */
    protected function _decode($data) {
        return json_decode(base64url_decode($data), true);
    }

}
