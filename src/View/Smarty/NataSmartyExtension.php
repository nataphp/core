<?php
/**
 * NataPHP Framework
 *
 * @copyright Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\View\Smarty;

use Smarty\Extension\Base;

/**
 * Prepended before CoreExtension so {block} uses NataBlockCompiler.
 */
final class NataSmartyExtension extends Base {

    public function getTagCompiler(string $tag): ?\Smarty\Compile\CompilerInterface {
        if ($tag === 'block') {
            return new NataBlockCompiler();
        }

        return null;
    }
}
