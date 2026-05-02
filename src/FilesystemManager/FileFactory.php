<?php
/**
 * NataPHP Framework.
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

namespace Nata\FilesystemManager;

use Nata\FilesystemManager\File;
use Nata\FilesystemManager\File\Image;
use Nata\Core\App;

/**
 * File class constructor factory to create file instances by type of file (if class for that type exists).
 */
class FileFactory {

/**
 * Build File instance.
 *
 * #### Usage with local file
 * ```
 * $file = FileFactory::build('/full/path/to/local/file.jpg', $options);
 * $file->mime(); // image/jpeg
 * $file->size(); // 1024
 * ```
 *
 * #### Usage with data URI
 * ```
 * $file = FileFactory::build('data:image/jpeg;base64,....', $options);
 * $file->mime(); // image/jpeg
 * $file->size(); // 1024
 * ```
 *
 * #### Usage with URL
 * ```
 * $file = FileFactory::build('http://example.com/file.jpg', $options);
 * $file->mime(); // image/jpeg
 * $file->size(); // 1024
 * ```
 *
 * ### Options
 * - `type` - Type of file (image, video, etc)
 * - `name` - File name
 * - `mime` - File mime type
 * - `mimeFallback` - File mime type fallback
 * - `datasource` - File datasource
 *
 * @param mixed $path File URL/Data URI, absolute path or contents of file
 * @param array $options Options
 * @return File|Image File
 */
    public static function build(mixed $path, $options = []): File {
        if ($path instanceof File) {
            return $path;
        }

        $options += [
            'type' => null,
            'name' => null,
            'extension' => null,
            'size' => null,
            'hash' => [],
            'mime' => null,
            'mimeFallback' => null,
            'datasource' => null
        ];

        if (!$options['type']) {
            $options['type'] = static::_detectType($path, $options);
        }

        $className = static::_getClassName($options);
        return new $className($path, $options);
    }

/**
 * Detect type of file.
 *
 * @param mixed $path File URL/Data URI, absolute path or contents of file
 * @return string File type
 */
    protected static function _detectType($path, $options): ?string {
        $type = null;
        if (strpos($path, 'data:') === 0) {
            $path = str_replace('data:', '', $path);
            [$type] = explode(';', $path);
        } elseif ($options['mime'] || $options['mimeFallback']) {
            $type = $options['mime'] ?? $options['mimeFallback'];
        } elseif ($extension = pathinfo($path, PATHINFO_EXTENSION)) {
            [$type] = Mimetype::get($extension);
        }
        return $type;
    }

/**
 * Get file class name.
 *
 * @param array $options Options
 * @return string Class name
 */
    protected static function _getClassName($options): string {
        $baseClass = '\Nata\FilesystemManager\File';
        $className = null;
        if ($options['type']) {
            $parts = explode('/', $options['type']);
            $parts = array_map('ucfirst', $parts);
            $type = implode('/', $parts);

            $className = App::className($type, 'FilesystemManager/File');
            if (!$className) {
                $className = App::className($parts[0], 'FilesystemManager/File');
            }
        }

        if (!$className || !is_subclass_of($className, $baseClass, true)) {
            $className = $baseClass;
        }

        return $className;
    }

}
