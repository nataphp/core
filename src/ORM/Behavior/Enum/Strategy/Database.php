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
use Nata\Utility\Inflector;

/**
 * Strategy for Enum behavior to extract values by database lookup.
 */
class Database extends Strategy {


/**
 * {@inheritdoc}
 *
 * @return void
 */
    public function enum(array $config = []): void {
        $prefix = $this->config('prefix');
        if (empty($prefix)) {
            $config['prefix'] = $this->_generatePrefix();
        }

        if (empty($config['field'])) {
            $config['field'] = Inflector::underscore($this->_alias);
        }

        if (empty($config['errorMessage'])) {
            $config['errorMessage'] = __('The provided value is invalid');
        }

        $this->config($config);
    }

/**
 * Generates default prefix for strategy.
 *
 * @return string
 */
    protected function _generatePrefix(): string {
        $prefix = Inflector::underscore(Inflector::singularize($this->_table->alias()));
        $prefix .= '_' . $this->_alias;
        return strtoupper($prefix);
    }

}