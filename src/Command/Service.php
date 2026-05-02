<?php
/**
 * Base class for Jobs
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
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         CakePHP(tm) v 1.2.0.5012
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

namespace Nata\Command;

use Exception;
use Nata\Console\Arguments;
use Nata\Console\Command;
use Nata\Console\Io;
use Nata\Console\OptionParser;
use Nata\Service\ServiceBuilder;
use Nata\Utility\Inflector;

/**
 * Run services from the command line.
 */
class Service extends Command {


/**
 * Execute command.
 *
 * @param Arguments $args Arguments
 * @param Io $io Console Input/output
 * @return int|null Exit code
 */
    public function execute(Arguments $args, Io $io): ?int {
        $this->_executeService($args);
        return Command::CODE_SUCCESS;
    }

/**
 * Dispatches a CLI request.
 *
 * @return boolean
 * @throws MissingJobMethodException
 */
    protected function _executeService(Arguments $args) {
        $serviceName = $args->getArgument(0);
        $serviceName = Inflector::camelize($serviceName);
        if (!ServiceBuilder::exists($serviceName)) {
            throw new Exception(sprintf('Missing service "%s".', $serviceName));
        }

        $service = ServiceBuilder::build($serviceName, $this->config());
        if (isset($args[1]) && ($args[1] === 'start' || $args[1] === 'stop')) {
            $service->{$args[1]}();
        }

        return $service;
    }

/**
 * Build option parser.
 *
 * @param OptionParser $optionParser Option parser
 * @return OptionParser
 */
    protected function _buildOptionParser($optionParser): OptionParser {
        return $optionParser;
    }

}
