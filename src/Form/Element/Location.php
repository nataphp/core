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

use Nata\Form\Attributes;

/**
 * Location element.
 */
class Location extends Input {

/**
 * Initial marker position.
 *
 * @var string
 */
    protected $_markerPosition;

/**
 * Initial map zoom.
 * Between 0 and 21
 *
 * @var int
 */
    protected $_zoom = 5;

/**
 * Location coordenates.
 *
 * @var string
 */
    protected $_location;

/**
 * Latitude.
 *
 * @var int
 */
    protected $_latitude;

/**
 * Longitude.
 *
 * @var int
 */
    protected $_longitude;

/**
 * Inline map.
 *
 * @var bool
 */
    protected $_inline = false;

/**
 * Autocomplete.
 *
 * @var bool
 */
    protected $_autocomplete = false;


/**
 * Pseudo-constructor.
 *
 * @return void
 */
    public function initialize($config) {
        parent::initialize($config);
        $config += array(
            'markerPosition' => null,
            'zoom' => null,
            'location' => null,
            'autocomplete' => null,
            'latitude' => null,
            'longitude' => null,
            'inline' => null
        );

        if ($config['inline']) {
            $this->_inline = $config['inline'];
        }

        if ($config['markerPosition']) {
            $this->_markerPosition = $config['markerPosition'];
        }

        if ($config['zoom']) {
            $this->_zoom = $config['zoom'];
        }

        if ($config['location']) {
            $this->location($config['location']);
        }

        if ($config['latitude']) {
            $this->latitude($config['latitude']);
        }

        if ($config['longitude']) {
            $this->longitude($config['longitude']);
        }

        if ($config['autocomplete']) {
            $this->autocomplete($config['autocomplete']);
        }

    }

/**
 * Validation.
 *
 * @param string $data Data to be validated
 * @return boolean True if valid, false otherwise
 */
    protected function _valid($data) {
        $error = $latitude = $longitude = null;

        if (strpos($data, ',') === false) {
            $error = __('Latitude and longitude must be separated by a comma (e.g. 45.3454656, -34.435632)!');
        } else {
            list($latitude, $longitude) = array_map('trim', explode(',', $data));
            if (!is_numeric($latitude) && !is_numeric($longitude)) {
                $error = __('Latitude and longitude must be numeric!');
            } elseif (!is_numeric($latitude)) {
                $error = __('Latitude must be numeric!');
            } elseif (!is_numeric($longitude)) {
                $error = __('Longitude must be numeric!');
            }
        }

        $this->error($error);

        return !$this->hasError();
    }

/**
 * Get/Set map marker position when value/data is empty.
 *
 * @param string $markerPosition Coordenates
 * @return $this|string
 */
    public function markerPosition($markerPosition = null) {
        if ($markerPosition === null) {
            return $this->_markerPosition;
        }

        $this->_markerPosition = $markerPosition;

        return $this;
    }

/**
 * Get/Set map zoom value.
 * Between 0 and 21
 *
 * @param int $zoom Zoom value
 * @return $this|int
 */
    public function zoom($zoom = null) {
        if ($zoom === null) {
            return $this->_zoom;
        }
        $this->_zoom = $zoom;
        return $this;
    }

/**
 * Get/Set location latitude and longitude comma separated.
 *
 * @param string $location Coordenates
 * @return $this|string
 */
    public function location($location = null) {
        if ($location === null) {
            return $this->_location;
        }
        $this->_location = $location;
        return $this;
    }

/**
 * Get/Set autocomplete setting.
 *
 * @param bool $autocomplete Autocomplete setting
 * @return $this|bool
 */
    public function autocomplete($autocomplete = null) {
        if ($autocomplete === null) {
            return $this->_autocomplete;
        }
        $this->_autocomplete = $autocomplete;
        return $this;
    }

/**
 * Get/Set map latitude value.
 *
 * @param int $latitude Latitude value
 * @return $this|int
 */
    public function latitude($latitude = null) {
        if ($latitude === null) {
            return $this->_latitude;
        }
        $this->_latitude = $latitude;
        return $this;
    }

/**
 * Get/Set map longitude value.
 *
 * @param int $longitude Latitude value
 * @return $this|int
 */
    public function longitude($longitude = null) {
        if ($longitude === null) {
            return $this->_longitude;
        }
        $this->_longitude = $longitude;
        return $this;
    }

/**
 * Get/Set show map inline.
 *
 * @param bool $inline Inline map
 * @return $this|bool
 */
    public function inline($inline = null) {
        if ($inline === null) {
            return $this->_inline;
        }
        $this->_inline = $inline;
        return $this;
    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 */
    public function beforeRender($event) {
        parent::beforeRender($event);

        $id = $this->id();
        $address = array_pop($id);
        $address .= '_address';
        $id[] = $address;
        $addressAttr = (new Attributes($this))->name($id);

        $name = $this->attrs()
            ->hidden(['name-coords-control', 'name-address-control'])
            ->name();

        $this->attrs()->set([
            'name-coords-control' => $name,
            'name-address-control' => $addressAttr->name()
        ]);

        $this->_getView()->extend(['input']);
    }

}
