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

use Nata\I18n\I18n;
use Nata\Form\Element\Select;

/**
 * Language select element.
 */
class Language extends Select {


/**
 * Pseudo-constructor.
 *
 * @return void
 */
    public function initialize($config) {
        $this->options()->loadAll($this->_catalog());
        parent::initialize($config);
    }

/**
 * Get options from L10n catalog.
 *
 * @return array Language options
 */
    protected function _catalog() {
        $l10n = I18n::getInstance()->l10n;
        $catalog = $l10n->catalog();
        $locale = I18n::locale();
        $userLang = $l10n->catalog($locale);
        $options = array();
        foreach ($catalog as $code => $language) {
            $options[] = array(
                'label' => $language['language'],
                'value' => $code,
                'selected' => $userLang['locale'] === $language['locale']
            );
        }
        return $options;
    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 */
    public function beforeRender($event) {
        parent::beforeRender($event);
        $this->template('select');
    }

}
