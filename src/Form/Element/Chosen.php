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
 * ChosenJS adapter (Select) element.
 *
 * @link https://harvesthq.github.io/chosen
 * @link https://harvesthq.github.io/chosen/options.html
 * @link https://github.com/harvesthq/chosen
 */
class Chosen extends Select {

/**
 * Pseudo-constructor.
 *
 * @return void
 */
    public function initialize($config) {
        $config['enableChosen'] = true;

        $this->template('select');

        parent::initialize($config);

    }

}
