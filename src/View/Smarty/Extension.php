<?php
/**
 * NataPHP Framework
 *
 * @copyright Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\View\Smarty;

use Smarty\Extension\Base;
use Smarty\Compile\CompilerInterface;
use Nata\View\Smarty\BlockCompiler;

/**
 * Prepended before CoreExtension so {block} uses NataBlockCompiler.
 */
final class Extension extends Base {

/**
 * Get tag compiler.
 *
 * @param string $tag Tag name.
 * @return \Smarty\Compile\CompilerInterface|null
 */
    public function getTagCompiler(string $tag): ?CompilerInterface {
        if ($tag === 'block') {
            return new BlockCompiler();
        }
        return null;
    }
}
