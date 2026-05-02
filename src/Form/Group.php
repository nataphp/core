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

namespace Nata\Form;

use Nata\View\ViewBuilder;
use Closure;

/**
 * Provides a registry/factory for groups of elements.
 */
class Group {

/**
 * Form instance.
 *
 * @var Form
 */
    private $_form;

/**
 * Group name.
 *
 * @var string
 */
    private $_group;

/**
 * Group id.
 *
 * @var string
 */
    private $_id;

/**
 * Group's legend.
 *
 * @var string
 */
    private $_legend;

/**
 * Group's title.
 *
 * @var string
 */
    private $_title;

/**
 * Use property value as title.
 *
 * @var string
 */
    private $_defaultTitle;

/**
 * Add row button text.
 *
 * @var string
 */
    private $_addRowText;

/**
 * Translate.
 *
 * @var bool
 */
    private $_translate;

/**
 * Label as placeholder setting.
 *
 * @var bool
 */
    private $_labelAsPlaceholder;

/**
 * Layout.
 *
 * @var string
 */
    private $_layout;

/**
 * Required.
 *
 * @var bool
 */
    private $_required;

/**
 * Horizontal layout column size.
 *
 * @var int
 */
    private $_columnSize;

/**
 * Theme.
 *
 * @var string
 */
    private $_theme;

/**
 * Template.
 *
 * @var string
 */
    private $_template = 'group';

/**
 * Group UI collapsed.
 *
 * @var bool
 */
    private $_open = false;

/**
 * Group's row elements params.
 *
 * @var array
 */
    private $_elements;

/**
 * Holds rows instances.
 *
 * @var array
 */
    private $_rows;

/**
 * Maximum number of rows.
 *
 * @var int
 */
    protected $_max;

/**
 * Data manager instance.
 *
 * @var \Nata\Form\DataManager
 */
    protected $_dataManager;

/**
 * Group attributes.
 *
 * @var \Nata\Form\Attributes
 */
    private $_attributes;

/**
 * Event manager instance.
 *
 * @var \Nata\Event\Manager
 */
    protected $_eventManager;

/**
 * Elements array data.
 *
 * @var array
 */
    private $_toArray;

/**
 * Group data is valid
 *
 * @var bool
 */
    private $_isValid;

/**
 * Form submitted.
 *
 * @var bool
 */
    private $_submitted;


/**
 * Group constructor.
 *
 * @param array $config Group options
 * @return void
 */
    public function __construct(Form $form, array $config) {
        $this->_form = $form;
        $this->_submitted = $config['submitted'];
        $this->_dataManager = $config['data'];
        $this->_required = $config['required'];
        if ($config['group']) {
            $this->group($config['group']);
        }
        if ($config['legend']) {
            $this->legend($config['legend']);
        }
        if ($config['title']) {
            $this->title($config['title']);
        }
        if ($config['max'] > 0) {
            $this->_max = $config['max'];
        }
        if ($config['defaultTitle']) {
            $this->defaultTitle($config['defaultTitle']);
        }
        if ($config['addRowText']) {
            $this->addRowText($config['addRowText']);
        }
        if ($config['elements']) {
            $this->elements($config['elements']);
        }
        if ($config['theme']) {
            $this->theme($config['theme']);
        }
        if ($config['template']) {
            $this->_template = $config['template'];
        }
        if ($config['layout']) {
            $this->_layout = $config['layout'];
        }
        if ($config['labelAsPlaceholder']) {
            $this->_labelAsPlaceholder = $config['labelAsPlaceholder'];
        }
        if ($config['translate']) {
            $this->_translate = $config['translate'];
        }
        if ($config['columnSize']) {
            $this->_columnSize = $config['columnSize'];
        }
        if ($config['eventManager']) {
            $this->_eventManager = $config['eventManager'];
        }

        $id = explode('.', $this->_group);
        $this->_id = array_merge((array)$config['id'], $id);
    }

/**
 * Get/Set group collapsed state.
 *
 * @param bool $open Collapsed state
 * @return bool|$this
 */
    public function open($open = null) {
        if ($open === null) {
            return $this->_open;
        }
        $this->_open = $open;
        return $this;
    }

/**
 * Get group id parts.
 *
 * @return array
 */
    public function id($id = null) {
        return $this->_id;
    }

/**
 * Get/Set group theme.
 *
 * @param mixed Multiple arguments
 * @return mixed group id
 */
    public function theme($theme = null) {
        if ($theme === null) {
            return $this->_theme;
        }
        $this->_theme = $theme;
        return $this;
    }

/**
 * Get/Set maximum number of rows allowed.
 *
 * @param int Maximum number of rows
 * @return int|$this Maxiimum
 */
    public function max($max = null) {
        if ($max === null) {
            return $this->_max;
        }
        $this->_max = $max;
        return $this;
    }

/**
 * Get/Set group name.
 *
 * @param string $group Group name
 * @return mixed \Nata\Form\Group or group name
 */
    public function group($group = null) {
        if ($group === null) {
            return $this->_group;
        }
        $this->_group = $group;
        return $this;
    }

/**
 * Get/Set Group legend.
 *
 * @param string $legend Group legend
 * @return mixed \Nata\Form\Group or group name
 */
    public function legend($legend = null) {
        if ($legend === null) {
            return $this->_legend;
        }
        $this->_legend = $legend;
        return $this;
    }

/**
 * Get/Set Group title.
 *
 * @param string $title Group title
 * @return mixed \Nata\Form\Group or group title
 */
    public function title($title = null) {
        if ($title === null) {
            return $this->_title;
        }
        $this->_title = $title;
        return $this;
    }

/**
 * Get/set element's template.
 *
 * @param string $template Element template
 * @return $this|string
 */
    public function template($template = null) {
        if ($template === null) {
            return $this->_template;
        }
        $this->_template = $template;
        return $this;
    }

/**
 * Get/Set Group required setting.
 *
 * @param bool $required Required
 * @return bool Required
 */
    public function required($required = null) {
        if ($required === null) {
            return $this->_required;
        }
        $this->_required = $required;
        return $this;
    }

/**
 * Get/Set Group title.
 *
 * @param string $defaultTitle Group title
 * @return mixed \Nata\Form\Group or group title
 */
    public function defaultTitle($defaultTitle = null) {
        if ($defaultTitle === null) {
            if ($this->_defaultTitle === null) {
                $this->_defaultTitle = __('New');
            }
            return $this->_defaultTitle;
        }
        $this->_defaultTitle = $defaultTitle;
        return $this;
    }

/**
 * Get/Set Add new row button text.
 *
 * @param string $addRowText Text
 * @return mixed \Nata\Form\Group or button text
 */
    public function addRowText($addRowText = null) {
        if ($addRowText === null) {
            if ($this->_addRowText === null) {
                $this->_addRowText = __('Add another');
            }
            return $this->_addRowText;
        }
        $this->_addRowText = $addRowText;
        return $this;
    }

/**
 * Add element(s) parameters.
 *
 * @param array $elementsParams Row of elements parameters.
 * @return \Nata\Form\Group|array
 */
    public function elements($elements = null) {
        if ($elements === null) {
            return $this->_elements;
        }
        if (func_num_args() > 1) {
            $elements = func_get_args();
        }
        $this->_elements = $elements;
        return $this;
    }

/**
 * Add element(s) parameters.
 *
 * @param array $elementsParams Row of elements parameters.
 * @return \Nata\Form\Group|array
 */
    public function layout($layout = null) {
        if ($layout === null) {
            return $this->_layout;
        }
        $this->_layout = $layout;
        return $this;
    }

/**
 * Get group data.
 *
 * @return mixed
 */
    public function data() {
        return $this->_dataManager->model()->extract();
    }

/**
 * Get form group \Nata\Form\DataManager instance.
 *
 * @return \Nata\Form\DataManager
 */
    public function dataManager() {
        return $this->_dataManager;
    }

/**
 * Get/set attributes.
 *
 * @return \Nata\Form\Attributes
 */
    public function attributes() {
        if ($this->_attributes === null) {
            $this->_attributes = new Attributes($this);
        }
        return $this->_attributes;
    }

/**
 * Alias/short hand for attributes.
 *
 * @return \Nata\Form\Attributes
 */
    public function attrs() {
        return $this->attributes();
    }

/**
 * Get GroupRow instances.
 *
 * @param \Closure|int $index Row index
 * @return \Nata\Form\GroupRow|array
 */
    public function rows($index = null) {
        if ($this->_rows === null) {
            $this->_rows = $this->_rows();
        }

        if ($index === null) {
            return $this->_rows;
        }

        if ($index instanceof Closure) {
            foreach ($this->_rows as $_index => $row) {
                $index($row, $_index, $this);
            }
        } elseif (is_int($index) && isset($this->_rows[$index])) {
            return $this->_rows[$index];
        }
    }

/**
 * Get/Set rows.
 *
 * @param array $row Row index
 * @return boolean True if form was submitted
 */
    private function _rows() {
        $rows = array();
        $dataManager = $this->_dataManager;
        $data = !$dataManager->request()->isEmpty() ? $dataManager->request() : $dataManager->model();
        if ($data->isEmpty()) {
            $data = $data->set([0 => null]);
        }
        foreach ($data->extract() as $index => $irrelevant) {
            $rows[$index] = $this->_row($index);
        }
        $this->open(count($rows) === 1);
        return $rows;
    }

/**
 * Create GroupRow.
 *
 * @param int $index Index for row
 * @return \Nata\Form\GroupRow
 */
    public function _row($index, $data = null) {
        return new GroupRow($this->_form, [
            'id' => $this->id(),
            'title' => $this->_title,
            'defaultTitle' => $this->_defaultTitle,
            'index' => $index,
            'group' => $this->_group,
            'submitted' => $this->_submitted,
            'labelAsPlaceholder' => $this->_labelAsPlaceholder,
            'translate' => $this->_translate,
            'theme' => $this->_theme,
            'layout' => $this->_layout,
            'columnSize' => $this->_columnSize,
            'eventManager' => $this->_eventManager,
            'data' => $this->_dataManager->nested($index),
            'required' => $this->_required,
            'elements' => $this->_elements
        ]);
    }

/**
 * Array of elements properties.
 *
 * @return array
 */
    public function rowCount() {
        $this->rows();
        return count($this->_rows);
    }

/**
 * Check if it's allowed to add more rows.
 *
 * @return bool
 */
    public function newRowAllowed() {
        return empty($this->_max) || ($this->_max > 0 && $this->rowCount() < $this->_max);
    }

/**
 * Array of elements properties.
 *
 * @return array
 */
    public function isEmpty() {
        $empty = true;
        foreach ($this->rows() as $index => $row) {
            if (!$row->isEmpty()) {
                $empty = false;
                break;
            }
        }
        return $empty;
    }

/**
 * Array of elements properties.
 *
 * @return array
 */
    public function isValid() {
        if ($this->_isValid === null) {
            $groupIsValid = $groupIsEmpty = true;

            $this->_toArray = [
                'id' => $this->attributes()->id(),
                'group' => $this->_group,
                'isValid' => null,
                'isEmpty' => null,
                'required' => $this->required(),
                'rows' => []
            ];

            foreach ($this->rows() as $index => $row) {
                $rowDataManager = $row->dataManager();

                $model = $rowDataManager->model();
                $request = $rowDataManager->request();
                $isValid = $row->isValid();
                $isRequired = $this->_required || (!$this->_required && !$row->isEmpty());
                $this->_toArray['rows'][$index] = $row->toArray();

                if ($groupIsEmpty && !$row->isEmpty()) {
                    $groupIsEmpty = false;
                }

                if ($isValid) {
                    $data = $model->extract();
                    if (empty($data)) {
                        $data = $request->extract();
                    }
                    $this->_dataManager->patch($index, $data);
                } elseif ($groupIsValid) {
                    $groupIsValid = false;
                }

                if (!$isRequired) {
                    if ($this->rowCount() === 1) {
                        $this->_dataManager->clear([]);
                    } else {
                        $this->_dataManager->model()->unsetProperty($index);
                    }
                }
            }

            $this->_checkRemovedRows();
            $this->_toArray['isEmpty'] = $groupIsEmpty;
            $this->_toArray['isValid'] = $this->_isValid = $groupIsValid;
        }
        return $this->_isValid;
    }

/**
 * Array of elements properties.
 *
 * @return array
 */
    public function _checkRemovedRows() {
        $rows = $this->_dataManager->model()->extract();
        if ($rows) {
            $request = $this->_dataManager->request();
            foreach ($rows as $index => $row) {
                if (!$request->has($index)) {
                    $this->_dataManager->model()->unsetProperty($index);
                }
            }
        }
    }

/**
 * Render.
 *
 * @param \Nata\View\View $view View instance
 * @return sring
 */
    public function render() {
        $view = ViewBuilder::build()->compileCheck(false);
        $view->set('group', $this);
        return $view->render('/Form/' . $this->_theme . '/' . $this->template());
    }

/**
 * Array of elements properties.
 *
 * @return array
 */
    public function toArray() {
        if ($this->_toArray === null) {
            $this->_toArray = [
                'id' => $this->attributes()->id(),
                'group' => $this->_group,
                'required' => $this->required(),
                'rows' => array_map(function($row) {
                    return $row->toArray();
                }, $this->rows())
            ];
        }
        return $this->_toArray;
    }

/**
 * __debugInfo.
 *
 * @return array
 */
    public function __debugInfo() {
        return $this->toArray();
    }

}
