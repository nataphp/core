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

namespace Nata\Email;

use Nata\Core\Configure;
use Nata\Core\App;
use Nata\Core\ConfigAwareTrait;
use Nata\Email\Mailer\Message;
use Nata\Email\Mailer\Transport;
use Nata\ORM\Entity;
use Nata\Utility\Hash;
use Nata\View\ViewBuilder;
use InvalidArgumentException;
use BadMethodCallException;
use JsonSerializable;

/**
 * Mailer class.
 */
class Mailer implements JsonSerializable {

    use ConfigAwareTrait;

/**
 * Current configuration profile.
 *
 * @var string
 */
    protected $_profile = 'default';

/**
 * Message.
 *
 * @var \Nata\Email\Mailer\Message
 */
    protected $_message;

/**
 * Email template.
 *
 * @var string
 */
    protected $_template;

/**
 * Email theme.
 *
 * @var string
 */
    protected $_theme;

/**
 * Email template layout.
 *
 * @var string
 */
    protected $_layout;

/**
 * View instance.
 *
 * @var \Nata\View\View
 */
    protected $_view;

/**
 * View Variables.
 *
 * @var array
 */
    protected $_viewVars = [];

/**
 * Template path.
 *
 * @var string
 */
    protected $_templatePath = 'Email';

/**
 * Error information.
 *
 * @var string
 */
    protected $_log;

/**
 * Transport instances.
 *
 * @var array
 */
    protected $_transport = 'PhpMailer';

/**
 * Transport instances.
 *
 * @var array
 */
    protected $_errors = [];

/**
 * Types.
 *
 * @var array
 */
    protected $_types = ['html', 'text'];

/**
 * Constructor.
 *
 * @param string|array $config Profile name or array with configuration.
 * @return void
 */
    public function __construct(string|array $config = []) {
        $this->_message = new Message();

        if (empty($config)) {
            $config = ['profile' => $this->_profile];
        } elseif (is_string($config)) {
            $config = ['profile' => $config];
        }

        $config += ['profile' => $this->_profile];

        $this->profile($config['profile']);
        unset($config['profile']);
        $this->_message->config($config);
    }

/**
 * Get/Set profile/configuration to be used.
 *
 * @param string|array $config Mailer configuration or profile name
 * @return Message|Mailer
 */
    public function profile($config = null) {
        if ($config === null) {
            return $this->_profile;
        }

        if (is_string($config)) {
            $name = $config;
            $config = Configure::read('Email.Mailer.' . $name);
            if (empty($config)) {
                throw new InvalidArgumentException(sprintf('Unknown mailer configuration "%s".', $name));
            }

            $this->_profile = $name;

            unset($name);
        }

        $this->config($config);
        $this->_message->config($config);

        return $this;
    }

/**
 * Get/Set template.
 *
 * @param string $template Template name or null to not use
 * @return string|$this
 */
    public function domain($domain = null) {
        if ($domain === null) {
            return $this->_template;
        }

        $this->_template = $domain;

        return $this;
    }

/**
 * Get/Set message.
 *
 * @param Message $message Message
 * @return Message|Mailer
 */
    public function message(Message|string $message = null) {
        if (func_num_args() === 0) {
            if ($this->_template && !$this->_message->textMessage() && !$this->_message->htmlMessage()) {
                $this->render();
            }
            return $this->_message;
        }

        if (is_string($message)) {
            $message = (new Message())->body($message);
        }

        $this->_message = $message;

        return $this;
    }

/**
 * Magic method to forward method class to Message instance.
 *
 * @param string $method Method name.
 * @param array $args Method arguments.
 * @return $this|mixed
 */
    public function __call(string $method, array $args) {
        $this->_message->{$method}(...$args);
        return $this;
    }

/**
 * Get/Set transport class (and configuration).
 *
 * @param string $transport Null to get, String with mailer,
 * @return string|$this
 */
    public function transport($transport = null) {
        if ($transport === null) {
            if (!($this->_transport instanceof Transport)) {
                $this->_transport = $this->_loadTransport($this->_transport);
            }
            return $this->_transport;
        }

        $this->_transport = $transport;

        return $this;
    }

/**
 * Load transport instance.
 *
 * @param string|array $transport Transport to load
 * @return Transport
 */
    protected function _loadTransport($transport) {
        if ($transport instanceof Transport) {
            return $transport;
        }

        if (is_string($transport)) {
            $transport = ['className' => $transport];
        }

        if (!isset($transport['className'])) {
            throw new InvalidArgumentException('Missing transport class name.');
        }

        $className = App::className($transport['className'], 'Email/Mailer/Transport');
        if (!$className) {
            throw new InvalidArgumentException(sprintf('Missing transport class name "%s".', $transport['className']));
        }

        return new $className($transport);
    }

/**
 * Get/Set template.
 *
 * @param string $template Template name or null to not use
 * @return string|$this
 */
    public function template($template = null) {
        if ($template === null) {
            return $this->_template;
        }

        if ($template !== $this->_template) {
            $this->_message->body([]);
        }

        $this->_template = $template;

        return $this;
    }

/**
 * Get/Set template layout.
 *
 * @param string $layout Layout name or null to not use
 * @return string|$this
 */
    public function layout($layout = null) {
        if ($layout === null) {
            return $this->_layout;
        }

        if ($layout !== $this->_layout) {
            $this->_message->body([]);
        }

        $this->_layout = $layout;

        return $this;
    }

/**
 * Check if template exists.
 *
 * @param string $template Template name or null to use property value
 * @param string $type Type of email
 * @return boolean|array
 */
    public function templateExists($template = null, $type = null) {
        if ($template === null) {
            $template = $this->_template;
        }

        $View = $this->viewBuilder();

        if ($this->_templatePath) {
            $View->templatePath($this->_templatePath);
        }

        if ($type) {
            return in_array($type, $this->_types) ? $View->exists($type . '/' . $template) : false;
        }

        $types = [];
        foreach ($this->_types as $type) {
            $types[$type] = $View->exists($type . '/' . $template);
        }

        return !empty($types) ? $types : false;
    }

/**
 * Add theme.
 *
 * @param string $theme Null to get, String with theme.
 * @return string|$this
 */
    public function theme($theme = null) {
        if ($theme === null) {
            return $this->_theme;
        }

        $this->_theme = $theme;

        return $this;
    }

/**
 * Passes on variables to the view.
 *
 * @param string|array $one A string or an array of data.
 * @param string|array $two Value in case $one is a string (which then works as the key).
 *   Unused if $one is an associative array, otherwise serves as the values to $one's keys.
 * @return $this
 */
    public function viewVars($one = null, $two = null) {
        if ($one === null) {
            return $this->_viewVars;
        }

        $this->_message->body([]);

        if ($one instanceof Entity) {
            $one = $one->toArray();
        }

        if (is_array($one)) {
            $data = $one;
            if (is_array($two)) {
                $data = array_combine($one, $two);
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
 * Alias of Mailer::viewVars.
 *
 * @see Mailer::viewVars
 */
    public function set($one, $two = null) {
        return $this->viewVars($one, $two);
    }

/**
 * Send email.
 *
 * If array, it will be imploded with line breaks ('\n')
 *
 * @param array|string $body Email content.
 * @return array|boolean
 */
    public function send($body = null) {
        $message = $this->message();

        if (empty($message->from())) {
            throw new BadMethodCallException('From is not specified.');
        }

        if ((!$message->to() || $message->to()->isEmpty())
             && (!$message->cc() || $message->cc()->isEmpty())
             && (!$message->bcc() || $message->bcc()->isEmpty())) {
            throw new BadMethodCallException('You need specify one destination on to, cc or bcc.');
        }

        if (empty($message->subject())) {
            throw new BadMethodCallException('Subject is empty.');
        }

        if (is_array($body) && !isset($body['html']) && !isset($body['text'])) {
            $body = implode(PHP_EOL, $body);
        }

        if (!empty($body)) {
            $message->body($body);
        }

        return $this->transport()->send($message);
    }

/**
 * Render message body content.
 *
 * @return Mailer
 */
    public function render() {
        $debug = Configure::read('debug');

        $View = ViewBuilder::build();
        $View->set($this->_viewVars)
            ->templatePath($this->_templatePath)
            ->compileCheck($debug)
            ->forceCompile($debug);

        foreach ($this->_message->bodyTypes() as $bodyType) {
            $templatePath = $bodyType . '/' . $this->_template;
            if (!$View->exists($templatePath)) {
                continue;
            }

            $this->_message->body($View->render($templatePath, $this->_layout), $bodyType);
        }

        return $this;
    }

/**
 * Static method to fast create an instance of \Nata\Email\Mailer
 *
 * @param string|array $to Address to send (see Nata\Email\Mailer::to()). If null, will try to use 'to' from transport config
 * @param string $subject String of subject or null to use 'subject' from transport config
 * @param string|array $message String with message or array with variables to be used in render
 * @param boolean $send Send email
 * @return \Nata\Email\Mailer
 * @throws \InvalidArgumentException
 */
    public static function deliver($to = null, $subject = null, $message = null, array $config = [], $send = true) {
        $class = __CLASS__;

        $instance = new $class($config);

        if ($to !== null) {
            $instance->to($to);
        }

        if ($subject !== null) {
            $instance->subject($subject);
        }

        if (is_array($message)) {
            $instance->viewVars($message);
            $message = null;
        } elseif ($message === null && array_key_exists('message', $config = $instance->config())) {
            $message = $config['message'];
        }

        if ($send === true) {
            $instance->send($message);
        }

        return $instance;
    }

/**
 * Check if has error.
 *
 * @return boolean
 */
    public function hasError() {
        return !empty($this->_errors)
            || $this->transport() instanceof Transport && $this->transport()->hasError();
    }

/**
 * Get the error.
 *
 * @return string
 */
    public function getError() {
        if ($this->transport() instanceof Transport && $this->transport()->hasError()) {
            $this->_errors = array_merge($this->_errors, $this->transport()->getErrors());
        }
        return $this->_errors;
    }

/**
 * JSON serialization.
 *
 * @return array JSON data to be serialized
 */
    public function jsonSerialize(): array {
        return $this->config();
    }

/**
 * Email configuration as an array.
 *
 * @return array
 */
    public function toArray() {
        return $this->config();
    }

/**
 * __debugInfo.
 *
 * @return array Email configuration
 */
    public function __debugInfo() {
        return [
            'from' => $this->message()->from(),
            'to' => $this->message()->to(),
            'cc' => $this->message()->cc(),
            'bcc' => $this->message()->bcc(),
            'subject' => $this->message()->subject(),
            'message' => $this->message(),
            'transport' => $this->_transport
        ];
    }

}
