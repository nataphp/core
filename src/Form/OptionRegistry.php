<?php
/**
 * NataPHP Framework
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

namespace Nata\Form;

use Nata\Form\Element;
use Nata\Form\Option;
use Nata\Form\OptionGroup;
use Nata\ORM\Behavior\Enum\EnumItem;
use RuntimeException;
use JsonSerializable;

/**
 * Holds colection of element options.
 */
class OptionRegistry implements JsonSerializable {

/**
 * Element instance.
 *
 * @var \Nata\Form\Element
 */
    protected $_element;

/**
 * Options instances.
 *
 * @var array
 */
    protected $_options = [];

/**
 * Value as key.
 *
 * @var bool
 */
    protected static $_valueAsKey = false;


/**
 * Construct.
 *
 * @param \Nata\Form\Form $form Form instance
 * @return void
 */
    public function __construct(Element $element) {
        $this->_element = $element;
    }

/**
 * Add multiple options.
 *
 * @param mixed $options Array of options
 * @return array Options
 */
    public function loadAll(mixed $options) {
        $isArrayList = array_is_list($options);
        foreach ($options as $value => $config) {
            if (str_contains($value, '@value')) {
                unset($options[$value]);
                continue;
            }

            if (is_string($value) && is_array($config) && $this->_isGroupConfig($config)) {
                $this->loadGroup($value, $config);
                continue;
            }
            $this->_loadOption($value, $config, $isArrayList);
        }
        return $this->_options;
    }

/**
 * Add an option group.
 *
 * @param string $label Group label
 * @param array $options Map of value => label pairs
 * @return \Nata\Form\OptionGroup
 */
    public function loadGroup(string $label, array $options): OptionGroup {
        $group = new OptionGroup($label);
        foreach ($options as $value => $config) {
            $normalized = $this->_normalizeConfig($value, $config, false);
            if ($normalized && strlen((string)$normalized['value']) > 0) {
                $normalized['id'] = $this->_autoId();
                $normalized['selected'] = $this->_isSelected($normalized);
                $group->add(new Option($normalized));
            }
        }
        $this->_options['@group_' . $label] = $group;
        return $group;
    }

/**
 * Add option.
 *
 * @param string|array|EnumItem $value Option value (label or value, label is deprecated)
 * @param string|array $config Option configuration
 * @return \Nata\Form\Option
 */
    public function load(mixed $value, mixed $config = null) {
        return $this->_loadOption($value, $config);
    }

/**
 * Add option.
 *
 * @param string|array|EnumItem $value Option value (label or value, label is deprecated)
 * @param string|array $config Option configuration
 * @return \Nata\Form\Option
 */
    protected function _loadOption(mixed $value, mixed $config = null, bool $isList = false) {
        $config = $this->_normalizeConfig($value, $config, $isList);
        if ($config && strlen((string)$config['value']) > 0) {
            $config['id'] = $this->_autoId();
            $config['selected'] = $this->_isSelected($config);
            $option = new Option($config);

            if ($config['prepend'] && !empty($this->_options)) {
                $this->_options = array_merge([$config['value'] => $option], $this->_options);
            } else {
                $this->_options[$config['value']] = $option;
            }

            return $option;
        }
    }

/**
 * Normalize option configuration.
 *
 * @param mixed $value Option value
 * @param string|array $config Option configuration
 * @return array
 */
    private function _normalizeConfig($value, $config, $isList) {
        if (is_numeric($value) && is_array($config) && empty($config)) {
            return;
        }

        if (is_array($value)) {
            $config = $value;
            $value = null;
        }

        if ($config instanceof EnumItem) {
            $config = [
                'label' => $config->text,
                'value' => $config->value
            ];
        } elseif (is_array($config) && !empty($config)) {
            if (!isset($config['label'])) {
                [$_label] = array_keys($config);
                $config = [
                    'label' => $_label,
                    'value' => $config[$_label]
                ];
            }
        } elseif ($config === null) {
            $config = [
                'label' => $value,
                'value' => $value
            ];
        } elseif ($isList === true) {
            if (static::$_valueAsKey) {
                $config = [
                    'value' => $value,
                    'label' => $config
                ];
            } else {
                $config = [
                    'label' => $config,
                    'value' => $config
                ];
            }
        } elseif (!is_array($config)) {
            if (static::$_valueAsKey === true) {
                $config = [
                    'value' => $value,
                    'label' => $config
                ];
            } else {
                $config = [
                    'label' => $value,
                    'value' => $config
                ];
            }
        } else {
            if (static::$_valueAsKey === true) {
                $config['value'] = $value;
            } else {
                $config['label'] = $value;
            }
        }

        if (is_bool($config['value'])) {
            $config['value'] = $config['value'] ? 1 : 0;
        }

        $config += [
            'prepend' => false,
            'label' => null,
            'value' => null,
            'disabled' => $this->_element->disabled(),
            'checked' => false,
            'selected' => false,
            'description' => null,
            'attrs' => null,
            'icon' => null,
            'id' => null
        ];

        return $config;
    }

/**
 * Check if option is selected/checked.
 *
 * @param string|array $data Element data to compare
 * @param string $value Option value
 * @return bool
 */
    private function _isSelected($config) {
        if ($config['selected'] || $config['checked']) {
            return true;
        }

        $value = $config['value'];

        $element = $this->_element;
        $data = $element->value();

        if (is_bool($data)) {
            $data = $data ? 1 : 0;
        }

        if (!is_array($data)) {
            $data = array($data);
        }

        $data = array_filter($data, function ($val) use ($element) {
            if (is_array($val)) {
                throw new RuntimeException(sprintf('Invalid data/value passed to element "%s". Expected array of strings, nested array was given.', $element->name()));
            }
            return strlen($val) > 0;
        });

        return in_array($value, $data);
    }

/**
 * Get option instance
 *
 * @param string|int $index Option index or option value
 * @return \Nata\Form\Option
 */
    public function get($value = null) {
        if ($value === null) {
            return $this->_options;
        }
        if (isset($this->_options[$value])) {
            return $this->_options[$value];
        }
        foreach ($this->_options as $item) {
            if ($item instanceof OptionGroup) {
                $grouped = $item->options();
                if (isset($grouped[$value])) {
                    return $grouped[$value];
                }
            }
        }
        return null;
    }

/**
 * Generate a unique ID for the option.
 *
 * @return string
 */
    protected function _autoId() {
        return implode('-', $this->_element->id()) . '-' . $this->count();
    }

/**
 * Detect whether an array config represents an option group
 * (a flat value => label map) rather than a single option config.
 *
 * @param array $config Config to inspect
 * @return bool
 */
    private function _isGroupConfig(array $config): bool {
        return !isset($config['label']) && !isset($config['value']) && !isset($config['prepend']);
    }

/**
 * Try to detect if the string passed is a value of an option or a label.
 *
 * @param string $check String to check
 * @return bool
 */
    protected function _isValue($check) {
        if (is_array($check)) {
            return false;
        }
        return (ctype_lower(substr($check, 0, 1)) && !ctype_upper($check)) || str_contains($check, '_');
    }

/**
 * Number of existing options.
 *
 * @return int
 */
    public function count() {
        return count($this->_options);
    }

/**
 * Select option.
 *
 * @param mixed $selector Option value
 * @return void
 */
    public function select($selector) {
        foreach ($this->_options as $value => $option) {
            if ($value === $selector) {
                $option->selected(true);
                break;
            }
        }
    }

/**
 * Disable option.
 *
 * @param mixed $selector Option value
 * @return void
 */
    public function disable($selector) {
        foreach ($this->_options as $value => $option) {
            if ($value === $selector) {
                $option->disabled(true);
                break;
            }
        }
    }

/**
 * Check if option exists.
 *
 * @param string|int $index Option index or option value
 * @return bool
 */
    public function has($value) {
        if (isset($this->_options[$value])) {
            return true;
        }
        foreach ($this->_options as $item) {
            if ($item instanceof OptionGroup && isset($item->options()[$value])) {
                return true;
            }
        }
        return false;
    }

/**
 * Check empty options.
 *
 * @return bool True if empty
 */
    public function isEmpty() {
        return empty($this->_options);
    }

/**
 * Unload option from list.
 *
 * @param string $value Option value
 * @return void
 */
    public function unload($value) {
        unset($this->_options[$value]);
    }

/**
 * Clear all options.
 *
 * @return void
 */
    public function clear() {
        $this->_options = [];
    }

/**
 * To array.
 *
 * @return array
 */
    public function toArray() {
        $array = [];
        foreach ($this->_options as $value => $option) {
            $array[$value] = $option->toArray();
        }
        return $array;
    }

/**
 * jsonSerialize.
 *
 * @return array
 */
    public function jsonSerialize(): array {
        return array_values($this->toArray());
    }

/**
 * Makes the array of options use the key as value.
 * Default is false.
 *
 * @param bool $valueAsKey Value as key
 * @return bool
 */
    public static function valueAsKey(bool $valueAsKey = null): bool {
        if ($valueAsKey === null) {
            return static::$_valueAsKey;
        }
        static::$_valueAsKey = $valueAsKey;
        return $valueAsKey;
    }

}
