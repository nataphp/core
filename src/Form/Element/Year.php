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

use Nata\Utility\Validation;

/**
 * Year element.
 */
class Year extends Select {
    
/**
 * Start year.
 *
 * @var int
 */
    protected $_start = 1920;
    
/**
 * Start year.
 *
 * @var int
 */
    protected $_end;
    
/**
 * Reverse year.
 *
 * @var int
 */
    protected $_reverse;
    
/**
 * Year list sorting, asc or desc.
 *
 * @var string
 */
    protected $_sort = 'desc';


/**
 * Pseudo-constructor.
 *
 * @return void
 */
    public function initialize($config) {
        parent::initialize($config);
        $config += array(
            'start' => null,
            'end' => null,
            'reverse' => null,
            'sort' => null
        );
        if ($config['start']) {
            $this->_start = $config['start'];
        }
        if ($config['end']) {
            $this->_end = $config['end'];
        }
        if ($config['reverse']) {
            $this->_sort = 'desc';
        }
        if ($config['sort']) {
            $this->_sort = $config['sort'];
        }
    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 * @return \Nata\Event\Event
 */
    public function beforeRender($event) {
        $this->_yearsAsOptions();
        parent::beforeRender($event);
        $this->template('select');
    }

/**
 * Generate list of years.
 *
 * @return array
 */
    protected function _yearsAsOptions() {
        $options = [];
        $start = $this->_start;
        $end = $this->end();

        for ($year = $start; $year <= $end; $year++) {
            $options[] = [
                'label' => $year,
                'value' => $year
            ];
        }

        if ($this->_sort === 'desc') {
            usort($options, function($a, $b) {
                return $b['value'] - $a['value'];
            });
        }

        $this->options()->loadAll($options);

    }

/**
 * Get/set start year.
 *
 * @param int $start Start year
 * @return $this|int
 */
    public function start($start = null) {
        if ($start === null) {
            return $this->_start;
        }
        $this->_start = $start;
        return $this;
    }

/**
 * Get/set end year.
 *
 * @param int $end End year
 * @return $this|int
 */
    public function end($end = null) {
        if ($end === null) {
            if ($this->_end === null) {
                $this->_end = date('Y');
            }
            return $this->_end;
        }
        $this->_end = $end;
        return $this;
    }

/**
 * Get/set year list sorting.
 *
 * @param bool $sort Year list sorting.
 * @return $this|bool
 */
    public function sort($sort = null) {
        if ($sort === null) {
            return $this->_sort;
        }
        $this->_sort = $sort;
        return $this;
    }

/**
 * @deprecated Use sort instead
 */
    public function reverse($reverse = null) {
        if ($reverse === null) {
            return $this->_sort === 'desc';
        }
        $this->_sort = $reverse ? 'desc' : 'asc';
        return $this;
    }

}
