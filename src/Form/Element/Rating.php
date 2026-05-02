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

use Nata\Form\Element\Input;

/**
 * Rating element.
 */
class Rating extends Input {

/**
 * Rating star size.
 *
 * @var string
 */
    protected $_starSize = '20px';

/**
 * Precision.
 *
 * @var int
 */
    protected $_precision = 1;

/**
 * Full star.
 *
 * @var bool
 */
    protected $_fullStar;


/**
 * Pseudo-constructor.
 *
 * @return void
 */
    public function initialize($config) {
        $config += array(
            'starSize' => null,
            'fullStar' => null,
            'precision' => null
        );

        if ($config['starSize']) {
            $this->starSize($config['starSize']);
        }

        if ($config['fullStar']) {
            $this->fullStar($config['fullStar']);
        }

        if ($config['precision'] !== null) {
            $this->precision($config['precision']);
        }

        $this->attributes()->addClass('form-rating');
    }

/**
 * Get/Set rating star size.
 *
 * @param string $starSize Inline options
 * @return $this|string
 */
    public function starSize($starSize = null) {
        if ($starSize === null) {
            return $this->_starSize;
        }

        $this->_starSize = $starSize;
        $this->attributes()->set('data-star-width', $this->_starSize);

        return $this;
    }

/**
 * Get/Set precision.
 *
 * @param int $precision Inline options
 * @return $this|int
 */
    public function precision($precision = null) {
        if ($precision === null) {
            return $this->_precision;
        }
        $this->_precision = $precision;
        $this->attributes()->set('data-precision', $this->_precision);
        return $this;
    }

/**
 * Get/Set full star option.
 *
 * @param bool $fullStar Inline options
 * @return $this|bool
 */
    public function fullStar($fullStar = null) {
        if ($fullStar === null) {
            return $this->_fullStar;
        }
        $this->_fullStar = $fullStar;
        $this->attributes()->set('data-full-star', $this->_fullStar);
        return $this;
    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 * @return \Nata\Event\Event
 */
    public function beforeRender($event) {
        parent::beforeRender($event);

        $attrs = $this->attributes();
        $value = $this->value();

        if ($this->disabled()) {
            $value = empty($value) ? 0 : $value;
            $attrs->set('data-read-only', true);
            $attrs->set('disabled', null)->set('readonly', null)->set('data-normal-fill', '#efefef');
        }

        $attrs->set('data-rating', $value);
    }

}
