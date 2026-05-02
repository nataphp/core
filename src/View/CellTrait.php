<?php
/**
 * NataPHP Framework
 *
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
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

namespace Nata\View;

use Nata\View\CellBuilder;

/**
 * Provides cell() method for usage in Controller and View classes.
 */
trait CellTrait {

/**
 * Renders the given cell.
 *
 * Example:
 *
 * ```
 * // Taxonomy\View\Cell\TagCloudCell::smallList()
 * $cell = $this->cell('Taxonomy.TagCloud::smallList', ['limit' => 10]);
 *
 * // App\View\Cell\TagCloudCell::smallList()
 * $cell = $this->cell('TagCloud::smallList', ['limit' => 10]);
 * ```
 *
 * The `display` action will be used by default when no action is provided:
 *
 * ```
 * // Taxonomy\View\Cell\TagCloudCell::display()
 * $cell = $this->cell('Taxonomy.TagCloud');
 * ```
 *
 * Cells are not rendered until they are echoed.
 *
 * @param string $cell You must indicate cell name, and optionally a cell action. e.g.: `TagCloud::smallList`
 * will invoke `View\Cell\TagCloudCell::smallList()`, `display` action will be invoked by default when none is provided.
 * @param array $data Additional arguments for cell method. e.g.:
 *    `cell('TagCloud::smallList', ['a1' => 'v1', 'a2' => 'v2'])` maps to `View\Cell\TagCloud::smallList(v1, v2)`
 * @param array $options Options for Cell's constructor
 * @return \Nata\View\Cell The cell instance
 * @throws \Nata\View\Exception\MissingCellException If Cell class was not found.
 * @throws \BadMethodCallException If Cell class does not specified cell action.
 */
    protected function cell($cell, array $data = [], array $options = []) {
        $instance = CellBuilder::build($cell, $data, $options, $this->request, $this->response, $this->eventManager());

        $builder = $instance->viewBuilder();

        if (!empty($plugin)) {
            $builder->plugin($plugin);
        }

        if (!empty($this->helpers)) {
            $builder->helpers($this->helpers);
            $instance->helpers = $this->helpers;
        }

        if ($this instanceof View) {
            if (!empty($this->theme)) {
                $builder->theme($this->theme);
            }

            $class = get_class($this);
            $builder->setClassName($class);
            $instance->viewClass = $class;

            return $instance;
        }

        if (method_exists($this, 'viewBuilder')) {
            $builder->theme($this->viewBuilder()->theme());
        }

        if (isset($this->viewClass)) {
            $builder->setClassName($this->viewClass);
            $instance->viewClass = $this->viewClass;
        }

        return $instance;
    }

}
