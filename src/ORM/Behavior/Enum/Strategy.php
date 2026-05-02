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

namespace Nata\ORM\Behavior\Enum;

use Nata\Core\ConfigAwareTrait;
use Nata\ORM\Table;
use Nata\Utility\Inflector;

/**
 * Base class for list
 */
abstract class Strategy {

    use ConfigAwareTrait;

/**
 * Default config.
 *
 * @var string
 */
    protected $_defaultConfig = [];

/**
 * Alias.
 *
 * @var string
 */
    protected $_alias;

/**
 * Table instance.
 *
 * @var \Nata\ORM\Table
 */
    protected $_table;


/**
 * Constructor.
 *
 * @param string $alias List alias
 * @param \Nata\ORM\Table $table Table instance
 * @return void
 */
    public function __construct(string $alias, Table $table) {
        $this->_alias = $alias;
        $this->_table = $table;
    }

/**
 * {@inheritdoc}
 *
 * @return void
 */
    public function initialize(array $config): void {
        if (empty($config['field'])) {
            $config['field'] = Inflector::underscore($this->_alias);
        }

        if (empty($config['errorMessage'])) {
            $config['errorMessage'] = __('The provided value is invalid');
        }

        $this->config($config);
    }

/**
 * Get enum values.
 *
 * @param array $config Strategy configuration
 * @return array
 */
    abstract public function enum(array $config): array;

}