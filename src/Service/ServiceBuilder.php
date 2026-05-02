<?php
/**
 * NataPHP Framework.
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

namespace Nata\Service;

use Nata\Core\App;
use Nata\Service\Exception\ServiceException;
use Nata\Service\Exception\MissingServiceException;
use RuntimeException;

/**
 * Provides a object factory for services.
 */
class ServiceBuilder {

/**
 * Build service class instance.
 *
 * ## Example
 *
 * $registration = ServiceBuilder::build('User/Registration');
 *
 * @param string $alias Service name
 * @param array $options Service options
 * @return \Nata\Service\Service
 * @throws \ServiceException
 */
    public static function build($alias, array $options = []) {
        $serviceInstance = static::_build($alias, $options);

        if (!($serviceInstance instanceof Service)) {
            list($plugin, $domain) = pluginSplit($alias);
            list($domain, $class) = splitter($domain, '/');
            throw new MissingServiceException(sprintf('Missing class "%s" for service alias "%s".', ($plugin ? $plugin : 'App') . '\Service\\' . $domain . '\\' . $class, $alias));
        }

        if (!is_subclass_of($serviceInstance, '\Nata\Service\Service')) {
            throw new RuntimeException(sprintf('"%s" must extend \Nata\Service\Service class.', $options['className']));
        }

        return $serviceInstance;
    }

/**
 * Check if service exists.
 *
 * @param string $alias Service name
 * @param array $options Service options
 * @return bool True if exists, false otherwise
 */
    public static function exists($alias, array $options = []) {
        $serviceInstance = static::_build($alias, $options);

        if (!is_subclass_of($serviceInstance, '\Nata\Service\Service')) {
            return false;
        }

        return ($serviceInstance instanceof Service);
    }

/**
 * Build service class instance.
 *
 * @param string $alias Service name
 * @param array $options Service options
 * @return \Nata\Service\Service
 */
    protected static function _build($alias, array $options = []) {
        $parts = explode('/', $alias);

        if (count($parts) === 1) {
            throw new ServiceException(sprintf('Missing service domain name from service alias "%s". Should only be "DomainName/ServiceClass"', $alias));
        } elseif (count($parts) > 2) {
            throw new ServiceException(sprintf('Invalid service alias "%s". Should only be "DomainName/ServiceClass"', $alias));
        }

        $options += [
            'name' => null,
            'domain' => null,
            'className' => null,
            'registryAlias' => null
        ];

        list($plugin, $domain) = pluginSplit($alias, true);
        list($domain, $class) = splitter($domain, '/');

        $options['className'] = App::className($plugin . $class, 'Service/' . $domain);
        if (!$options['className']) {
            return;
        }

        $options['name'] = $class;
        $options['domain'] = $domain;
        $options['registryAlias'] = $alias;

        $serviceInstance = new $options['className']($options);

        unset($options);

        return $serviceInstance;
    }

}
