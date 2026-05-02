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

use RuntimeException;

/**
 * IMAP message mover.
 *
 * This class is used to move messages between IMAP folders using the UID MOVE
 * command and parsing the response to get the new UID.
 * This is superior to the native PHP IMAP functions because it uses the UID MOVE
 * command and parsing the response to get the new UID.
 *
 * @link https://tools.ietf.org/html/rfc6851
 */
class ImapMessageMover {

/**
 * The IMAP server host.
 *
 * @var string
 */
    private string $_host;

/**
 * The port number.
 *
 * @var int
 */
    private int $_port;

/**
 * The username.
 *
 * @var string
 */
    private string $_username;

/**
 * The password.
 *
 * @var string
 */
    private string $_password;

/**
 * The socket.
 *
 * @var resource|null
 */
    private $_socket = null;

/**
 * The tag count.
 *
 * @var int
 */
    private int $_tagCount = 1;

/**
 * Constructor.
 *
 * @param string $host The IMAP server host
 * @param string $username The username
 * @param string $password The password
 * @param int $port The port number
 */
    public function __construct(string $host, string $username, string $password, int $port = 993) {
        $this->_host = $host;
        $this->_username = $username;
        $this->_password = $password;
        $this->_port = $port;

        register_shutdown_function(function () {
            $this->disconnect();
        });
    }

/**
 * Move the message.
 *
 * @param string|int $msgUid The message UID
 * @param string $targetFolder The target folder
 * @param string $sourceFolder The source folder
 * @return int|null The new UID or null on failure
 */
    public function moveMessage(string|int $msgUid, string $targetFolder, string $sourceFolder = 'INBOX') {
        if (!$this->_connect()) {
            return null;
        }

        // 1. Select the INBOX (or source folder)
        // Note: Usually UID moves are done from the currently selected folder.
        // If your logic requires moving FROM a specific folder, select it here.
        $this->sendCommand("SELECT {$sourceFolder}");

        // 2. Perform the UID MOVE (RFC 6851)
        // Many servers (Gmail included) support UID MOVE.
        // If not, standard fallback is COPY + STORE \Deleted + EXPUNGE (complex).
        // Let's try the modern UID MOVE first.
        $tag = "A" . $this->_tagCount++;
        $command = "{$tag} UID MOVE {$msgUid} \"{$targetFolder}\"\r\n";
        fwrite($this->_socket, $command);

        // 3. Parse Response
        $newUid = null;
        while (!feof($this->_socket)) {
            $line = fgets($this->_socket);

            // Capture the COPYUID response
            // Format: * OK [COPYUID <uidvalidity> <source-uids> <dest-uids>]
            if (preg_match('/\[COPYUID \d+ \d+ (\d+)\]/', $line, $matches)) {
                $newUid = $matches[1];
            }

            // Break when command finishes
            if (strpos($line, "{$tag} ") === 0) {
                break;
            }
        }

        return $newUid;
    }

/**
 * Move multiple messages and return the COPYUID map (src UID => dst UID).
 *
 * @param string|array $msgUids The message UIDs (array or IMAP UID-set string)
 * @param string $targetFolder The target folder
 * @param string $sourceFolder The source folder
 * @return array<int,int> Map of source UID to destination UID
 */
    public function moveMessages(string|array $msgUids, string $targetFolder, string $sourceFolder = 'INBOX'): array {
        if (!$this->_connect()) {
            return [];
        }

        // Ensure source folder is selected
        $this->sendCommand("SELECT {$sourceFolder}");

        // Normalize and compact UID set for the MOVE command
        $uidSet = $this->compactUidSet($msgUids);

        // Perform MOVE
        $tag = "A" . $this->_tagCount++;
        $command = "{$tag} UID MOVE {$uidSet} \"{$targetFolder}\"\r\n";
        fwrite($this->_socket, $command);

        // Parse COPYUID response into a map
        $copyUidMap = [];
        while (!feof($this->_socket)) {
            $line = fgets($this->_socket);
            if (preg_match('/\[COPYUID\s+(\d+)\s+([^\s\]]+)\s+([^\s\]]+)\]/', $line, $m)) {
                $copyUidMap = $this->buildCopyUidMap($m[2], $m[3]);
            }
            if (strpos($line, "{$tag} ") === 0) {
                break;
            }
        }

        return $copyUidMap;
    }

/**
 * Connect to the IMAP server.
 *
 * @return boolean True if successful, false otherwise
 */
    private function _connect() {
        if ($this->isConnected()) {
            return true;
        }

        $target = "ssl://{$this->_host}:{$this->_port}";
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);

        $this->_socket = stream_socket_client($target, $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
        if (!$this->_socket) {
            return false;
        }

        // Consume initial server greeting
        fgets($this->_socket);

        // Login
        $tag = "L01";
        fwrite($this->_socket, "{$tag} LOGIN \"{$this->_username}\" \"{$this->_password}\"\r\n");

        // Consume login response
        while (!feof($this->_socket)) {
            $line = fgets($this->_socket);
            if (strpos($line, "{$tag} ") === 0) {
                return true;
            }
            if (strpos($line, "{$tag} NO") === 0 || strpos($line, "{$tag} BAD") === 0) {
                return false;
            }
        }

        return false;
    }

/**
 * Find the UID of the message from a given message ID.
 * This is
 *
 * @param string $messageId The message ID to find
 * @param string $folder The folder to search in
 * @return int|null The UID of the message or null if not found
 */
    public function uidFinder(string $messageId, string $folder = 'INBOX'): ?int {
        if (!$this->_connect()) {
            return null;
        }

        // 1) Select folder
        $tag = "A" . $this->_tagCount++;
        fwrite($this->_socket, "{$tag} SELECT \"{$folder}\"\r\n");
        while (!feof($this->_socket)) {
            $line = fgets($this->_socket);
            if (strpos($line, "{$tag} ") === 0) {
                if (str_starts_with($line, "{$tag} NO") || str_starts_with($line, "{$tag} BAD")) {
                    return null;
                }
                break;
            }
        }

        // 2) Search by Message-ID header
        $escapedId = addcslashes($messageId, '\\"');
        $tag = "A" . $this->_tagCount++;
        fwrite($this->_socket, "{$tag} UID SEARCH HEADER \"Message-ID\" \"{$escapedId}\"\r\n");

        $uids = [];
        while (!feof($this->_socket)) {
            $line = fgets($this->_socket);
            if (preg_match('/^\* SEARCH\s*(.*)$/', trim($line), $m)) {
                $tokens = preg_split('/\s+/', trim($m[1]));
                foreach ($tokens as $tok) {
                    if (ctype_digit($tok)) {
                        $uids[] = (int)$tok;
                    }
                }
            }
            if (strpos($line, "{$tag} ") === 0) {
                break;
            }
        }

        return $uids[0] ?? null;
    }

/**
 * Send a command to the IMAP server.
 *
 * @param string $cmd The command to send
 * @return void
 */
    private function sendCommand(string $cmd) {
        $tag = "C" . $this->_tagCount++;
        fwrite($this->_socket, "$tag $cmd\r\n");
        // Simple consume until done (for non-critical commands)
        while (!feof($this->_socket)) {
            $line = fgets($this->_socket);
            if (strpos($line, "$tag ") === 0) {
                break;
            }
        }
    }

/**
 * Check if the IMAP server is connected.
 *
 * @return bool True if connected, false otherwise
 */
    public function isConnected(): bool {
        return $this->_socket !== null;
    }

/**
 * Expand the UID set.
 *
 * @param string $set The UID set to expand
 * @return array The expanded UID set
 */
    public function expandUidSet(string $set): array {
        $out = [];
        foreach (explode(',', $set) as $part) {
            $part = trim($part);
            if ($part === '' || $part === '*') {
                continue;
            }
            if (strpos($part, ':') === false) {
                $out[] = (int)$part;
                continue;
            }

            [$a, $b] = array_map('intval', explode(':', $part, 2));
            if ($a <= $b) {
                for ($i = $a; $i <= $b; $i++) {
                    $out[] = $i;
                }
            } else {
                for ($i = $a; $i >= $b; $i--) {
                    $out[] = $i;
                }
            }
        }
        return $out;
    }

/**
 * Build the MOVE UID set from a list of UIDs.
 * Consecutive UIDs are compressed into IMAP UID ranges (start:end).
 *
 * #### Example
 * ```
 * $mover->buildMoveUidSet([1, 2, 3, 4, 45, 50]);
 * // returns '1:4,45,50'
 * ```
 *
 * @param string|array $uids The list of UIDs or an IMAP UID-set string
 * @return string The compressed IMAP UID-set
 */
    public function compactUidSet(string|array $uids): string {
        // Normalize input into a list of integer UIDs
        if (is_string($uids)) {
            $list = $this->expandUidSet($uids);
        } else {
            $list = array_map('intval', $uids);
        }

        if (empty($list)) {
            return '';
        }

        // Sort and de-duplicate
        sort($list, SORT_NUMERIC);
        $list = array_values(array_unique($list));

        // Compress consecutive sequences into ranges
        $parts = [];
        $start = $list[0];
        $prev = $start;
        $count = count($list);
        for ($i = 1; $i < $count; $i++) {
            $curr = $list[$i];
            if ($curr === $prev + 1) {
                $prev = $curr;
                continue;
            }
            // Close previous run
            $parts[] = ($start === $prev) ? (string)$start : "{$start}:{$prev}";
            // Start new run
            $start = $prev = $curr;
        }
        // Close the last run
        $parts[] = ($start === $prev) ? (string)$start : "{$start}:{$prev}";

        return implode(',', $parts);
    }

/**
 * Build the COPYUID map.
 *
 * @param string $srcSet The source UID set
 * @param string $dstSet The destination UID set
 * @return array The COPYUID map
 */
    public function buildCopyUidMap(string $srcSet, string $dstSet): array {
        $src = $this->expandUidSet($srcSet);
        $dst = $this->expandUidSet($dstSet);
        if (count($src) !== count($dst)) {
            throw new RuntimeException('COPYUID count mismatch');
        }
        $map = [];
        foreach ($src as $i => $s) {
            $map[$s] = $dst[$i];
        }
        return $map;
    }

/**
 * Disconnect from the IMAP server.
 *
 * @return void
 */
    public function disconnect() {
        if ($this->_socket) {
            fwrite($this->_socket, "LOGOUT\r\n");
            fclose($this->_socket);
            $this->_socket = null;
            $this->_tagCount = 1;
        }
    }

}