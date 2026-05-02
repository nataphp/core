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
use Nata\Database\Exception\DatabaseException;
use Nata\Database\Query\Upsert;
use Nata\Database\Query\UpsertResult;

/**
 * ExpressionBuilder class is responsible to dynamically create SQL query parts.
 *
 * @todo
 */
class Pgsql extends Upsert {


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

        [$primaryKey, $cols] = $this->_getUniqueColumns();

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
            $set .= $comma . $name . ' = ' . ($value == '?' ? '?' : 'EXCLUDED.' . $value);
        }

        $sql = 'INSERT INTO ' . $this->_table . ($this->_alias ? ' ' . $this->_alias : '');
        $sql .= ' (' . $columnsList . ')';
        $sql .= ' VALUES (' . $values . ')';
        $sql .= ' ON CONFLICT(' . implode(',', $cols) . ')';
        $sql .= ' DO UPDATE SET ' . $set;
        $sql .= ' RETURNING ' . $primaryKey;

        return $sql;
    }

/**
 * Get unique columns in indexes.
 *
 * @return array
 */
    protected function _getUniqueColumns(): array {
        $uniqidIndexes = $this->_schema->uniqueIndexes();
        $cols = [];
        $primaryKey = 'id';
        foreach ($uniqidIndexes as $type => $index) {
            if ($type === 'primary') {
                [$primaryKey] = $index->getColumns();
            }

            $cols = array_merge($cols, $index->getColumns());
        }
        return [$primaryKey, $cols];
    }

/**
 * Prepare UPSERT result.
 *
 * @param Result $result Doctrine result
 * @return UpsertResult
 */
    protected function _buildResult(Result $result): UpsertResult {
        $result = new UpsertResult($result);
        $returning = $result->fetch();
        if (isset($returning['id'])) {
            $result->setLastUpsertId($returning['id']);
        }
        return $result;
    }

}
