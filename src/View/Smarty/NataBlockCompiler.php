<?php
/**
 * NataPHP Framework
 *
 * @copyright Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\View\Smarty;

use Smarty\Compile\Tag\Block;
use Smarty\ParseTree\Template;

/**
 * Smarty 5 only lists append/prepend as {block} option flags when blockNesting === 0.
 * Nested blocks then treat "append" as an unnamed attribute → "too many shorthand attributes".
 * Allow append/prepend at any depth (same compiled output shape as top-level blocks).
 */
final class NataBlockCompiler extends Block {

    /**
     * @param array $args array with attributes from parser
     * @param array<int, mixed> $parameter array with compilation parameter
     */
    public function compile($args, \Smarty\Compiler\Template $compiler, $parameter = [], $tag = null, $function = null): string {
        if (!isset($compiler->_cache['blockNesting'])) {
            $compiler->_cache['blockNesting'] = 0;
        }
        if ($compiler->_cache['blockNesting'] === 0) {
            $this->registerInit($compiler);
        }
        $this->option_flags = ['hide', 'nocache', 'append', 'prepend'];

        $_attr = $this->getAttributes($compiler, $args);
        ++$compiler->_cache['blockNesting'];
        $_className = 'Block_' . preg_replace('![^\w]+!', '_', uniqid(mt_rand(), true));

        $this->openTag(
            $compiler,
            'block',
            [
                $_attr, $compiler->tag_nocache, $compiler->getParser()->current_buffer,
                $compiler->getTemplate()->getCompiled()->getNocacheCode(), $_className,
            ]
        );

        $compiler->getParser()->current_buffer = new Template();
        $compiler->getTemplate()->getCompiled()->setNocacheCode(false);
        $compiler->suppressNocacheProcessing = true;

        return '';
    }
}
