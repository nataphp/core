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

use Nata\Utility\Text;
use Nata\Utility\Validation;

/**
 * Password element.
 */
class Password extends Input {

/**
 * Allow to show password.
 *
 * @var bool
 */
    protected $_show = true;

/**
 * Regular expressions for respective validation strategy.
 *
 * @var array
 */
    protected $_regex = [
        'uppercase' => '/\p{Lu}/',
        'lowercase' => '/\p{Ll}/',
        'specialChars' => '/[^\w]/',
        'number' => '/[0-9]/'
    ];

/**
 * Contain a uppercase letter.
 *
 * @var bool
 */
    protected $_uppercase;

/**
 * Contain a lowercase letter.
 *
 * @var bool
 */
    protected $_lowercase;

/**
 * Contain a special character.
 *
 * @var bool
 */
    protected $_specialChars;

/**
 * Contain a number.
 *
 * @var bool
 */
    protected $_number;


/**
 * Pseudo-constructor.
 *
 * @return void
 */
    public function initialize($config) {
        $config += [
            'show' => null,
            'uppercase' => null,
            'lowercase' => null,
            'specialChars' => null,
            'number' => null
        ];

        parent::initialize($config);

        if ($config['show']) {
            $this->_show = $config['show'];
        }

        if ($config['uppercase'] !== null) {
            $this->_uppercase = $config['uppercase'];
        }

        if ($config['lowercase'] !== null) {
            $this->_lowercase = $config['lowercase'];
        }

        if ($config['specialChars'] !== null) {
            $this->_specialChars = $config['specialChars'];
        }

        if ($config['number'] !== null) {
            $this->_number = $config['number'];
        }

    }

/**
 * Get/Set option to toggle password visibility.
 *
 * @param bool $show Allow show password
 * @return $this|bool
 */
    public function show($show = null) {
        return $this->_property('_show', $show);
    }

/**
 * Get/Set uppercase validation check.
 *
 * @param bool $uppercase Validate uppercase
 * @return $this|bool
 */
    public function uppercase($uppercase = null) {
        return $this->_property('_uppercase', $uppercase);
    }

/**
 * Get/Set lowercase validation check.
 *
 * @param bool $lowercase Validate lowercase
 * @return $this|bool
 */
    public function lowercase($lowercase = null) {
        return $this->_property('_lowercase', $lowercase);
    }

/**
 * Get/Set option to contain special charaters.
 *
 * @param bool $specialChars Contain special charaters
 * @return $this|bool
 */
    public function specialChars($specialChars = null) {
        return $this->_property('_specialChars', $specialChars);
    }

/**
 * Get/Set number validation check.
 *
 * @param bool $number Validate number
 * @return $this|bool
 */
    public function number($number = null) {
        return $this->_property('_number', $number);
    }

/**
 * Validation.
 *
 * @param string $data Data to be validated
 * @return boolean True if valid, false otherwise
 */
    protected function _valid($data) {
        $error = [];

        if ($this->number() && !Validation::custom($data, $this->_regex['number'])) {
            $error[] = $this->_getRequirementMessage('number');
        }

        if ($this->uppercase() && !Validation::custom($data, $this->_regex['uppercase'])) {
            $error[] = $this->_getRequirementMessage('uppercase');
        }

        if ($this->lowercase() && !Validation::custom($data, $this->_regex['lowercase'])) {
            $error[] = $this->_getRequirementMessage('lowercase');
        }

        if ($this->specialChars() && !Validation::custom($data, $this->_regex['specialChars'])) {
            $error[] = $this->_getRequirementMessage('specialChars');
        }

        if ($error) {
            $this->_error = __x('password_requirements', 'Must contain at least %s', Text::toList($error, __('and')));
        }

        return empty($error);
    }

/**
 * Before render.
 *
 * @param \Nata\Event\Event $event Event instance
 */
    public function beforeRender($event) {
        if ($this->_description === null) {
            $rules = [];
            if ($this->_minLength > 0) {
                $rules[] = __x('password_requirements', 'minimum of %d characters', $this->_minLength);
            }

            if ($this->number() || $this->uppercase() || $this->lowercase() || $this->specialChars()) {
                $validation = [];
                if ($this->number()) {
                    $validation[] = $this->_getRequirementMessage('number');
                }
                if ($this->uppercase()) {
                    $validation[] = $this->_getRequirementMessage('uppercase');
                }
                if ($this->lowercase()) {
                    $validation[] = $this->_getRequirementMessage('lowercase');
                }
                if ($this->specialChars()) {
                    $validation[] = $this->_getRequirementMessage('specialChars');
                }

                $rules[] = __x('password_requirements', 'must contain at least %s', Text::toList($validation, __('and')));
            }

            if ($rules) {
                $this->_description = ucfirst(Text::toList($rules, __('and'))) . '.';
            }
        }

        parent::beforeRender($event);

        if ($this->show()) {
            $this->attrs()->addClass('input-show-password');
        }

        $this->_getView()->extend(['input']);
    }

/**
 * Get requirement message for given type.
 *
 * @param string $type Type of check
 * @return string Requirement message
 */
    protected function _getRequirementMessage($type) {
        $errors = [
            'uppercase' => __x('password_requirements', 'uppercase letter'),
            'lowercase' => __x('password_requirements', 'lowercase letter'),
            'specialChars' => __x('password_requirements', 'one special character'),
            'number' => __x('password_requirements', 'a number')
        ];

        return isset($errors[$type]) ? $errors[$type] : null;
    }

}
