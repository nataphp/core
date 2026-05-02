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

namespace Nata\Log;

use Nata\Utility\ObjectCollection;
use Nata\Core\App;
use NataLogException;

/**
 * Registry of loaded log engines.
 */
class EngineRegistry extends ObjectCollection {

/**
 * Loads/constructs a Log engine.
 *
 * @param string $name instance identifier
 * @param array $options Setting for the Log Engine
 * @return BaseLog BaseLog engine instance
 * @throws NataLogException when logger class does not implement a write method
 */
    public function load($name, $options = array()) {
        $enable = isset($options['enabled']) ? $options['enabled'] : true;

        if (isset($options['engine'])) {
            $options['className'] = $options['engine'];
            unset($options['engine']);
        }

        $loggerName = $options['className'];

        $className = $this->_getLogger($loggerName);
        $logger = new $className($options);

        if (!$logger instanceof EngineInterface) {
            throw new NataLogException(sprintf(
                'logger class %s does not implement a write method.',
                $loggerName
            ));
        }

        $this->_loaded[$name] = $logger;

        if ($enable) {
            $this->enable($name);
        }

        return $logger;
    }

/**
 * Attempts to import a logger class from the various paths it could be on.
 * Checks that the logger class implements a write method as well.
 *
 * @param string $loggerName the plugin.className of the logger class you want to build.
 * @return mixed boolean false on any failures, string of classname to use if search was successful.
 * @throws NataLogException
 */
    protected static function _getLogger($loggerName) {
        list($plugin, $loggerName) = pluginSplit($loggerName, true);

        $loggerName = App::className($loggerName, 'Log/Engine');

        if (!$loggerName) {
            throw new NataLogException(sprintf('Could not load class %s', $loggerName));
        }

        return $loggerName;
    }

}
