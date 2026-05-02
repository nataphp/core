<?php
/**
 * NataPHP Framework
 *
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

namespace Nata\FilesystemManager\File;

use Nata\Core\App;
use Nata\FilesystemManager\File\DataSourceInterface;
use Nata\FilesystemManager\FileStorage;
use InvalidArgumentException;

/**
 * Data source factory.
 * This class is used to create instances of `Nata\FilesystemManager\File\DataSource\DataSource`.
 */
class DataSourceFactory {

/**
 * Build a datasource instance based on the file path or file contents.
 *
 * @param mixed $file File source/contents
 * @param array $options Options
 * @return DataSourceInterface
 */
    public static function build(mixed $file, array $options = []): DataSourceInterface {
        if ($file instanceof DataSourceInterface) {
            return $file;
        }

        if ($file === null) {
            throw new InvalidArgumentException('File cannot be null');
        }

        $dataSource = $options['datasource'] ?? static::_detectDataSource($file);
        $className = App::className($dataSource, 'FilesystemManager/File/DataSource');
        if (!$className) {
            $className = '\Nata\FilesystemManager\File\DataSource\Local';
        }
        return new $className($file, $options);
    }

/**
 * Detect the data source class to use for a given path.
 *
 * @param string $path Path to file
 * @return string Data source class name
 */
    protected static function _detectDataSource(string &$path) {
        [$datasource, $data] = splitter($path, ':');
        switch ($datasource) {
            case 'http':
            case 'https':
                $datasource = 'Web';
                break;
            case 'natafs':
                $path = FileStorage::get($path);
                $datasource = static::_detectDataSource($path);
                break;
            case 'data':
                $datasource = 'DataUri';
                $path = $data;
                break;
            case 'memory':
            case 'virtual':
                $datasource = 'Memory';
                $path = $data;
                break;
            default:
                $datasource = 'Local';
                break;
        }
        return $datasource;
    }

}
