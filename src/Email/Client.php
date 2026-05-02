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

use Exception;
use Nata\Cache\Cache;
use Nata\Email\Client\Message;
use Nata\Core\Configure;
use Nata\Email\Exception\ConnectionFailedException;
use Nata\Core\App;
use Nata\Core\NataObject;
use Nata\Email\Exception\ConnectionTimeoutException;
use Nata\Utility\Inflector;

/**
 * IMAP, POP3 and NNTP basic (for now) implementation for PHP's imap_* functions.
 *
 * @link http://php.net/manual/en/book.imap.php
 */
class Client extends NataObject {

/**
 * Default configuration.
 *
 * @var array
 */
    protected $_defaultConfig = [
        'host' => null,
        'port' => 993,
        'ssl' => true,
        'timeout' => 60,
        'username' => null,
        'password' => null,
        'folder' => 'INBOX',
        'server' => 'imap4',
        'attachmentCacheLifetime' => '5 days',
        'cache' => '_nata_core_email_client_'
    ];

/**
 * Provider adapter instance.
 *
 * @var \Nata\Email\Client\Provider
 */
    protected $_provider;

/**
 * Custom socket stream.
 *
 * @var resource
 */
    protected $_socketStream;

/**
 * Criteria map.
 *
 * @var array
 */
    protected $_criteriaMap = [
        'ALL'
    ];


/**
 * Construtor.
 *
 * @param string|array $host URL/Host to connect to or array
 * @param array $config Configuration
 * @return void
 */
    public function __construct($host, array $config = []) {
        if (is_array($host)) {
            $config = $host;
        } else {
            $config += ['host' => $host];
        }

        $config += (array)Configure::read('Email');
        if (!empty($config)) {
            $this->config($config);
        }

        $cache = $this->config('cache');
        if (!in_array($cache, Cache::configured())) {
            Cache::config($cache, [
                'engine' => ['Apc', 'File'],
                'duration'=> '1 month',
                'servers' => 'localhost',
                'probability'=> 90,
                'prefix' => 'maismls_',
                'lock' => false,
                'serialize' => 'php'
            ]);
        }
    }

/**
 * Send custom command through stream.
 *
 * @return \Nata\Email\Client\Provider
 */
    protected function _loadProvider() {
        if ($this->_provider === null) {
            $host = $this->config('host');

            if (preg_match("/\.([a-z0-9]+).([a-z0-9]+)/i", $host, $matches)) {
                $class = Inflector::classify($matches[1]);
                $className = App::className($class, 'Email/Client/Provider');
                if (!$className) {
                    $className = '\Nata\Email\Client\Provider';
                }
            }

            $this->_provider = new $className($this->config());
        }
        return $this->_provider;
    }

/**
 * Login.
 *
 * @param string $username Username
 * @param string $password Password
 * @return boolean True if successful, false otherwise
 */
    public function login($username = null, $password = null) {
        return $this->_loadProvider()->login($username, $password);
    }

/**
 * Login successfully or fail.
 *
 * @param string $username Username
 * @param string $password Password
 * @return boolean True if successful, false otherwise
 * @throws ConnectionFailedException If login fails
 */
    public function loginOrFail($username = null, $password = null) {
        return $this->_loadProvider()->loginOrFail($username, $password);
    }

/**
 * Check if is connected.
 *
 * @return bool True if connected, false otherwise
 */
    public function isConnected() {
        return $this->_loadProvider()->ping();
    }

/**
 * Get/Set IMAP stream to a mailbox.
 *
 * @param resource $stream Stream
 * @return resource|$this
 */
    public function stream($stream = null) {
        return $this->_loadProvider()->stream($stream);
    }

/**
 * Check if the IMAP stream is still active.
 *
 * @return bool
 */
    public function ping() {
        return $this->_loadProvider()->ping();
    }

/**
 * Get or change current folder.
 *
 * @param string $folder Folder name
 * @return $this|boolean
 */
    public function folder($folder = null) {
        return $this->_loadProvider()->folder($folder);
    }

/**
 * Check if given folder exists.
 *
 * @param string $name Folder name to check
 * @return bool True if folder exists, false otherwise
 */
    public function folderExists($name): bool {
        return $this->_loadProvider()->folderExists($name);
    }

/**
 * Get or change current folder.
 *
 * @param string $folder Folder name
 * @return $this|boolean
 */
    public function listSubscribed($pattern = '*') {
        return $this->_loadProvider()->listSubscribed($pattern);
    }

/**
 * Get or change current folder.
 *
 * @param string $folder Folder name
 * @return $this|boolean
 */
    public function subscribe($folder = null) {
        return $this->_loadProvider()->subscribe($folder);
    }

/**
 * Returns headers for all messages in a mailbox.
 *
 * @return array Formatted with header info. One element per mail message.
 */
    public function getHeaders() {
        return $this->_loadProvider()->getHeaders();
    }

/**
 * Read mailbox.
 *
 * ## Example
 * $this->get([
 *  'ALL SUBJECT' => [
 *      'matching' => ['My Subject Message'],
 *      'since' => Time::now()->modify('-1 hour')
 *  ]
 * ])
 *
 * NOTE: PHP IMAP client doesn't support OR yet
 *
 * @return \Nata\Collection\Collection
 */
    public function get($criteria = null) {
        return $this->_loadProvider()->get($criteria);
    }

/**
 * Read mailbox since last given UID.
 *
 * ## Example
 * $this->getSinceLast(1432);
 *
 * @param int $uid Last UID received
 * @return \Nata\Collection\Collection
 */
    public function getSinceLast(int $nextUid) {
        return $this->_loadProvider()->get($nextUid . ':*');
    }

/**
 * Read mailbox's unread/unseen emails.
 *
 * @return \Nata\Collection\Collection
 */
    public function getRead($criteria = null) {
        return $this->_loadProvider()->getRead($criteria);
    }

/**
 * Read mailbox's unread/unseen emails.
 *
 * @return \Nata\Collection\Collection
 */
    public function getDeleted() {
        return $this->_loadProvider()->getDeleted();
    }

/**
 * Read mailbox's unread/unseen emails.
 *
 * @return \Nata\Collection\Collection
 */
    public function getUnread() {
        return $this->_loadProvider()->getUnread();
    }

/**
 * Mark given email as 'SEEN'.
 *
 * @param Message $message Email to flag
 * @return boolean True if successful, false otherwise
 */
    public function markSeen(Message $message) {
        return $this->_loadProvider()->markSeen($message);
    }

/**
 * Mark given email as 'UNSEEN'.
 *
 * @param Message $message Email to flag
 * @return boolean True if successful, false otherwise
 */
    public function markUnseen(Message $message) {
        return $this->_loadProvider()->markUnseen($message);
    }

/**
 * Alias of Client::markSeen()
 */
    public function markRead(Message $message) {
        return $this->_loadProvider()->markRead($message);
    }

/**
 * Alias of Client::markUnseen()
 */
    public function markUnread(Message $message) {
        return $this->_loadProvider()->markUnread($message);
    }

/**
 * Archive email (flags email as deleted and seen).
 *
 * @param \Nata\Email\Client\Email $message Email to move
 * @return boolean True if successful, false otherwise
 */
    public function archive(Message $message) {
        return $this->_loadProvider()->archive($message);
    }

/**
 * Unarchive email (removes deleted and seen flag).
 *
 * @param \Nata\Email\Client\Email $message Email to move
 * @return boolean True if successful, false otherwise
 */
    public function unArchive(Message $message) {
        return $this->_loadProvider()->unArchive($message);
    }

/**
 * Mark email as deleted.
 *
 * @param \Nata\Email\Client\Email $message Email to flag
 * @return boolean True if successful, false otherwise
 */
    public function markDeleted(Message $message) {
        return $this->_loadProvider()->markDeleted($message);
    }

/**
 * Unmark email as deleted.
 *
 * @param \Nata\Email\Client\Email $message Email to move
 * @return boolean True if successful, false otherwise
 */
    public function unmarkDeleted(Message $message) {
        return $this->_loadProvider()->unmarkDeleted($message);
    }

/**
 * Mark email as draft.
 *
 * @param \Nata\Email\Client\Email $message Email to move
 * @return boolean True if successful, false otherwise
 */
    public function markDraft(Message $message) {
        return $this->_loadProvider()->markDraft($message);
    }

/**
 * Unmark email as draft.
 *
 * @param \Nata\Email\Client\Email $message Email to move
 * @return boolean True if successful, false otherwise
 */
    public function unmarkDraft(Message $message) {
        return $this->_loadProvider()->unmarkDraft($message);
    }

/**
 * Move email.
 *
 * @param \Nata\Email\Client\Email $message Email to move
 * @param string $folder Folder name
 * @return boolean True if successful, false otherwise
 */
    public function move(Message &$message, $folder) {
        return $this->_loadProvider()->move($message, $folder);
    }

/**
 * Move emails.
 *
 * @param array $messages Emails to move
 * @param string $folder Folder name or existing label
 */
    public function moveAll(array &$messages, $folder) {
        return $this->_loadProvider()->moveAll($messages, $folder);
    }

/**
 * Copy email.
 *
 * @param \Nata\Email\Client\Email $message Email to move
 * @param string $folder Folder name
 * @return Email|boolean Email copy if successful, false otherwise
 */
    public function copy(Message $message, $folder) {
        return $this->_loadProvider()->copy($message, $folder);
    }

/**
 * Copy emails.
 *
 * @param array $messages Emails to copy
 * @param string $folder Folder name
 * @return boolean True if successful, false otherwise
 */
    public function copyAll(array $messages, $mailbox) {
        return $this->_loadProvider()->copyAll($messages, $mailbox);
    }

/**
 * Get number of messages in current mailbox.
 *
 * @return int number of messages
 */
    public function count() {
        return $this->_loadProvider()->count();
    }

/**
 * Get number of recent messages in current mailbox.
 *
 * @return int number of recent messages
 */
    public function recentCount() {
        return $this->_loadProvider()->recentCount();
    }

/**
 * Get mailbox current status.

 * @return object True if successful, false otherwise
 * @see https://www.php.net/manual/en/function.imap-status.php
 */
    public function getStatus() {
        return $this->_loadProvider()->getStatus();
    }

/**
 * Read the list of mailboxes, returning detailed information on each one.
 *
 * @param string $pattern Specifies where in the mailbox hierarchy to start searching.
 *  There are two special characters you can pass as part of the pattern: '*' and '%'.
 *  '*' means to return all mailboxes.
 *  If you pass pattern as '*', you will get a list of the entire mailbox hierarchy.
 *  '%' means to return the current level only. '%' as the pattern parameter will return only the top level mailboxes;
 * '~/mail/%' on UW_IMAPD will return every mailbox in the ~/mail directory, but none in subfolders of that directory.
 * @return array True if successful, false otherwise
 * @see https://www.php.net/manual/en/function.imap-getmailboxes.php
 */
    public function getFolders($pattern = '*') {
        return $this->_loadProvider()->getFolders($pattern);
    }

/**
 * Get list of flags.
 *
 * @param Message $message Email to get flag(s) from
 * @return array|null
 */
    public function getFlags(Message $message): ?array {
        return $this->_loadProvider()->getFlags($message);
    }

/**
 * Add/Set flag(s).
 *
 * @param Message $message Email to add flag(s) to
 * @param string|array $flag Flag(s) to set
 * @return bool True if successful, false otherwise
 */
    public function addFlag(Message $message, $flag) {
        return $this->_loadProvider()->addFlag($message, $flag);
    }

/**
 * Remove flag(s).
 *
 * @param Message $message Email to add flag(s) to
 * @param string|array $flag Flag(s) to remove
 * @return bool True if successful, false otherwise
 */
    public function removeFlag(Message $message, $flag) {
        return $this->_loadProvider()->removeFlag($message, $flag);
    }

/**
 * EXPERIMENTAL
 * Send custom command through open sockets.
 *
 * @return \Nata\Collection\Collection
 */
    public function send(string $cmd, string $uid = '.') {
        $query = "{$cmd}\r\n";
        $stream = $this->_openSocket();
        $count = fwrite($stream, $query);
        if ($count !== strlen($query)) {
            throw new Exception(sprintf("Unable to execute '%s' command", $cmd));
        }

        $result = [];
        while ($line = fgets($stream)) {
            $line = preg_split('/\s+/', $line, 0, PREG_SPLIT_NO_EMPTY);
            print_a($line);
        }
        //$result[] = substr($line, 0, -2);

        return $result;
    }

/**
 * Send custom command through stream.
 *
 * @return \Nata\Collection\Collection
 */
    protected function _openSocket() {
        if ($this->_socketStream === null) {
            $host = ($this->config('ssl') === true ? 'ssl://' : '') . $this->config('host');
            $this->_socketStream = fsockopen($host, $this->config('port'), $errno, $errstr);
            if ($this->_socketStream && !stream_set_timeout($this->_socketStream, $this->config('timeout'))) {
                throw new ConnectionTimeoutException([$host . ':' . $this->config('port')]);
            }
        }
        return $this->_socketStream;
    }

/**
 * Logout/close IMAP stream.
 *
 * @param bool $expunge If set to true, the function will silently expunge the mailbox before closing,
 *  removing all messages marked for deletion. You can achieve the same thing by using imap_expunge.
 * @return boolean True if successful, false otherwise
 */
    public function logout($expunge = false) {
        return $this->_loadProvider()->logout($expunge);
    }

/**
 * Logout/close IMAP stream.
 *
 * @param bool $expunge If set to true, the function will silently expunge the mailbox before closing,
 *  removing all messages marked for deletion. You can achieve the same thing by using imap_expunge.
 * @return boolean True if successful, false otherwise
 */
    public function gc() {
        return $this->_loadProvider()->gc();
    }

/**
 * Logout/close IMAP stream.
 *
 * @param bool $expunge If set to true, the function will silently expunge the mailbox before closing,
 *  removing all messages marked for deletion. You can achieve the same thing by using imap_expunge.
 * @return boolean True if successful, false otherwise
 */
    public function pointer() {
        return $this->_loadProvider()->pointer();
    }

/**
 * Get last error.
 *
 * @return string Last error
 */
    public function getError() {
        return $this->_loadProvider()->getError();
    }

/**
 * __clone.
 *
 * @return Client Cloned email client
 */
    public function __clone() {
        $clone = $this;
        $clone->_provider = clone $this->_provider;
        return $clone;
    }

}
