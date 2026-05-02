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

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Types\Type;
use Nata\Database\Exception\SchemaException;

/**
 * Table schema.
 */
class Schema {

/**
 * Connection.
 *
 * @var \Nata\Database\Connection
 */
    private $_connection;

/**
 * Doctrine schema manager.
 *
 * @var \Doctrine\DBAL\Schema\AbstractSchemaManager
 */
    private $_doctrineSchema;

/**
 * Table name.
 *
 * @var string
 */
    private $_tableName;

/**
 * Schema list.
 *
 * @var array
 */
    private static $_runtimeCache = [];


/**
 * Schema Constructor.
 *
 * @param Connection $connection Database connection
 * @param string $tableName Table name
 * @return void
 */
    public function __construct(Connection $connection, ?string $tableName = null) {
        $this->_connection = $connection;
        if ($tableName) {
            $this->tableName($tableName);
        }
    }

/**
 * Get/Set table name.
 *
 * @param string $tableName
 * @return $this|string
 */
    public function tableName(string $tableName = null) {
        if ($tableName === null) {
            if ($this->_tableName === null) {
                throw new SchemaException('Table name not set.');
            }
            return $this->_tableName;
        }

        $this->_tableName = $tableName;

        return $this;
    }

/**
 * Get/Set type of column.
 *
 * ## Valid types:
 *  'array'
 *  'simple_array'
 *  'bigint'
 *  'boolean'
 *  'datetime'
 *  'datetimetz'
 *  'date'
 *  'time'
 *  'decimal'
 *  'integer'
 *  'object'
 *  'smallint'
 *  'string'
 *  'text'
 *  'binary'
 *  'blob'
 *  'float'
 *  'guid'
 *
 * @param string $column Column name or array of column => type
 * @param string $type Column type name
 * @return $this|string
 * @see \Doctrine\DBAL\Types\Type for list of type and class names
 */
    public function columnType($column, $type = null) {
        $columnTypes = $this->_runtimeCache('columnTypes');
        if (!$columnTypes) {
            if ($type !== null) {
                $this->_runtimeCache('presetColumnTypes', [
                    $column => $type
                ], true);
                return $this;
            }

            $columns = $this->_columns();
        }

        if ($type === null && is_string($column)) {
            return $columnTypes[$column] ?? null;
        }

        $columnTypes[$column] = Type::getType($type);
        if (isset($columns[$column])) {
            $columns[$column] = $columns[$column]->setType($columnTypes[$column]);
            $this->_runtimeCache('columns', $columns);
        }
        $this->_runtimeCache('columnTypes', $columnTypes);

        return $this;
    }

/**
 * Get/create list of table's primary keys.
 *
 * @param string $primaryKey Primary key name
 * @return \Doctrine\DBAL\Schema\Index|$this
 */
    public function primaryKey($primaryKey = null) {
        if (func_num_args() === 0) {
            $primaryKey = $this->_runtimeCache('primaryKey');
            if ($primaryKey === null) {
                $primaryKey = $this->indexes('primary');
                $this->_runtimeCache('primaryKey', $primaryKey);
            }
            return $primaryKey;
        }

        // @todo create

        return $this;
    }

/**
 * Get table's primary key columns.
 *
 * @return array<int,string>
 */
    public function getPrimaryKeyColumns() {
        $primaryKeyColumns = $this->_runtimeCache('primaryKeyColumns');
        if ($primaryKeyColumns === null && $this->primaryKey()) {
            $primaryKeyColumns = $this->primaryKey()->getColumns();
            $this->_runtimeCache('primaryKeyColumns', $primaryKeyColumns);
        }
        return $primaryKeyColumns;
    }

/**
 * Get/create table's unique indexes.
 *
 * @param string $uniqueIndexes Unique index name
 * @return array<string,Index>
 */
    public function uniqueIndexes($uniqueIndexes = null): array {
        if (func_num_args() === 0) {
            $uniqueIndexes = $this->_runtimeCache('uniqueIndexes');
            if ($uniqueIndexes === null) {
                $uniqueIndexes = [];
                foreach ($this->indexes() as $name => $index) {
                    if (!$index->isUnique()) {
                        continue;
                    }
                    $uniqueIndexes[$name] = $index;
                }
                $this->_runtimeCache('uniqueIndexes', $uniqueIndexes);
            }
            return $uniqueIndexes;
        }

        // @todo create

        return $this;
    }

/**
 * Get table's columns with unique indexes.
 * Primary key columns are NOT included, use Schema::getPrimaryKeyColumns() instead.
 *
 * @return array<int,string>
 */
    public function getUniqueColumns(): array {
        $uniqueColumnsList = $this->_runtimeCache('uniqueColumnsList');
        if ($uniqueColumnsList === null) {
            $uniqueColumnsList = [];
            foreach ($this->uniqueIndexes() as $name => $index) {
                if ($name === 'primary') {
                    continue;
                }
                $uniqueColumnsList = array_merge($uniqueColumnsList, $index->getColumns());
            }
            $this->_runtimeCache('uniqueColumnsList', $uniqueColumnsList);
        }
        return $uniqueColumnsList;
    }

/**
 * Get list of table's foreign keys.
 *
 * @param string $foreignKey Foreign key name
 * @return \Doctrine\DBAL\Schema\ForeignKeyConstraint|array
 */
    public function foreignKeys($foreignKey = null) {
        if ($foreignKey === null) {
            $foreignKeys = $this->_runtimeCache('foreignKeys');
            if ($foreignKeys === null) {
                $foreignKeys = $this->_runtimeCache(
                    'foreignKeys',
                    $this->_list('listTableForeignKeys')
                );
            }
            return $foreignKeys;
        }

        // @todo create

        return $this;
    }

/**
 * Check if foreign key exists in current table.
 *
 * @return int True if foreign key exists, false otherwise
 */
    public function hasForeignKey($foreignKey) {
        $foreignKeys = $this->foreignKeys();
        return isset($foreignKeys[$foreignKey]) ? $foreignKeys[$foreignKey] : null;
    }

/**
 * Check if foreign key with given column name exists in current table.
 *
 * @param string $columnName Column name to check
 * @param string $tableName Foreign table name
 * @return int True if foreign key exists, false otherwise
 */
    public function hasForeignKeyColumn($columnName, $foreignTableName = null) {
        $foreignKeys = $this->foreignKeys();
        foreach ($foreignKeys as $foreignKey) {
            if ($foreignTableName && $foreignTableName !== $foreignKey->getForeignTableName()) {
                continue;
            }
            if (in_array($columnName, $foreignKey->getLocalColumns())) {
                return true;
            }
        }
        return false;
    }

/**
 * Get column schema.
 *
 * @param string $field Field name
 * @return Column
 */
    public function column($field): ?Column {
        return $this->_columns($field);
    }

/**
 * Get list of columns schema instances.
 *
 * @param bool $namesOnly To get only column names
 * @return array List of columns
 */
    public function columns($namesOnly = true): array {
        $columns = $this->_columns();
        if ($namesOnly) {
            return $this->_runtimeCache('columnsNames');
        }
        return $columns;
    }

/**
 * Get table's columns that are non NULL.
 *
 * @return array<int,string>
 */
    public function getNotNullColumns(): array {
        $notNullColumns = $this->_runtimeCache('notNullColumns');
        if ($notNullColumns === null) {
            $this->_columns();
        }
        return $this->_runtimeCache('notNullColumns');
    }

/**
 * Get columns list.
 *
 * @param string $name To get one in particular
 * @return array|object
 */
    private function _columns($name = null) {
        $columns = $this->_runtimeCache('columns');
        if (!$columns) {
            $columns = [];
            $columnTypes = $this->_runtimeCache('presetColumnTypes') ?? [];
            $columnNames = [];
            $notNullColumns = [];
            $list = $this->_list('listTableColumns');
            foreach ($list as $column) {
                $columnName = $column->getName();
                if (isset($columnTypes[$columnName])) {
                    if (!($columnTypes[$columnName] instanceof Type)) {
                        $columnTypes[$columnName] = Type::getType($columnTypes[$columnName]);
                    }
                    $column->setType($columnTypes[$columnName]);
                } else {
                    $columnTypes[$columnName] = $column->getType();
                }

                $columns[$columnName] = $column;
                $columnNames[] = $columnName;
                // Not NULL
                if ($column->getNotnull()) {
                    $notNullColumns[] = $columnName;
                }
            }
            $this->_runtimeCache('columns', $columns);
            $this->_runtimeCache('columnTypes', $columnTypes);
            $this->_runtimeCache('notNullColumns', $notNullColumns);
            $this->_runtimeCache('columnsNames', $columnNames);
        }

        if ($name !== null) {
            return $columns[$name] ?? null;
        }

        return $columns;
    }

/**
 * Get list of table's indexes.
 *
 * @param string $index Index name
 * @return \Doctrine\DBAL\Schema\Index|array
 */
    public function indexes($index = null) {
        $indexes = $this->_list('listTableIndexes');
        if ($index !== null) {
            return $indexes[$index] ?? null;
        }
        return $indexes;
    }

/**
 * Check if index exists.
 *
 * @return int True if index exists, false otherwise
 */
    public function hasIndex($index) {
        return $this->indexes($index) !== null;
    }

/**
 * Get schema data for given type.
 * If table name changes during runtime, it will create new lists.
 *
 * @param string $schemaList Type of schema information
 * @return array|object
 */
    private function _list(string $schemaList) {
        $tableName = $this->tableName();
        $schema = $this->_runtimeCache($schemaList);
        if (!$schema) {
            $schema = $this->_runtimeCache(
                $schemaList,
                $this->_connection->loadDoctrineSchemaManager()->{$schemaList}($tableName)
            );
        }
        return $schema;
    }

/**
 * @todo
 * Creating and listing tables should go to a \Nata\Database\Schema
 * There must be a class \Nata\Database\Connection class that abstracts
 * Doctrine Connection class. This class will, like \Nata\ORM\Table::schema()
 * initialize \Nata\Database\Schema
 *
 * @return
 */
    private function _create($tableName) {
        return $this->_doctrineSchema->createTable($tableName);
    }

/**
 * TODO
 *
 * @param string|array $fieldName Array of columns to add or field name
 * @param string $type Type of field
 * @param array $config Array column configuration
 * @return int True if index exists, false otherwise
 */
    public function addColumns($tableName, $fieldName, $type = null, array $config = []) {
        $create = $this->_create($tableName);
        // $create->addColumn($fieldName, $type, $options);
        $this->reset();
        return $create;
    }

/**
 * Runtime cache getter/setter.
 *
 * @param string $key Key name
 * @param mixed $value Value to set
 * @param bool $merge To merge value with existing one
 * @return mixed
 */
    private function _runtimeCache(string $key, $value = null, bool $merge = false) {
        if ($this->_tableName === null) {
            return null;
        }

        if ($value === null) {
            return static::$_runtimeCache[$this->_tableName][$key] ?? null;
        }

        if ($merge) {
            $value = array_merge(static::$_runtimeCache[$this->_tableName][$key] ?? [], $value);
        }

        return static::$_runtimeCache[$this->_tableName][$key] = $value;
    }

/**
 * Reset current instance properties values.
 *
 * @return $this
 */
    public function reset() {
        static::$_runtimeCache[$this->_tableName] = [];
        return $this;
    }

}
