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
 * @since         NataPHP 1.1.5
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\Database;

use LogicException;
use WeakMap;

/**
 * A database password that never shows when printed. The value is not a
 * property of the object (var_export() ignores __debugInfo() and prints
 * every property) but sits in a static map keyed by the object, so
 * var_dump(), print_r(), var_export(), json_encode() and Xdebug show an
 * empty object, and serialize() refuses it. Only reveal() gives the value.
 */
final class Password {

/**
 * Values by object.
 *
 * @var \WeakMap<self, string>|null
 */
    private static ?WeakMap $_values = null;

/**
 * Constructor.
 *
 * @param string $value Password
 */
    public function __construct(#[\SensitiveParameter] string $value) {
        self::$_values ??= new WeakMap();
        self::$_values[$this] = $value;
    }

/**
 * The password.
 *
 * @return string Password
 */
    public function reveal(): string {
        return self::$_values[$this] ?? '';
    }

/**
 * Nothing to show.
 *
 * @return array Empty
 */
    public function __debugInfo(): array {
        return [];
    }

/**
 * A password is never serialized.
 *
 * @return array Never returns
 * @throws \LogicException Always
 */
    public function __serialize(): array {
        throw new LogicException('A database password cannot be serialized.');
    }

/**
 * A password is never unserialized.
 *
 * @param array $data Data
 * @return void
 * @throws \LogicException Always
 */
    public function __unserialize(array $data): void {
        throw new LogicException('A database password cannot be unserialized.');
    }

}
