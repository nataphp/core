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

namespace Nata\Database\Query\Upsert;

use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Column;
use Nata\Database\Exception\DatabaseException;
use Nata\Database\Query\Upsert;
use Nata\Database\Query\UpsertResult;

/**
 * ExpressionBuilder class is responsible to dynamically create SQL query parts.
 *
 * @todo
 */
class Mysql extends Upsert {

/**
 * Insert/primary ID.
 *
 * @var mixed
 */
    protected $_lastInsertId;


/**
 * Build SQL string.
 *
 * @return string
 */
    protected function _buildSql(): string {
        if (empty($this->_values)) {
            throw new DatabaseException('VALUES are missing.');
        }
        if (empty($this->_set)) {
            throw new DatabaseException('SET values are missing.');
        }

        $columnsList = '';
        $values = '';
        foreach ($this->_values as $name => $v) {
            $comma = $columnsList === '' ? '' : ',';
            $values .= $comma . '?';
            $columnsList .= $comma . $name;
        }

        $set = '';
        foreach ($this->_set as $name => $value) {
            $comma = $set === '' ? '' : ',';
            $set .= $comma . $name . ' = ' . ($value == '?' ? '?' : '' . $value);
        }

        $sql = 'INSERT INTO ' . $this->_table . ($this->_alias ? ' ' . $this->_alias : '');
        $sql .= ' (' . $columnsList . ')';
        $sql .= ' VALUES (' . $values . ')';
        $sql .= ' ON DUPLICATE KEY UPDATE ' . $set;

        // If the primary key is an integer, we can get the last insert ID
        $primaryKey = $this->_getPrimaryColumn();
        if ($primaryKey instanceof Column) {
            $primaryKeyName = $primaryKey->getName();
            if ($primaryKey->getType()->lookupName($primaryKey->getType()) === 'integer') {
                $sql .= ',' . $primaryKeyName . ' = LAST_INSERT_ID(' . $primaryKeyName . ')';
            } else {
                $this->_lastInsertId = $this->_values[$primaryKeyName] ?? null;
            }
        }

        return $sql;
    }

/**
 * Get unique columns in indexes.
 *
 * @return Column
 */
    protected function _getPrimaryColumn() {
        [$primaryKey] = $this->_schema->primaryKey()->getColumns();
        return $this->_schema->column($primaryKey);
    }

/**
 * Prepare UPSERT result.
 *
 * @param Result $result Doctrine result
 * @return UpsertResult
 */
    protected function _buildResult($result): UpsertResult {
        $result = new UpsertResult($result);
        $lastInsertId = $this->_lastInsertId ?? $this->_connection->lastInsertId();
        if (!$lastInsertId) {
            throw new DatabaseException('Last insert ID is missing.');
        }
        $result->setLastUpsertId($lastInsertId);
        return $result;
    }

}
