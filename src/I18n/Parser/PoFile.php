<?php
/**
 * NataPHP Framework
 *
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * Copyright (c) 2010, Union of RAD http://union-of-rad.org (http://lithify.me/)
 * Copyright (c) 2012, Clemens Tolboom
 * Copyright (c) 2014, Fabien Potencier https://github.com/symfony/Translation/blob/master/LICENSE
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @link          http://nataphp.com NataPHP Project
 * @since         1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\I18n\Parser;

use Exception;
use Nata\I18n\Time;
use Nata\Utility\Hash;
use Nata\Filesystem\File;

/**
 * Parses file in PO format.
 */
class PoFile {

/**
 * File to parse.
 *
 * @var File
 */
    protected $_resource;

/**
 * POT file header.
 *
 * @var array
 */
    protected $_header = [];

/**
 * Items.
 *
 * @var array
 */
    protected $_items = [];

/**
 * Items.
 *
 * @var array
 */
    protected $_itemIds = [];

/**
 * Items.
 *
 * @var array
 */
    protected $_itemDefaults = [
        'format' => null,
        'context' => null,
        'ids' => [],
        'translated' => null,
        'references' => [],
    ];

/**
 * Fuzzy items.
 *
 * @var array
 */
    protected $_fuzzy = [];

/**
 * Parsed messages.
 *
 * @var array
 */
    protected $_messages = ['_reversei18n' => []];

/**
 * Parsed file.
 *
 * @var bool
 */
    protected $_parsed = false;

/**
 * PO file locale.
 *
 * @var string
 */
    protected $_changed = false;

/**
 * PO file locale.
 *
 * @var string
 */
    protected $_readOnly = false;


/**
 * Parses portable object (PO) format.
 *
 * From http://www.gnu.org/software/gettext/manual/gettext.html#PO-Files
 * we should be able to parse files having:
 *
 * white-space
 * #  translator-comments
 * #. extracted-comments
 * #: reference...
 * #, flag...
 * #| msgid previous-untranslated-string
 * msgid untranslated-string
 * msgstr translated-string
 *
 * extra or different lines are:
 *
 * #| msgctxt previous-context
 * #| msgid previous-untranslated-string
 * msgctxt context
 *
 * #| msgid previous-untranslated-string-singular
 * #| msgid_plural previous-untranslated-string-plural
 * msgid untranslated-string-singular
 * msgid_plural untranslated-string-plural
 * msgstr[0] translated-string-case-0
 * ...
 * msgstr[N] translated-string-case-n
 *
 * The definition states:
 * - white-space and comments are optional.
 * - msgid "" that an empty singleline defines a header.
 *
 * This parser sacrifices some features of the reference implementation the
 * differences to that implementation are as follows.
 * - No support for comments spanning multiple lines.
 * - Translator and extracted comments are treated as being the same type.
 * - Message IDs are allowed to have other encodings as just US-ASCII.
 *
 * Items with an empty id are ignored.
 *
 * @param string|File $resource The file name to parse
 * @param bool $readOnly Read only file
 * @return array
 */
    public function __construct($resource, $readOnly = true) {
        if (!($resource instanceof File)) {
            $resource = new File($resource);
        }

        $this->_resource = $resource;
        $this->_readOnly = $readOnly;

        $this->_parse();
    }

/**
 * Get/Set header in portable object (PO) file.
 *
 * @param array|string $header List of header lines or header name
 * @param string $value Value
 * @return array|$this
 */
    public function header(array $header = [], $value = null) {
        if (func_num_args() === 0) {
            return $this->_header;
        }

        if ($this->_readOnly === true) {
            throw new Exception(sprintf('PO file "%s" is read only.', $this->_resource->pwd()));
        }

        $this->_header = [];
        $this->addHeader($header, $value);

        return $this;
    }

/**
 * Add header line(s) in portable object (PO) file.
 *
 * @param array|string $header List of header lines or header name
 * @param string $value Value
 * @return array|$this
 */
    public function addHeader($header, string $value = null) {
        if (is_string($header) && $value) {
            $header = [$header => $value];
        }

        if ($this->_readOnly === true) {
            throw new Exception(sprintf('PO file "%s" is read only.', $this->_resource->pwd()));
        }

        $this->_header = array_merge($this->_header, $header);

        return $this;
    }

/**
 * Get/Set items in portable object (PO) file.
 *
 * @param array|string $singular List of items or singular
 * @param string $plural Plural
 * @param string $translation Translation
 * @param string $context Context
 * @param array $references List of references
 * @return array|$this
 */
    public function items($singular = null, $plural = null, $translation = null, $context = null, $references = []) {
        if (func_num_args() === 0) {
            return $this->_items;
        }

        $this->_items = [];
        $this->addItems($singular, $plural, $translation, $context, $references);

        return $this;
    }

/**
 * Add item(s) in portable object (PO) file.
 *
 * @param array|string $singular List of items or singular
 * @param string $plural Plural
 * @param string $translation Translation
 * @param string $context Context
 * @param array $references List of references
 * @param bool $merge True to merge with existing item, false otherwise
 * @return array|$this
 */
    public function addItems($singular, $plural = null, $translation = null, $context = null, $references = [], $merge = false) {
        if (is_string($singular)) {
            $singular = [
                'context' => $context,
                'ids' => [
                    'singular' => $singular,
                    'plural' => $plural
                ],
                'translated' => $translation,
                'references' => $references
            ] + $this->_itemDefaults;
        }

        if ($this->_readOnly === true) {
            throw new Exception(sprintf('PO file "%s" is read only.', $this->_resource->pwd()));
        }

        $this->_setItems($singular, $merge);

        $this->_changed = true;
        return $this;
    }

/**
 * Merge/Add item(s) in portable object (PO) file.
 *
 * @param array|string $singular List of items or singular
 * @param string $plural Plural
 * @param string $translation Translation
 * @param string $context Context
 * @param array $references List of references
 * @param bool $merge True to merge with existing item, false otherwise
 * @return array|$this
 */
    public function mergeItems($singular, $plural = null, $translation = null, $context = null, $references = []) {
        return $this->addItems($singular, $plural, $translation, $context, $references, true);
    }

/**
 * Save changes to PO file.
 *
 * @return bool
 */
    public function save() {
        if ($this->_readOnly || !$this->_changed) {
            return false;
        }

        $this->addHeader('POT-Creation-Date', (new Time())->format('Y-m-d H:iO'));

        $contents = '';

        // PO edit requirement
        $contents .= 'msgid ""' . PHP_EOL . 'msgstr ""' . PHP_EOL;

        // Properties
        foreach ($this->_header as $name => $value) {
            $contents .= sprintf('"%s: %s\n"', $name, rtrim($value, '\\n'));
            $contents .= PHP_EOL;
        }
        $contents .= PHP_EOL;

        foreach ($this->_items as $item) {
            foreach ($item['references'] as $reference) {
                $contents .= '#: ' . $reference . PHP_EOL;
            }

            if ($item['format']) {
                $contents .= $item['format'] . PHP_EOL;
            }

            if ($item['context']) {
                $contents .= sprintf('msgctxt "%s"', $item['context']) . PHP_EOL;
            }

            $contents .= sprintf('msgid "%s"', $this->_safeQuotes($item['ids']['singular'])) . PHP_EOL;
            if (isset($item['ids']['plural'])) {
                $contents .= sprintf('msgid_plural "%s"', $this->_safeQuotes($item['ids']['plural'])) . PHP_EOL;
            }

            if (is_array($item['translated'])) {
                foreach ($item['translated'] as $index => $translation) {
                    $contents .= sprintf('msgstr[%d] "%s"', $index, $this->_safeQuotes($translation)) . PHP_EOL;
                }
            } else {
                $contents .= sprintf('msgstr "%s"', $this->_safeQuotes($item['translated'])) . PHP_EOL;
            }

            $contents .= PHP_EOL;
        }

        $this->_changed = false;

        return $this->_resource->write($contents);
    }

/**
 * Check if message ID exists.
 *
 * @param string $messageId Message ID to check
 * @return bool
 */
    public function exists($messageId): bool {
        return isset($this->_itemIds[$messageId]);
    }

/**
 * Check if message ID is translated.
 *
 * @param string $messageId Message ID to check
 * @return bool
 */
    public function isTranslated($messageId): bool {
        if (!$this->exists($messageId)) {
            return false;
        }
        $index = $this->_itemIds[$messageId];
        return isset($this->_items[$index]['translated']) && !empty($this->_items[$index]['translated']);
    }

/**
 * Get parsed portable object (PO) messages.
 *
 * @return array
 */
    public function getMessages() {
        return $this->_messages;
    }

/**
 * Parses portable object (PO) format.
 *
 * @return void
 */
    protected function _parse() {
        if ($this->_parsed) {
            return;
        }

        $item = $this->_itemDefaults;
        $header = [];
        $headerLines = true;
        $stream = fopen($this->_resource->pwd(), 'r');
        while ($line = fgets($stream)) {
            $line = trim($line);
            if ($line === '') {
                $headerLines = false;

                // Whitespace indicated current item is done
                $this->_addItem($item);
                $this->_addMessage($item);
                $item = $this->_itemDefaults;
            } elseif ($headerLines && strpos($line, '"') === 0) {
                $header[] = $line;
            } elseif (strpos($line, '#:') === 0) {
                $item['references'][] = str_replace('#: ', '', $line);
            } elseif (strpos($line, '#,') === 0) {
                $item['format'] = $line;
            } elseif (substr($line, 0, 7) === 'msgid "') {
                // We start a new item so save previous
                $this->_addItem($item);
                $this->_addMessage($item);
                $item['ids']['singular'] = $this->_removeSafeQuotes(substr($line, 7, -1));
            } elseif (substr($line, 0, 8) === 'msgstr "') {
                $item['translated'] = $this->_removeSafeQuotes(substr($line, 8, -1));
            } elseif (substr($line, 0, 9) === 'msgctxt "') {
                $item['context'] = $this->_removeSafeQuotes(substr($line, 9, -1));
            } elseif ($line[0] === '"') {
                $continues = isset($item['translated']) ? 'translated' : 'ids';
                if (is_array($item[$continues])) {
                    end($item[$continues]);
                    $item[$continues][key($item[$continues])] .= $this->_removeSafeQuotes(substr($line, 1, -1));
                } else {
                    $item[$continues] .= $this->_removeSafeQuotes(substr($line, 1, -1));
                }
            } elseif (substr($line, 0, 14) === 'msgid_plural "') {
                $item['ids']['plural'] = $this->_removeSafeQuotes(substr($line, 14, -1));
            } elseif (substr($line, 0, 7) === 'msgstr[') {
                $size = strpos($line, ']');
                $item['translated'][(int)substr($line, 7, 1)] = $this->_removeSafeQuotes(substr($line, $size + 3, -1));
            }

        }

        fclose($stream);

        // Save last item
        $this->_addItem($item);
        $this->_addMessage($item);
        $this->_addHeader($header);

        $this->_parsed = true;
        $this->_changed = false;
    }

/**
 * Get existing item (if any).
 *
 * @param string $messageId Message ID to check
 * @return array
 */
    protected function _getExistingItem($messageId): array {
        if (!$this->exists($messageId)) {
            return [];
        }
        $index = $this->_itemIds[$messageId];
        return $this->_items[$index];
    }

/**
 * Check if item exists.
 *
 * @param string $messageId Message ID to check
 * @return bool
 */
    protected function _itemExists($item): bool {
        $id = $item['ids']['singular'];
        return isset($this->_itemIds[$id]);
    }

/**
 * Add parsed item.
 *
 * @param array $item Item
 * @return void
 */
    protected function _addItem(array $item) {
        if (!$item['ids'] || empty($item['ids']['singular']) || $this->exists($item['ids']['singular'])) {
            return;
        }
        $this->_addItemId($item);
        $this->_items[] = $item;
    }

/**
 * Add item ID.
 *
 * @param array $item Item
 * @return void
 */
    protected function _addItemId($item) {
        $id = $item['ids']['singular'];
        $this->_itemIds[$id] = count($this->_items);
    }

/**
 * Add item to messages.
 *
 * @param array $item Item
 * @return void
 */
    protected function _addMessage(array $item) {
        if (!$item['ids']) {
            return;
        }

        if (empty($item['ids']['singular']) && empty($item['ids']['plural'])) {
            return;
        }

        $singular = stripcslashes($item['ids']['singular']);
        $translation = $item['translated'];
        if (is_array($translation)) {
            $translation = $translation[0];
        }

        $context = $item['context'] ?? null;
        $context = 'msgctxt_' . $context;
        if ($context && !isset($this->_messages[$context])) {
            $this->_messages[$context] = [];
        }

        $translation = stripcslashes($translation);

        if ($context) {
            $this->_messages['_reversei18n'][$context][$translation] = $singular;
            $this->_messages[$context][$singular] = $translation;
        } else {
            $this->_messages['_reversei18n'][$translation] = $singular;
            $this->_messages[$singular] = $translation;
        }

        if (isset($item['ids']['plural'])) {
            $plurals = $item['translated'];
            // PO are by definition indexed so sort by index.
            ksort($plurals);

            // Make sure every index is filled.
            end($plurals);
            $count = key($plurals);

            // Fill missing spots with an empty string.
            $empties = array_fill(0, $count + 1, '');
            $plurals += $empties;
            ksort($plurals);

            $plurals = array_map('stripcslashes', $plurals);
            $key = stripcslashes($item['ids']['plural']);

            if ($context) {
                $this->_messages[$context][$key] = $plurals;
            } else {
                $this->_messages[$key] = $plurals;
            }
        }

    }

/**
 * Add header.
 *
 * @param array $header Header
 * @return void
 */
    protected function _addHeader($header) {
        $continueHeader = null;
        foreach ($header as $line) {
            $line = trim(str_replace('"', '', $line));
            if ($continueHeader) {
                $this->_header[$continueHeader] .= $line;
                if (substr($line, -2, 2) == '\n') {
                    $continueHeader = null;
                }
                continue;
            }

            [$name, $value] = array_map('trim', explode(": ", $line));
            $this->_header[$name] = $value;

            if (substr($line, -2, 2) !== '\n') {
                $continueHeader = $name;
            }
        }
    }

/**
 * Normalize quotes.
 *
 * @param string $string String to normalize
 * @return string
 */
    protected function _removeSafeQuotes($string) {
        return str_replace('\\"', '"', $string);
    }

/**
 * Normalize quotes.
 *
 * @param string $string String to normalize
 * @return string
 */
    protected function _safeQuotes($string) {
        $string = str_replace('\\"', '"', $string);
        $string = str_replace('"', '\"', $string);
        return $string;
    }

/**
 * Saves a translation item to items.
 *
 * @param array $item The current item being inspected
 * @return void
 */
    public function _setItems($items, bool $merge) {
        if (!isset($items[0]) && isset($items['ids'])) {
            $items = [$items];
        }

        foreach ($items as $index => $item) {
            $item += $this->_itemDefaults;
            if ($this->_itemExists($item)) {
                unset($items[$index]);

                if ($merge === false) {
                    continue;
                }

                $messageId = $item['ids']['singular'];
                $itemIndex = $this->_itemIds[$messageId];

                $existingItem = $this->_getExistingItem($item['ids']['singular']);
                $this->_items[$itemIndex] = Hash::merge($existingItem, $item);
                continue;
            }

            $items[$index] = $item;
        }

        $this->_items = array_merge($this->_items, $items);
    }

}
