<?php
/**
 * NataPHP Framework.
 *
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * Copyright 2015 - 2019, Cake Development Corporation (http://cakedc.com)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * This behavior was inspired based on the work of Cake Development Corporation.
 *
 * @copyright     Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @link          http://nataphp.com NataPHP Project
 * @link          https://github.com/CakeDC/Enum
 * @since         NataPHP 1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\ORM\Behavior;

use BadMethodCallException;
use Nata\Core\ConfigAwareTrait;
use Nata\ORM\Behavior;
use Nata\ORM\Behavior\Enum\EnumSet;
use Nata\ORM\Behavior\Enum\Exception\MissingEnumConfigurationException;
use Nata\ORM\Behavior\Enum\Exception\MissingEnumStrategyException;
use Nata\ORM\Behavior\Enum\Strategy;
use Nata\Utility\Hash;
use Nata\Utility\Inflector;

/**
 * Enumeration managment for model's fields.
 */
class Enum extends Behavior {

    use ConfigAwareTrait;

/**
 * Default configuration.
 *
 * - `implementedMethods`: custom table methods made accessible by this behavior.
 * - `defaultStrategy`: the default strategy to use.
 * - `translate`: Whether values of lists returned by enum() method should
 *   be translated. Defaults to `false`.
 * - `translationDomain`: Domain to use when translating list value.
 *   Defaults to "default".
 * - `nested`: (bool) If `true` the array returned by enum() method will be of form
 *   `[['value' => 'v1', 'text' => 't1'], ['value' => 'v2', 'text' => 't2']`
 *   instead of default `['v1' => 't1', 'v2' => 't2']`.
 * - `lists`: the defined enumeration lists. Lists can use different strategies,
 *   use prefixes to differentiate them (defaults to the uppercased list name) and
 *   are persisted into a table's field (default to the underscored list name).
 *
 *   Example:
 *
 *   ```php
 *   $lists = [
 *       'priority' => [
 *           'strategy' => 'lookup',
 *           'prefix' => 'PRIORITY',
 *           'field' => 'priority',
 *           'errorMessage' => 'Invalid priority',
 *           // Create application rule to ensure only valid enum value can be saved.
 *           'applicationRules' => true,
 *           // Allow saving field without any enum value.
 *           'allowEmpty' => false
 *       ],
 *   ];
 *   ```
 *
 * @var array
 */
    protected $_defaultConfig = [
        'implementedMethods' => [
            'enum' => 'enum',
            'getText' => 'getEnumText'
        ],
        'defaultStrategy' => 'const',
        'translate' => false,
        'nested' => false,
        'translationDomain' => 'default',
        'strategyclassMap' => [],
        'lists' => []
    ];

/**
 * Default list configuration.
 *
 * - `strategy`: Strategy name.
 * - `prefix`: Prefix in use (defaults to alias name).
 * - `field`: Field name (defaults to alias name).
 * - `errorMessage`: Error message.
 * - `applicationRules`: Create application rule to ensure only valid enum value can be saved.
 * - `allowEmpty`: Allow saving field without any enum value.
 * - `values`: Normally, the strategy is the responsably for obtaining the list of values
 *      but, as an alternative, you can pass the values passed directly, bypassing the strategy.
 *
 * @var array
 */
    protected $_defaultListConfig = [
        'strategy' => null,
        'prefix' => null,
        'field' => null,
        'errorMessage' => null,
        'applicationRules' => true,
        'allowEmpty' => false,
        'values' => []
    ];

/**
 * Strategy class map.
 *
 * Keyed by the respective alias.
 *
 * @var array
 */
    protected $_strategyClassMap = [
        'database' => 'Nata\ORM\Behavior\Enum\Strategy\Database',
        'const' => 'Nata\ORM\Behavior\Enum\Strategy\Constant',
        'config' => 'Nata\ORM\Behavior\Enum\Strategy\Config',
    ];

/**
 * Stack of strategies in use.
 *
 * @var array
 */
    protected $_strategies = [];

/**
 * Enum sets.
 *
 * @var array
 */
    protected $_sets = [];


/**
 * Initialize hook.
 *
 * If events are specified - do *not* merge them with existing events,
 * overwrite the events to listen on
 *
 * @param array $config The config for this behavior.
 * @return void
 */
    public function initialize($config) {
        parent::initialize($config);
        $this->_normalizeConfig();
    }

/**
 * Normalizes the strategies configuration and initializes the strategies.
 *
 * @return void
 */
    protected function _normalizeConfig(): void {
        $classMap = $this->config('strategyclassMap');
        $this->_strategyClassMap = array_merge($this->_strategyClassMap, $classMap);

        $defaultStrategy = $this->config('defaultStrategy');
        $lists = $this->config('lists');
        foreach ($lists as $alias => $config) {
            if (is_numeric($alias)) {
                unset($lists[$alias]);
                $alias = $config;
                $config = [];
                $lists[$alias] = $config;
            }

            if (is_string($config)) {
                $config = ['prefix' => strtoupper($config)];
            }

            if (empty($config['strategy'])) {
                $config['strategy'] = $defaultStrategy;
            }

            $config += $this->_defaultListConfig;

            $strategy = $this->strategy($alias, $config['strategy']);
            $strategy->initialize($config);
            $lists[$alias] = $strategy->config();
        }

        $this->config('lists', $lists, false);
    }

/**
 * Getter/setter for strategies.
 *
 * @param string $alias Strategy's alias.
 * @param mixed $strategy Strategy name from the class map or some strategy instance.
 * @return \Nata\ORM\Behavior\Enum\Strategy
 * @throws \Nata\ORM\Behavior\Enum\Exception\MissingEnumStrategyException
 */
    public function strategy(string $alias, $strategy): Strategy {
        if (!empty($this->_strategies[$alias])) {
            return $this->_strategies[$alias];
        }

        $this->_strategies[$alias] = $strategy;
        unset($this->_sets[$alias]);

        if ($strategy instanceof Strategy) {
            return $strategy;
        }

        $class = null;
        if (isset($this->_strategyClassMap[$strategy])) {
            $class = $this->_strategyClassMap[$strategy];
        }

        if ($class === null || !class_exists($class)) {
            throw new MissingEnumStrategyException([$class]);
        }

        return $this->_strategies[$alias] = new $class($alias, $this->_table);
    }

/**
 * Get enum list.
 *
 * @param string $alias Enum alias.
 * @return array|EnumSet
 */
    public function enum($alias = null) {
        if (is_string($alias)) {
            if (isset($this->_sets[$alias])) {
                return $this->_sets[$alias];
            }

            $config = $this->config('lists.' . $alias);
            if ($config === null) {
                throw new MissingEnumConfigurationException([$alias]);
            }

            return $this->_sets[$alias] = $this->_enumList($alias, $config);
        }

        $lists = $this->config('lists');

        $this->_sets = [];
        foreach ($lists as $alias => $config) {
            $this->_sets[$alias] = $this->_enumList($alias, $config);
        }
        return $this->_sets;
    }

/**
 * Load enum alias values.
 *
 * @param string $alias Alias.
 * @param array $config The config for this behavior.
 * @return EnumSet Enum values
 */
    protected function _enumList(string $alias, array $config): EnumSet {
        if (isset($this->_sets[$alias])) {
            return $this->_sets[$alias];
        }

        $config += $this->_defaultListConfig;
        $values = $config['values'];
        if (!$values) {
            $strategy = $config['strategy'];
            if (empty($strategy)) {
                throw new MissingEnumStrategyException([$alias]);
            }

            $strategy = $this->strategy($alias, $config);
            $values = $strategy->enum($config);
            $this->config('lists.' . $alias . '.values', $values);
        }

        if ($this->config('nested')) {
            array_walk(
                $values,
                function (&$item, $val) {
                    $item = ['value' => $val, 'text' => $item];
                }
            );

            $values = array_values($values);
        }

        return new EnumSet($alias, $values);
    }

/**
 * Universal validation rule for lists.
 *
 * @param string $method Method name.
 * @param array $args Method's arguments.
 * @return bool
 * @throws \BadMethodCallException
 * @throws \Nata\ORM\Behavior\Enum\Exception\MissingEnumStrategyException
 */
    public function __call($method, array $args) {
        if (strpos($method, 'isValid') !== 0) {
            throw new BadMethodCallException(sprintf('Call to undefined method "%s".', $method));
        }

        $alias = Inflector::underscore(str_replace('isValid', '', $method));
        [$entity, ] = $args;

        $config = $this->config('lists.' . $alias);
        if ($config === null) {
            throw new MissingEnumStrategyException([$alias]);
        }

        if (!$entity->has($config['field']) && Hash::get($config, 'allowEmpty') === true) {
            return true;
        }

        return $this->enum($alias) && $this->enum($alias)->isValid($entity->{$config['field']});
    }

}
