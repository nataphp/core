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

use Nata\Routing\Route\PluginShort;
use Nata\Routing\Router;
use Nata\Core\Plugin;
use Nata\Utility\Inflector;

$prefixes = Router::prefixes();

if ($plugins = Plugin::loaded()) {
    foreach ($plugins as $key => $value) {
        $plugins[$key] = Inflector::hyphen($value);
    }
    $pluginPattern = implode('|', $plugins);
    $match = array('plugin' => $pluginPattern);
    $shortParams = array('routeClass' => '\Nata\Routing\Route\PluginShort', 'plugin' => $pluginPattern);

    foreach ($prefixes as $prefix) {
        $params = array('prefix' => $prefix, $prefix => true);
        $indexParams = $params + array('action' => 'index');
        Router::connect("/{$prefix}/:plugin", $indexParams, $shortParams);
        Router::connect("/{$prefix}/:plugin/:controller", $indexParams, $match);
        Router::connect("/{$prefix}/:plugin/:controller/:action/*", $params, $match);
    }    
    Router::connect('/:plugin', array('action' => 'index'), $shortParams);
    Router::connect('/:plugin/:action', array(), $shortParams);
    Router::connect('/:plugin/:controller', array('action' => 'index'), $match);
    Router::connect('/:plugin/:controller/:action/*', array(), $match);
}

foreach ($prefixes as $prefix) {
    $params = array('prefix' => $prefix, $prefix => true);
    $indexParams = $params + array('action' => 'index');
    Router::connect("/{$prefix}/:controller", $indexParams);
    Router::connect("/{$prefix}/:controller/:action/*", $params);
}

Router::connect('/:controller', array('action' => 'index'));
Router::connect('/:controller/:action/*');

$namedConfig = Router::namedConfig();
if ($namedConfig['rules'] === false) {
    Router::connectNamed(true);
}

unset($namedConfig, $params, $indexParams, $prefix, $prefixes, $shortParams, $match,
    $pluginPattern, $plugins, $key, $value);
?>