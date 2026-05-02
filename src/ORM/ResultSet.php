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

namespace Nata\ORM;

use Nata\ORM\Query;
use Nata\Collection\Collection;
use ArrayAccess;
use JsonSerializable;
use Nata\Collection\CollectionInterface;
use Serializable;

/**
 * Holds the query results.
 * Allows for manipulation of data with \Nata\Collection\Collection library.
 */
class ResultSet extends Collection implements JsonSerializable, ArrayAccess, Serializable {

/**
 * Query instance.
 *
 * @var \Nata\ORM\Query
 */
    private $_query;

/**
 * Results.
 *
 * @var array
 */
    private $_results;

/**
 * Keep track of results that have been prepared.
 *
 * @var array
 */
    private $_prepared = [];

/**
 * Iterator index.
 *
 * @var int
 */
    protected $_index = 0;


/**
 * Constructor.
 *
 * @param \Nata\ORM\Query $query Query instance
 * @param array $results Results
 * @return void
 */
    public function __construct(Query $query, $results) {
        $this->_query = $query;
        $this->_results = $results;
    }

/**
 * @inheritDoc
 */
    public function prepend($items): CollectionInterface {
        $results = [];
        foreach ($items as $item) {
            $results[] = $item;
        }
        $this->_results = array_merge($results, $this->_results);
        $results = null;
        unset($results);
        return $this;
    }

/**
 * @inheritDoc
 */
    public function prependItem($item, $key = null): CollectionInterface {
        array_unshift($this->_results, $item);
        return $this;
    }

/**
 * @inheritDoc
 */
    public function append($items): CollectionInterface {
        foreach ($items as $item) {
            $this->_results[] = $item;
        }
        return $this;
    }

/**
 * @inheritDoc
 */
    public function appendItem($item, $key = null): CollectionInterface {
        $this->_results[] = $item;
        return $this;
    }

/**
 * \Iterator implementation.
 *
 * @return void
 */
    public function rewind(): void {
        $this->_index = 0;
    }

/**
 * \Iterator implementation.
 *
 * @return bool
 */
    public function valid(): bool {
        return isset($this->_results[$this->_index]);
    }

/**
 * \Iterator implementation.
 *
 * @return int
 */
    public function key(): mixed {
        return $this->_index;
    }

/**
 * \Iterator implementation.
 *
 * @return Entity|array
 */
    public function current(): mixed {
        return $this->_groupResult($this->_index);
    }

/**
 * \Iterator implementation.
 *
 * @return void
 */
    public function next(): void {
        $this->_index++;
    }

/**
 * Implements isset($results);
 *
 * @param mixed $offset The offset to check.
 * @return bool Success
 */
    public function offsetExists($offset): bool {
        return isset($this->_results[$offset]);
    }

/**
 * Implements $results[$offset];
 *
 * @param int $offset The offset to get.
 * @return Entity|array
 */
    public function offsetGet(mixed $offset): mixed {
        return $this->_groupResult($offset);
    }

/**
 * Implements $results[$offset] = $value;
 *
 * @param mixed $offset The offset to set.
 * @param mixed $value The value to set.
 * @return void
 */
    public function offsetSet($offset, $value): void {
        $this->_results[$offset] = $value;
    }

/**
 * Implements unset($results[$offset);
 *
 * @param mixed $offset The offset to remove.
 * @return void
 */
    public function offsetUnset($offset): void {
        unset($this->_results[$offset]);
    }

/**
 * Get result.
 * If hydration is set to true, it will hydrate the result.
 *
 * @param int $index Iteration
 * @return Entity|array
 */
    private function _groupResult($index) {
        $result = $this->_results[$index];

        if ($this->_query && !isset($this->_prepared[$index])) {
            $query = $this->_query;

            $result = $query->eagerLoader()->prepareResult($result);

            if ($query->hydrate() && $result && !($result instanceof Entity)) {
                $repository = $query->repository();
                $result = $repository->newEntity($result, [
                    'markClean' => true
                ])->accessible($repository->primaryKey(), false);
            }

            $this->_prepared[$index] = $index;
            $this->_results[$index] = $result;
        }

        return $result;
    }

/**
 * Convert the current state of the object to an array.
 *
 * @return array
 */
    public function toArray($keepKeys = true): array {
        $data = [];
        foreach ($this->_results as $index => $v) {
            $data[$index] = $this->_groupResult($index);
        }
        return $data;
    }

/**
 * \JsonSerializable class implementation.
 *
 * @return array JSON Serialized data
 */
    public function jsonSerialize(): array {
        return $this->toArray();
    }

/**
 * \Serializable class implementation.
 *
 * @return string Serialized data
 */
    public function serialize(): string {
        return serialize($this->toArray());
    }

/**
 * \Serializable class implementation.
 *
 * @param string $data Serialized data
 * @return void
 */
    public function unserialize($data): void {
        $this->_results = unserialize($data);
    }

}
