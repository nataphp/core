<?php
/**
 * NataPHP Framework
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
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

namespace Nata\Error;

use Nata\Utility\Inflector;
use Nata\Core\App;
use Nata\Core\Configure;
use Nata\Routing\Router;
use Nata\Http\Request;
use Nata\Log\Log;
use Nata\Http\Response;
use Throwable;
use Exception;
use Nata\Controller\Controller;
use NataException;

/**
 * Exception Renderer.
 *
 * Provides Exception rendering features. Which allow exceptions to be rendered
 * as HTML pages.
 *
 * Captures and handles all unhandled exceptions. Displays helpful framework errors when debug > 1.
 * When debug < 1 a NataException will render 404 or 500 errors. If an uncaught exception is thrown
 * and it is a type that Exception\Handler does not know about it will be treated as a 500 error.
 *
 * ### Implementing application specific exception rendering
 *
 * You can implement application specific exception handling in one of a few ways:
 *
 * - Create a App\Controller\Error::appError();
 * - Create a subclass of \Nata\Error\Renderer and configure it to be the `Exception.renderer`
 *
 * #### Using App\Controller\Error::appError();
 *
 * This controller method is called instead of the default exception handling. It receives the
 * thrown exception as its only argument. You should implement your error handling in that method.
 *
 * #### Using a subclass of \Nata\Error\Renderer
 *
 * Using a subclass of \Nata\Error\Renderer gives you full control over how Errors are rendered, you
 * can configure your class in your core.php, with `Configure::write('Exception.renderer', 'MyClass');`
 * You should place any custom exception renderers in `App/Error`.
 */
class Renderer {

/**
 * Controller instance.
 *
 * @var \Nata\Controller\Controller
 */
    protected $_controller;

/**
 * Template to render for Exception.
 *
 * @var string
 */
    protected $_template = '';

/**
 * The method corresponding to the Exception this object is for.
 *
 * @var string
 */
    protected $_method = '';

/**
 * The Exception code.
 *
 * @var int
 */
    protected $_code = 500;

/**
 * The exception being handled.
 *
 * @var Exception
 */
    protected $_error;


/**
 * Creates the controller to perform rendering on the error response.
 * If the error is a NataException it will be converted to either a 400 or a 500
 * code error depending on the code used to construct the error.
 *
 * @param \Exception $exception Exception
 * @return void
 */
    public function __construct($exception) {
        $this->_error = $exception;
        $this->_controller = $this->_getController($exception);
    }

/**
 * Get the controller instance to handle the exception.
 * Override this method in subclasses to customize the controller used.
 * This method returns the built in `\Nata\Controller\Error` normally, or if an error is repeated
 * a bare controller will be used.
 *
 * @param \Exception $exception The exception to get a controller for.
 * @return \Nata\Controller\Controller
 */
    protected function _getController($exception) {
        if (!$request = Router::getRequest(true)) {
            $request = new Request();
        }

        $classNamespace = 'Controller';
        if ($request->prefix) {
            $classNamespace .= '/' . Inflector::camelize($request->prefix);
        }

        $className = App::className('Error', $classNamespace);
        if (!$className) {
            $className = App::className('Error', 'Controller');
        }

        $response = new Response(['charset' => Configure::read('App.encoding')]);
        try {
            $controller = new $className($request, $response);
            $controller->startupProcess();
        } catch (Throwable $e) {
            $controller = new Controller($request, $response);
        }

        return $controller;
    }

/**
 * Get exception code.
 *
 * @param \Exception $exception Exception instance.
 * @return int Error code
 */
    protected function _getCode($exception) {
        $code = 500;
        $errorCode = $exception->getCode();
        if ($errorCode && $errorCode >= 400 && $errorCode < 506) {
            $code = $errorCode;
        }
        return $code;
    }

/**
 * Get exception name from class.
 *
 * @param \Exception $exception Exception
 * @return string CamelCase string
 */
    protected function _getName($exception) {
        if (!Configure::read('debug')) {
            return $this->_isInternalError($this->_getCode($exception)) ? 'Internal Server Error' : 'Not Found';
        }
        $name = $this->_getExceptionShortName($exception);
        $name = Inflector::underscore($name);
        $name = Inflector::humanize($name);
        return $name;
    }

/**
 * Normalize exception name.
 *
 * @param \Exception $exception Exception instance.
 * @return string Without 'Exception'
 */
    protected function _getExceptionShortName($exception) {
        $name = App::classShortName($exception);
        $name = str_replace('Exception', '', $name);
        if (empty($name)) {
            $name = 'Error';
        }
        return $name;
    }

/**
 * Get method name that will handle the exception.
 *
 * @param \Exception $exception Exception.
 * @param int $code Error code.
 * @return string Method name
 */
    protected function _getMethodName($exception, $code) {
        $method = Inflector::variable($this->_getExceptionShortName($exception));
        if (method_exists($this->_controller, $method)) {
            return $method;
        }

        $generic = 'appError';
        if (!Configure::read('debug')) {
            $generic = 'error500';
            if (!$this->_isInternalError($code)) {
                $generic = 'error400';
            }
        }

        if (method_exists($this->_controller, $generic)) {
            return $generic;
        }

        return null;
    }

/**
 * Get template name.
 *
 * @param \Exception $exception Exception instance.
 * @param string $method Method name.
 * @return int Error code
 */
    protected function _getTemplate($exception, $code) {
        if (!Configure::read('debug')) {
            if (!$this->_isInternalError($code)) {
                return 'error400';
            }
            return 'error500';
        }
        return Inflector::underscore($this->_getExceptionShortName($exception));
    }

/**
 * Get error message.
 *
 * @param \Exception $exception Exception
 * @param int $code Error code
 * @return string Error message
 */
    protected function _getMessage($exception, $code) {
        if (!Configure::read('debug')) {
            if (!$this->_isInternalError($code)) {
                return __("The page at %s doesn't exist.", $this->_getUrl());
            }
            return __('An internal error has occurred.');
        }
        return $exception->getMessage();
    }

/**
 * Check if error code is relative to a internal server error.
 *
 * @param int $code Error code
 * @return bool True if it is
 */
    private function _isInternalError($code) {
        return $code >= 500;
    }

/**
 * Get current URL html encoded.
 *
 * @return string Encoded Url
 */
    private function _getUrl() {
        return h($this->_controller->request->here());
    }

/**
 * Get current URL html encoded.
 *
 * @return string Encoded Url
 */
    protected function _viewVars($exception, $code) {
        $name = $this->_getName($exception);
        $message = $this->_getMessage($exception, $code);
        $url = $this->_getUrl();
        $attributes = method_exists($exception, 'getAttributes') ? $exception->getAttributes() : [];

        $viewVars = array_merge([
            'name' => $name,
            'message' => $message,
            'trace' => false,
            'error' => false,
            'url' => $url
        ], $this->_controller->request->params, $attributes);

        if (Configure::read('debug')) {
            $viewVars = [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'error' => $exception,
                'trace' => nl2br($exception->getTraceAsString()),
                '_serialize' => ['name', 'url', 'message']
             ] + $viewVars;
        }

        return $viewVars;
    }

/**
 * Renders the response for the exception.
 *
 * @return \Nata\Http\Response|void
 */
    public function render() {
        $exception = $this->_error;
        $response = null;
        $code = $this->_getCode($exception);
        $template = $this->_getTemplate($exception, $code);

        $controller = $this->_controller;
        $controller->response->statusCode($code);
        $controller->viewBuilder()
            ->templatePath('Error')
            ->template($template)
            ->set($this->_viewVars($exception, $code));

        if ($methodName = $this->_getMethodName($exception, $code)) {
            $response = $controller->{$methodName}($exception, $code);
        } else {
            $response = call_user_func_array([$this, '_nataError'], [$exception, $template]);
        }

        if (!($response instanceof Response)) {
            $response = $controller->render();
        }

        return $response->send();
    }

/**
 * Generic handler for the internal framework errors NataPHP can generate.
 *
 * @param \Exception $error
 * @return void
 */
    protected function _nataError($error, $template) {
        $this->_outputMessage($template);
    }

/**
 * Generate the response using the controller object.
 *
 * @param string $template The template to render.
 * @return void
 */
    protected function _outputMessage($template) {
        if ($this->_error instanceof NataException) {
            $this->_controller->set($this->_error->getAttributes());
        }

        $this->_controller
            ->viewBuilder()
            ->layout('nata_' . (!Configure::read('debug') ? 'layout' : 'layout_debug'));

        try {
            $this->_controller->render($template);
            return $this->_controller->response;
        } catch (Throwable $error) {
            $this->_logTemplateError($error);
            return $this->_outputMessageSafe($error);
        }
    }

/**
 * Log template rendering error.
 *
 * This is useful when creating a custom template, to know
 * what went wrong.
 *
 * @param \Exception $exception Exception instance.
 */
    protected function _logTemplateError($exception) {
        $debug = Configure::read('debug');
        if (!$debug) {
            return;
        }

        $view = $this->_controller->viewBuilder();

        $templateName = $view->basePath();
        if ($view->templatePath()) {
            $templateName .= '/' . $view->templatePath();
        }
        $templateName .= '/' . $view->template();
        $templateName .= $view->ext();

        $message = sprintf(
            "[\Nata\Error\Renderer] Error on template '%s' from '%s':\n",
            $templateName,
            get_class($this->_controller)
        );

        $message .= sprintf("[%s] %s\n%s\n",
            get_class($exception),
            $exception->getMessage(),
            $exception->getTraceAsString()
        );

        Log::write(LOG_ERR, $message);
    }

/**
 * A safer way to render error messages, replaces all helpers, with basics
 * and doesn't call component methods.
 *
 * @param string $template The template to render
 * @return \Nata\Http\Response HTTP Response
 */
    protected function _outputMessageSafe($error) {
        $debug = Configure::read('debug');

        $_error = $this->_error;
        $code = $this->_getCode($_error);
        $template = 'exception';
        $layout = $debug ? 'nata_layout_debug' : 'nata_layout';

        $view = $this->_controller->viewBuilder('View');
        $view->layout($layout)->templatePath('Error');

        $view->set($this->_viewVars($_error, $code));

        $this->_controller->response->body($view->render($template));
        $this->_controller->response->type('html');

        return $this->_controller->response;
    }

}
