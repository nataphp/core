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

namespace Nata\Database;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Nata\Core\App;
use Nata\Core\ClassLoader;
use Nata\Core\ConfigAwareTrait;
use Nata\Core\Configure;

ClassLoader::registerPackage('Doctrine', ['core' => true]);

/**
 * Doctrine DBAL database connection decorator.
 */
class Connection {

    use ConfigAwareTrait;

/**
 * Doctrine database connection instance.
 *
 * @var DoctrineConnection
 */
    protected $_doctrineConnection;

/**
 * Doctrine database schema manager instance.
 *
 * @var \Doctrine\DBAL\Schema\AbstractSchemaManager
 */
    protected $_doctrineSchemaManager;

/**
 * Driver name.
 *
 * @var string
 */
    protected $_driverName;

/**
 * Database configuration name.
 *
 * @var string
 */
    protected $_configName;


/**
 * Get SQL Query Builder instance.
 *
 * @param array $config Database configuration parameters
 * @param Configuration $configuration Database configuration instance
 * @return void
 */
    public function __construct(array $config, ?Configuration $configuration = null) {
        if ($configuration === null) {
            $configuration = new Configuration();
        }

        $config += [
            'driver' => 'pdo_mysql',
            'host' => 'localhost',
            'dbname' => '',
            'user' => '',
            'password' => '',
            'charset' => str_replace('-', '', Configure::read('App.encoding'))
        ];

        $this->_doctrineConnection = DriverManager::getConnection($config, $configuration);
        $this->_configName = $config['_configName'];

        [$d, $name] = explode('_', $config['driver']);
        $this->_driverName = ucfirst($name);
    }

/**
 * Get configuration name.
 *
 * @return string
 */
    public function getConfigName(): string {
        return $this->_configName;
    }

/**
 * Get SQL Query Builder instance.
 *
 * @return \Nata\Database\Query
 */
    public function query(): Query {
        $className = App::className($this->_driverName, 'Database/Query/Custom');
        if (!$className) {
            $className = '\Nata\Database\Query';
        }
        return new $className($this);
    }

/**
 * Get database schema manager.
 *
 * @return \Nata\Database\Schema
 */
    public function schema(): Schema {
        return new Schema($this);
    }

/**
 * Get Doctrine DBAL connection.
 *
 * @return \Doctrine\DBAL\Connection
 */
    public function getDoctrineConnection(): DoctrineConnection {
        return $this->_doctrineConnection;
    }

/**
 * Get database schema manager.
 *
 * @return \Doctrine\DBAL\Schema\AbstractSchemaManager
 */
    public function loadDoctrineSchemaManager(): AbstractSchemaManager {
        if ($this->_doctrineSchemaManager === null) {
            $this->_doctrineSchemaManager = $this->_doctrineConnection->createSchemaManager();
        }
        return $this->_doctrineSchemaManager;
    }

/**
 * Get last INSERT id.
 *
 * @return mixed
 */
    public function getLastInsertId(): mixed {
        return $this->_doctrineConnection->lastInsertId();
    }

/**
 * Execute SQL statement.
 *
 * @param string $sql SQL statement
 * @param array $args Statement arguments
 * @param array $types Argument types
 * @param \Doctrine\DBAL\Cache\QueryCacheProfile $qcp Query cache profile
 * @return Result
 */
    public function executeQuery(string $sql, array $args, $types = [], ?QueryCacheProfile $qcp = null): Result {
        return $this->_doctrineConnection->executeQuery($sql, $args, $types, $qcp);
    }

/**
 * __call.
 *
 * @return mixed
 */
    public function __call($name, $args): mixed {
        return $this->_doctrineConnection->{$name}(...$args);
    }

}
