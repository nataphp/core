<?php
/**
 * Exceptions file.  Contains the various exceptions CakePHP will throw until they are
 * moved into their permanent location.
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2012, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://book.cakephp.org/2.0/en/development/testing.html
 * @package       Cake.Error
 * @since         CakePHP(tm) v 2.0
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

use Nata\I18n\Time;

/**
 * Parent class for all of the HTTP related exceptions in CakePHP.
 * All HTTP status/error related exceptions should extend this class so
 * catch blocks can be specifically typed.
 */
if (!class_exists('HttpException')) {
    class HttpException extends NataException {
    }
}

/**
 * Represents an HTTP 400 error.
 */
class BadRequestException extends HttpException {

/**
 * Constructor
 *
 * @param string $message If no message is given 'Bad Request' will be the message
 * @param string $code Status code, defaults to 400
 */
    public function __construct($message = null, $code = 400) {
        if (empty($message)) {
            $message = 'Bad Request';
        }
        parent::__construct($message, $code);
    }

}

/**
 * Represents an HTTP 401 error.
 */
class UnauthorizedException extends HttpException {

/**
 * Constructor
 *
 * @param string $message If no message is given 'Unauthorized' will be the message
 * @param string $code Status code, defaults to 401
 */
    public function __construct($message = null, $code = 401) {
        if (empty($message)) {
            $message = 'Unauthorized';
        }
        parent::__construct($message, $code);
    }

}

/**
 * Represents an HTTP 403 error.
 */
class ForbiddenException extends HttpException {

/**
 * Constructor
 *
 * @param string $message If no message is given 'Forbidden' will be the message
 * @param string $code Status code, defaults to 403
 */
    public function __construct($message = null, $code = 403) {
        if (empty($message)) {
            $message = 'Forbidden';
        }
        parent::__construct($message, $code);
    }

}

/**
 * Represents an HTTP 503 error.
 */
class ServiceUnavailableException extends HttpException {

/**
 * Constructor
 *
 * @param string $message If no message is given 'Forbidden' will be the message
 * @param string $code Status code, defaults to 403
 */
    public function __construct($message = null, $code = 503) {
        if (empty($message)) {
            $message = 'Service Unavailable';
        }
        parent::__construct($message, $code);
    }

}

/**
 * Represents an HTTP 404 error.
 */
class NotFoundException extends HttpException {

/**
 * Constructor
 *
 * @param string $message If no message is given 'Not Found' will be the message
 * @param string $code Status code, defaults to 404
 */
    public function __construct($message = null, $code = 404) {
        if (empty($message)) {
            $message = 'Not Found';
        }
        parent::__construct($message, $code);
    }

}

/**
 * Represents an HTTP 405 error.
 */
class MethodNotAllowedException extends HttpException {

/**
 * Constructor
 *
 * @param string $message If no message is given 'Method Not Allowed' will be the message
 * @param string $code Status code, defaults to 405
 */
    public function __construct($message = null, $code = 405) {
        if (empty($message)) {
            $message = 'Method Not Allowed';
        }
        parent::__construct($message, $code);
    }

}

/**
 * Represents an HTTP 500 error.
 */
class InternalErrorException extends HttpException {

/**
 * Constructor
 *
 * @param string $message If no message is given 'Internal Server Error' will be the message
 * @param string $code Status code, defaults to 500
 */
    public function __construct($message = null, $code = 500) {
        if (empty($message)) {
            $message = 'Internal Server Error';
        }
        parent::__construct($message, $code);
    }

}

/**
 * Represents an HTTP 503 error.
 */
class DisabledUnderMaintenanceException extends HttpException {

/**
 * Default attributes.
 *
 * - 'until': Date/Time that is expected to be back online.
 *
 * @var array
 */
    protected $_attributes = [
        'until' => null
    ];

/**
 * Default message.
 *
 * @var string
 */
    protected $_message = 'Website currently under maintenance. We promise to return soon. Thank you.';

/**
 * Constructor
 *
 * @param string $message If no message is given 'Internal Server Error' will be the message
 * @param string $code Status code, defaults to 500
 */
    public function __construct($message = null, $code = 503) {
        if ($message instanceof Time) {
            $this->_attributes['until'] = $message;
        } elseif (is_array($message)) {
            if ($this->_attributes['until'] && !($this->_attributes['until'] instanceof Time)) {
                $this->_attributes['until'] = new Time($this->_attributes['until']);
            }
        }

        if ($this->_attributes['until']) {
            $message = sprintf('Website currently under maintenance. We promise to return in %s. Thank you.', $this->_attributes['until']->timeAgoInWords());
        }

        parent::__construct($message, $code);
    }

}

/**
 * NataException is used a base class for CakePHP's internal exceptions.
 * In general framework errors are interpreted as 500 code errors.
 */
class NataException extends RuntimeException {

/**
 * Array of attributes that are passed in from the constructor, and
 * made available in the view when a development error is displayed.
 *
 * @var array
 */
    protected $_attributes = [];

/**
 * Template string that has attributes sprintf()'ed into it.
 *
 * @var string
 */
    protected $_templateMessage = '';

/**
 * Constructor.
 *
 * Allows you to create exceptions that are treated as framework errors and disabled
 * when debug = 0.
 *
 * @param string|array $message Either the string of the error message, or an array of attributes
 *   that are made available in the view, and sprintf()'d into NataException::$_messageTemplate
 * @param string $code The code of the error, is also the HTTP status code for the error.
 */
    public function __construct($message, $code = 500) {
        if (is_array($message)) {
            $attributes = $message;
            if (isset($attributes['message'])) {
                $message = $attributes['message'];
                unset($attributes['message']);
            } else {
                $message = vsprintf($this->_templateMessage, array_values($attributes));
            }

            $this->_attributes = $attributes;
        }
        parent::__construct($message, $code);
    }

/**
 * Get the passed in attributes.
 *
 * @return array
 */
    public function getAttributes() {
        return $this->_attributes;
    }

/**
 * Check if given attribute name exists.
 *
 * @param string $name Attriibute name to check
 * @return bool True if is set, false otherwise
 */
    public function hasAttribute($name) {
        return isset($this->_attributes[$name]);
    }

/**
 * Get defined attribute value.
 * If is not set, it will throw a undefined method Error.
 *
 * @param string $method Method name
 * @param array $args Method arguments
 * @return mixed Attribute value
 * @throws Error
 */
    public function __call(string $method, array $args) {
        if (stripos($method, 'get') === 0 && $this->_attributes) {
            $attribute = lcfirst(substr($method, 3));
            if (isset($this->_attributes[$attribute])) {
                return $this->_attributes[$attribute];
            }
        }
        throw new Error(sprintf('Call to undefined method %s::%s', __CLASS__, $method));
    }

}

/**
 * Invalid primary key exception - used when a invalid primary
 * key is passed to \Nata\ORM\Table::get()
 */
class InvalidPrimaryKeyException extends NataException {}

/**
 * Missing Action exception - used when a controller action
 * cannot be found.
 */
class MissingActionException extends NataException {

    protected $_templateMessage = 'Action %s() could not be found in controller %s.';

//@codingStandardsIgnoreStart
    public function __construct($message, $code = 404) {
        parent::__construct($message, $code);
    }
//@codingStandardsIgnoreEnd

}

/**
 * Private Action exception - used when a controller action
 * starts with a  `_`.
 */
class PrivateActionException extends NataException {

    protected $_templateMessage = 'Private Action %s::%s() is not directly accessible.';

//@codingStandardsIgnoreStart
    public function __construct($message, $code = 404, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
//@codingStandardsIgnoreEnd

}

/**
 * Used when a component cannot be found.
 */
class MissingComponentException extends NataException {

    protected $_templateMessage = 'Component class %s could not be found.';

}

/**
 * Used when a behavior cannot be found.
 */
class MissingBehaviorException extends NataException {

    protected $_templateMessage = 'Behavior class %s could not be found.';

}

/**
 * Used when a view file cannot be found.
 */
class MissingViewException extends NataException {

    protected $_templateMessage = 'View class "%s" could not be found.';

}

/**
 * Used when a view file cannot be found.
 */
class TemplateCompilerException extends NataException {

}

/**
 * Used when a file passed to Merger cannot be found.
 */
class MissingMergerException extends NataException {

    protected $_templateMessage = 'Merger file "%s" is missing.';

}

/**
 * Used when a template file cannot be found.
 */
class MissingTemplateException extends NataException {

    protected $_templateMessage = 'Template file "%s" is missing.';

}

/**
 * Used when a layout file cannot be found.
 */
class MissingLayoutException extends NataException {

    protected $_templateMessage = 'Layout file "%s" is missing.';

}

/**
 * Used when a helper cannot be found.
 */
class MissingHelperException extends NataException {

    protected $_templateMessage = 'Helper class %s could not be found.';

}

/**
 * Used when a form cannot be found.
 */
class MissingFormException extends NataException {

    protected $_templateMessage = 'Form class %s could not be found.';

}

/**
 * Used when a CSRF token is invalid or missing.
 * Returns 403 Forbidden (client error) rather than 500.
 */
class InvalidCsrfTokenException extends ForbiddenException {

    protected $_templateMessage = 'Invalid CSRF token.';

/**
 * Constructor
 *
 * @param string $message If no message is given 'Invalid CSRF token.' will be the message
 * @param int $code Status code, defaults to 403
 */
    public function __construct($message = null, $code = 403) {
        if (empty($message)) {
            $message = 'Invalid CSRF token.';
        }
        parent::__construct($message, $code);
    }

}

/**
 * Runtime Exceptions for ConnectionManager
 */
class MissingDatabaseException extends NataException {

    protected $_templateMessage = 'Database connection "%s" could not be found.';

}

/**
 * Used when no connections can be found.
 */
class MissingConnectionException extends NataException {

    protected $_templateMessage = 'Database connection "%s" is missing, or could not be created.';

    public function __construct($message, $code = 500) {
        if (is_array($message)) {
            $message += array('enabled' => true);
        }
        parent::__construct($message, $code);
    }

}

/**
 * Exception class to be thrown when a datasource configuration is not found
 */
class MissingDatabaseConfigException extends NataException {

    protected $_templateMessage = 'The database configuration "%s" was not found in database.init.php';

}

/**
 * Used when a datasource cannot be found.
 */
class MissingDatasourceException extends NataException {

    protected $_templateMessage = 'Datasource class %s could not be found.';

}

/**
 * Exception class to be thrown when a database table is not found in the datasource
 */
class MissingTableException extends NataException {

    protected $_templateMessage = 'Table %s was not found.';

}
/**
 * Exception class to be thrown when a database table is not found in the datasource
 */
class MissingAssociationException extends NataException {

    protected $_templateMessage = 'Table %s is not associated with %s in %s.';

}

/**
 * Exception class to be thrown when a database table is not found in the datasource
 */
class TableAssociationException extends NataException {

    protected $_templateMessage = 'Error when trying to establish relationship %s between table %s and %s.';

}

/**
 * Exception class to be thrown when a database table is not found in the datasource
 */
class MissingEntityException extends NataException {

    protected $_templateMessage = 'Entity %s was not found.';

}
/**
 * Exception raised when a Model could not be found.
 */
class MissingModelException extends NataException {

    protected $_templateMessage = 'Model %s could not be found.';

}

/**
 * Exception raised when a Record is not found in database.
 */
class RecordNotFoundException extends NataException {

    protected $_templateMessage = 'Record not found in table "%s"';

    public function __construct($message, $code = 400) {
        if (is_array($message)) {
            $message += array('enabled' => true);
        }
        parent::__construct($message, $code);
    }
}

/**
 * Used when a shell method cannot be found.
 */
class MissingJobMethodException extends NataException {

    protected $_templateMessage = "Unknown command %1\$s %2\$s.\nFor usage try `nata %1\$s help`";

}

/**
 * Used when a shell cannot be found.
 */
class MissingJobException extends NataException {

    protected $_templateMessage = '(Cron)Job class %s could not be found.';

}

/**
 * Used when a shell cannot be found.
 */
class MissingCommandException extends NataException {

    protected $_templateMessage = 'Console command class %s could not be found.';

}

/**
 * Exception raised when a test loader could not be found
 */
class MissingTestLoaderException extends NataException {

    protected $_templateMessage = 'Test loader %s could not be found.';

}

/**
 * Exception raised when a plugin could not be found
 */
class MissingPluginException extends NataException {

    protected $_templateMessage = 'Plugin %s could not be found.';

}

/**
 * Exception raised when a Dispatcher filter could not be found
 */
class MissingDispatcherFilterException extends NataException {

    protected $_templateMessage = 'Dispatcher filter %s could not be found.';

}

/**
 * Exception raised when a class not be found by autoloader
 */
class MissingClassException extends NataException {

    protected $_templateMessage = 'Class %s not found in %s.';

}

/**
 * Exception class to be thrown when a database table is not found in the datasource
 */
class MissingConfigurationException extends NataException {

    protected $_templateMessage = 'Missing required configuration parameter %s.';

}

/**
 * Exception class for AclComponent and Interface implementations.
 */
class AclException extends NataException {
}

/**
 * Exception class for Cache.  This exception will be thrown from Cache when it
 * encounters an error.
 */
class CacheException extends NataException {
}

/**
 * Exception class for Form. This exception will be thrown from Form when it
 * encounters an error.
 */
class FormException extends NataException {
}

/**
 * Exception class for Table. This exception will be thrown from Table when it
 * encounters an error.
 *
 * @package Cake.Error
 */
class TableException extends NataException {
}

/**
 * Exception class for View. This exception will be thrown from View when it
 * encounters an error.
 *
 * @package Cake.Error
 */
class ViewException extends NataException {
}

/**
 * Exception class for Router.  This exception will be thrown from Router when it
 * encounters an error.
 */
class RouterException extends NataException {
}

/**
 * Exception class for NataLog.  This exception will be thrown from NataLog when it
 * encounters an error.
 */
class NataLogException extends NataException {
}

/**
 * Exception class for NataSession.  This exception will be thrown from NataSession when it
 * encounters an error.
 */
class NataSessionException extends NataException {
}

/**
 * Exception class for Configure.  This exception will be thrown from Configure when it
 * encounters an error.
 */
class ConfigureException extends NataException {
}

/**
 * Exception class for Socket. This exception will be thrown from CakeSocket, CakeEmail, HttpSocket
 * SmtpTransport, MailTransport and HttpResponse when it encounters an error.
 */
class SocketException extends NataException {
}

/**
 * Exception class for Xml.  This exception will be thrown from Xml when it
 * encounters an error.
 */
class XmlException extends NataException {
}

/**
 * Exception class for Console libraries.  This exception will be thrown from Console library
 * classes when they encounter an error.
 */
class ConsoleException extends NataException {
}

/**
 * Represents a fatal error
 */
class FatalErrorException extends NataException {

/**
 * Constructor
 *
 * @param string $message
 * @param integer $code
 * @param string $file
 * @param integer $line
 */
    public function __construct($message, $code = 500, $file = null, $line = null) {
        parent::__construct($message, $code);
        if ($file) {
            $this->file = $file;
        }
        if ($line) {
            $this->line = $line;
        }
    }
}

/**
 * Not Implemented Exception - used when an API method is not implemented
 */
class NotImplementedException extends NataException {

    protected $_templateMessage = '%s is not implemented.';

//@codingStandardsIgnoreStart
    public function __construct($message, $code = 501) {
        parent::__construct($message, $code);
    }
//@codingStandardsIgnoreEnd

}
