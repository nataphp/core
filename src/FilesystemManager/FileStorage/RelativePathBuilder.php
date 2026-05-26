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

namespace Nata\FilesystemManager\FileStorage;

use Nata\FilesystemManager\File\Image;
use Nata\FilesystemManager\File;
use Nata\FilesystemManager\Mimetype;
use Nata\Utility\Text;
use Nata\FilesystemManager\Exception\RelativePathBuilderException;
use Closure;

/**
 * Creates relative paths for files based on a template.
 */
class RelativePathBuilder {

/**
 * Template parsing regex cache.
 * Given a template, this property will hold a list of
 * required data for placeholders.
 *
 * @var array
 */
    protected static $_regexCache = [];

/**
 * Build relative file path.
 *
 * ### Example:
 *
 * ```php
 * $file = new File('path/to/file.jpg');
 * $template = '/files/{mime_top_level}/{sha1_5}/{name}{extension}';
 * $relativePath = RelativePathBuilder::build($template, $file);
 * ```
 * Will return:
 * ```
 * '/files/image/0a1b2/0a1b2c3d4e5f6.jpg'
 *
 * @param File|string|array $data File data for placeholders
 * @param string $template Template
 * @param array $options Options
 * @return string Relative path
 * @throws RelativePathBuilderException When required data is missing
 */
    public static function build(File|string|array $data, string $template, array $options = []): ?string {
        $options += [
            'checkMissing' => false,
            'before' => '{',
            'after' => '}'
        ];

        if (is_string($data) && str_contains($data, '/')) {
            return $data;
        }

        $placeholders = static::_templatePlaceholders($template);
        if ($data instanceof File) {
            $data = static::_extractDataFromFile($data, $placeholders);
        } elseif (is_string($data)) {
            $data = static::_extractDataFromFilename($data, $placeholders);
        } elseif (is_array($data)) {
            static::_extractData($data, $placeholders);
        }

        foreach ($placeholders as $placeholder) {
            $parts = explode('_', $placeholder);
            if (count($parts) === 2 && is_numeric($parts[1]) && $parts[1] > 0) {
                $data[$placeholder] = substr($data[$parts[0]], 0, (int)$parts[1]);
            }
        }

        if (isset($data['mime']) && !isset($data['mime_top_level']) && in_array('mime_top_level', $placeholders)) {
            $data['mime_top_level'] = Mimetype::getTopLevel($data['mime']);
        }

        if ($options['checkMissing']) {
            $missing = array_diff($placeholders, array_keys(array_filter($data, function ($value) {
                return $value !== null;
            })));
            if ($missing) {
                throw new RelativePathBuilderException(sprintf('Missing required data for template: "%s"', implode('", "', $missing)));
            }
        }

        $path = Text::insert($template, $data, [
            'before' => $options['before'],
            'after' => $options['after']
        ]);

        return $path;
    }

/**
 * Get data from filename/path.
 *
 * @param string $path Path to file
 * @param array $placeholders Placeholders
 * @return array Data
 */
    protected static function _extractDataFromFilename(string $path, array $placeholders): array {
        if (!str_contains($path, '.')) {
            return [];
        }

        $parts = explode('/', $path);
        $filename = array_pop($parts);
        [$sha1, $extension] = explode('.', $filename);
        if (!preg_match("/^[a-fA-F0-9]{40}$/", $sha1)) {
            return [];
        }

        [$mime] = Mimetype::get($extension);
        if ($mime) {
            $mimeTopLevel = Mimetype::getTopLevel($mime);
        }

        return [
            'sha1' => $sha1,
            'extension' => '.' . $extension,
            'mime' => $mime,
            'mime_top_level' => $mimeTopLevel
        ];
    }

/**
 * Prepare given data.
 *
 * @param array $data Data
 * @param array $placeholders Placeholders
 * @return void
 */
    protected static function _extractData(array &$data, array $placeholders): void {
        $data += [
            'extension' => null,
            'mime' => null
        ];

        if (!$data['mime'] && $data['extension']) {
            [$data['mime']] = Mimetype::get($data['extension']);
        } elseif (!$data['extension'] && $data['mime']) {
            $data['extension'] = Mimetype::getExtension($data['mime']);
        }
        if ($data['extension']) {
            $data['extension'] = '.' . ltrim($data['extension'], '.');
        }

        $mimeTopLevel = null;
        if ($data['mime']) {
        }
    }

/**
 * Extract data from file.
 *
 * @param File $file File
 * @param array $placeholders Placeholders
 * @return array Data
 */
    protected static function _extractDataFromFile(File $file, array $placeholders): array {
        $sha1 = $file->sha1(true);
        $sha256 = $file->hash('sha256');
        $mime = $file->mime();
        $mimeTopLevel = Mimetype::getTopLevel($mime);

        $data = [
            'sha1' => $sha1,
            'sha256' => $sha256,
            'name' => function () use ($file) {
                return $file->name();
            },
            'basename' => function () use ($file) {
                return $file->basename();
            },
            'extension' => function () use ($file) {
                return $file->extension(true);
            },
            'mime' => $mime,
            'mime_top_level' => $mimeTopLevel
        ];

        if ($file instanceof Image) {
            $data += [
                'width' => function () use ($file) {
                    return $file->width();
                },
                'height' => function () use ($file) {
                    return $file->height();
                },
                'aspect_ratio' => function () use ($file) {
                    return $file->aspectRatio();
                }
            ];
        }

        $_data = [];
        foreach ($placeholders as $placeholder) {
            if (!isset($data[$placeholder])) {
                continue;
            }
            $_data[$placeholder] = $data[$placeholder] instanceof Closure ? $data[$placeholder]() : $data[$placeholder];
        }
        return $_data;
    }

/**
 * Get placeholders present in template.
 *
 * @param string $template Template
 * @return array Template placeholders
 */
    protected static function _templatePlaceholders(string $template): array {
        if (isset(static::$_regexCache[$template])) {
            return static::$_regexCache[$template];
        }
        if (!preg_match_all("/\{([_0-9a-z]+)\}?/i", $template, $matches)) {
            return [];
        }
        return static::$_regexCache[$template] = $matches[1];
    }

}
