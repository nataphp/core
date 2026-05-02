<?php
/**
 * NataPHP Framework
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

namespace Nata\Form\Element;

use Nata\Collection\Collection;
use Nata\Form\Element\Text;
use Nata\ORM\Entity;
use Nata\Routing\Router;
use Nata\Utility\Text as UtilityText;

/**
 * Autocomplete text element.
 * Supports most of the options in the Flexdatalist plugin.
 *
 * @see https://github.com/sergiodlopes/jquery-flexdatalist/
 */
class Autocomplete extends Text {

/**
 * Autocomplete URL to load data.
 *
 * @var string
 */
    protected $_url;

/**
 * Cache data between requests.
 *
 * @var bool
 */
    protected $_cache = true;

/**
 * Search contain.
 *
 * @var bool
 */
    protected $_searchContain = false;

/**
 * Search by word.
 *
 * @var bool
 */
    protected $_searchByWord = false;

/**
 * Request type.
 *
 * @var string
 */
    protected $_requestType = 'get';

/**
 * Minimum length from which it starts searching.
 *
 * @var int
 */
    protected $_searchMinLength = 3;

/**
 * No search results text.
 *
 * @var string
 */
    protected $_noResultsText;

/**
 * Maximum shown results.
 *
 * @var int
 */
    protected $_maxShownResults = 100;

/**
 * Value limit.
 *
 * @var int
 */
    protected $_limitOfValues = 0;

/**
 * Relative fields.
 *
 * @var string
 */
    protected $_relatives;

/**
 * Chained relative fields.
 *
 * @var string
 */
    protected $_chainedRelatives = false;

/**
 * Property to set in field on select.
 *
 * @var string
 */
    protected $_valueProperty;

/**
 * Name of the property to send its value.
 *
 * @var string
 */
    protected $_textProperty;

/**
 * List of properties that will be visible in the list.
 *
 * @var string|array
 */
    protected $_visibleProperties;

/**
 * Merge remote data with current data.
 *
 * @var bool
 */
    protected $_mergeRemoteData = false;

/**
 * Group by data property.
 *
 * @var string
 */
    protected $_groupBy;

/**
 * Search in properties.
 *
 * @var string|array
 */
    protected $_searchIn;

/**
 * Data to search in.
 *
 * @var array
 */
    protected $_searchData = [];

/**
 * Search disabled.
 *
 * @var array
 */
    protected $_searchDisabled = false;

/**
 * Selection from data list
 * is required to fill input field.
 *
 * @var bool
 */
    protected $_selectionRequired = false;

/**
 * Collapse after N items.
 *
 * @var int
 */
    protected $_collapseAfterN;

/**
 * Collapse values button text.
 *
 * @var string
 */
    protected $_collapsedValuesText;

/**
 * Focus first result.
 *
 * @var bool
 */
    protected $_focusFirstResult = false;

/**
 * Show add new item option.
 *
 * @var bool
 */
    protected $_showAddNewItem = false;

/**
 * Add new item text.
 *
 * @var string
 */
    protected $_addNewItemText;

/**
 * URL to open a quick-create dialog when "add new" is clicked.
 *
 * @var string
 */
    protected $_addNewUrl;

/**
 * Template.
 *
 * @var string
 */
    protected $_template = 'text';

/**
 * Per value validation.
 *
 * @var Closure
 */
    protected $_valueValidation;

/**
 * List of invalid values.
 *
 * @var array
 */
    protected $_valueError;


/**
 * Pseudo-constructor.
 *
 * @return void
 */
    public function initialize($config) {
        parent::initialize($config);

        $config += [
            'url' => null,
            'cache' => null,
            'searchData' => null,
            'searchContain' => null,
            'searchDisabled' => null,
            'searchMinLength' => null,
            'noResultsText' => null,
            'maxShownResults' => null,
            'relatives' => null,
            'chainedRelatives' => null,
            'valueProperty' => null,
            'textProperty' => null,
            'visibleProperties' => null,
            'collapseAfterN' => 10,
            'collapsedValuesText' => __('{count} More'),
            'groupBy' => null,
            'searchIn' => null,
            'selectionRequired' => null,
            'focusFirstResult' => null,
            'requestType' => null,
            'searchByWord' => null,
            'limitOfValues' => null,
            'valueValidation' => null,
            'showAddNewItem' => null,
            'addNewItemText' => null,
            'addNewUrl' => null,
        ];

        if ($config['url']) {
            $this->url($config['url']);
        }

        if ($config['cache']) {
            $this->_cache = $config['cache'];
        }

        if ($config['searchData']) {
            $this->_searchData = $config['searchData'];
        }

        if ($config['searchContain']) {
            $this->_searchContain = $config['searchContain'];
        }

        if ($config['searchDisabled'] !== null) {
            $this->_searchDisabled = $config['searchDisabled'];
        }

        if ($config['searchMinLength'] !== null) {
            $this->_searchMinLength = $config['searchMinLength'];
        }

        if ($config['searchByWord'] !== null) {
            $this->_searchByWord = $config['searchByWord'];
        }

        if ($config['collapseAfterN'] !== null) {
            $this->_collapseAfterN = $config['collapseAfterN'];
        }

        if ($config['collapsedValuesText'] !== null) {
            $this->_collapsedValuesText = $config['collapsedValuesText'];
        }

        if ($config['requestType']) {
            $this->_requestType = $config['requestType'];
        }

        if ($config['maxShownResults'] !== null) {
            $this->_maxShownResults = $config['maxShownResults'];
        }

        if ($config['relatives']) {
            $this->relatives($config['relatives']);
        }

        if ($config['chainedRelatives']) {
            $this->_chainedRelatives = $config['chainedRelatives'];
        }

        if ($config['valueProperty']) {
            $this->_valueProperty = $config['valueProperty'];
        }

        if ($config['textProperty']) {
            $this->_textProperty = $config['textProperty'];
        }

        if ($config['visibleProperties']) {
            $this->_visibleProperties = $config['visibleProperties'];
        }

        if ($config['groupBy']) {
            $this->_groupBy = $config['groupBy'];
        }

        if ($config['searchIn']) {
            $this->_searchIn = $config['searchIn'];
        }

        if ($config['selectionRequired']) {
            $this->_selectionRequired = $config['selectionRequired'];
        }

        if ($config['focusFirstResult']) {
            $this->_focusFirstResult = $config['focusFirstResult'];
        }

        if ($config['noResultsText'] !== null) {
            $this->_noResultsText = $config['noResultsText'];
        }

        if ($config['limitOfValues'] !== null) {
            $this->_limitOfValues = $config['limitOfValues'];
        }

        if ($config['valueValidation'] !== null) {
            $this->_valueValidation = $config['valueValidation'];
        }

        if ($config['showAddNewItem'] !== null) {
            $this->_showAddNewItem = $config['showAddNewItem'];
        }

        if ($config['addNewItemText'] !== null) {
            $this->_addNewItemText = $config['addNewItemText'];
        }

        if ($config['addNewUrl'] !== null) {
            $this->_addNewUrl = $config['addNewUrl'];
        }

        $this->template('text');
    }

/**
 * Get value output sanitized to be presented on HTML view/template.
 *
 * @param array $filter Filter options
 * @return string Value output sanitized
 */
    public function getValueOutput(array $filter = []) {
        $this->_value = $this->_normalizeData($this->value());
        return parent::_sanitizer($this->_value, $filter);
    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 * @return \Nata\Event\Event
 */
    public function beforeRender($event) {
        parent::beforeRender($event);

        $this->attributes()
            ->addClass('flexdatalist form-control')
            ->set('data', [
                'url' => $this->url(),
                'cache' => $this->_cache,
                'data' => json_encode($this->_searchData),
                'search-contain' => $this->_searchContain,
                'search-disabled' => $this->_searchDisabled,
                'min-length' => $this->_searchMinLength,
                'max-shown-results' => $this->_maxShownResults,
                'relatives' => $this->_relatives,
                'chainedRelatives' => $this->_chainedRelatives,
                'value-property' => $this->_valueProperty,
                'text-property' => $this->_textProperty,
                'visible-properties' => $this->_visibleProperties,
                'collapse-after-n' => $this->_collapseAfterN,
                'collapsed-values-text' => $this->_collapsedValuesText,
                'group-by' => $this->_groupBy,
                'search-in' => $this->_searchIn,
                'limit-of-values' => $this->_limitOfValues,
                'no-results-text' => $this->noResultsText(),
                'selection-required' => $this->_selectionRequired,
                'focus-first-result' => $this->_focusFirstResult,
                'search-by-word' => $this->_searchByWord,
                'request-type' => $this->_requestType,
                'show-add-new-item' => $this->_showAddNewItem,
                'add-new-item-text' => $this->addNewItemText(),
                'add-new-url' => $this->_addNewUrl,
            ]);
    }

/**
 * Validation.
 *
 * @param string $data Data to be validated
 * @return boolean True if valid, false otherwise
 */
    protected function _valid($data) {
        $isValid = true;
        $data = $this->_normalizeData($data, true);
        if (is_callable($this->_valueValidation) && !$this->_selectionRequired && $this->_multiple) {
            $callback = $this->_valueValidation;
            $this->_valueError = [];
            foreach ((array)$data as $position => $value) {
                $valid = $callback($value, $position, $this);
                if ($valid === true) {
                    continue;
                }

                $this->_valueError[$position] = [
                    'index' => $position,
                    'value' => $value,
                    'error' => is_string($valid) ? $valid : __('"%s" is invalid.', $value)
                ];
            }

            $isValid = empty($this->_valueError);
            if (!$isValid && !$this->_error)  {
                if (!$this->_error) {
                    $count = count($this->_valueError);
                    $valuelist = array_map(function ($error) {
                        $value = $error['value'];
                        if (is_array($value)) {
                            $value = implode(',', $value);
                        }
                        return '"' . $value . '"';
                    }, $this->_valueError);
                    $this->error(__n('%s is invalid.', '%s are invalid.', $count, UtilityText::toList($valuelist, __('and'))));
                }
            }
        }

        if ($isValid) {
            $this->data($data);
        }

        return $isValid;
    }

/**
 * Normalize data for autocomplete.
 *
 * @param string $data Data to be normalized
 * @param bool $submitted Submitted form
 * @return mixed
 */
    private function _normalizeData($data, $submitted = false) {
        if (!empty($data)) {
            if ($submitted) {
                if (is_string($data)) {
                    if ($this->_isJson()) {
                        return json_decode($data, true);
                    } else if ($this->_isCsv()) {
                        return explode(',', $data);
                    }
                }
            } else {
                $data = $this->_extractValuePropertiesFromData($data);
                if ($this->_valueProperty && !is_array($this->_valueProperty) && isset($data[$this->_valueProperty])) {
                    return $data[$this->_valueProperty];
                } elseif (is_array($data)) {
                    if ($this->_isJson()) {
                        return json_encode($data);
                    } else if ($this->_isCsv()) {
                        return implode(',', $data);
                    }
                }
            }
        }

        if (is_array($data) && empty($data)) {
            return '';
        }

        return $data;
    }

/**
 * Extract data respecting the value properties.
 *
 * @param mixed $data Data to extract
 * @return array|string Data
 */
    private function _extractValuePropertiesFromData($data) {
        if ($this->_valueProperty === '*' && is_array($data)) {
            return $data;
        }

        if (empty($data)) {
            return $data;
        }

        if ($data instanceof Collection) {
            $data = $data->toArray();
        }

        if (!is_array($data)) {
            $data = [$data];
        }

        // Reset indexes
        $data = array_values($data);

        if (!isset($data[0])) {
            $data = [$data];
        }

        if ($this->_valueProperty !== '*') {
            $valueProperties = (array)$this->_valueProperty;
            $singleProperty = count($valueProperties) === 1;
            foreach ($data as $index => $item) {
                $item = $this->_extractValuePropertiesFromItem($singleProperty, $valueProperties, $item);
                if (!$item) {
                    unset($data[$index]);
                    continue;
                }
                $data[$index] = $item;
            }
        }

        if (empty($data)) {
            return $data;
        }

        // Reset indexes
        $data = array_values($data);

        if (!$this->multiple()) {
            return $data[0];
        }

        return $data;
    }

/**
 * Extract item data respecting the required value properties.
 *
 * @param bool $singleProperty If it's single property
 * @param array $valueProperties Value properties
 * @param mixed $item Item
 * @return mixed Item
 */
    private function _extractValuePropertiesFromItem($singleProperty, $valueProperties, $item) {
        if ($singleProperty) {
            if (is_array($item) || $item instanceof Entity) {
                return $item[$valueProperties[0]] ?? null;
            }
            return $item;
        }

        $data = [];
        foreach ($valueProperties as $valueProperty) {
            if (!isset($item[$valueProperty])) {
                continue;
            }
            $data[$valueProperty] = $item[$valueProperty];
        }

        if (empty($data)) {
            return $item;
        }

        return $data;
    }

/**
 * Check if is expected JSON.
 *
 * @return boolean True if JSON, false otherwise
 */
    private function _isJson() {
        return ($this->_valueProperty === '*' || is_array($this->_valueProperty));
    }

/**
 * Validation.
 *
 * @param string $data Data to be validated
 * @return boolean True if valid, false otherwise
 */
    private function _isCsv() {
        return $this->_multiple && !$this->_isJson();
    }

/**
 * Get/set URL to retrieve results list.
 *
 * @param string $url Valid Url
 * @return $this|bool
 */
    public function url($url = null, $full = false) {
        if ($url === null) {
            return $this->_url;
        }
        $this->_url = Router::url($url, $full);
        return $this;
    }

/**
 * Get/set cache.
 *
 * @param bool $cache Data
 * @return $this|bool
 */
    public function cache($cache = null) {
        return $this->_property('_cache', $cache);
    }

/**
 * Get/set search data.
 *
 * @param array $searchData Data
 * @return $this|array
 */
    public function searchData($searchData = null) {
        return $this->_property('_searchData', $searchData);
    }

/**
 * Get/set search contain.
 *
 * @param bool $searchContain Search containing keyword
 * @return $this|bool
 */
    public function searchContain($searchContain = null) {
        return $this->_property('_searchContain', $searchContain);
    }

/**
 * Get/set minimum length from which starts searching.
 *
 * @param int $searchMinLength Minimum length to search
 * @return $this|int
 */
    public function searchMinLength($searchMinLength = null) {
        return $this->_property('_searchMinLength', $searchMinLength);
    }

/**
 * Get/set no search results text.
 *
 * @param string $noResultsText No search results text
 * @return $this|string
 */
    public function noResultsText($noResultsText = null) {
        if ($noResultsText === null && $this->_noResultsText === null) {
            $this->_noResultsText = __('No results found for "{keyword}"');
        }
        return $this->_property('_noResultsText', $noResultsText);
    }

/**
 * Get/set maximum number of shown results.
 *
 * @param int $maxShownResults Maximum shown results
 * @return $this|int
 */
    public function maxShownResults($maxShownResults = null) {
        return $this->_property('_maxShownResults', $maxShownResults);
    }

/**
 * Get/set relative fields.
 *
 * @param string $relatives Relative fields
 * @return $this|string
 */
    public function relatives($relatives = null) {
        if (!empty($relatives)) {
            if (is_array($relatives)) {
                $relatives = implode(',', $relatives);
            }
            if ($this->_groupRow) {
                $relatives = str_replace('{n}', $this->_groupRow->index(), $relatives);
            }
        }
        return $this->_property('_relatives', $relatives);
    }

/**
 * Get/set chain relative fields.
 *
 * @param bool $chainedRelatives Chained relatives
 * @return $this|bool
 */
    public function chainedRelatives($chainedRelatives = null) {
        return $this->_property('_chainedRelatives', $chainedRelatives);
    }

/**
 * Get/set the property name in data to set its value in field.
 *
 * @param string $valueProperty Property name
 * @return $this|string
 */
    public function valueProperty($valueProperty = null) {
        return $this->_property('_valueProperty', $valueProperty);
    }

/**
 * Get/set the property name in data to send its value.
 *
 * @param string $textProperty Property name
 * @return $this|string
 */
    public function textProperty($textProperty = null) {
        return $this->_property('_textProperty', $textProperty);
    }

/**
 * Get/set properties that will be visible.
 *
 * @param string|array $visibleProperties Visible properties
 * @return $this|string|array
 */
    public function visibleProperties($visibleProperties = null) {
        return $this->_property('_visibleProperties', $visibleProperties);
    }

/**
 * Get/set group by property value.
 *
 * @param string $groupBy Merge remote data
 * @return $this|string
 */
    public function groupBy($groupBy = null) {
        return $this->_property('_groupBy', $groupBy);
    }

/**
 * Get/set list of properties to search.
 *
 * @param string $searchIn Properties
 * @return $this|array
 */
    public function searchIn($searchIn = null) {
        if ($searchIn === null) {
            return $this->_searchIn;
        }
        $this->_searchIn = (array)$searchIn;
        return $this;
    }

/**
 * Get/set search by word setting.
 *
 * @param boolean $searchByWord Search by word
 * @return $this|boolean
 */
    public function searchByWord($searchByWord = null) {
        return $this->_property('_searchByWord', $searchByWord);
    }

/**
 * Get/set Type of request that is expected to be done.
 *
 * @param string $requestType Request type
 * @return $this|string
 */
    public function requestType($requestType = null) {
        return $this->_property('_requestType', $requestType);
    }

/**
 * Get/set require result selection to set value.
 *
 * @param bool $selectionRequired Selection requirement
 * @return $this|bool
 */
    public function selectionRequired($selectionRequired = null) {
        return $this->_property('_selectionRequired', $selectionRequired);
    }

/**
 * Get/set focus first result.
 *
 * @param bool $focusFirstResult Focus first result
 * @return $this|bool
 */
    public function focusFirstResult($focusFirstResult = null) {
        return $this->_property('_focusFirstResult', $focusFirstResult);
    }

/**
 * Get/set show add new item option.
 *
 * @param bool $showAddNewItem Show add new item
 * @return $this|bool
 */
    public function showAddNewItem($showAddNewItem = null) {
        return $this->_property('_showAddNewItem', $showAddNewItem);
    }

/**
 * Get/set add new item text.
 *
 * @param string $addNewItemText Add new item text
 * @return $this|string
 */
    public function addNewItemText($addNewItemText = null) {
        if ($addNewItemText === null && $this->_addNewItemText === null) {
            return $this->_addNewItemText = __('Add "{keyword}".');
        }
        return $this->_property('_addNewItemText', $addNewItemText);
    }

/**
 * Get/set the quick-create dialog URL for the "add new" option.
 *
 * @param string $addNewUrl URL
 * @return $this|string
 */
    public function addNewUrl($addNewUrl = null) {
        return $this->_property('_addNewUrl', $addNewUrl);
    }

/**
 * Get/set focus first result.
 *
 * @param bool $focusFirstResult Focus first result
 * @return $this|bool
 */
    public function __toString() {
        if ($this->_textProperty) {
            $data = $this->data();
            if (is_string($data) && substr($data, 0, 1) === '{') {
                $data = json_decode($data, true);
            }
            return UtilityText::insert($this->_textProperty, $data, ['before' => '{', 'after' => '}']);
        }
    }

/**
 * Array of some properties.
 *
 * @return array
 */
    public function toArray() {
        return array(
            'valueError' => $this->_valueError,
        ) + parent::toArray();
    }

}