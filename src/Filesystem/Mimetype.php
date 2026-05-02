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

namespace Nata\Filesystem;

use Nata\FilesystemManager\Mimetype as FilesystemManagerMimetype;

/**
 * Holds and manages a list of valid mimetypes, by their extension.
 *
 * Allows to get the mimetype by given extension, validate extension
 * agains't list of mimetypes and one given, etc.
 */
class Mimetype extends FilesystemManagerMimetype {}