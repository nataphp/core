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
class PoFileHeader {

/**
 * Header lines.
 *
 * @var string
 */
    protected $_lines = [];


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
    public function __construct(array $header = []) {
    }

/**
 * Get ID.
 *
 * @return string
 */
    public function addLine($name, $value) {
    }

/**
 * Get PO file item formatted string.
 *
 * @return string
 */
    public function getPoString() {
        $content = PHP_OS;
        $content .= PHP_EOL;
        return $content;
    }

}
