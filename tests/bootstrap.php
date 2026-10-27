<?php
/**
 * PHPUnit bootstrap for the framework's own tests: the constants and settings an
 * app defines in its front controller and config, pointing at this repository.
 */

use Nata\Core\Configure;

define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(__DIR__) . DS);
define('APP_DIR', 'src');
define('APP', ROOT . APP_DIR . DS);
define('TMP', sys_get_temp_dir() . DS . 'nata-tests' . DS);
define('LOGS', TMP . 'logs' . DS);

require ROOT . 'vendor' . DS . 'autoload.php';

// What an app's config sets and the framework reads
Configure::write('App.encoding', 'UTF-8');
