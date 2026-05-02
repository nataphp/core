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

namespace Nata\FilesystemManager\FileStorage;

use Nata\Utility\Hash;
use JsonSerializable;
use Nata\ORM\Entity;
use Nata\FilesystemManager\Exception\FileRepositoryException;

/**
 * File metadata manager.
 */
class MetadataManager implements JsonSerializable {

/**
 * Metadata.
 *
 * @var array
 */
    protected $_metadata = [];

/**
 * Hidden keys.
 *
 * @var array
 */
    protected $_hidden = [];

/**
 * Metadata has changed.
 *
 * @var bool
 */
    protected $_changed = false;

/**
 * Disabled.
 *
 * @var bool
 */
    protected $_disabled = false;

/**
 * Freezed.
 *
 * @var bool
 */
    protected $_freezed = false;


/**
 * Constructor.
 *
 * @param array|string $metadata Metadata
 * @return void
 */
    public function __construct($metadata = []) {
        if (is_string($metadata) && substr($metadata, 0, 1) === '{') {
            $metadata = json_decode($metadata, true);
        } elseif (!is_array($metadata)) {
            $metadata = [];
        }
        $this->_metadata = $metadata;
    }

/**
 * Get metadata value.
 *
 * @param string $key Key
 * @return mixed Value
 */
    public function get(string $key, $context = null, $owner = '*') {
        $owner = $this->_prepareOwner($owner);
        $context = $this->_prepareContext($context);
        $value = Hash::get($this->_metadata, $this->_generatePath($key, $context, $owner));
        return $value ?? $this->_metadata[$key] ?? null;
    }

/**
 * Set value.
 *
 * @param string $key Key
 * @param mixed $value Value
 * @return $this
 */
    public function set($data, $value = null, $context = null, $owner = '*') {
        if ($this->_freezed === true) {
            throw new FileRepositoryException('Metadata is freezed');
        }

        if (!is_array($data)) {
            $data = [$data => $value];
        }

        $owner = $this->_prepareOwner($owner);
        $context = $this->_prepareContext($context);

        foreach ($data as $key => $value) {
            $key = $this->_generatePath($key, $context, $owner);
            $currentValue = Hash::get($this->_metadata, $key);
            if ($currentValue !== null && $currentValue !== $value) {
                $this->_changed = true;
            }
            $this->_metadata = Hash::insert($this->_metadata, $key, $value);
        }

        return $this;
    }

/**
 * Delete value.
 *
 * @param string $key Key
 * @return $this
 */
    public function delete(string $key, $context = null, $owner = '*') {
        if ($this->_freezed === true) {
            throw new FileRepositoryException('Metadata is freezed');
        }

        $owner = $this->_prepareOwner($owner);
        $context = $this->_prepareContext($context);

        $this->_metadata = Hash::remove($this->_metadata, $this->_generatePath($key, $context, $owner));
        $this->_keyGarbageCollector();
        return $this;
    }

/**
 * Freeze metadata.
 *
 * @param bool $freeze Freeze
 * @return $this
 */
    public function freeze(bool $freeze = null) {
        if (func_num_args() === 0) {
            return $this->_freezed ?? false;
        }

        $this->_freezed = $freeze;

        return $this;
    }

/**
 * Hide metadata keys
 *
 * @param array|string $hidden Hidden keys
 * @return $this|array
 */
    public function hidden($keys = null, bool $merge = false) {
        if (func_num_args() === 0) {
            return $this->_hidden;
        }

        $this->_hidden = $merge === true ?
            array_unique(array_merge($this->_hidden, (array)$keys)) : (array)$keys;

        return $this;
    }

/**
 * Generate path.
 *
 * @param string $key Key
 * @param string $context Context
 * @param string $owner Owner
 * @return mixed Path
 */
    protected function _generatePath(string $key, string $context, string $owner) {
        $fullKey = '';

        if ($owner !== '*') {
            $fullKey .= $owner . '.';
            $key = $this->_getKeyNameIndex($key);
        }

        if ($context) {
            $fullKey .= $context . '.';
        }

        $fullKey .= $key;
        return $fullKey;
    }

/**
 * Get key name index.
 *
 * @param string $key Key
 * @return int Index
 */
    protected function _getKeyNameIndex(string $name) {
        if (!isset($this->_metadata['__k'])) {
            $this->_metadata['__k'] = [];
        }

        if (!isset($this->_metadata['__k'][$name])) {
            $this->_metadata['__k'][$name] = count($this->_metadata['__k']);
        }

        return $this->_metadata['__k'][$name];
    }

/**
 * Get owner key.
 *
 * @param string|Entity $owner Owner
 * @return string
 */
    protected function _prepareOwner($owner) {
        return $this->_getEntityKey($owner) ?? '*';
    }

/**
 * Get context key.
 *
 * @param string|Entity $context Context
 * @return string Context
 */
    protected function _prepareContext($context) {
        if (!is_array($context)) {
            $context = [$context];
        }

        $string = '';
        foreach ($context as $ctxt) {
            $string .= $this->_getEntityKey($ctxt);
        }

        return $string;
    }

/**
 * Get key index name.
 *
 * @param string $key Key
 * @return string Index
 */
    protected function _getKeyIndexName($index) {
        return array_search($index, $this->_metadata['__k']);
    }

/**
 * Keys garbage collector.
 *
 * @return void
 */
    protected function _keyGarbageCollector(): void {
        $keysIndexes = [];
        $keys = $this->_metadata['__k'] ?? [];
        $ownerKeys = $this->_metadata['__own'] ?? [];
        foreach ($ownerKeys as $own => $contexts) {
            foreach ($contexts as $context => $values) {
                if (empty($values)) {
                    unset($this->_metadata['__own'][$own][$context]);
                    continue;
                }

                foreach ($values as $key => $value) {
                    $keysIndexes[] = $key;
                }
            }

            if (isset($this->_metadata['__own'][$own]) && empty($this->_metadata['__own'][$own])) {
                unset($this->_metadata['__own'][$own]);
            }
        }

        $deleteKeys = array_diff(array_values($keys), $keysIndexes);
        foreach ($keys as $name => $key) {
            if (in_array($key, $deleteKeys)) {
                unset($this->_metadata['__k'][$name]);
            }
        }

        $this->_metadata = array_filter($this->_metadata);
    }

/**
 * Get references.
 *
 * @return array References
 */
    public function hasReferences(): array {
        return array_keys($this->_metadata['__own'][]);
    }

/**
 * Check if metadata has changed.
 *
 * @return bool Changed
 */
    public function hasChanged(): bool {
        return $this->_changed;
    }

/**
 * Check if metadata is empty.
 *
 * @return bool Is empty
 */
    public function isEmpty(): bool {
        return empty($this->_metadata);
    }

/**
 * Get metadata as array.
 *
 * @return array Metadata
 */
    public function toArray(): array {
        $data = [];
        foreach ($this->_metadata as $key => $val) {
            if (in_array($key, $this->_hidden)) {
                continue;
            }
            $data[$key] = $val;
        }
        return $data;
    }

/**
 * Get entity key.
 *
 * @param Entity|string $value Entity or string key
 * @return string String key.
 */
    protected function _getEntityKey($value): ?string {
        if ($value instanceof Entity) {
            if (!($value->id > 0)) {
                return null;
            }
            return strtolower($value->source()) . $value->id;
        }
        return $value;
    }

/**
 * jsonSerialize.
 *
 * @return array
 */
    public function jsonSerialize(): array {
        return $this->toArray();
    }

}
