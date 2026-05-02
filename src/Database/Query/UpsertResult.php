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

use Nata\Database\Exception\DatabaseException;

/**
 * Decoration for Doctrine result and stores the last UPSERT ID.
 */
class UpsertResult {

/**
 * UPSERT query row count.
 *
 * @var int
 */
    protected $_rowCount;

/**
 * Last UPSERT ID.
 * Holds the inserted or updated row.
 *
 * @var mixed
 */
    protected $_lastUpsertId;


/**
 * Get the last upsert ID.
 *
 * @param int $rowCount
 * @return void
 */
    public function __construct(int $rowCount) {
        $this->_rowCount = $rowCount;
    }

/**
 * Get the last upsert ID.
 *
 * @return int
 */
    public function getLastUpsertId() {
        return $this->_lastUpsertId;
    }

/**
 * Set last UPSERT ID.
 *
 * @param mixed $lastUpsertId
 * @return void
 */
    public function setLastUpsertId($lastUpsertId) {
        if ($this->_lastUpsertId !== null) {
            throw new DatabaseException("Last UPSERT id it's already set");
        }
        $this->_lastUpsertId = $lastUpsertId;
    }

/**
 * Count of affected rows.
 *
 * @return int
 */
    public function rowCount(): int {
        return $this->_rowCount;
    }

}
