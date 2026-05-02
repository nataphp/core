<?php
/**
 * NataPHP Framework
 *
 * @author      Sergio Dinis Lopes <sergiodlopes at gmail dot com>
 * @copyright   Copyright (c)
 * @file        Application's Behavior File
 */

namespace Nata\ORM\Behavior;

use Nata\ORM\Behavior;

/**
 * Implements a finder to obtain the entities nearest/farthest to given coords.
 */
class Proximity extends Behavior {

/**
 * Default config
 *
 * These are merged with user-provided configuration when the behavior is used.
 *
 * @var array
 */
    protected $_defaultConfig = [
        'implementedFinders' => [
            'nearest' => 'findNearest',
            'bounds' => 'findWithinBounds'
        ],
        'implementedMethods' => [],
        'latitudeField' => 'latitude',
        'longitudeField' => 'longitude',
        'unit' => 'km'
    ];


/**
 * Finder nearest entities according to given latitude and longitude.
 *
 * @param \Nata\ORM\Query $query Query
 * @param array $options Options
 * @return \Nata\ORM\Query
 */
    public function findNearest($query, $options) {
        if ($this->_selectDistance($query, $options) !== false) {
            $this->_prependOrder($query, 'asc');
        }
        return $query;
    }

/**
 * Finder farthest entities according to given latitude and longitude.
 *
 * @param \Nata\ORM\Query $query Query
 * @param array $options Options
 * @return \Nata\ORM\Query
 */
    public function findFarthest($query, $options) {
        if ($this->_selectDistance($query, $options) !== false) {
            $this->_prependOrder($query, 'desc');
        }
        return $query;
    }

/**
 * Finder entities within given north and south bounds.
 *
 * // swlat, swlng, nelat, nelng = a, b, c, d.
 * // south, west, north, east = a, b, c, d.
 *
 * (south < north AND latitude BETWEEN south AND north) OR (north < south AND latitude BETWEEN north AND south)
 * AND
 * (west < east AND longitude BETWEEN west AND east) OR (east < west AND longitude BETWEEN east AND west)
 *
 * (a < c AND lat BETWEEN a AND c) OR (c < a AND lat BETWEEN c AND a)
 * AND
 * (b < d AND lng BETWEEN b AND d) OR (d < b AND lng BETWEEN d AND b)
 *
 * @param \Nata\ORM\Query $query Query
 * @param array $options Options
 * @return \Nata\ORM\Query
 */
    public function findWithinBounds($query, $options) {
        $options += [
            'latitude' => null,
            'longitude' => null,
            'delta' => 0.04,
            'south' => null,
            'west' => null,
            'north' => null,
            'east' => null
        ];

        if ($options['latitude'] && $options['longitude'] && ($options['north'] === null || $options['west'] === null || $options['south'] === null || $options['east'] === null)) {
            $options['south'] = (float) $options['latitude'] - (float) $options['delta'];
            $options['west'] = (float) $options['longitude'] - (float) $options['delta'];
            $options['north'] = (float) $options['latitude'] + (float) $options['delta'];
            $options['east'] = (float) $options['longitude'] + (float) $options['delta'];
        }

        $options = array_map(function ($val) {
            return (float)$val;
        }, $options);

        extract($options);

        $latitudeField = $this->_table->aliasField($this->config('latitudeField'));
        $longitudeField = $this->_table->aliasField($this->config('longitudeField'));

        /*
        $sqlWhere = sprintf("
            %s BETWEEN ? AND ?
            AND
            %s BETWEEN ? AND ?
        ", $longitudeField, $latitudeField);

        $query->andWhere($sqlWhere)->addParams([
            $east, $west,
            $north, $south
        ]);
        */

        $sqlWhere = sprintf("(%s > ? AND %s < ?) AND (%s > ? AND %s < ?)", $latitudeField, $latitudeField, $longitudeField, $longitudeField);

        $query->andWhere($sqlWhere)->addParams([
            $south, $north,
            $west, $east,
        ]);

        /*
        $sqlWhere = sprintf("
            (? < ? AND %s BETWEEN ? AND ?) OR (? < ? AND %s BETWEEN ? AND ?)
            AND
            (? < ? AND %s BETWEEN ? AND ?) OR (? < ? AND %s BETWEEN ? AND ?)
        ", $latitudeField, $latitudeField, $longitudeField, $longitudeField);

        $query->andWhere($sqlWhere)->addParams([
            $south, $north,
            $south, $north,
            $north, $south,
            $north, $south,

            $west, $east,
            $west, $east,
            $east, $west,
            $east, $west
        ]);
        */

        /*
        print_a($query->sql());
        print_a($query->params());
        die;
        */

        return $query;
    }

/**
 * Prepend 'distance' to the beginning of query's order by.
 *
 * @param \Nata\ORM\Query $query Query
 * @param string $direction Sort direction
 */
    protected function _prependOrder($query, $direction) {
        $order = $query->order();
        array_unshift($order, sprintf('distance %s', strtoupper($direction)));
        $query->order($order);
    }

/**
 * Add distance calculation to query selection.
 *
 * @param \Nata\ORM\Query $query Query
 * @param array $options Finder options
 * @return void
 */
    protected function _selectDistance($query, $options) {
        $options += [
            'unit' => $this->config('unit'),
            'latitude' => null,
            'longitude' => null,
            'radius' => 0
        ];

        extract($options);

        if (!is_numeric($latitude) || empty($latitude)
            || !is_numeric($longitude) || empty($longitude)) {
            return false;
        }

        // If autofields and select is empty, select all columns
        $select = $query->select();
        if (empty($select) && $query->autoFields()) {
            $query->select($this->_table->aliasField('*'));
        }

        $radiusValue = $this->_getRadiusValue($unit);

        $latitudeField = $this->_table->aliasField($this->config('latitudeField'));
        $longitudeField = $this->_table->aliasField($this->config('longitudeField'));

        $selectDistance = sprintf("
        ( {$radiusValue}
            * ACOS( COS( RADIANS( {$latitude} ) )
                * COS( RADIANS( %s ) )
                * COS(
                    RADIANS( %s ) - RADIANS( {$longitude} )
                )
                + SIN( RADIANS( {$latitude} ) )
                * SIN( RADIANS( %s ) )
            )
        ) AS distance", $latitudeField, $longitudeField, $latitudeField);

        $query->addSelect($selectDistance)
            // ->addParams([$radiusValue, $latitude, $longitude, $latitude])
            ->counter(function ($query) use ($selectDistance) {
                return $query->addSelect($selectDistance)->groupBy('distance');
            });

        if ($radius > 0) {
            $query->having(['distance <' => $radius]);
        }

        return $query;
    }

/**
 * Get the radius value based on type of unit ('km' or 'miles').
 *
 * @param string $unit Unit type ('km' or 'miles')
 * @return int Radius value
 */
    protected function _getRadiusValue(string $unit = 'km'): int {
        return match ($unit) {
            'km' => 6371,
            'miles' => 3959,
            default => 6371
        };
    }

}
