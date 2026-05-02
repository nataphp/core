<?php
/**
 * NataPHP Framework
 *
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
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

use Nata\Console\Output;

/**
 * Console log engine.
 *
 * Writes log messages to stdout or stderr instead of a file.
 * Used when running console commands so that Log::write(), Log::info(), etc.
 * output directly to the terminal.
 *
 * Config:
 * - `stream` Output instance (stdout or stderr)
 * - `levels` or `types` Log levels to handle (e.g. ['notice', 'info', 'debug'])
 */
class ConsoleLog extends Base {

/**
 * Output stream (stdout or stderr).
 *
 * @var \Nata\Console\Output
 */
    protected $_stream;

/**
 * Constructs a new Console Logger.
 *
 * Config:
 * - `levels` or `types` Log levels to handle
 * - `stream` Output instance
 *
 * @param array $config Options for the ConsoleLog.
 */
    public function __construct(array $config = []) {
        parent::__construct($config);

        $config += [
            'stream' => null,
            'levels' => null,
            'types' => null,
        ] + $this->_config;

        $config = $this->config($config);

        if (isset($config['types']) && empty($config['levels'])) {
            $config['levels'] = $config['types'];
        }
        $this->config($config);

        $this->_stream = $config['stream'];
    }

/**
 * Writes the log message to the console stream.
 *
 * @param string $level The level of log (e.g. error, info, debug).
 * @param string $message The message to log.
 * @return bool Success of write.
 */
    public function write($level, $message): bool {
        if ($this->_stream === null) {
            return false;
        }

        $output = date('Y-m-d H:i:s') . ' ' . ucfirst($level) . ': ' . $message . Output::LF;
        $this->_stream->write($output);

        return true;
    }

}
