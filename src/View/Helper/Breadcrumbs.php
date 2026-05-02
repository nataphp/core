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

namespace Nata\View\Helper;

use LogicException;
use Nata\View\Helper;
use Nata\Routing\Router;
use Nata\Utility\Html;

/**
 * Create/render breadcrumbs.
 */
class Breadcrumbs extends Helper {

/**
 * Default config for the helper.
 *
 * Templates not being taken into account when rendering
 * at the moment.
 *
 * @var array
 */
    protected $_defaultConfig = [
        'templates' => [
            'wrapper' => '<ol{{attrs}}>{{content}}</ol>',
            'item' => '<li{{attrs}}>{{inner}}</li>',
            'link' => '<a href="{{url}}"{{innerAttrs}}>{{title}}</a>',
            'current' => '<span{{innerAttrs}}>{{title}}</span>',
            'separator' => '<span{{innerAttrs}}>{{separator}}</span>'
        ],
        'separator' => ' > ',
        'currentAsLink' => false
    ];

/**
 * Default parameters.
 *
 * @var array
 */
    protected $_defaultParams = [
        'reset' => false,
        'add' => [],
        'prepend' => [],
        'append' => []
    ];

/**
 * Default crumb parameters.
 *
 * @var array
 */
    protected $_defaultCrumb = [
        'title' => '', 'url' => null, 'options' => []
    ];

/**
 * List of crumbs.
 *
 * @var array
 */
    protected $crumbs = [];


/**
 * Pseudo-constructor.
 *
 * @param array $config Configuration parameters
 * @return void
 */
    public function initialize(array $config) {}

/**
 * Add a crumb to the end of the trail.
 *
 * @param string|array $title If provided as a string, it represents the title of the crumb.
 * Alternatively, if you want to add multiple crumbs at once, you can provide an array, with each values being a
 * single crumb. Arrays are expected to be of this form:
 * - *title* The title of the crumb
 * - *link* The link of the crumb. If not provided, no link will be made
 * - *options* Options of the crumb. See description of params option of this method.
 * @param string|array|null $url URL of the crumb. Either a string, an array of route params to pass to
 * Url::build() or null / empty if the crumb does not have a link.
 * @param array $options Array of options. These options will be used as attributes HTML attribute the crumb will
 * be rendered in (a <li> tag by default). It accepts two special keys:
 * - *innerAttrs*: An array that allows you to define attributes for the inner element of the crumb (by default, to
 * the link)
 * - *templateVars*: Specific template vars in case you override the templates provided.
 * @return $this
 */
    public function add($title, $url = null, array $options = []) {
        if (is_array($title)) {
            foreach ($title as $index => $crumb) {
                $this->crumbs[] = $this->_normalizeCrumb($index, $crumb);
            }

            return $this;
        }

        $this->crumbs[] = compact('title', 'url', 'options');

        return $this;
    }

/**
 * Prepend a crumb to the start of the queue.
 *
 * @param string $title If provided as a string, it represents the title of the crumb.
 * Alternatively, if you want to add multiple crumbs at once, you can provide an array, with each values being a
 * single crumb. Arrays are expected to be of this form:
 * - *title* The title of the crumb
 * - *link* The link of the crumb. If not provided, no link will be made
 * - *options* Options of the crumb. See description of params option of this method.
 * @param string|array|null $url URL of the crumb. Either a string, an array of route params to pass to
 * Url::build() or null / empty if the crumb does not have a link.
 * @param array $options Array of options. These options will be used as attributes HTML attribute the crumb will
 * be rendered in (a <li> tag by default). It accepts two special keys:
 * - *innerAttrs*: An array that allows you to define attributes for the inner element of the crumb (by default, to
 * the link)
 * - *templateVars*: Specific template vars in case you override the templates provided.
 * @return $this
 */
    public function prepend($title, $url = null, array $options = []) {
        if (is_array($title)) {
            $crumbs = [];

            foreach ($title as $index => $crumb) {
                $crumbs[] = $this->_normalizeCrumb($index, $crumb);
            }

            array_splice($this->crumbs, 0, 0, $crumbs);

            return $this;
        }

        array_unshift($this->crumbs, compact('title', 'url', 'options'));

        return $this;
    }

/**
 * Normalize crumb array.
 *
 * @param int|string $index Crumb index/title
 * @param string|array $crumb Crumb title/url or crumb array
 * @return array Normalized crumb
 */
    protected function _normalizeCrumb($index, $crumb) {
        if (is_string($index) && is_string($crumb)) {
            $crumb = [
                'title' => $index,
                'url' => $crumb
            ];
        } elseif (is_int($index) && !is_array($crumb)) {
            $crumb = [
                'title' => $crumb
            ];
        }

        return $crumb + $this->_defaultCrumb;
    }

/**
 * Insert a crumb at a specific index.
 *
 * If the index already exists, the new crumb will be inserted,
 * and the existing element will be shifted one index greater.
 * If the index is out of bounds, it will throw an exception.
 *
 * @param int $index The index to insert at.
 * @param string $title Title of the crumb.
 * @param string|array|null $url URL of the crumb. Either a string, an array of route params to pass to
 * Url::build() or null / empty if the crumb does not have a link.
 * @param array $options Array of options. These options will be used as attributes HTML attribute the crumb will
 * be rendered in (a <li> tag by default). It accepts two special keys:
 * - *innerAttrs*: An array that allows you to define attributes for the inner element of the crumb (by default, to
 * the link)
 * - *templateVars*: Specific template vars in case you override the templates provided.
 * @return $this
 * @throws \LogicException In case the index is out of bound
 */
    public function insertAt($index, $title, $url = null, array $options = []) {
        if (!isset($this->crumbs[$index])) {
            throw new LogicException(sprintf("No crumb could be found at index '%s'", $index));
        }

        array_splice($this->crumbs, $index, 0, [compact('title', 'url', 'options')]);

        return $this;
    }

/**
 * Insert a crumb before the first matching crumb with the specified title.
 *
 * Finds the index of the first crumb that matches the provided class,
 * and inserts the supplied callable before it.
 *
 * @param string $matchingTitle The title of the crumb you want to insert this one before.
 * @param string $title Title of the crumb.
 * @param string|array|null $url URL of the crumb. Either a string, an array of route params to pass to
 * Url::build() or null / empty if the crumb does not have a link.
 * @param array $options Array of options. These options will be used as attributes HTML attribute the crumb will
 * be rendered in (a <li> tag by default). It accepts two special keys:
 * - *innerAttrs*: An array that allows you to define attributes for the inner element of the crumb (by default, to
 * the link)
 * - *templateVars*: Specific template vars in case you override the templates provided.
 * @return $this
 * @throws \LogicException In case the matching crumb can not be found
 */
    public function insertBefore($matchingTitle, $title, $url = null, array $options = []) {
        $key = $this->findCrumb($matchingTitle);

        if ($key === null) {
            throw new LogicException(sprintf("No crumb matching '%s' could be found.", $matchingTitle));
        }

        return $this->insertAt($key, $title, $url, $options);
    }

/**
 * Insert a crumb after the first matching crumb with the specified title.
 *
 * Finds the index of the first crumb that matches the provided class,
 * and inserts the supplied callable before it.
 *
 * @param string $matchingTitle The title of the crumb you want to insert this one after.
 * @param string $title Title of the crumb.
 * @param string|array|null $url URL of the crumb. Either a string, an array of route params to pass to
 * Url::build() or null / empty if the crumb does not have a link.
 * @param array $options Array of options. These options will be used as attributes HTML attribute the crumb will
 * be rendered in (a <li> tag by default). It accepts two special keys:
 * - *innerAttrs*: An array that allows you to define attributes for the inner element of the crumb (by default, to
 * the link)
 * - *templateVars*: Specific template vars in case you override the templates provided.
 * @return $this
 * @throws \LogicException In case the matching crumb can not be found.
 */
    public function insertAfter($matchingTitle, $title, $url = null, array $options = []) {
        $key = $this->findCrumb($matchingTitle);

        if ($key === null) {
            throw new LogicException(sprintf("No crumb matching '%s' could be found.", $matchingTitle));
        }

        return $this->insertAt($key + 1, $title, $url, $options);
    }

/**
 * Returns the crumb list.
 *
 * @return array
 */
    public function getCrumbs() {
        return $this->crumbs;
    }

/**
 * Removes all existing crumbs.
 *
 * @return $this
 */
    public function reset() {
        $this->crumbs = [];

        return $this;
    }

/**
 * Search a crumb in the current stack which title matches the one provided as argument.
 * If found, the index of the matching crumb will be returned.
 *
 * @param string $title Title to find.
 * @return int|null Index of the crumb found, or null if it can not be found.
 */
    protected function findCrumb($title) {
        foreach ($this->crumbs as $key => $crumb) {
            if ($crumb['title'] === $title) {
                return $key;
            }
        }

        return null;
    }

/**
 * Render breadcrumb.
 *
 * @param array $params Smarty parameters
 * @return string Breadcrumb's HTML
 */
    public function render($params) {
        $render = '';

        $crumbs = $this->_get($params);
        if (empty($crumbs)) {
            return;
        }

        $currentAsLink = $this->config('currentAsLink');

        foreach ($crumbs as $crumb) {
            $crumb['options'] += [
                'attrs' => [],
                'innerAttrs' => []
            ];

            if (!$crumb['active'] || $currentAsLink) {
                $a = Html::elem('<a>', $crumb['title'], [
                    'href' => $crumb['url']
                ] + $crumb['options']['innerAttrs']);
            } else {
                $a = Html::elem('<span>', $crumb['title'], $crumb['options']['innerAttrs']);
            }

            $activeClass = ($crumb['active'] ? 'active' : '');
            if (isset($crumb['options']['attrs']['class'])) {
                $crumb['options']['attrs']['class'] .= ' ' . $activeClass;
            } else {
                $crumb['options']['attrs']['class'] = $activeClass;
            }

            $render .= Html::elem('<li>', $a, $crumb['options']['attrs']);
        }

        return Html::elem('<ol>', $render, [
            'class' => 'breadcrumb'
        ]);
    }

/**
 * Get array of crumbs from parsed values
 *
 * @param array $params Smarty parameters
 * @return array Crumbs array
 */
    protected function _get($params) {
        $params = $this->_normalizeParams($params);
        $separator = $this->config('separator');

        if ($params['reset']) {
            $this->reset();
        }

        if (!empty($params['add'])) {
            $this->add($params['add']);
        } elseif ($params['prepend']) {
            $this->prepend($params['prepend']);
        } elseif ($params['append']) {
            $this->append($params['append']);
        }

        $crumbs = [];

        foreach ($this->crumbs as $index => $crumb) {
            $crumbs[$index] = [
                'url' => Router::url($crumb['url']),
                'active' => $index == (count($this->crumbs) - 1)
            ] + $crumb;
        }

        return $crumbs;
    }

}
