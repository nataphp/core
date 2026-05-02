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

/**
 * CaseExpression builds a simple SQL CASE expression of the form:
 *
 *   CASE <field>
 *     WHEN ? THEN ?
 *     [WHEN ? THEN ?]...
 *     [ELSE ?|<field>]
 *   END
 *
 * Parameters are bound in the order: when1, then1, when2, then2, ..., else?
 */
class CaseExpression {

/**
 * Field name used in CASE <field> ... END.
 *
 * @var string
 */
    private string $_field;

/**
 * Map of WHEN => THEN values.
 *
 * @var array
 */
    private array $_map;

/**
 * ELSE value. When null, defaults to the field itself (no parameter bound).
 *
 * @var mixed
 */
    private $_else;

/**
 * Cached SQL.
 *
 * @var string|null
 */
    private ?string $_sql = null;

/**
 * Bound parameters for this expression.
 *
 * @var array
 */
    private array $_params = [];

/**
 * Constructor.
 *
 * @param string $field The field used in the CASE expression
 * @param array $map Associative array of whenValue => thenValue
 * @param mixed $else Optional ELSE value; when null, uses the field (no param)
 */
    public function __construct(string $field, array $map, mixed $else = null) {
        $this->_field = $field;
        $this->_map = $map;
        $this->_else = $else;
    }

/**
 * Build and return the SQL for this CASE expression.
 *
 * @return string
 */
    public function getSql(): string {
        if ($this->_sql === null) {
            $this->_params = [];
            $parts = [];
            $parts[] = 'CASE ' . $this->_field;

            foreach ($this->_map as $when => $then) {
                $parts[] = 'WHEN ? THEN ?';
                $this->_params[] = $when;
                $this->_params[] = $then;
            }

            if ($this->_else === null) {
                $parts[] = 'ELSE ' . $this->_field;
            } else {
                $parts[] = 'ELSE ?';
                $this->_params[] = $this->_else;
            }

            $parts[] = 'END';
            $this->_sql = implode(' ', $parts);
        }

        return $this->_sql;
    }

/**
 * Return this expression bound parameters.
 *
 * @return array
 */
    public function getParams(): array {
        $this->getSql();
        return $this->_params;
    }

}
