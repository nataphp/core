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

namespace Nata\Filesystem;

use Nata\Core\App;
use Nata\Filesystem\File;
use Nata\Filesystem\File\Image;
use Nata\Filesystem\File\Image\Editor;
use Nata\Filesystem\Folder;
use Nata\Http\Client;
use Nata\Log\Log;
use Nata\Utility\Text;

/**
 * This class manages the file's repository.
 *
 * It organizes files by their type in respective folders.
 * Files are saved with their SHA1 hash as filename.
 */
class FileRepository {

/**
 * Base directory where to organize and save files.
 * This is must be relative to App folder as root.
 *
 * @var string
 */
    protected static $_basePath = '/public/files';

/**
 * List of valid mime types.
 *
 * @var array
 * @see https://en.wikipedia.org/wiki/Media_type#Naming
 */
    protected static $_validTypes = [
        'application',
        'audio',
        'example',
        'image',
        'message',
        'model',
        'multipart',
        'text',
        'video'
    ];

/**
 * \Nata\Filesystem\File and \Nata\Filesystem\Folder instances
 * holder.
 *
 * @var array
 */
    protected static $_registry = [];

/**
 * Holds the instances options
 *
 * @var array
 */
    protected static $_options = [];

/**
 * Holds the last error.
 *
 * @var string
 */
    protected static $_lastError;


/**
 * Get \Nata\Filesystem\File instance by giving a file name
 * or \Nata\Filesystem\Folder if given one of valid types of files.
 *
 * @param string $name File name/type
 * @param array $options File options
 * @return \Nata\Filesystem\File|\Nata\Filesystem\Folder
 */
    public static function get($name, array $options = []) {
        if ($name instanceof File || $name instanceof Folder) {
            return $name;
        } elseif (isset(static::$_registry[$name])) {
            return static::$_registry[$name];
        } elseif (in_array($name, static::$_validTypes)) {
            return static::$_registry[$name] = static::_folderInstance($name);
        } elseif (!is_string($name)) {
            return;
        }

        $repositoryFile = static::_findRepositoryFile($name, $options);
        if (!$repositoryFile || !$repositoryFile->exists()) {
            return null;
        }

        static::$_registry[$name] = $repositoryFile;

        return static::$_registry[$name]->readOnly(true);
    }

/**
 * Get \Nata\Filesystem\File instance by giving a file name
 * or \Nata\Filesystem\Folder if given one of valid types of files.
 *
 * @param string $name File name/type
 * @param array $options File options
 * @return \Nata\Filesystem\File|\Nata\Filesystem\Folder
 */
    protected static function _findRepositoryFile($filename, array $options) {
        $options += [
            'mime' => null
        ];

        $mime = $hash = $extension = null;
        if ($filename instanceof File) {
            $file = $filename;
            $filename = $file->pwd();
            $mime = $file->mime();
            $extension = $file->extension();
            $hash = $file->sha1(true);
        }

        if ($hash === null) {
            $hash = pathinfo($filename, PATHINFO_FILENAME);
        }

        if ($mime === null) {
            if (!$extension) {
                $extension = pathinfo($filename, PATHINFO_EXTENSION);
            }
            $mime = Mimetype::get($extension);
        }

        if (empty($mime) && $options['mime']) {
            $mime = $options['mime'];
        }

        $mimes = (array)$mime;
        foreach ($mimes as $mime) {
            $repositoryDirPath = static::_getRepositoryFolderPath($mime);
            $repositoryFilePath = $repositoryDirPath . $hash;

            // Check if there's a mime, it's invalid and we've got a better extension for it...
            if ($extension && $mime && !Mimetype::isValid($extension, $mime) && Mimetype::getExtension($mime)) {
                $extension = Mimetype::getExtension($mime);
            }
            if (!empty($extension)) {
                $repositoryFilePath .= '.' . strtolower($extension);
            }

            $fileType = Mimetype::getTopLevel($mime);
            $className = static::_fileClassName($fileType);
            $repositoryFile = new $className($repositoryFilePath);
            if (!$repositoryFile->exists()) {
                continue;
            }

            return $repositoryFile;
        }

        return null;
    }

/**
 * Get absolute path to file's repository path.
 *
 * @return string Absolute path to repository
 */
    protected static function _getRepositoryBasePath() {
        $filePath = static::$_basePath;
        return App::path($filePath);
    }

/**
 * Get public URL to given file.
 *
 * @param string|integer $file File identifier
 * @return string URL to file
 */
    private static function _fileClassName($type) {
        $class = ucfirst($type);
        $className = App::className($class, 'Filesystem\File');

        if (!$className) {
            $className = '\Nata\Filesystem\File';
        }

        return $className;
    }

/**
 * Get public URL to to given file.
 *
 * @param string|integer $file File identifier
 * @return string URL to file
 */
    private static function _folderInstance($name) {
        return new Folder(App::path(static::$_basePath . DS . $name));
    }

/**
 * Get Nata-relative URL to to given file.
 * The returned url string, still needs to pass through \Nata\Routing\Router::url()
 * to be publicly accessible.
 *
 * @param \Nata\Filesystem\File|string $file File name/instance
 * @return string File's relative URL
 */
    public static function url($file) {
        $file = static::get($file);
        $path = App::path('public');
        if (!($file instanceof File) || strpos($file->pwd(), $path) === false) {
            return;
        }

        [$path, $publicPath] = explode($path, $file->pwd(), 2);

        return str_replace(DS, '/', $publicPath);
    }

/**
 * Import file(s) to file repository.
 *
 *  // Save file as '/public/files/image/file.jpg'
 *  FileRepository::import('/absolute/path/to/file.jpg');
 *
 * ### Options
 *
 * @param \Nata\Filesystem\File|string|array $files File(s) to import
 * @param array $options File saving options
 * @return \Nata\Filesystem\File File instance
 */
    public static function import($files, array $options = []) {
        $single = false;
        static::$_lastError = null;

        $options += [
            'deleteSource' => false,
            'accept' => '*/*',
            'preview' => false
        ];

        if (!is_array($files) && !is_string($files) && !($files instanceof File)) {
            return false;
        }

        if (is_string($files)) {
            $files = new File($files);
        }

        if ($files instanceof File) {
            $files = [$files];
            $single = true;
        }

        $imported = [];
        foreach ($files as $path) {
            if (strpos($path, 'http') === 0) {
                if (!$path = static::_downloadRemoteFile($path, $options)) {
                    continue;
                }
            }

            $imported[] = static::_import($path, $options);
        }

        if ($single) {
            return !empty($imported) ? $imported[0] : null;
        }

        return $imported;
    }

/**
 * @deprecated Use import() instead
 */
    public static function save($file, array $options = []) {
        return static::import($file, $options);
    }

/**
 * Import file to file repository.
 *
 * @param array $sourceFile Source file info
 * @param array $options File saving options
 * @return \Nata\Filesystem\File File instance
 * @throws \NataException
 */
    protected static function _import($sourceFile, $options) {
        if (!($sourceFile instanceof File)) {
            $sourceFile = new File($sourceFile);
        }

        if (!$sourceFile->exists()) {
            static::_setError(__x('filesystem', "Source file '%s' doesn't exist.", $sourceFile ? $sourceFile->pwd() : null));
            return false;
        } elseif (!static::_isAllowed($sourceFile, $options)) {
            static::_setError(
                __x('filesystem', "Source file '%s' MIME type '%s' is not allowed. Allowed: %s", [
                    $sourceFile ? $sourceFile->pwd() : null,
                    $sourceFile ? $sourceFile->mime() : null,
                    Text::toList((array)$options['accept'], __('and'))
                ])
            );
            return false;
        }

        $repositoryFile = static::_getRepositoryFile($sourceFile, $options);

        // Check if there's enough free space to save file
        $basePath = App::path(static::$_basePath);
        $freeSpace = disk_free_space($basePath);
        if ($sourceFile->size() > $freeSpace) {
            static::_setError(
                __x('filesystem', "Not enough free space to save file '%s' (free: %s needed: %s).", [$sourceFile->pwd(), $freeSpace, $sourceFile->size()])
            );
            return false;
        }

        if ($options['preview'] === true) {
            return $repositoryFile;
        }

        if (!$repositoryFile->exists() || $repositoryFile->size() !== $sourceFile->size()) {
            $repositoryFile = $sourceFile->copy($repositoryFile->pwd());
        }

        if (!$repositoryFile || !$repositoryFile->exists()) {
            $error = sprintf("Error copying file '%s'.", $sourceFile->pwd());
            if ($repositoryFile instanceof File) {
                $error = sprintf("Error copying file '%s' to '%s'.", $sourceFile->pwd(), $repositoryFile->pwd());
            }
            static::_setError($error);
            return false;
        }

        if ($repositoryFile->is('image')) {
            $repositoryFile = new Image($repositoryFile->pwd());

            if ($repositoryFile->getExif()->orientation > 1) {
                $extension = $repositoryFile->extension(true);
                $editor = new Editor($repositoryFile);
                $repositoryFile = $editor->fixOrientation()->save(true);
                $basename = $repositoryFile->sha1(true) . $extension;
                $repositoryFile->rename($basename);
            }

        }

        if (strpos($sourceFile->name(), 'nata_tmp_') === 0 || $options['deleteSource'] === true) {
            $sourceFile->delete();
        }

        return $repositoryFile;
    }

/**
 * Download remote file.
 *
 * @param string $url File URL
 * @return \Nata\Filesystem\File If successful
 */
    protected static function _downloadRemoteFile($url, $options) {
        $basename = pathinfo($url, PATHINFO_BASENAME);

        list($basename) = explode('?', $basename);

        $response = Client::get($url)
            ->options(CURLOPT_SSL_VERIFYPEER, false)
            ->send();

        $file = new File(sys_get_temp_dir() . DS . 'nata_tmp_' . $basename);

        if (!$file->write($response->getBody())) {
            $error = sprintf(
                'Unable to save downloaded file from "%s".',
                $url
            );
            Log::error($error);
            static::_setError($error);
            return;
        }

        $mime = $file->mime();

        // Fallback for empty mimetype.
        if (empty($mime)) {
            $extension = pathinfo($basename, PATHINFO_EXTENSION);
            $mime = File::mimeFromExtension(strtolower($extension));
            if (is_array($mime)) {
                $mime = array_shift($mime);
            }
        }

        list($contentType, $charset) = splitter(strtolower($response->getHeader('Content-Type')), ';');

        $accept = (array)$options['accept'];
        $error = null;

        // Check if MIME type is accepted.
        if (!static::_isAllowed($mime, $options)) {
            $error = sprintf(
                'Downloaded file "%s" with MIME "%s", accepts only "%s".',
                $url,
                $mime,
                implode(', ', $accept)
            );
        }

        if ($contentType !== 'application/octet-stream' && $file->mime() != $contentType) {
            $error = sprintf(
                'Downloaded file "%s" with mime "%s", expected from Content-Type "%s".',
                $url,
                $file->mime(),
                $contentType
            );
        }

        if ($error) {
            if ($file->delete()) {
                $error .= ' Downloaded file deleted.';
            }

            Log::error($error);

            return;
        }

        return $file;
    }

/**
 * Check if file is part of the accepted MIME types.
 *
 * @param string|File $mime MIME type
 * @param array $accept Accepted MIME types
 * @return boolean True if allowed, false otherwise
 */
    protected static function _isAllowed($check, $options) {
        if ($check instanceof File) {
            $check = $check->mime();
        }
        return Mimetype::is($check, $options['accept']);
    }

/**
 * Get repository \Nata\Filesystem\File for given file/name.
 *
 * @param string|File $file Source file/name
 * @return File Repository file instance
 */
    private static function _getRepositoryFile($filename, $options) {
        $mime = $hash = $extension = null;
        if ($filename instanceof File) {
            $file = $filename;
            $filename = $file->pwd();
            $mime = $file->mime();
            $extension = $file->extension();
            $hash = $file->sha1(true);
        }

        if ($hash === null) {
            $hash = pathinfo($filename, PATHINFO_FILENAME);
        }

        if ($mime === null) {
            if (!$extension) {
                $extension = pathinfo($filename, PATHINFO_EXTENSION);
            }

            $mime = Mimetype::get($extension);
            if (is_array($mime)) {
                $mime = array_shift($mime);
            }
        }

        // Check if there's a mime, it's invalid and we've got a better extension for it...
        if ($mime && !Mimetype::isValid($extension, $mime) && Mimetype::getExtension($mime)) {
            $extension = Mimetype::getExtension($mime);
        }

        $repositoryDirPath = static::_getRepositoryFolderPath($mime);
        $repositoryFilePath = $repositoryDirPath . $hash;
        if (!empty($extension)) {
            $repositoryFilePath .= '.' . strtolower($extension);
        }

        $fileType = Mimetype::getTopLevel($mime);
        $className = static::_fileClassName($fileType);

        return new $className($repositoryFilePath);
    }

/**
 * Get absolute path to repository folder (based on given mimetype).
 *
 * @param string $mime Mimetype
 * @return string Absolute path to repository folder in respect to given mimetype
 */
    private static function _getRepositoryFolderPath($mime) {
        if ($mime instanceof File) {
            $mime = $mime->mime();
        }

        if (empty($mime)) {
            $mime = 'application/octet-stream';
        }

        $filePath = static::$_basePath;
        $filePath .= DS . Mimetype::getTopLevel($mime) . DS;

        $path = App::path($filePath);
        // Check if repository directory exists
        if (!is_dir($path)) {
            mkdir($path);
        }

        return $path;
    }

/**
 * Get absolute file path.
 *
 * @param string $file File basename
 * @return string Absolute path to file in repository
 */
    public static function index($hash = null) {
        $path = static::_getRepositoryBasePath();

        $folder = new Folder($path);

        list($dirs) = $folder->read();

        $index = [];

        foreach ($dirs as $dir) {
            $subDir = new Folder($folder->realpath($dir));

            list($d, $files) = $subDir->read();

            foreach ($files as $file) {
                $key = substr($file, 0, 10);
                $index = array_merge($index, [$key => $file]);
            }

        }

        file_put_contents($path . DS . 'index.json', json_encode($index));

    }

/**
 * Set error.
 *
 * @param string $string Error
 * @return void
 */
    protected static function _setError($error) {
        return static::$_lastError = $error;
    }

/**
 * Check if there's an error.
 *
 * @return bool Has error
 */
    public static function hasError() {
        return static::$_lastError !== null;
    }

/**
 * Get last error.
 *
 * @return string Last error
 */
    public static function getLastError() {
        return static::$_lastError;
    }

}
