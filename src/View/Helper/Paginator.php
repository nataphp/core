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

use Nata\Filesystem\File;
use Nata\Filesystem\FileRepository;
use Nata\View\View;
use Nata\View\Helper;
use Nata\Utility\Html;
use Nata\Utility\Text;
use Nata\Routing\Router;

/**
 * Pagination Helper.
 */
class Paginator extends Helper {

/**
 * Paging parameters.
 *
 * @var array
 */
    protected $_paging;

/**
 * Default configuration.
 *
 * @var array
 */
    protected $_defaultConfig = [
        'template' => null,
        'templates' => [
            'default' => [
                'prevDisabled' => '<li class="disabled"><span><span aria-hidden="true">{text}</span></span></li>',
                'prevEnabled' => '<li><a href="{url}"><span aria-hidden="true">{text}</span></span></li>',
                'nextDisabled' => '<li class="disabled"><span><span aria-hidden="true">{text}</span></span></li>',
                'nextEnabled' => '<li><a href="{url}"><span aria-hidden="true">{text}</span></span></li>',
                'current' => '<li class="active"><span>{index} <span class="sr-only">(current)</span></span></li>',
                'number' => '<li><a href="{url}">{index}</a></li>',
                'ellipsis' => '<li class="ellipsis">...</li>',
            ]
        ]
    ];

/**
 * Default parameters.
 *
 * @var array
 */
    protected $_defaultParams = [
        'align' => null,
        'pagination' => null,
        'type' => null, // deprecated
        'prev' => null,
        'next' => null,
        'truncate' => 10,
        'template' => null,
        'class' => null,
        'nav_class' => null,
        'ajax' => false,
        'autoload' => false,
        'container' => null,
        'scroll_container' => null,
        'table' => null,
        'attrs' => [],
        'title' => '',
        'key' => '',
        'direction' => null,
        'direction_default' => 'asc',
        'sort_multiple' => false,
        'full' => false,
        'htmx' => false,
        'hide_active' => false,
        'button_prev_text' => null,
        'button_next_text' => null,
        'sort_icon' => null,
        'sort_icon_asc' => null,
        'sort_icon_desc' => null,
        'sort_append_icon' => null,
        'sort_prepend_icon' => null,
        'htmx' => false,
        'htmx_id' => null,
        'htmx_trigger' => null,
    ];

/**
 * Initialize.
 *
 * @param array $config Configuration parameters
 * @return void
 */
    public function initialize(array $config) {}

/**
 * Get filter <a> parameters.
 *
 * @param array $params Smarty params
 * @return array Parameters
 */
    public function filter($params) {
        return;
    }

/**
 * Get sort link.
 *
 * @param array $params Smarty params
 * @return string Sort link <a>
 */
    public function sortUrl($params) {
        $params = $this->_normalizeParams($params);
        $paging = $this->_paging($params['table']);

        $sortState = $this->_getSortState($params, $paging);
        return $this->_getSortUrl($params, $sortState);
    }

/**
 * Get sort link.
 *
 * @param array $params Smarty params
 * @return string Sort link <a>
 */
    public function sort($params) {
        $params = $this->_normalizeParams($params);
        $paging = $this->_paging($params['table']);

        $sortState = $this->_getSortState($params, $paging);
        $title = $this->_getSortTitle($params, $sortState);
        $url = $this->_getSortUrl($params, $sortState);
        $attrs = $this->_getSortLinkAttrs($params, $sortState['key']);

        $currentDirection = $this->_getCurrentSortDirection($sortState['key']);
        if ($this->_isSortActive($sortState['key']) && $currentDirection == $params['direction'] && $params['hide_active']) {
            return;
        }

        $link = Html::elem('<a>', $title, [
            'href' => $url
        ] + $attrs);

        return $this->_getSortTemplate($params, $link);
    }

/**
 * Get link to remove given key from sort.
 *
 * @param array $params Smarty params
 * @return string Sort link <a>
 */
    public function removeSort($params) {
        $params = $this->_normalizeParams($params);
        $paging = $this->_paging($params['table']);

        $sortState = $this->_getSortState($params, $paging);
        if (!$this->_isSortActive($sortState['key'])) {
            //return;
        }

        $title = $this->_getSortTitle($params, $sortState);
        $url = $this->_getRemovedSortUrl($params, $sortState);
        $attrs = $this->_getSortLinkAttrs($params);

        $request = $this->_view->request;
        if (!$request->query('sort') && $params['hide_active']) {
            return;
        }

        $link = Html::elem('<a>', $title, [
            'href' => $url
        ] + $attrs);

        return $this->_getSortTemplate($params, $link);
    }

/**
 * Get sorting URI.
 *
 * @param array $params Smarty params
 * @return string Sort URL
 */
    protected function _getRemovedSortUrl($sortState) {
        $request = $this->_view->request;
        $newQuery = $request->query();
        $currentSort = $request->query('sort');

        extract($sortState);

        if (isset($newQuery['sort'][$key])) {
            unset($newQuery['sort'][$key]);
        } elseif ($currentSort === $key) {
            unset($newQuery['sort'], $newQuery['direction']);
        }

        return $this->_getUrl([], false);
    }

/**
 * Get sorting URI.
 *
 * @param array $params Smarty params
 * @return string Sort URL
 */
    protected function _getSortTemplate($params, $link) {
        if (empty($link)) {
            return $link;
        }

        $template = $link;
        if (str_contains($params['template'], '{link}')) {
            $template = Text::insert($params['template'], ['link' => $link], [
                'before' => '{',
                'after' => '}'
            ]);
        }
        return $template;
    }

/**
 * Get sorting URI.
 *
 * @param array $params Smarty params
 * @return string Sort URL
 */
    protected function _getSortUrl($params, $sortState) {
        if ($params['sort_multiple']) {
            $newQuery = $this->_getSortMultipleQueryParams($sortState);
        } else {
            $newQuery = $this->_getSortQueryParams($sortState);
        }

        return $this->_getUrl($newQuery, false);
    }

/**
 * Get sort URI query parameters for non multiple.
 *
 * @param array $sortState Smarty params
 * @return array URI sort query params
 */
    protected function _getSortQueryParams(array $sortState): array {
        if (empty($sortState['key'])) {
            return [];
        }

        $request = $this->_view->request;
        $newQuery = $request->query();

        $newQuery['sort'] = $sortState['key'];
        $newQuery['direction'] = $sortState['direction'];

        return $newQuery;
    }

/**
 * Get sort URI query parameters for multiple sort keys.
 *
 * @param array $sortState Smarty params
 * @return array URI sort query params
 */
    protected function _getSortMultipleQueryParams(array $sortState): array {
        $request = $this->_view->request;
        $newQuery = $request->query();
        $currentSort = $request->query('sort');

        extract($sortState);

        if (is_array($currentSort)) {
            $newQuery['sort'][$key] = $direction;
        } elseif ($currentSort !== null && $currentSort !== $key) {
            $newQuery['sort'] = [
                $currentSort => $request->query('direction'),
                $key => $direction
            ];
        } else {
            $newQuery = $this->_getSortQueryParams($sortState);
        }

        return $newQuery;
    }

/**
 * Get new link's expected sort state for given key's by checking from URL query parameters.
 *
 * @param array $params Smarty params
 * @param array $paging Paging
 * @return array Sort state
 */
    protected function _getSortState(array $params, array $paging): array {
        $request = $this->_view->request;
        $key = $params['key'];
        $direction = $params['direction'] ? $params['direction'] : $params['direction_default'];
        $currentDirection = null;
        $keyAliased = $key;
        if (strpos($key, '.') === false) {
            $keyAliased = $paging['_model'] . '.' . $key;
        }

        $querySort = $request->query('sort');
        if (isset($querySort[$key])) {
            $currentDirection = $querySort[$key];
        } elseif (isset($querySort[$keyAliased])) {
            $key = $keyAliased;
            $currentDirection = $querySort[$keyAliased];
        } elseif ($querySort === $key) {
            $currentDirection = $request->query('direction');
        } elseif ($querySort === $keyAliased) {
            $key = $keyAliased;
            $currentDirection = $request->query('direction');
        }

        if ($currentDirection && $params['direction'] === null) {
            $direction = $this->_getReverseSortDirection($currentDirection);
        }

        return [
            'key' => $key,
            'direction' => $direction
        ];
    }

/**
 * Get <a> sorting title.
 * If set, append/prepend icon to given link title.
 *
 * @param array $params Smarty params
 * @param array $sortState New sort state
 * @return string Title
 */
    protected function _getSortTitle($params, $sortState) {
        $title = $params['title'];
        $key = $sortState['key'];
        $direction = $this->_getCurrentSortDirection($key);

        if ($params['sort_icon'] !== false && $params['sort_append_icon'] === null && $params['sort_prepend_icon'] === null) {
            $params['sort_append_icon'] = true;
        }

        if ($params['sort_icon']) {
            $icon = $params['sort_icon'];
        } else {
            $icon = 'sort_icon';
            if ($direction) {
                $icon .= '_' . strtolower($direction);
            }
            $icon = $params[$icon];
        }

        $icon = $this->_getSortIconByType($icon);

        if ($params['sort_append_icon']) {
            $title .= ' ' . $icon;
        } elseif ($params['sort_prepend_icon']) {
            $title = $icon . ' ' . $title;
        }

        return $title;
    }

/**
 * Get sorting <a> attributes.
 *
 * @param array $params Smarty params
 * @return array Sort link attributes
 */
    protected function _getSortIconByType($icon) {
        if ($icon instanceof File || strpos($icon, '/') !== false || strpos($icon, '.') !== false) {
            $icon = $this->_getImageIcon($icon);
        } elseif (is_string($icon) && strpos($icon, '-')) {
            $icon = $this->_getClassIcon($icon);
        }
        return $icon;
    }

/**
 * Get sorting <a> attributes.
 *
 * @param array $params Smarty params
 * @return array Sort link attributes
 */
    protected function _getImageIcon($icon) {
        if ($icon instanceof File) {
            $icon = FileRepository::url($icon);
        }

        return Html::elem('<img>', false, [
            'src' => Router::url($icon)
        ]);
    }

/**
 * Get sorting <a> attributes.
 *
 * @param array $params Smarty params
 * @return array Sort link attributes
 */
    protected function _getClassIcon($icon) {
        return Html::elem('<i>', null, [
            'class' => $icon
        ]);
    }

/**
 * Get sorting <a> attributes.
 *
 * @param array $params Smarty params
 * @param string $sortKey Sort key
 * @return array Sort link attributes
 */
    protected function _getSortLinkAttrs($params, $sortKey = null) {
        $attrs = $params['attrs'];

        if ($params['class']) {
            $attrs['class'] = $params['class'];
        }

        if (!isset($attrs['data'])) {
            $attrs['data']['key'] = $params['key'];
        }

        if ($sortKey && $this->_isSortActive($sortKey)) {
            if (!isset($attrs['class'])) {
                $attrs['class'] = '';
            }

            $attrs['class'] = (!empty($attrs['class']) ? ' ' : '') . 'active';
        }

        return $attrs;
    }

/**
 * Toggle sorting direction.
 *
 * @param string $direction Direction
 * @return string Toggled direction
 */
    protected function _getReverseSortDirection($direction) {
        return strtolower($direction) === 'asc' ? 'desc' : 'asc';
    }

/**
 * Get given key's current direction.
 *
 * @param string $key Sorting key
 * @return bool True if active, false otherwise
 */
    protected function _getCurrentSortDirection($key): ?string {
        $request = $this->_view->request;
        $sort = $request->query('sort');

        if ($sort === $key) {
            return $request->query('direction');
        } elseif ($sort && isset($sort[$key])) {
            return $sort[$key];
        }

        return null;
    }

/**
 * Check if given sort key is currently active.
 *
 * @param string $key Sorting key
 * @return bool True if active, false otherwise
 */
    protected function _isSortActive($key): bool {
        $request = $this->_view->request;
        $sort = $request->query('sort');
        return $sort === $key || ($sort && isset($sort[$key]));
    }

/**
 * Get paging parameters.
 *
 * @param array $params Smarty params
 * @return array Parameters
 */
    public function get($params) {
        return $this->_paging($params);
    }

/**
 * Get URL for a given page number.
 *
 * @param array $params Smarty params — expects 'page' key
 * @return string URL for the requested page
 */
    public function url($params) {
        return $this->_getPaginatorUrl($params['page']);
    }

/**
 * Render requested pagination type.
 *
 * @param array $params Smarty params
 * @return string HTML string
 */
    public function render($params) {
        $params = $this->_normalizeParams($params);
        $paging = $this->_paging($params['table']);

        if (empty($paging)) {
            return '';
        }

        if ($params['template'] && !is_array($params['template'])) {
            $View = new View;
            $View->set($params);
            $View->set('paging', $paging);
            return $View->render($params['template']);
        }

        return $this->_render($params, $paging);
    }

/**
 * Render requested pagination type.
 *
 * @param array $params Smarty params
 * @param array $paging Pagination parameters
 * @return string HTML string
 */
    private function _render($params, $paging) {
        extract($params);
        $html = '';

        if ($paging['pageCount'] < 2) {
            return $html;
        }

        if ($type === 'pager') {
            $pagination = $this->_pager($params, $paging);
        } elseif ($type === 'inline') {
            if (!empty($htmx)) {
                return $this->_htmxInline($params, $paging);
            }
            return $this->_inline($params, $paging);
        } else {
            $pagination = $this->_pagination($params, $paging);
        }

        $html .= Html::elem('<ul>', $pagination, array(
            'class' => 'pagination'
        ) + $attrs);

        return $this->_navWrapper($html, $params, $paging);
    }

/**
 * Render pagination.
 *
 * @param array $params Smarty params
 * @param array $paging Pagination parameters
 * @return string HTML string
 */
    private function _pagination($params, $paging) {
        $pageCount = $paging['pageCount'];
        $start = max($paging['page'] - intval($params['truncate'] / 2), 1);
        $end = $start + $params['truncate'] - 1;

        // Previous Button
        $list = $this->_prevButton($params, $paging);

        if ($start > 1) {
            $index = 1;
            $list .= $this->_numberButton($index, $paging);
            $list .= $this->_disabledNumberButton('...', $paging);
        }

        for ($index = $start; $index <= $end && $index <= $pageCount; $index++) {
            $list .= $this->_numberButton($index, $paging);
        }

        if ($pageCount > $end) {
            $index = $pageCount;
            $list .= $this->_disabledNumberButton('...', $paging);
            $list .= $this->_numberButton($index, $paging);
        }

        // Next Button
        $list .= $this->_nextButton($params, $paging);

        return $list;
    }

/**
 * Render the page number button.
 *
 * @param int $number Page number
 * @param array $paging Pagination parameters
 * @return string HTML string
 */
    private function _numberButton($number, $paging) {
        $attrs = array();
        $elem = '';
        if ($paging['page'] == $number) {
            $attrs['class'] = 'active';
            $elem = Html::elem('<span>', $number . ' <span class="sr-only">(current)</span>');
        } else {
            $elem = Html::elem('<a>', $number, array(
                'href' => $this->_getPaginatorUrl($number, $paging)
            ));
        }
        return Html::elem('<li>', $elem, $attrs);
    }

/**
 * Render disabled number button in pagination.
 *
 * @param int $number Page number
 * @param array $paging Pagination parameters
 * @return string HTML string
 */
    private function _disabledNumberButton($number, $paging) {
        $span = Html::elem('<span>', $number, array(
            'aria-hidden' => 'true'
        ));
        return Html::elem('<li>', $span, array(
            'class' => 'disabled'
        ));
    }

/**
 * Render simple/pager pagination simple.
 *
 * @param array $params Smarty params
 * @param array $paging Pagination parameters
 * @return string HTML string
 */
    private function _pager($params, $paging) {
        $list = $this->_prevButton($params, $paging);
        $list .= $this->_nextButton($params, $paging);
        return $list;
    }

/**
 * Render 'previous' button.
 *
 * @param array $params Smarty params
 * @param array $paging Pagination parameters
 * @return string HTML string
 */
    private function _prevButton($params, $paging) {
        $attrs = array();

        $span = Html::elem('<span>', '<i class="fa fa-chevron-left"></i> ' . $params['prev'], array(
            'aria-hidden' => 'true'
        ));

        $a = Html::elem('<a>', $span, array(
            'href' => $this->_getPaginatorUrl($paging['page'] - 1, $paging),
            'alia-label' => $params['prev']
        ));

        if (!$paging['prevPage']) {
            $attrs['class'] = 'disabled';
            $a = $span;
        }

        return Html::elem('<li>', $a, $attrs);
    }

/**
 * Render 'next' button.
 *
 * @param array $params Smarty params
 * @param array $paging Pagination parameters
 * @return string HTML string
 */
    private function _nextButton($params, $paging) {
        $attrs = [];

        $span = Html::elem('<span>', $params['next'] . ' <i class="fa fa-chevron-right"></i>', [
            'aria-hidden' => 'true'
        ]);

        $a = Html::elem('<a>', $span, [
            'href' => $this->_getPaginatorUrl($paging['page'] + 1, $paging),
            'alia-label' => $params['next']
        ]);

        if (!$paging['nextPage']) {
            $attrs['class'] = 'disabled';
            $a = $span;
        }

        return Html::elem('<li>', $a, $attrs);
    }

/**
 * Render simple/pager pagination simple.
 *
 * @param array $params Smarty params
 * @param array $paging Pagination parameters
 * @return string HTML string
 */
    private function _inline($params, $paging) {
        $nextNumber = $paging['page'] + 1;

        $nextText = $this->_inlineButtonNextText($params);

        $text = Text::insert($nextText, [
            'prev' => ($paging['page'] - 1),
            'next' => $nextNumber,
            'total' => $paging['pageCount']
        ], [
            'before' => '{',
            'after' => '}',
        ]);

        $a = Html::elem('<a>', $text, array(
            'href' => $this->_getPaginatorUrl($nextNumber, $paging),
            'class' => ($params['class'] ? $params['class'] : 'btn btn-primary btn-sm'),
            'data-loading-text' => $params['loadingText'],
            'data-btn-next-text' => $nextText,
        ));

        return $this->_navWrapper($a, $params, $paging);
    }

/**
 * Render inline HTMX load-more button.
 *
 * Outputs a self-replacing wrapper div with hx-* attributes. When triggered
 * (default: intersect once), HTMX fetches the next page URL and replaces this
 * element with the server response — which should contain the new cards via
 * hx-swap-oob and a new instance of this button for the following page.
 *
 * @param array $params Smarty params
 * @param array $paging Pagination parameters
 * @return string HTML string
 */
    private function _htmxInline($params, $paging) {
        $nextNumber = $paging['page'] + 1;
        $nextUrl = $this->_getPaginatorUrl($nextNumber);
        $id = $params['htmx_id'] ?? 'paginator-' . ($paging['_model'] ?? 'page');
        $container = $params['container'];

        if (!$paging['nextPage']) {
            return '';
        }

        // Default trigger is click, but if autoload is true, use intersect once
        $trigger = $params['htmx_trigger'] ?: 'click';
        if ($params['autoload']) {
            $trigger = 'intersect once';
        }

        $text = Text::insert($this->_inlineButtonNextText($params), [
            'prev' => $paging['page'] - 1,
            'next' => $nextNumber,
            'total' => $paging['pageCount'],
        ], ['before' => '{', 'after' => '}']);

        $buttonClass = $params['class'] ?: 'btn btn-primary';
        $button = Html::elem('<button>', $text, ['class' => $buttonClass, 'type' => 'button']);

        $wrapperClass = 'paginator paginator-htmx' . $this->_alignment($params) . ($params['nav_class'] ? ' ' . $params['nav_class'] : '');

        if ($container) {
            $target = $container;
            $swap = 'beforeend';
        } else {
            $target = '#' . $id;
            $swap = 'outerHTML';
        }

        return Html::elem('<div>', $button, [
            'id' => $id,
            'hx-get' => $nextUrl,
            'hx-trigger' => $trigger,
            'hx-target' => $target,
            'hx-swap' => $swap,
            'hx-on::before-request' => 'this.remove()',
            'class' => $wrapperClass
        ]);
    }

/**
 * Get inline button next text.
 *
 * @param array $params Smarty params
 * @return string Button text
 */
    private function _inlineButtonNextText($params) {
        return $params['button_next_text'] ? $params['button_next_text'] : __('Page {next} of {total}');
    }

/**
 * Render <nav> wrapper.
 *
 * @param string $html HTML content
 * @param array $params Smarty params
 * @param array $paging Pagination parameters
 * @return string HTML string
 */
    private function _navWrapper($html, $params, $paging) {
        $class = 'paginator';
        if ($params['ajax']) {
            $class .= ' paginator-ajax';
        }
        if ($params['pagination']) {
            $class .= ' paginator-' . $params['pagination'];
        }
        $class .= $this->_alignment($params);
        $clearfix = Html::elem('<div>', '', ['class' => 'clearfix']);
        return $clearfix . Html::elem('<nav>', $html, [
            'class' => $class,
            'aria-label' => 'Page navigation',
            'data' => [
                'pagination' => $params['pagination'],
                'container' => $params['container'],
                'scroll_container' => $params['scroll_container'],
                'page-count' => $paging['pageCount'],
                'autoload' => $params['autoload'],
                'model' => $paging['_model']
            ]
        ]);
    }

/**
 * Pagination align.
 *
 * @param array $params Smarty params
 * @return string CSS class name
 */
    private function _alignment($params) {
        $align = 'text-';

        if (!$params['pagination']) {
            $align = 'text-';
        }

        if (!$params['align']) {
            switch ($params['pagination']) {
                case 'inline':
                    $align .= 'center';
                    break;
                case 'pager':
                    $align .= 'center';
                    break;
                default:
                    $align .= 'right';
                    break;
            }

        } else {
            $align .= $params['align'];
        }

        return ' ' . $align;
    }

/**
 * Get paginator URL with given page number.
 *
 * @param int $number Page number
 * @return string Current Url with given page number
 */
    protected function _getPaginatorUrl($number) {
        $request = $this->_view->request;
        $page = ['page' => $number];
        if (count($request->paging) > 1) {
            $page = ['page' => [$request->paging['_model'] => $number]];
        }
        return $this->_getUrl($page);
    }

/**
 * Get URL with given parameters.
 *
 * @param array $queryParams URI query parameters
 * @param bool $append Append given parameters to existing ones
 * @return string Current Url with given query parameters
 */
    protected function _getUrl(array $queryParams, bool $append = true) {
        $request = Router::getRequest(true);
        [$base] = explode('?', $request->here);

        if ($append === true) {
            $queryParams += $request->query();
        }

        $query = http_build_query($queryParams);
        if (!$query) {
            return $base;
        }
        return $base . '?' . $query;
    }

/**
 * Smarty parameters normalized.
 *
 * @param array $params Smarty params
 * @return array Parameters
 */
    protected function _normalizeParams(array $params) {
        $params = parent::_normalizeParams($params);
        $params = $params + $this->_config;

        if (!empty($params['type'])) {
            $params['pagination'] = $params['type'];
        }

        if (!$params['prev']) {
            $params['prev'] = __('Previous');
        }

        if (!$params['next']) {
            $params['next'] = __('Next');
        }

        if (!$params['ajax'] && $params['pagination'] === 'inline') {
            $params['ajax'] = true;
        }

        if (empty($params['loadingText'])) {
            $params['loadingText'] = '<i class="fa fa-spinner fa-spin"></i> ' . __('Loading page...');
        }

        return $params + $this->_config;
    }

/**
 * Getting paging parameters for given model name.
 *
 * If empty model name is given and there's only one,
 * will return that pagination parameters, otherwise, will use
 * controller name in request as possible candidate.
 *
 * @param atring $model Model name
 * @return array Pagination
 */
    private function _paging($model) {
        $paging = array();

        if ($this->_paging === null) {
            $this->_paging = $this->_view->request->paging;
        }

        if (empty($model)) {
            if (!empty($this->_paging)) {
                list($paging) = array_values($this->_paging);
                list($paging['_model']) = array_keys($this->_paging);
            }
        } elseif (isset($this->_paging[$model])) {
            $paging = $this->_paging[$model];
            $paging['_model'] = $model;
        }

        return $paging;
    }

}
