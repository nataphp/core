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

use Exception;
use InvalidArgumentException;
use Nata\Collection\Collection;
use Nata\Core\App;
use Nata\Core\NataObject;
use Nata\Email\Exception\ConnectionFailedException;
use Nata\Filesystem\GarbageCollector;
use Nata\I18n\Time;

/**
 * IMAP service provider adapter's base class.
 */
class Provider extends NataObject {

/**
 * IMAP stream to a mailbox.
 *
 * @var \IMAP\Connection
 */
    protected $_stream;

/**
 * IMAP service provider name.
 *
 * @var string
 */
    protected $_providerName;

/**
 * IMAP stream to a mailbox.
 *
 * @var string
 */
    protected $_serverSpecification;

/**
 * List of mailboxes.
 *
 * @var array
 */
    protected $_folders;

/**
 * Caches the last obtained message UID in respective folder.
 *
 * @var array
 */
    protected $_pointer = [];


/**
 * Constructor.
 *
 * @param array $parts Message parts.
 */
    public function __construct(array $config) {
        $this->_config = $config;

        if (is_subclass_of($this, self::class)) {
            $this->_providerName = App::classShortName($this);
        }

        register_shutdown_function([$this, 'shutdown']);

        $this->gc();
    }

/**
 * Login.
 *
 * @param string $username Username
 * @param string $password Password
 * @return boolean True if successful, false otherwise
 */
    public function login($username = null, $password = null) {
        if ($this->_stream !== null) {
            return true;
        }

        $config = $this->config();
        if ($username === null) {
            $username = $config['username'];
            $this->config('username', $username);
        }

        if ($password === null) {
            $password = $config['password'];
        }

        $folder = $this->folder();

        $mailbox = sprintf('%s%s', $this->_getServerSpecification(), $folder);
        $stream = imap_open($mailbox, $username, $password);
        if ($stream === false) {
            return false;
        }

        $this->_stream = $stream;

        return true;
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
        if ($this->login($username, $password)) {
            return true;
        }

        $folder = $this->folder();

        $mailbox = sprintf('%s%s', $this->_getServerSpecification(), $folder);
        throw new ConnectionFailedException([$mailbox]);
    }

/**
 * Get/Set IMAP stream to a mailbox.
 *
 * @param \IMAP\Connection $stream Stream
 * @return \IMAP\Connection|$this
 */
    public function stream($stream = null) {
        if ($stream === null) {
            if ($this->_stream === null || !$this->ping()) {
                $this->_stream = null;
                $this->loginOrFail();
            }
            return $this->_stream;
        }

        $this->_stream = $stream;

        return $this;
    }

/**
 * Check if the IMAP stream is still active.
 *
 * @return bool
 */
    public function ping() {
        if ($this->_stream === null) {
            return false;
        }
        return imap_ping($this->_stream);
    }

/**
 * Get or change current folder.
 *
 * @param string $folder Folder name
 * @return $this|boolean
 */
    public function folder(string $folder = null) {
        $currentFolder = $this->config('folder');
        if (func_num_args() === 0) {
            return $currentFolder;
        }

        if (strtolower($folder) === strtolower($currentFolder)) {
            return $this;
        }

        if (!$this->folderExists($folder)) {
            throw new InvalidArgumentException(sprintf(
                "Folder '%s' doesn't exist. Use Client::getFolders() to see list of folders.",
                $folder
            ));
        }

        $mailbox = sprintf('%s%s', $this->_getServerSpecification(), $folder);
        if (!imap_reopen($this->stream(), $mailbox)) {
            return false;
        }

        $this->config('folder', $folder);

        return $this;
    }

/**
 * Check if given folder exists.
 *
 * @param string $name Folder name to check
 * @return bool True if folder exists, false otherwise
 */
    public function folderExists($name): bool {
        $folders = $this->getFolders();
        $name = strtolower($name);
        foreach ($folders as $folder) {
            if (strtolower($folder->name) === $name) {
                return true;
            }
        }
        return false;
    }

/**
 * Get or change current folder.
 *
 * @param string $folder Folder name
 * @return $this|boolean
 */
    public function listSubscribed($pattern) {
        return imap_listsubscribed($this->stream(), $this->_getServerSpecification(), $pattern);
    }

/**
 * Get server specification.
 *
 * @return string Server specification
 */
    protected function _getServerSpecification() {
        if ($this->_serverSpecification === null) {
            $config = $this->config();
            $ssl = $config['ssl'] === false ? 'novalidate-cert' : 'notls/validate-cert';
            $this->_serverSpecification = sprintf('{%s:%s/%s/ssl/%s}', $config['host'], $config['port'], $config['server'], $ssl);
        }
        return $this->_serverSpecification;
    }

/**
 * Get mailbox name for given folder.
 *
 * @param string $folder Folder name
 * @return string Mailbox name
 */
    protected function _getMailboxName($folder = null) {
        if ($folder === null) {
            $folder = $this->folder();
        }
        return sprintf('%s%s', $this->_getServerSpecification(), $folder);
    }

/**
 * Returns headers for all messages in a mailbox.
 *
 * @return array Formatted with header info. One element per mail message.
 */
    public function getHeaders() {
        return imap_headers($this->stream());
    }

/**
 * Read mailbox.
 *
 * ```php
 * $this->get([
 *  'ALL SUBJECT' => [
 *      'matching' => ['My Subject Message'],
 *      'since' => Time::now()->modify('-1 hour')
 *  ]
 * ])
 * ```
 * NOTE: PHP IMAP client doesn't support OR yet
 *
 * @return \Nata\Collection\Collection
 */
    public function get($criteria = 'ALL', $sortBy = null, $direction = 'ASC') {
        return $this->_search($criteria);
    }

/**
 * Read mailbox's unread/unseen emails.
 *
 * @return \Nata\Collection\Collection
 */
    public function getRead() {
        return $this->get('SEEN');
    }

/**
 * Read mailbox's unread/unseen emails.
 *
 * @return \Nata\Collection\Collection
 */
    public function getDeleted() {
        return $this->get('DELETED');
    }

/**
 * Read mailbox's unread/unseen emails.
 *
 * @return \Nata\Collection\Collection
 */
    public function getUnread() {
        return $this->get('UNSEEN');
    }

/**
 * Search by given criteria.
 *
 * @param string|array $criteria Criteria to parse
 * @return array Criteria per search
 */
    public function _search($argument) {
        if (!$argument) {
            return null;
        }

        if (is_numeric($argument)) {
            return $this->_searchByUid($argument);
        } elseif (is_string($argument) && preg_match("/[\*0-9+]+\:[\*0-9]+/", $argument)) {
            return $this->_searchByRange($argument);
        // Message ID "<message@domain.com>"
        } elseif (is_string($argument) && preg_match("/^<.*@.*>$/", $argument)) {
            return $this->_searchByMessageId($argument);
        }
        return $this->_searchByCriteria($argument);
    }

/**
 * Search by given UID.
 *
 * @param int $uid Message UID
 * @return array Criteria per search
 */
    public function _searchByUid($uid) {
        $message = $this->_searchByRange($uid);
        if (!$message) {
            return null;
        }
        return $message->first();
    }

/**
 * Search by given criteria.
 *
 * @param string|array $criteria Criteria to parse
 * @return Collection Criteria per search
 */
    public function _searchByRange($range) {
        $results = imap_fetch_overview($this->stream(), $range, FT_UID);
        return $this->_loadMessages($results);
    }

/**
 * Search by given criteria.
 *
 * @param string|array $criteria Criteria to parse
 * @return Collection Criteria per search
 */
    public function _searchByCriteria($criteria) {
        $messages = [];
        $stream = $this->stream();

        $criteria = $this->_parseCriteria($criteria);
        foreach ($criteria as $condition) {
            if ($result = imap_search($stream, $condition, SE_UID)) {
                $messages = array_merge($messages, $result);
            }
        }

        return $this->_loadMessages($messages);
    }


/**
 * Search by given message ID.
 *
 * @param string $messageId Message ID
 * @return array Criteria per search
 */
    public function _searchByMessageId($messageId) {
        $results = imap_search($this->stream(), 'HEADER Message-ID "' . $messageId . '"', SE_UID);
        return $this->_loadMessages($results);
    }

/**
 * Parse criteria.
 *
 * @param string|array $criteria Criteria to parse
 * @return array Criteria per search
 */
    public function _parseCriteria($criteria) {
        $conditions = [];

        foreach ((array)$criteria as $cond => $options) {
            if (is_int($cond)) {
                $conditions[] = $options;
                continue;
            }

            if (!is_array($options)) {
                $options = ['matching' => [$options]];
            } elseif (!isset($options['matching'])) {
                $options = [
                    'matching' => $options
                ];
            }

            $options += [
                'matching' => null,
                'since' => null
            ];

            // Since as relative time
            if (preg_match("/second|minute|hour|day|month|year/i", $options['since'])) {
                $options['since'] = (new Time('-' . $options['since']))->format('j F Y');
            } elseif ($options['since'] instanceof Time) {
                $options['since'] = $options['since']->format('j F Y');
            }

            foreach ((array)$options['matching'] as $match) {
                if ($match instanceof Time) {
                    $match = $match['matching']->format('j F Y');
                }

                $query = sprintf('%s "%s"', $cond, $match);

                if ($options['since']) {
                    $query .= sprintf(' SINCE "%s"', $options['since']);
                }

                $conditions[] = $query;
            }

        }

        return $conditions;
    }

/**
 * Read emails from mailbox.
 *
 * @param array $sequence UID's Sequence
 * @return \Nata\Collection\Collection
 */
    protected function _loadMessages($result) {
        $stream = $this->stream();

        $messages = [];
        if (!empty($result)) {
            $folder = $this->folder();
            $uniqid = $this->config('host') . $this->config('username') . $this->folder();
            foreach ($result as $uid) {
                $overview = null;
                if (is_object($uid)) {
                    $overview = $uid;
                    $uid = $overview->uid;
                }

                $messageUniqid = sha1($uniqid . $uid);
                $messages[] = new Message($stream, $uid, [
                    'overview' => $overview,
                    'uniqid' => $messageUniqid,
                    'folder' => $folder,
                    'provider' => $this->_providerName
                ]);
            }

            $this->pointer($uid);

        }
        return new Collection($messages);
    }

/**
 * Mark given email as 'SEEN'.
 *
 * @param Message $message Message to flag
 * @return boolean True if successful, false otherwise
 */
    public function markSeen(Message $message) {
        return $this->addFlag($message, "\\Seen");
    }

/**
 * Mark given email as 'UNSEEN'.
 *
 * @param Message $message Message to flag
 * @return boolean True if successful, false otherwise
 */
    public function markUnseen(Message $message) {
        return $this->removeFlag($message, "\\Seen");
    }

/**
 * Alias of Client::markSeen()
 */
    public function markRead(Message $message) {
        return $this->markSeen($message);
    }

/**
 * Alias of Client::markUnseen()
 */
    public function markUnread(Message $message) {
        return $this->markUnseen($message);
    }

/**
 * Archive email (flags email as deleted and seen).
 *
 * @param Message $message Message to move
 * @return boolean True if successful, false otherwise
 */
    public function archive(Message $message) {
        return $this->markSeen($message) && $this->markDeleted($message);
    }

/**
 * Unarchive email (removes deleted and seen flag).
 *
 * @param Message $message Message to move
 * @return boolean True if successful, false otherwise
 */
    public function unArchive(Message $message) {
        return $this->markUnseen($message) && $this->unmarkDeleted($message);
    }

/**
 * Mark email as deleted.
 *
 * @param Message $message Message to flag
 * @return boolean True if successful, false otherwise
 */
    public function markDeleted(Message $message) {
        return $this->addFlag($message, "\\Deleted");
    }

/**
 * Unmark email as deleted.
 *
 * @param Message $message Message to move
 * @return boolean True if successful, false otherwise
 */
    public function unmarkDeleted(Message $message) {
        return $this->removeFlag($message, "\\Deleted");
    }

/**
 * Mark email as draft.
 *
 * @param Message $message Message to move
 * @return boolean True if successful, false otherwise
 */
    public function markDraft(Message $message) {
        return $this->addFlag($message, "\\Draft");
    }

/**
 * Unmark email as draft.
 *
 * @param Message $message Message to move
 * @return boolean True if successful, false otherwise
 */
    public function unmarkDraft(Message $message) {
        return $this->removeFlag($message, "\\Draft");
    }

/**
 * Move email.
 *
 * @param Message $message Message to move
 * @param string $folder Folder name or mailbox
 * @return boolean True if successful, false otherwise
 */
    public function move(Message &$message, $folder) {
        if (!$this->folderExists($folder)) {
            throw new InvalidArgumentException(sprintf(
                "Folder '%s' doesn't exist. Use Client::getFolders() to see list of folders.",
                $folder
            ));
        }

        // IMPORTANT: Obtain the header/message ID before being moved
        $message->getMessageId();

        $clonedProvider = clone $this;
        $clonedProvider->folder($folder);
        $status = $clonedProvider->getStatus();

        if ($result = imap_mail_move($this->stream(), $message->getUid(), $folder, CP_UID)) {
            imap_expunge($this->stream());
        }

        if ($movedMessage = $this->_findMessageInFolder($status, $clonedProvider, $message)) {
            $message = $movedMessage;
        }

        return $result;
    }

/**
 * Move emails.
 *
 * @param array $messages Messages to move
 * @param string $mailbox Mailbox name or existing label
 */
    public function moveAll(array &$messages, $mailbox) {
        foreach ($messages as $index => $message) {
            $this->move($message, $mailbox);
            $messages[$index] = $message;
        }
    }

/**
 * Copy message.
 *
 * @param Message $message Message to move
 * @param string $mailbox Mailbox name or existing label
 * @return Message|boolean Message copy if successful, false otherwise
 */
    public function copy(Message $message, $folder) {
        if (!$this->folderExists($folder)) {
            throw new InvalidArgumentException(sprintf(
                "Folder '%s' doesn't exist. Use Client::getFolders() to see list of folders.",
                $folder
            ));
        }

        // IMPORTANT: Obtain the header/message ID before being moved
        $message->getMessageId();

        $clonedProvider = clone $this;
        $clonedProvider->folder($folder);
        $status = $clonedProvider->getStatus();

        if (imap_mail_copy($this->stream(), $message->getUid(), $folder, CP_UID)) {
            imap_expunge($this->stream());
        }

        return $this->_findMessageInFolder($status, $clonedProvider, $message);
    }

/**
 * Copy emails.
 *
 * @param array $messages Messages to copy
 * @param string $mailbox Mailbox name or existing label
 * @return boolean True if successful, false otherwise
 */
    public function copyAll(array $messages, $mailbox) {
        $copies = [];
        foreach ($messages as $index => $message) {
            $copies[$index] = $this->copy($message, $mailbox);
        }
        return $copies;
    }

/**
 * Get number of messages in current mailbox.
 *
 * @return int number of messages
 */
    protected function _findMessageInFolder($status, Provider $messageProvider, Message $prevMessage) {
        $messages = $messageProvider->get($status->uidnext . ':*');
        $foundMessage = null;
        foreach ($messages as $message) {
            if ($message->getMessageId() == $prevMessage->getMessageId()) {
                $foundMessage = $message;
                break;
            }
        }
        return $foundMessage;
    }

/**
 * Get number of messages in current mailbox.
 *
 * @return int number of messages
 */
    public function count() {
        return imap_num_msg($this->stream());
    }

/**
 * Get number of recent messages in current mailbox.
 *
 * @return int number of recent messages
 */
    public function recentCount() {
        return imap_num_recent($this->stream());
    }

/**
 * Get information about the current folder.
 *
 * @return array Current folder status
 * @see https://www.php.net/manual/en/function.imap-status.php
 */
    public function getStatus() {
        return imap_status($this->stream(), $this->_getMailboxName(), SA_ALL);
    }

/**
 * Read the list of mailbox folders, returning detailed information on each one.
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
        if (!isset($this->_folders[$pattern])) {
            $this->_folders[$pattern] = [];

            $serverSpec = $this->_getServerSpecification();
            $folders = imap_getmailboxes($this->stream(), $serverSpec, $pattern);
            if ($folders) {
                foreach ($folders as $index => $folder) {
                    $folder->fullname = $folder->name;
                    $folder->name = str_replace($serverSpec, '', $folder->name);
                    $this->_folders[$pattern][$index] = $folder;
                }
            }
        }
        return $this->_folders[$pattern];
    }

/**
 * Get list of flags.
 *
 * @param string $username Username
 * @param string $password Password
 * @return array|null
 */
    public function getFlags(Message $message): ?array {
        $result = $this->send("IMAP FETCH (FLAGS)");
        preg_match_all("|\\* \\d+ FETCH \\(FLAGS \\((.*)\\)\\)|", $result[0], $matches);
        if (!isset($matches[1][0])) {
            return null;
        }
        return explode(' ', $matches[1][0]);
    }

/**
 * Add/Set flag(s).
 *
 * @param Message $message Message to add flag(s) to
 * @param string|array $flag Flag(s) to set
 * @return bool True if successful, false otherwise
 */
    public function addFlag(Message $message, $flag) {
        $connection = $this->stream();
        $uid = $message->getUid();
        $flags = (array)$flag;

        $success = [];
        foreach ($flags as $flag) {
            $flag = $this->_normalizeFlag($flag);
            if (imap_setflag_full($connection, $uid, $flag, ST_UID) === true) {
                $success[] = true;
            }
        }
        return count($success) === count($flags);
    }

/**
 * Remove flag(s).
 *
 * @param Message $message Message to add flag(s) to
 * @param string|array $flag Flag(s) to remove
 * @return bool True if successful, false otherwise
 */
    public function removeFlag(Message $message, $flag) {
        $connection = $this->stream();
        $uid = $message->getUid();
        $flags = (array)$flag;

        $success = [];
        foreach ($flags as $flag) {
            $flag = $this->_normalizeFlag($flag);
            if (imap_clearflag_full($connection, $uid, $flag, ST_UID) === true) {
                $success[] = true;
            }
        }

        return count($success) === count($flags);
    }

/**
 * Normalize flag.
 *
 * @param string $flag
 * @return string
 */
    protected function _normalizeFlag($flag) {
        $flag = ltrim($flag, '\\');
        $flag = ucfirst(strtolower($flag));
        return '\\' . $flag;
    }

/**
 * Set/Get pointer UID.
 *
 * @param int $uid UID
 * @return int UID
 */
    public function pointer($uid = null) {
        $mailbox = sprintf('%s%s', $this->_getServerSpecification(), $this->folder());
        $currentPointer = isset($this->_pointer[$mailbox]) ? $this->_pointer[$mailbox] : null;
        if ($uid === null) {
            return $currentPointer;
        }

        if ($currentPointer && $currentPointer > $uid) {
            return;
        }

        $this->_pointer[$mailbox] = $uid;
    }

/**
 * Logout/close IMAP stream.
 *
 * @param bool $expunge If set to true, the function will silently expunge the mailbox before closing,
 *  removing all messages marked for deletion. You can achieve the same thing by using imap_expunge.
 * @return boolean True if successful, false otherwise
 */
    public function logout($expunge = false) {
        if (!is_resource($this->_stream)) {
            return true;
        }
        //@ Workaround for reconnection
        imap_errors();
        //@ End workaround
        return imap_close($this->_stream, ($expunge ? CL_EXPUNGE : 0));
    }

/**
 * Get last error.
 *
 * @return string Last error
 */
    public function getError() {
        return imap_last_error();
    }

/**
 * Garbage collector for local copy for attached files.
 *
 * @return boolean True if successful, false otherwise
 */
    public function gc() {
        $lifetime = $this->config('attachmentCacheLifetime');

        $gc = new GarbageCollector(TMP . 'cache');
        return $gc->pattern('(.*).mailattach')
            ->lifetime($lifetime)
            ->probability(20)
            ->collect();
    }

/**
 * Logout/close IMAP stream.
 *
 * @param bool $expunge If set to true, the function will silently expunge the mailbox before closing,
 *  removing all messages marked for deletion. You can achieve the same thing by using imap_expunge.
 * @return boolean True if successful, false otherwise
 */
    public function shutdown() {
        $this->logout();
    }

/**
 * __clone.
 *
 * @return Client Cloned email client
 */
    public function __clone() {
        $clone = $this;
        $clone->_stream = null;
        return $clone;
    }

}
