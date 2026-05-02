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

namespace Nata\Form\Element;

/**
 * Yesno element.
 */
class Yesno extends Radio {

/**
 * Pseudo-constructor.
 * This method is called after user and defaults are setup in class.
 *
 * @param array $config Element configuration
 */
    public function initialize($config) {
        $this->data()->type('boolean');

        parent::initialize($config);
    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 */
    public function beforeRender($event) {
        parent::beforeRender($event);

        $this->inline(true)
            ->options()->loadAll([
                __('Yes') => true,
                __('No') => false
            ]);

        $this->template('radio');
    }

}
