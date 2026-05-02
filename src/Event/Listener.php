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

namespace Nata\Event;

/**
 * Objects implementing this interface should declare the `implementedEvents` function
 * to hint the event manager what methods should be called when an event is triggered.
 */
interface Listener  {

/**
 * Returns a list of events this object is implementing, when the class is registered
 * in an event manager, each individual method will be associated to the respective event.
 *
 * ## Example:
 *
 * {{{
 *    public function implementedEvents() {
 *        return [
 *            'Order.complete' => 'sendEmail',
 *            'Article.afterBuy' => 'decrementInventory',
 *            'User.onRegister' => ['callable' => 'logRegistration', 'priority' => 20, 'passParams' => true]
 *        ];
 *    }
 * }}}
 *
 * @return array associative array or event key names pointing to the function
 * that should be called in the object when the respective event is fired
 */
    public function implementedEvents();

}
