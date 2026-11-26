<?php
/**
 * NataPHP Framework
 *
 * @copyright     Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Nata\Test\View;

use Nata\Core\App;
use Nata\View\Smarty;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Where the Smarty view looks for a template: for a plugin, the app's
 * override folder (templates/plugin/{Plugin}/) comes before the plugin's own
 * templates, and the app's other templates stay after them as a fallback.
 */
class SmartyTemplatePathsTest extends TestCase {

/**
 * Template folders of a view, in the order Smarty searches them.
 *
 * @param \Nata\View\Smarty $view View.
 * @param string $template Template name.
 * @return array<int, string> Folders.
 */
    protected function _paths(Smarty $view, string $template): array {
        $method = new ReflectionMethod($view, '_paths');
        $method->setAccessible(true);

        return $method->invoke($view, $template);
    }

/**
 * A view of a plugin controller in an area.
 *
 * @param string|null $plugin Plugin set on the view, as a controller sets it.
 * @return \Nata\View\Smarty View.
 */
    protected function _view(?string $plugin): Smarty {
        $view = new Smarty();
        if ($plugin !== null) {
            $view->plugin($plugin);
        }
        foreach (['_folder' => 'Dashboard', '_channel' => 'http', '_prefix' => 'Platform'] as $property => $value) {
            $reflection = new ReflectionProperty($view, $property);
            $reflection->setAccessible(true);
            $reflection->setValue($view, $value);
        }

        return $view;
    }

/**
 * Position of a folder in the list (fails when it is not there).
 *
 * @param array<int, string> $paths Folders.
 * @param string $folder Folder.
 * @return int Position.
 */
    protected function _position(array $paths, string $folder): int {
        $position = array_search($folder, $paths, true);
        $this->assertNotFalse($position, $folder . " is not searched:\n" . implode("\n", $paths));

        return (int)$position;
    }

/**
 * The plugin set on the view (no "Plugin." in the template name) is
 * camelized: it used to call an Inflector that was never imported.
 *
 * @return void
 */
    public function testPluginFromTheViewIsCamelized(): void {
        $paths = $this->_paths($this->_view('platform_admin'), 'index');

        $this->assertSame(rtrim(App::path('Template' . DS . 'plugin' . DS . 'PlatformAdmin'), DS) . DS . 'http' . DS . 'Platform' . DS . 'Dashboard', $paths[0]);
    }

/**
 * Override folders (deepest first, never above templates/plugin/{Plugin}),
 * then the plugin's, then the app's, then the core's.
 *
 * @return void
 */
    public function testAppOverrideComesBeforeThePlugin(): void {
        $paths = $this->_paths($this->_view(null), 'Demo.index');
        $override = rtrim(App::path('Template' . DS . 'plugin' . DS . 'Demo'), DS);
        $plugin = rtrim(App::path('Template' . DS . 'http' . DS . 'Platform' . DS . 'Dashboard', 'Demo'), DS);
        $app = rtrim(App::path('Template' . DS . 'http' . DS . 'Platform' . DS . 'Dashboard'), DS);

        $this->assertSame([
            $override . DS . 'http' . DS . 'Platform' . DS . 'Dashboard',
            $override . DS . 'http' . DS . 'Platform',
            $override . DS . 'http',
            $override,
        ], array_slice($paths, 0, 4));
        $this->assertNotContains(dirname($override), array_slice($paths, 0, $this->_position($paths, $plugin)), 'nothing above the override root comes before the plugin');
        $this->assertLessThan($this->_position($paths, $app), $this->_position($paths, $plugin), 'the plugin comes before the app\'s own templates');
    }

/**
 * Without a plugin nothing changes: the app's folders come first.
 *
 * @return void
 */
    public function testWithoutPluginTheAppComesFirst(): void {
        $paths = $this->_paths($this->_view(null), 'index');

        $this->assertSame(rtrim(App::path('Template' . DS . 'http' . DS . 'Platform' . DS . 'Dashboard'), DS), rtrim($paths[0], DS));
        foreach ($paths as $path) {
            $this->assertStringNotContainsString(DS . 'plugin' . DS, $path);
        }
    }

}
