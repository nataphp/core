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

use Nata\Core\App;
use Nata\Http\Request;
use Nata\Http\Response;
use Nata\Routing\Router;
use Nata\View\Exception\MissingCellException;

/**
 * Provides cell() method for usage in Controller and View classes.
 */
class CellBuilder {

/**
 * Renders the given cell.
 *
 * Example:
 *
 * ```
 * // Taxonomy\View\Cell\TagCloud::smallList()
 * $cell = $this->cell('Taxonomy.TagCloud::smallList', ['limit' => 10]);
 *
 * // App\View\Cell\TagCloud::smallList()
 * $cell = $this->cell('TagCloud::smallList', ['limit' => 10]);
 * ```
 *
 * The `display` action will be used by default when no action is provided:
 *
 * ```
 * // Taxonomy\View\Cell\TagCloud::display()
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
    public static function build($cell, array $data = [], array $options = [], Request $request = null, Response $response = null, $eventManager = null) {
        [$pluginAndCell, $action] = splitter($cell, '::');

        if ($action === null) {
            $action = 'display';
        }

        [$plugin] = pluginSplit($pluginAndCell);
        $className = App::className($pluginAndCell, 'View/Cell');
        if (!$className) {
            throw new MissingCellException([$pluginAndCell]);
        }

        $namedArgs = $data['namedArgs'] ?? [];
        unset($data['namedArgs']);
        if (!empty($data)) {
            $data = array_values($data);
        }

        $options = [
            'action' => $action,
            'plugin' => $plugin,
            'args' => $data,
            'viewVars' => $namedArgs,
        ] + $options;

        if ($request === null) {
            $request = Router::getRequest();
        }

        /* @var \Nata\View\Cell $instance */
        return new $className($request, $response, $eventManager, $options);
    }

}
