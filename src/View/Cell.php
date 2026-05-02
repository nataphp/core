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
use Nata\Core\NataObject;
use Nata\Http\Request;
use Nata\Http\Response;
use Nata\Event\Manager;
use Nata\Event\Listener;
use Nata\View\ViewBuilder;
use Nata\Utility\Inflector;
use Nata\Utility\Hash;
use Nata\View\Exception\MissingCellTemplateException;
use MissingTemplateException;
use ReflectionMethod;
use ReflectionException;
use BadMethodCallException;
use Throwable;

/**
 * View Cell.
 */
abstract class Cell extends NataObject implements Listener {

/**
 * Cell name.
 *
 * @var string
 */
    public $name;

/**
 * Request instance.
 *
 * @var \Nata\Http\Request
 */
    protected $request;

/**
 * Response instance.
 *
 * @var \Nata\Http\Response
 */
    protected $response;

/**
 * View.
 *
 * @var \Nata\View\View
 */
    protected $View;

/**
 * View variables.
 *
 * @var array
 */
    protected $_viewVars = [];

/**
 * Automatically set to the name of a plugin.
 *
 * @var string
 */
    public $plugin;

/**
 * The widget's action to invoke.
 *
 * @var string
 */
    public $action;

/**
 * The widget's template theme to use.
 *
 * @var string
 */
    public $theme;

/**
 * Arguments to pass to widget's action (positional).
 *
 * @var array
 */
    private $_args = [];

/**
 * Named arguments to pass to widget's action, matched by parameter name.
 * Takes precedence over $args when present.
 *
 * @var array
 */
    private $namedArgs = [];

/**
 * Valid Cell options.
 *
 * @var array
 */
    protected $_validOptions = [];

/**
 * Page-level asset registry, shared across all cell instances.
 * Keyed by URL so duplicates are naturally suppressed.
 *
 * @var array<string, true>
 */
    protected static $_assets = [];

/**
 * Assets already injected into rendered output this request.
 * Prevents the same <script> tag being emitted more than once.
 *
 * @var array<string, true>
 */
    protected static $_emittedAssets = [];

/**
 * Cell constructor.
 *
 * @param \Nata\Http\Request $request Request instance
 * @param \Nata\Http\Response $response Response instance
 * @param array $options Options
 */
    public function __construct(Request $request = null, Response $response = null, Manager $eventManager = null, array $options = []) {
        if ($this->name === null) {
            $this->name = App::classShortName($this);
        }

        $this->request = $request;
        $this->response = $response;
        $this->_eventManager = $eventManager;
        $this->_viewVars = $options['viewVars'] ?? [];
        $this->_validOptions = array_merge(['action', 'args', 'namedArgs', 'plugin', 'theme'], $this->_validOptions);
        foreach ($this->_validOptions as $var) {
            $this->{$var} = $options[$var] ?? null;
        }

        $this->dispatchEvent('Cell.initialize');
    }

/**
 * Returns the \Nata\Event\Manager manager instance that is handling any callbacks.
 * You can use this instance to register any new listeners or callbacks to the
 * controller events, or create your own events and trigger them at will.
 *
 * @return \Nata\Event\Manager
 */
    public function initialize() {}

/**
 * Returns a list of all events that will fire in the controller during it's lifecycle.
 * You can override this function to add you own listener callbacks
 *
 * @return array
 */
    public function implementedEvents() {
        return [
            'Cell.initialize' => 'initialize',
            'Cell.beforeRender' => 'beforeRender',
            'Cell.afterRender' => 'afterRender',
            'Cell.shutdown' => 'beforeShutdown'
        ];
    }

/**
 * Built view instance.
 *
 * @return \Nata\View\View
 */
    public function viewBuilder() {
        if ($this->View === null) {
            $this->View = ViewBuilder::build(
                    'View',
                    $this->request,
                    $this->response
                )
                ->themed($this->theme ? true : false)
                ->theme($this->theme)
                ->plugin($this->plugin)
                ->basePath('Cell');
        }

        return $this->View;
    }

/**
 * Saves a variable for use inside a view template.
 * This method is the same as in \Nata\View\View::set(),
 * In the future this must be in some shared trait.
 *
 * @param string|array $one A string or an array of data to set.
 * @param string|array $two Value in case $one is a string (which then works as the key).
 *   Unused if $one is an associative array, otherwise serves as the values to $one's keys.
 * @return $this
 */
    public function set($one, $two = null) {
        if (is_array($one)) {
            if (is_array($two)) {
                $data = array_combine($one, $two);
            } else {
                $data = $one;
            }
        } else {
            if (strpos($one, '.') !== false) {
                $this->_viewVars = Hash::insert($this->_viewVars, $one, $two);
                return $this;
            }
            $data = [$one => $two];
        }

        $this->_viewVars = $data + $this->_viewVars;

        return $this;
    }

/**
 * Get a variable value.
 *
 * @param string $key A string as key's variable.
 * @param mixed $default The default/fallback content of $var.
 * @return string|array
 */
    public function get($key, $default = null) {
        if (strpos($key, '.') !== false) {
            return Hash::get($this->_viewVars, $key, $default);
        }
        return $this->_viewVars[$key] ?? $default;
    }

/**
 * Lazy loading of Table.
 *
 * @param string $name Class name
 * @return mixed
 */
    public function __get($name) {
        $plugin = ($this->plugin ? $this->plugin . '.' : '');
        return $this->loadModel($plugin . $name);
    }

/**
 * Render the widget.
 *
 * @param string|null $template Custom template name to render. If not provided (null), the last
 * value will be used. This value is automatically set by `CellTrait::widget()`.
 * @return string The rendered widget.
 * @throws \Nata\View\Exception\MissingCellTemplateException When a MissingTemplateException is raised during rendering.
 */
    public function render($template = null) {
        try {
            $reflect = new ReflectionMethod($this, $this->action);
            $reflect->invokeArgs($this, $this->args);
        } catch (ReflectionException $e) {
            throw new BadMethodCallException(sprintf(
                'Class %s does not have a "%s" method.',
                get_class($this),
                $this->action
            ));
        }

        if ($template !== null &&
            strpos($template, '/') === false &&
            strpos($template, '.') === false
        ) {
            $template = Inflector::underscore($template);
        }


        $view = $this->viewBuilder();
        if ($template === null) {
            $template = $view->template() ?: Inflector::underscore($this->action);
        }

        $view->layout(false)->template($template);

        if (!$view->templatePath()) {
            $view->templatePath($this->name);
        }

        $view->set($this->_viewVars);

        try {
            $output = $view->render($template);
        } catch (MissingTemplateException $e) {
            throw new MissingCellTemplateException([$template]);
        }

        $pending = array_diff_key(static::$_assets, static::$_emittedAssets);
        if (!empty($pending)) {
            static::$_emittedAssets += $pending;
            $output .= "\n" . implode("\n", array_map(
                fn($url) => '<script type="module" src="' . htmlspecialchars($url, ENT_QUOTES) . '"></script>',
                array_keys($pending)
            ));
        }

        return $output;
    }

/**
 * Render the cell wrapped in a custom element tag.
 *
 * Optionally uses Declarative Shadow DOM so the server-rendered HTML is
 * adopted by the browser without JavaScript, enabling SSR-compatible web
 * components with no layout shift.
 *
 * Example (without shadow DOM):
 *   echo $cell->renderAsComponent('weather-widget');
 *   // <weather-widget data-city="Porto">...</weather-widget>
 *
 * Example (with Declarative Shadow DOM):
 *   echo $cell->renderAsComponent('weather-widget', [], true);
 *   // <weather-widget data-city="Porto">
 *   //   <template shadowrootmode="open">...</template>
 *   // </weather-widget>
 *
 * @param string $tagName Custom element tag name (must contain a hyphen per spec).
 * @param array $extraAttrs Additional HTML attributes to set on the element.
 * @param bool $shadow Wrap content in a declarative shadow root.
 * @return string
 */
    public function renderAsComponent(string $tagName, array $extraAttrs = [], bool $shadow = false): string {
        $content = $this->render();
        $attrs = $this->_serializeAttrs(array_merge($this->_viewVars, $extraAttrs));
        $attrStr = $attrs ? ' ' . $attrs : '';

        if ($shadow) {
            return "<{$tagName}{$attrStr}>"
                . '<template shadowrootmode="open">' . $content . '</template>'
                . "</{$tagName}>";
        }

        return "<{$tagName}{$attrStr}>{$content}</{$tagName}>";
    }

/**
 * Render content into a named slot for use inside a parent web component.
 *
 * Example:
 *   echo $cell->renderSlot('header', '<h1>Hello</h1>');
 *   // <div slot="header"><h1>Hello</h1></div>
 *
 * @param string $slotName The slot name declared in the parent component.
 * @param string $content HTML content to place in the slot.
 * @return string
 */
    public function renderSlot(string $slotName, string $content): string {
        return '<div slot="' . htmlspecialchars($slotName, ENT_QUOTES) . '">' . $content . '</div>';
    }

/**
 * Declare a JS module asset required by this cell.
 *
 * The asset will be injected as a <script type="module"> tag into the cell's
 * rendered output the first time it is encountered; subsequent cells declaring
 * the same URL are silently skipped.
 *
 * @param string $moduleUrl Path or URL to the ES module.
 * @return $this
 */
    public function requiresAsset(string $moduleUrl): static {
        static::$_assets[$moduleUrl] = true;
        return $this;
    }

/**
 * Serialize an associative array to HTML data-* attribute strings.
 *
 * Scalar values become data-{key}="value".
 * Arrays/objects are JSON-encoded into data-{key}='...'.
 * Keys are dasherized (camelCase → camel-case).
 *
 * @param array $vars
 * @return string
 */
    protected function _serializeAttrs(array $vars): string {
        $parts = [];
        foreach ($vars as $key => $value) {
            $attr = 'data-' . Inflector::dasherize(Inflector::underscore($key));
            if (is_scalar($value) || $value === null) {
                $parts[] = $attr . '="' . htmlspecialchars((string)$value, ENT_QUOTES) . '"';
            } else {
                $parts[] = $attr . "='" . htmlspecialchars(json_encode($value, JSON_UNESCAPED_UNICODE), ENT_QUOTES) . "'";
            }
        }
        return implode(' ', $parts);
    }

/**
 * Magic method.
 *
 * Starts the rendering process when Cell is echoed.
 *
 * @return string Rendered widget
 */
    public function __toString() {
        try {
            return $this->render();
        } catch (Throwable $e) {
            trigger_error(sprintf(
                'Could not render cell - %s [%s, line %d]',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ), E_USER_WARNING);
            return '';
        }
    }

/**
 * Debug info.
 *
 * @return array
 */
    public function __debugInfo() {
        return [
            'plugin' => $this->plugin,
            'action' => $this->action,
            'args' => $this->args,
            'assets' => $this->_assets,
            'request' => $this->request,
            'response' => $this->response
        ];
    }
}
