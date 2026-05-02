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

namespace Nata\Database\Query;

use Doctrine\DBAL\Result;
use Nata\Database\Connection;
use Nata\Database\Schema;

/**
 * UPSERT implementation base class for driver.
 */
abstract class Upsert {

/**
 * Connection.
 *
 * @var Connection
 */
    protected $_connection;

/**
 * Parameter manager.
 *
 * @var ParameterManager
 */
    protected $_parameterManager;

/**
 * Schema manager.
 *
 * @var Schema
 */
    protected $_schema;

/**
 * Table name.
 *
 * @var string
 */
    protected $_table;

/**
 * Table alias.
 *
 * @var string
 */
    protected $_alias;

/**
 * SQL.
 *
 * @var string
 */
    protected $_sql;

/**
 * Values.
 *
 * @var array
 */
    protected $_values = [];

/**
 * Set.
 *
 * @var array
 */
    protected $_set = [];


/**
 * Constructor.
 *
 * @param Connection $connection Connection
 * @param ParameterManager $parameterManager Connection
 * @param string $table Table name
 * @param string $alias Table alias
 * @return void
 */
    public function __construct(Connection $connection, ParameterManager $parameterManager, Schema $schema, string $table, ?string $alias) {
        $this->_connection = $connection;
        $this->_parameterManager = $parameterManager;
        $this->_schema = $schema->tableName($table);
        $this->_table = $table;
        $this->_alias = $alias;
        $this->initialize();
    }

/**
 * Pseudo constructor.
 *
 * @return void
 */
    public function initialize() {}

/**
 * Set VALUES values for INSERT.
 *
 * @param string|array $field Field name or array of values
 * @param string $value Field value
 * @return $this
 */
    public function values($fields = null, $value = null) {
        if ($fields === null) {
            return $this->_values;
        }

        $this->_values = [];
        $this->_parameterManager->clear('_values');

        return $this->_values('_values', $fields, $value, false);
    }

/**
 * Add VALUES values for INSERT query.
 *
 * @param string|array $field Field name or array of values
 * @param string $value Field value to update
 * @return $this
 */
    public function addValues($fields, $value = null) {
        return $this->_values('_values', $fields, $value);
    }

/**
 * Set UPDATE SET values.
 *
 * @param string|array $field Field name or array of values
 * @param string $value Field value
 * @return $this
 */
    public function set($fields = null, $value = null) {
        if ($fields === null) {
            return $this->_set;
        }

        $this->_set = [];
        $this->_parameterManager->clear('_set');

        return $this->_values('_set', $fields, $value);
    }

/**
 * Set VALUES key/value pair values.
 *
 * @param string|array $field Field name or array of values
 * @param string|array $field Field name or array of values
 * @param string $value Field value
 * @return $this
 */
    protected function _values($propName, $fields, $value) {
        if (!is_array($fields)) {
            $fields = [$fields => $value];
        }

        foreach ($fields as $name => $value) {
            if (is_int($name)) {
                $name = $value;
                $value = $name;
            } else {
                $this->_parameterManager->add($value, $propName);
                $value = '?';
            }

            $this->{$propName}[$name] = $value;
        }

        return $this;
    }

/**
 * Get SQL.
 *
 * @return string
 */
    public function getSql(): string {
        if ($this->_sql === null) {
            $this->_sql = $this->_buildSql();
        }
        return $this->_sql;
    }

/**
 * Execute query.
 *
 * @return UpsertResult
 */
    public function execute(): UpsertResult {
        /*
        print_a($this->getSql());
        print_a($this->_parameterManager->getPrepared(true));
        print_a('---------------------------------------------------------------------');
        */

        return $this->_buildResult(
            $this->_connection->executeStatement(
                $this->getSql(),
                $this->_parameterManager->getPrepared(true)
            )
        );
    }

/**
 * Build SQL.
 *
 * @return string
 */
    abstract protected function _buildSql(): string;

/**
 * Prepare UPSERT result.
 *
 * @param int $rowCount Row count
 * @return UpsertResult
 */
    abstract protected function _buildResult(int $rowCount): UpsertResult;

/**
 * __toString method.
 *
 * @return string
 */
    public function __toString() {
        return $this->getSql();
    }

/**
 * __debugInfo.
 *
 * @return array
 */
    public function __debugInfo() {
        return [
            'sql' => $this->getSql(),
            'params' => $this->_parameterManager->getPrepared(true)
        ];
    }

}
