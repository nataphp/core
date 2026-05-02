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

namespace Nata\Ftp;

use Closure;
use Nata\Core\App;
use UnexpectedValueException;
use Exception;
use InvalidArgumentException;
use Nata\Collection\Collection;
use Nata\Core\NataObject;
use Nata\Filesystem\File as FilesystemFile;
use Nata\Ftp\Client\File;
use Nata\Filesystem\GarbageCollector;
use RuntimeException;

/**
 * FTP class abstraction for PHP's ftp_* functions.
 *
 * @link http://php.net/manual/en/book.ftp.php
 */
class Client extends NataObject {

/**
 * Default config.
 *
 * ## Options
 *
 * 'host' - Hostname.
 * 'port' - Connection port (defaults to 21).
 * 'ssl' - Use SSL connection.
 * 'timeout' - Connection timeout (in seconds).
 * 'dir' - Directory path.
 * 'passive' - True to passive mode ON, false for OFF
 *
 * @var array
 */
    protected $_defaultConfig = [
        'host' => null,
        'port' => 21,
        'timeout' => 90,
        'ssl' => false,
        'username' => null,
        'password' => null,
        'dir' => null,
        'passive' => true
    ];

/**
 * Connection resource.
 *
 * @var resource
 */
    protected $_connection;

/**
 * Logged in.
 *
 * @var boolean
 */
    protected $_loggedIn = false;

/**
 * @todo Last error.
 *
 * @var string
 */
    protected $_lastError;


/**
 * Construtor.
 *
 * @param string|array $host URL/Host to connect to or array
 * @param array $config Configuration
 */
    public function __construct($host, array $config = []) {
        if (!function_exists('ftp_connect')) {
            throw new RuntimeException('FTP extension is not installed!');
        }

        if (is_array($host)) {
            $config = $host;
        } else {
            $config = ['host' => $host];
        }

        parent::config($config + $this->_defaultConfig);

        // Garbage collector
        GarbageCollector::run(TMP . 'cache', [
            'probability' => 100,
            'lifetime' => '10 minutes',
            'pattern' => 'ftpclient-tmp-(.*)'
        ]);
    }

/**
 * Host to connect to.
 *
 * A full URL 'ftp://username:password@sld.domain.tld/path1/path2/' is also supported.
 *
 * @param string $host URL/Host to connect to
 * @return string|$this
 */
    public function host($host = null) {
        if ($host === null) {
            return $this->config('host');
        }

        if (strpos($host, 'ftp://') !== false) {
            $host = rtrim($host, '/') . '/';

            if (preg_match("/ftp:\/\/(.*?):(.*?)@(.*?)(\/.*)/i", $host, $matches)) {
                $matches = array_pad($matches, 5, null);

                $this->config([
                    'username' => $matches[1],
                    'password' => $matches[2],
                    'dir' => $matches[4]
                ]);
                $host = $matches[3];
            }

        }

        $this->close();

        $this->config('host', $host);

        return $this;
    }

/**
 * Get/Set an FTP connection.
 *
 * @param resource|FTP\Connection $connection Resource ID
 * @return resource|FTP\Connection Connection instanse or resource ID
 */
    public function connection($connection = null) {
        if ($connection === null) {
            if ($this->_connection === null) {
                $config = $this->config();

                if (empty($config['host'])) {
                    throw new UnexpectedValueException('Missing FTP URL/Host to connect to');
                }

                $func = $config['ssl'] === true ? 'ftp_ssl_connect' : 'ftp_connect';
                $this->_connection = call_user_func($func, $config['host'], $config['port'], $config['timeout']);
            }

            return $this->_connection;
        }

        $this->_connection = $connection;

        return $this;
    }

/**
 * Login.
 *
 * @param string $username Username
 * @param string $password Password
 * @return boolean True if successful, false otherwise
 */
    public function login($username = null, $password = null) {
        if ($this->_loggedIn) {
            return true;
        }

        if ($username === null) {
            $username = $this->config('username');
        }

        if ($password === null) {
            $password = $this->config('password');
        }

        if (empty($username)) {
            throw new InvalidArgumentException('Missing username.');
        } elseif (empty($password)) {
            throw new InvalidArgumentException('Missing password.');
        }

        $connection = $this->connection();

        $this->_loggedIn = @ftp_login($connection, $username, $password);
        if ($this->_loggedIn) {
            // @see http://php.net/manual/en/function.ftp-nlist.php#95371
            ftp_pasv($connection, $this->config('passive'));
        }

        return $this->_loggedIn;

    }

/**
 * Login of fail.
 *
 * @param string $username Username
 * @param string $password Password
 * @return boolean True if successful
 * @throws \Exception If unse
 */
    public function loginOrFail($username = null, $password = null) {
        if (!$this->login($username, $password)) {
            throw new Exception(sprintf('Invalid login credentials for "%s"', $this->config('host')));
        }
        return true;
    }

/**
 * Changes to the parent directory.
 *
 * @return $this|boolean $this if successful, boolean false otherwise
 */
    public function cdup() {
        $this->login();
        return ftp_cdup($this->connection()) ? $this : false;
    }

/**
 * Changes the current directory.
 *
 * @param string $dir Change directory
 * @return $this|boolean $this if successful, boolean false otherwise
 */
    public function cd($dir) {
        $this->login();
        return ftp_chdir($this->connection(), $dir) ? $this : false;
    }

/**
 * Create new directory.
 *
 * @param string $dir Change directory
 * @return $this|boolean $this if successful, boolean false otherwise
 */
    public function createDir($dirname) {
        $this->login();
        return ftp_mkdir($this->connection(), $dirname) ? $this : false;
    }

/**
 * Returns the current directory name
 *
 * @return string Current directory name
 */
    public function pwd() {
        $this->login();
        return ftp_pwd($this->connection());
    }

/**
 * Set permissions on a remote file.
 *
 * @param int $perm The new permissions, given as an octal value.
 * @param string $filename Filename to change permissions
 * @return bool True if successful, false otherwise
 */
    public function chmod($perm, $filename) {
        $this->login();
        return ftp_chmod($this->connection(), $perm, $filename);
    }

/**
 * Returns a list of files in the given directory.
 *
 * @param string $dir Directory
 * @return \Nata\Collection\Collection List of files
 */
    public function listFiles($dir = '.') {
        $this->login();

        $connection = $this->connection();

        return new Collection(array_map(function ($filename) use ($connection) {
            return new File($filename, $connection);
        }, ftp_nlist($connection, $dir)));
    }

/**
 * Get/download file.
 *
 * @param string $remoteFilename Remote filename to get/download
 * @param \Nata\Filesystem\File|string $localFilename Local filename to save to
 * @param int $mode The transfer mode. Must be either FTP_ASCII or FTP_BINARY
 * @param int $resumePosition The position in the remote file to start downloading from
 * @return \Nata\Filesystem\File Downloaded file
 */
    public function get($remoteFilename, $localFilename = null, $mode = FTP_BINARY, $resumePosition = 0) {
        $this->login();
        if ($localFilename === null) {
            $filename = pathinfo($remoteFilename, PATHINFO_BASENAME);
            $localFilename = TMP . 'cache' . DS . 'ftpclient-tmp-' . $filename;
        }

        $localFilename = new FilesystemFile($localFilename);

        if (!ftp_get($this->connection(), $localFilename->pwd(), $remoteFilename, $mode, $resumePosition)) {
            return false;
        }

        return $localFilename;
    }

/**
 * Put/Upload file to remote server.
 *
 * @param \Nata\Filesystem\File|string $localFilename Local filename to upload
 * @param string $remoteFilename Remote filename (defaults to local file's filename)
 * @param int $mode The transfer mode. Must be either FTP_ASCII or FTP_BINARY
 * @param int $resumePosition The position in the remote file to start upload to
 * @return boolean True if successful, or false otherwise
 */
    public function put($localFilename, $remoteFilename = null, $mode = FTP_BINARY, $resumePosition = 0) {
        $this->login();

        if (is_string($localFilename)) {
            $localFilename = new FilesystemFile($localFilename);
        }

        if (!$localFilename->exists()) {
            throw new InvalidArgumentException(sprintf("File '%s' doesn't exist", $localFilename->pwd()));
        }

        if ($remoteFilename === null) {
            $remoteFilename = $localFilename->basename();
        }

        $connection = $this->connection();
        if (is_string($remoteFilename)) {
            $remoteFilename = new File($remoteFilename, $connection);
        }

        $ret = ftp_put($this->connection(), $remoteFilename->pwd(), $localFilename->pwd(), $mode, $resumePosition);
        return $ret === true ? $remoteFilename : false;
    }

/**
 * Put/Upload file to remote server asynchronously.
 *
 * @param \Nata\Filesystem\File|string $localFilename Local filename to upload
 * @param string $remoteFilename Remote filename (defaults to local file's filename)
 * @param int $mode The transfer mode. Must be either FTP_ASCII or FTP_BINARY
 * @param Closure $callback Callback
 * @param int $resumePosition The position in the remote file to start upload to
 * @return boolean True if successful, or false otherwise
 */
    public function asyncPut($localFilename, $remoteFilename = null, $mode = FTP_BINARY, Closure $callback = null, $resumePosition = 0) {
        $this->login();
        $connection = $this->connection();

        if (is_string($localFilename)) {
            $localFilename = new FilesystemFile($localFilename);
        }

        if (!$localFilename->exists()) {
            throw new InvalidArgumentException(sprintf("File '%s' doesn't exist", $localFilename->pwd()));
        }

        if ($remoteFilename === null) {
            $remoteFilename = $localFilename->basename();
        }

        if (is_string($remoteFilename)) {
            $remoteFilename = new File($remoteFilename, $connection);
        }

        // Start uploading...
        $return = ftp_nb_put($connection, $remoteFilename->pwd(), $localFilename->pwd(), $mode, $resumePosition);
        while ($return === FTP_MOREDATA) {
            if ($callback instanceof Closure) {
                $callback($remoteFilename, $localFilename);
            }
            // Continue uploading...
            $return = ftp_nb_continue($connection);
        }

        // Error uploading...
        if ($return !== FTP_FINISHED) {
            return false;
        }

        // Finished uploading...
        return $remoteFilename;
    }

/**
 * Get remote server system type.
 *
 * @return string System type name
 */
    public function getSysType() {
        $this->login();
        return ftp_systype($this->connection());
    }

/**
 * Close connection.
 *
 * @return boolean True if successful, or false otherwise
 */
    public function close() {
        if (is_resource($this->_connection)) {
            return ftp_close($this->_connection);
        }
    }

/**
 * __destruct.
 *
 */
    public function __destruct() {
        if (is_resource($this->_connection)) {
            ftp_close($this->_connection);
        }
    }

}
