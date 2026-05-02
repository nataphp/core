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

namespace Nata\Email\Client;

/**
 * @TODO Place the parsing logic inside the client into this class for reuse outside.
 *
 * It should not be dependent on the connection or the UID of the message, just the string.
 *
 * Parser for given MIME message to be parsed into respective parts.
 */
class MimeMessageParser {


/**
 * Constructor.
 *
 * @param string $mimeMessage MIME message
 */
    public function __construct(string $mimeMessage) {}

/**
 * Parse MIME message header.
 *
 * @param resource $stream Connection stream
 * @param int $uid Email UID
 * @return array Parsed message header
 */
    public static function parseHeader(string $header): ?array {
        if (!$header) {
            return null;
        }

        preg_match_all('/([^: ]+): (.+?(?:\r\n\s(?:.+?))*)\r\n/m', $header, $matches);
        if (count($matches) < 3) {
            return null;
        }

        return array_combine($matches[1], $matches[2]);
    }

/**
 * Decode message part.
 *
 * @param mixed $data Body part to decode
 * @param int $encoding Encoding ID
 * @return mixed Decoded message/data
 */
    public static function decode($data, int $encoding): string {
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

}
