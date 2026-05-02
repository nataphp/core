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

namespace Nata\FilesystemManager;

use Nata\Core\App;
use Nata\FilesystemManager\Folder;
use Nata\I18n\Time;
use Exception;
use Closure;
use SplFileInfo;

/**
 * Library for garbage collecting or removing temporary files across the framework
 *
 * `GarbageCollector` Provides a universal way to garbage collect given pattern
 * files
 */
class GarbageCollector {

/**
 * Path to folder to garbage collect.
 *
 * @var \Nata\FilesystemManager\Folder|string
 */
    protected $_folder;

/**
 * Pattern.
 *
 * @var string
 */
    protected $_pattern = '(.*).tmp';

/**
 * Expected files lifetime.
 *
 * @var string|int
 */
    protected $_lifetime = '10 days';

/**
 * Probability of running.
 *
 * @var int
 */
    protected $_probability = 60;


/**
 * Constructor.
 *
 * @param \Nata\FilesystemManager\Folder|string $folder Folder to garbage collect
 * @param array $options Options
 */
    public function __construct($folder, array $options = []) {
        if (is_array($folder)) {
            $options = $folder;
            $folder = null;
        }

        $this->_folder = $folder;

        $options += [
            'folder' => null,
            'pattern' => null,
            'lifetime' => null,
            'probability' => null
        ];

        if ($options['folder']) {
            $this->_folder = $options['folder'];
        }

        if ($options['pattern']) {
            $this->_pattern = $options['pattern'];
        }

        if ($options['lifetime']) {
            $this->_lifetime = $options['lifetime'];
        }

        if ($options['probability']) {
            $this->_probability = $options['probability'];
        }
    }

/**
 * Set/get folder.
 *
 * @param \Nata\FilesystemManager\Folder|string $folder Folder to collect
 * @return \Nata\FilesystemManager\Folder|$this
 */
    public function folder($folder = null) {
        if ($folder === null) {
            if ($this->_folder !== null && !($this->_folder instanceof Folder)) {
                if (!Folder::isAbsolute($this->_folder)) {
                    $this->_folder = App::path($this->_folder);
                }

                $this->_folder = new Folder($this->_folder);
            }

            return $this->_folder;
        }

        $this->_folder = $folder;
        return $this;
    }

/**
 * Set/get file's pattern.
 *
 * @param string $pattern file pattern
 * @return string|$this
 */
    public function pattern($pattern = null) {
        if ($pattern === null) {
            return $this->_pattern;
        }
        $this->_pattern = $pattern;
        return $this;
    }

/**
 * Set/get file's lifetime to be considered garbage.
 * Any strtotime format string is valid in seconds.
 *
 * @param string|int $lifetime Life time
 * @return int|$this
 */
    public function lifetime($lifetime = null) {
        if ($lifetime === null) {
            if (!is_int($this->_lifetime)) {
                $this->_lifetime = (new Time($this->_lifetime))->inSeconds();
            }
            return $this->_lifetime;
        }
        $this->_lifetime = $lifetime;
        return $this;
    }

/**
 * Set/get probability to run.
 * With a probability of 10, has a low probability of running on each call,
 * instead of a probability of 90, which more likely will collect garbage.
 *
 * @param int $probability Life time
 * @return int|$this
 */
    public function probability($probability = null) {
        if ($probability === null) {
            return $this->_probability;
        }
        $this->_probability = $probability;
        return $this;
    }

/**
 * Garbage collect files.
 *
 * // Usage
 *  $files = (new GarbageCollector('.tmp/cache/'))->collect(function (SplFileInfo $file) {
 *      // Do something with each file before deletion
 *      echo $file->getRealPath();
 *  });
 *
 * @param function $callback Optionally setting a callback for each file iteration
 * @return bool|array False is did not run, otherwise, array with removed files
 */
    public function collect($callback = null) {
        return $this->_collect($callback);
    }

/**
 * Test run the garbage collecting of files.
 *
 * @param function $callback Optionally setting a callback for each file iteration
 * @return bool|array False is did not run, otherwise, array with removed files
 */
    public function testCollect($callback = null) {
        return $this->_collect($callback, true);
    }

/**
 * Garbage collect files.
 *
 * // Usage
 *  $files = (new GarbageCollector('.tmp/cache/'))->collect(function (SplFileInfo $file) {
 *      // Do something with each file before deletion
 *      echo $file->getRealPath();
 *  });
 *
 * @param \Closure $callback Optionally setting a callback for each file iteration
 * @param boolean $testRun True to do a test run without deleting any files. Useful to test current config.
 * @return bool|array False is did not run, otherwise, array with removed files
 */
    protected function _collect($callback = null, $testRun = false) {
        if (!$this->_shouldRun()) {
            return false;
        }

        $folder = $this->folder();
        if (!($folder instanceof Folder)) {
            throw new Exception('Folder not set.');
        }

        $result = [];
        $path = $this->folder()->pwd();
        $now = time();
        $threshold = ($now - $this->lifetime());

        if (!Folder::isSlashTerm($path)) {
            $path .= DS;
        }

        $dirInstance = dir($path);

        while (($entry = $dirInstance->read()) !== false) {
            $file = new SplFileInfo($path . $entry);

            if (!$file->isFile() || preg_match('/' . $this->_pattern . '/', $file->getBasename()) === 0) {
                continue;
            }

            $mtime = $file->getMTime();

            $realPath = $file->getRealPath();

            $check = [
                'realpath' => $file->getRealPath(),
                'delete' => false,
                'deleted' => null,
                'modified' => (new Time($mtime))->format('c'),
                'age' => (new Time($mtime))->timeAgoInWords()
            ];

            if ($mtime <= $threshold) {
                if ($callback instanceof Closure) {
                    $callback($file);
                }

                $check['delete'] = true;

                if (!$testRun) {
                    $check['deleted'] = @unlink($realPath);
                }

            }

            $result[] = $check;
        }

        $dirInstance->close();

        return $result;
    }

/**
 * Check if we should garbage collect files based on probability setting.
 *
 * @return boolean True if we should execute, false otherwise
 */
    protected function _shouldRun() {
        $probability = $this->probability();
        return ($probability === 100) || !(time() % (100 - $probability) !== 0);
    }

/**
 * Quick way to initalize and run garbage collector.
 *
 * // Usage
 *  $deletedFiles = GarbageCollector::fetch('.tmp/cache/', function(SplFileInfo $file) {
 *      // Do something with each file before deletion
 *      echo $file->getRealPath();
 *  });
 *
 * @param \Nata\FilesystemManager\Folder|string $folder Folder to garbage collect
 * @param array $options Options
 * @param function $callback Optionally setting a callback for each file iteration
 * @return boolean True if we should execute, false otherwise
 */
    public static function run($path, $options = [], $callback = null) {
        if ($options instanceof Closure) {
            $callback = $options;
            $options = [];
        }

        $gc = new GarbageCollector($path, $options);

        return $gc->collect($callback);
    }

/**
 * @inherited
 * @deprecated Use run() instead
 */
    public static function fetch($path, $options = [], $callback = null) {
        return static::run($path, $options, $callback);
    }

}
