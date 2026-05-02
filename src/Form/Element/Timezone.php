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

use Nata\I18n\Time;
use DateTimeZone;

/**
 * Timezone select.
 */
class Timezone extends Select {

/**
 * Pseudo-constructor.
 *
 * @return void
 */
    public function initialize($config) {
		$options = $this->_loadOptions();
		$this->options()->loadAll($options);

		if ($this->placeholder() === null) {
            $this->_placeholder = __(' -- Select timezone -- ');
        }

		parent::initialize($config);
        $this->template('select');
    }

/**
 * Load options (timezones).
 */
    protected function _loadOptions() {
		$options = [];
		$timezones = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
		
		if (!$this->value()) {
			$time = new Time();
			$this->_value = (string)$time->timezone()->getName();
		}

		foreach ($timezones as $timezone) {
			$tz = new DateTimeZone($timezone);
			$now = new Time('now', $tz);
			$offset = ($tz->getOffset($now) / 3600);
			
			$formatted = '';

			if ($offset != 0) {
				$formatted .= ($offset < 0 ? '-' : '+');
			}

			if ($offset > -10 || $offset < 10) {
				$formatted .= '0' . str_replace('-', '', (string)$offset);
			} else {
				$formatted .= $offset;
			}

			$options[] = [
				'value' => $timezone,
				'offset' => $offset,
				'label' => sprintf('(GMT %s:00) %s', $formatted, $timezone)
			];
		}
		
		usort($options, function ($a, $b) {
			return $a['offset'] - $b['offset'];
		});

		return $options;
	}

}
