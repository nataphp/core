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

namespace Nata\Core;

use Nata\Service\ServiceBuilder;
use Exception;

/**
 * Provides service loading capabilities to any class without requiring
 * inheritance from NataObject.
 */
trait ServiceAwareTrait {

/**
 * Builds and optionally assigns a service instance to the current object.
 *
 * When 'assignProperty' is true the service is stored under a property whose
 * name is derived from the service name with the plugin prefix and slashes
 * stripped (e.g. 'Shop.Order/Invoice' → $this->OrderInvoice).
 *
 * @param string $name Service name, optionally plugin-prefixed and slash-separated (e.g. 'Shop.Order/Invoice').
 * @param array $options Options forwarded to ServiceBuilder::build(). Pass 'assignProperty' => true to store on $this.
 * @return \Nata\Service\Service The service instance.
 * @throws \Exception When 'assignProperty' is true and the target property is already in use.
 */
    public function loadService(string $name, array $options = []) {
        $serviceInstance = ServiceBuilder::build($name, $options);

        $options += ['assignProperty' => false];

        if (!$options['assignProperty']) {
            return $serviceInstance;
        }

        [, $propertyName] = pluginSplit($name);
        $propertyName = str_replace('/', '', $propertyName);

        if (isset($this->{$propertyName})) {
            throw new Exception(sprintf('%s::$%s is already in use.', get_class($this), $propertyName));
        }

        $this->{$propertyName} = $serviceInstance;

        return $serviceInstance;
    }

}
