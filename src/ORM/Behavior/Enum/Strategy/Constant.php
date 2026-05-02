<?php
/**
 * NataPHP Framework.
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

namespace Nata\ORM\Behavior\Enum\Strategy;

use Nata\ORM\Behavior\Enum\Strategy;
use ReflectionClass;

/**
 * Strategy for Enum behavior to extract values in table's constants.
 */
class Constant extends Strategy {

/**
 * Constants list.
 *
 * @var array
 */
    protected $_constants;


/**
 * {@inheritdoc}
 *
 * @param array $config List of callable filters to limit items generated from list.
 * @return array
 */
    public function enum(array $config = []): array {
        $config += [
            'className' => null,
            'lowercase' => true
        ];
        $this->config($config);

        $constants = $this->_getConstants();
        $keys = array_keys($constants);

        foreach ($config as $callable) {
            if (is_callable($callable)) {
                $keys = array_filter($keys, $callable);
            }
        }

        $values = array_map(function ($v) use ($constants) {
            return $constants[$v];
        }, $keys);

        return array_combine($keys, $values);
    }

/**
 * Returns defined constants for the current `$_table`.
 *
 * @return array
 */
    protected function _getConstants(): array {
        if (isset($this->_constants)) {
            return $this->_constants;
        }

        $prefix = $this->config('prefix') ?? strtoupper($this->_alias);
        $lowercase = $this->config('lowercase');
        $className = $this->config('className') ?: get_class($this->_table);
        $length = strlen($prefix) + 1;
        $classConstants = (new ReflectionClass($className))->getConstants();
        $constants = [];

        foreach ($classConstants as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                $listKey = substr($key, $length);
                if ($lowercase) {
                    $listKey = strtolower($listKey);
                }
                $constants[$listKey] = $value;
            }
        }

        return $this->_constants = $constants;
    }

}