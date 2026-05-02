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
 * @since         NataPHP 1.0.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\Database\Query;

use InvalidArgumentException;
use Nata\I18n\Time;

/**
 * Holder and manager of Query parameters.
 */
class ParameterManager {

/**
 * Parameters.
 *
 * @var array
 */
    private $_params = [];

/**
 * Named parameters.
 *
 * @var array
 */
    private $_namedParams = [];

/**
 * Holds list of prepared parameters for PDO.
 * It works as a runtime cache for 'getPrepared()',
 * and it will be cleared everytime parameters are added/removed.
 *
 * @var array
 */
    private $_prepared;

/**
 * Parameters mapped per clause.
 *
 * @var array
 */
    protected $_paramsMap = [
        'select' => [],
        'from' => [],
        'set' => [],
        'values' => [],
        'join' => [],
        'where' => [],
        'having' => [],
        'orderBy' => []
    ];

/**
 * Loose parameters.
 *
 * It holds the parameter's positions that don't have a clause/placeholders to match to.
 *
 * @var array
 */
    private $_looseParams = [];

/**
 * Reserved parameters.
 *
 * It holds the positions of parameters the are reserved (null) for
 * the clause's placeholders that where passed.
 *
 * @var array
 */
    private $_reservedParams = [];


/**
 * Constructor.
 *
 * @return void
 */
    public function __construct() {}

/**
 * Get list of (unprepared) parameters.
 *
 * @return array
 */
    public function get($clause = null) {
        if ($clause === null) {
            return $this->_params;
        }

        if (!isset($this->_paramsMap[$clause])) {
            throw new InvalidArgumentException(sprintf('Invalid clause. Valid clauses are: %s', implode(', ', array_keys($this->_paramsMap))));
        }

        $params = [];
        foreach ($this->_paramsMap[$clause] as $key) {
            $params[] = $this->_params[$key];
        }

        return $params;
    }

/**
 * Add parameter(s).
 *
 * @param mixed $param Parameter(s) to add
 * @param string $clause Clause that the given parameters are associated with
 * @return $this
 */
    public function add($param, $clause = null) {
        return $this->_merge($param, $clause);
    }

/**
 * Reserve parameter(s) position(s) for given SQL with '?' placeholder(s).
 *
 * @param string $sql SQL argument with '?' placeholder
 * @param string $clause Clause that the given parameters are associated with
 * @return $this
 */
    public function reserveFor(string $sql, $clause) {
        $count = substr_count($sql, '?');
        $this->reserve($count, $clause);
    }

/**
 * Reserve X number of parameter's positions for to be given parameters.
 *
 * @param int $positions Number of parameter positions to reserve
 * @param string $clause Clause that the given parameters are associated with
 * @return void
 */
    public function reserve(int $positions, $clause): void {
        if ($positions === 0) {
            return;
        }

        $keys = array_keys($this->_params);
        $last = array_pop($keys);
        if (is_int($last)) {
            $last += 1;
        }

        for ($i = 0; $i < $positions; $i++) {
            if ($this->_looseParams) {
                $this->_paramsMap[$clause][] = $this->_looseParams[$i];
                unset($this->_looseParams[$i]);
            } else {
                $this->_params[] = null;
                $this->_reservedParams[count($this->_params) - 1] = $clause;
            }
        }

        // Reset keys
        $this->_looseParams = array_values($this->_looseParams);
    }

/**
 * Merge parameter(s).
 *
 * @param mixed $param Parameter(s) to merge
 * @param string $clause Clause that the given parameters are associated with
 * @return $this
 */
    protected function _merge($params, $clause) {
        if (!is_array($params)) {
            $params = [$params];
        }

        $this->_prepared = null;

        $keys = array_keys($this->_params);
        $position = array_pop($keys) ?? -1;

        $reservedParamKeys = array_keys($this->_reservedParams);
        $hasClause = $clause !== null;
        foreach ($params as $param => $value) {
            $value = $this->_prepare($value);

            if (is_string($param)) {
                $this->_namedParams[$param] = $value;
                continue;
            }

            if ($hasClause === false && $reservedParamKeys) {
                $this->_assignReservedParam($reservedParamKeys, $value);
                continue;
            }

            $position++;
            $this->_params[$position] = $value;

            if ($hasClause === false) {
                $this->_looseParams[] = $position;
            } elseif ($hasClause) {
                $this->_paramsMap[$clause][] = $position;
            }

        }

        return $this;
    }

/**
 * Assign reserved positions to added clauseless parameters.
 *
 * @param array $reservedParamKeys Keys of reserved parameters
 * @param mixed $value PArameter value to set on the reserved position
 * @return void
 */
    protected function _assignReservedParam(&$reservedParamKeys, $value) {
        $position = array_shift($reservedParamKeys);
        $clause = $this->_reservedParams[$position];
        $this->_params[$position] = $value;
        $this->_paramsMap[$clause][] = $position;

        unset($this->_reservedParams[$position]);
    }

/**
 * Normalize parameter's values.
 *
 * @param array $params Parameters to normalize
 * @return array Normalized parameters
 */
    protected function _prepare($value) {
        // Boolean
        if (is_bool($value)) {
            $value = $value === true ? 1 : 0;
        // Time
        } elseif ($value instanceof Time) {
            $value = $value->timezone('UTC')->format('Y-m-d H:i:s');
        }
        return $value;
    }

/**
 * Get list of parameters prepared for PDO/SQL.
 *
 * @param bool $zeroIndexed True for *ZERO* indexed array
 * @return array
 */
    public function getPrepared($zeroIndexed = false) {
        if ($this->_prepared === null) {
            $this->_prepared = $this->_namedParams;
            foreach ($this->_paramsMap as $keys) {
                foreach ($keys as $key) {
                    $this->_prepared[] = $this->_params[$key];
                }
            }
        }

        if ($zeroIndexed === true) {
            return $this->_prepared;
        }

        $prepared = $this->_prepared;
        array_unshift($prepared, 'foobar');
        unset($prepared[0]);

        return $prepared;
    }

/**
 * Clear parameters.
 *
 * @param string $clause Clause to clear parameters of
 * @return $this
 */
    public function clear($clause = null) {
        if ($clause === null) {
            foreach ($this->_paramsMap as $clause => $keys) {
                foreach ($keys as $key) {
                    unset($this->_params[$key]);
                    unset($this->_paramsMap[$clause][$key]);
                }
            }
        } elseif (in_array($clause, array_keys($this->_paramsMap))) {
            $keys = $this->_paramsMap[$clause];
            foreach ($keys as $key) {
                unset($this->_params[$key]);
            }
            $this->_paramsMap[$clause] = [];
        }

        $this->_prepared = null;

        return $this;
    }

}
