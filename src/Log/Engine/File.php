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

namespace Nata\Log\Engine;

use Nata\Log\Engine\Base;

/**
 * File Storage stream for Logging.
 *
 * Writes logs to different files based on the level of log it is.
 */
class File extends Base {

/**
 * Path to save log files on.
 *
 * @var string
 */
    protected $_path;

/**
 * Filename to save logs to.
 *
 * @var string
 */
    protected $_file;


/**
 * Constructs a new File Logger.
 *
 * Config
 *
 * - `levels` string or array, levels the engine is interested in
 * - `scopes` string or array, scopes the engine is interested in
 * - `file` log file name
 * - `path` the path to save logs on.
 *
 * @param array $options Options for the FileLog, see above.
 */
    public function __construct(array $config = []) {
        parent::__construct($config);

        $config += [
            'path' => LOGS,
            'file' => null,
            'levels' => null,
            'scopes' => [],
         ] + $this->_config;

        $config = $this->config($config);

        $this->_path = $config['path'];
        $this->_file = $config['file'];

        if (!empty($this->_file) && !preg_match('/\.log$/', $this->_file)) {
            $this->_file .= '.log';
        }

    }

/**
 * Implements writing to log files.
 *
 * @param string $level The level of log you are making.
 * @param string $message The message you want to log.
 * @return boolean success of write.
 */
    public function write($level, $message) {
        if (!empty($this->_file)) {
            $filename = $this->_path . $this->_file;
        } else {
            $filename = $this->_path . $level . '.log';
        }

        $output = date('Y-m-d H:i:s') . ' ' . ucfirst($level) . ': ' . $message . "\n";

        return file_put_contents($filename, $output, FILE_APPEND);
    }

}
