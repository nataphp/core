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

/**
 * PO file message item.
 */
class PoFileItem {

/**
 * Format.
 *
 * @var string
 */
    protected $_format;

/**
 * Context.
 *
 * @var string
 */
    protected $_context;

/**
 * Singular message.
 *
 * @var string
 */
    protected $_singular;

/**
 * POT file header.
 *
 * @var string
 */
    protected $_plural;

/**
 * Translated messages (array if plural).
 *
 * @var array|string
 */
    protected $_translated = [];

/**
 * Fuzzy translation.
 *
 * @var bool
 */
    protected $_fuzzy = false;

/**
 * Message references across the app.
 *
 * @var array
 */
    protected $_references = [];


/**
 * Constructor.
 *
 * @param string|array $singular Properties array or singular string
 * @param string $plural Plural
 * @param string|array $translated Translation
 * @param string $context Context
 * @param array $references List of references
 * @param string $format Format
 * @param bool $fuzzy Fuzzy
 * @return void
 */
    public function __construct($singular, string $plural = null, $translated = null, string $context = null, array $references = [], ?string $format = null, bool $fuzzy = false) {
        if (is_array($singular)) {
            extract($singular);
        }

        $this->_references = $references;
        $this->_format = $format;
        $this->_context = $context;
        $this->_singular = $singular;
        $this->_plural = $plural;
        $this->translated($translated);
        $this->_fuzzy = $fuzzy;
    }

/**
 * Get ID.
 *
 * @return string
 */
    public function getId() {
        return $this->_singular;
    }

/**
 * Get singular.
 *
 * @return string
 */
    public function getSingular() {
        return $this->_singular;
    }

/**
 * Get format.
 *
 * @return string
 */
    public function getPlural() {
        return $this->_plural;
    }

/**
 * Get format.
 *
 * @return string
 */
    public function getFormat() {
        return $this->_format;
    }

/**
 * Get context.
 *
 * @return string
 */
    public function getContext() {
        return $this->_context;
    }

/**
 * Get references.
 *
 * @return array
 */
    public function getReferences() {
        return $this->_references;
    }

/**
 * Get/Set translation.
 *
 * @param array|string $translation
 * @return string|array|$this
 */
    public function translated($translated = null) {
        if (func_num_args() === 0) {
            return $this->_translated;
        }

        if ($this->_plural && !is_array($translated)) {
            throw new Exception(sprintf('Must be an array of translated string.'));
        }

        $this->_translated = $translated;

        return $this;
    }

/**
 * Is translated.
 *
 * @return bool
 */
    public function isTranslated(): bool {
        return empty($this->_translated);
    }

/**
 * Get PO file item formatted string.
 *
 * @return string
 */
    public function getPoString() {
        $content = PHP_OS;
        foreach ($this->_references as $reference) {
            $content .= '#: ' . $reference . PHP_EOL;
        }

        if ($this->_format) {
            $content .= $this->_format . PHP_EOL;
        }

        if ($this->_context) {
            $content .= sprintf('msgctxt "%s"', $this->_context) . PHP_EOL;
        }

        $content .= sprintf('msgid "%s"', $this->_safeQuotes($this->_singular)) . PHP_EOL;
        if (isset($item['ids']['plural'])) {
            $content .= sprintf('msgid_plural "%s"', $this->_safeQuotes($this->_plural)) . PHP_EOL;
        }

        if (is_array($this->_translated)) {
            foreach ($this->_translated as $index => $translation) {
                $content .= sprintf('msgstr[%d] "%s"', $index, $this->_safeQuotes($translation)) . PHP_EOL;
            }
        } else {
            $content .= sprintf('msgstr "%s"', $this->_safeQuotes($this->_translated)) . PHP_EOL;
        }

        $content .= PHP_EOL;
        return $content;
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

}
