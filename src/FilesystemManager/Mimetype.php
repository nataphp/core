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

namespace Nata\FilesystemManager;

use Nata\Core\App;

/**
 * Holds and manages a list of valid mimetypes, by their extension.
 *
 * Allows to get the mimetype by given extension, validate extension
 * agains't list of mimetypes and one given, etc.
 */
class Mimetype {

/**
 * Holds known mime type mappings.
 *
 * @var array
 */
    protected static $_mimeTypes = [
        'html' => ['text/html', '*/*'],
        'htm' => ['text/html', '*/*'],
        'json' => 'application/json',
        'xml' => ['application/xml', 'text/xml'],
        'rss' => 'application/rss+xml',
        'ai' => 'application/postscript',
        'bcpio' => 'application/x-bcpio',
        'bin' => 'application/octet-stream',
        'ccad' => 'application/clariscad',
        'cdf' => 'application/x-netcdf',
        'class' => 'application/octet-stream',
        'cpio' => 'application/x-cpio',
        'cpt' => 'application/mac-compactpro',
        'csh' => 'application/x-csh',
        'csv' => ['text/csv', 'application/vnd.ms-excel'],
        'dcr' => 'application/x-director',
        'dir' => 'application/x-director',
        'dms' => 'application/octet-stream',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'drw' => 'application/drafting',
        'dvi' => 'application/x-dvi',
        'dwg' => 'application/acad',
        'dxf' => 'application/dxf',
        'dxr' => 'application/x-director',
        'eml' => ['message/rfc822', 'text/rfc822'],
        'rfc822' => 'eml',
        'eot' => 'application/vnd.ms-fontobject',
        'eps' => 'application/postscript',
        'exe' => 'application/octet-stream',
        'ez' => 'application/andrew-inset',
        'flv' => 'video/x-flv',
        'gtar' => 'application/x-gtar',
        'zip' => ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip', 'application/octet-stream'],
        'gz' => 'application/x-gzip',
        'bz2' => 'application/x-bzip',
        '7z' => 'application/x-7z-compressed',
        'rar' => ['application/x-rar', 'application/x-rar-compressed', 'application/octet-stream'],
        'hdf' => 'application/x-hdf',
        'hqx' => 'application/mac-binhex40',
        'ico' => 'image/x-icon',
        'ips' => 'application/x-ipscript',
        'ipx' => 'application/x-ipix',
        'js' => 'application/javascript',
        'latex' => 'application/x-latex',
        'lha' => 'application/octet-stream',
        'lsp' => 'application/x-lisp',
        'lzh' => 'application/octet-stream',
        'man' => 'application/x-troff-man',
        'me' => 'application/x-troff-me',
        'mif' => 'application/vnd.mif',
        'ms' => 'application/x-troff-ms',
        'nc' => 'application/x-netcdf',
        'oda' => 'application/oda',
        'otf' => 'font/otf',
        'odp' => 'application/vnd.oasis.opendocument.presentation',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'odg' => 'application/vnd.oasis.opendocument.graphics',
        'pdf' => 'application/pdf',
        'pgn' => 'application/x-chess-pgn',
        'php' => 'text/x-php',
        'pot' => 'application/vnd.ms-powerpoint',
        'pps' => 'application/vnd.ms-powerpoint',
        'ppt' => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/octet-stream'],
        'ppz' => 'application/vnd.ms-powerpoint',
        'pre' => 'application/x-freelance',
        'prt' => 'application/pro_eng',
        'ps' => 'application/postscript',
        'roff' => 'application/x-troff',
        'scm' => 'application/x-lotusscreencam',
        'set' => 'application/set',
        'sh' => 'application/x-sh',
        'shar' => 'application/x-shar',
        'sit' => 'application/x-stuffit',
        'skd' => 'application/x-koan',
        'skm' => 'application/x-koan',
        'skp' => 'application/x-koan',
        'skt' => 'application/x-koan',
        'smi' => 'application/smil',
        'smil' => 'application/smil',
        'sol' => 'application/solids',
        'spl' => 'application/x-futuresplash',
        'src' => 'application/x-wais-source',
        'step' => 'application/STEP',
        'stl' => 'application/SLA',
        'stp' => 'application/STEP',
        'sv4cpio' => 'application/x-sv4cpio',
        'sv4crc' => 'application/x-sv4crc',
        'svg' => ['image/svg+xml', 'image/svg'],
        'svgz' => ['image/svg+xml', 'image/svg'],
        'swf' => 'application/x-shockwave-flash',
        't' => 'application/x-troff',
        'tar' => 'application/x-tar',
        'tcl' => 'application/x-tcl',
        'tex' => 'application/x-tex',
        'texi' => 'application/x-texinfo',
        'texinfo' => 'application/x-texinfo',
        'tr' => 'application/x-troff',
        'tsp' => 'application/dsptype',
        'ttc' => 'font/ttf',
        'ttf' => 'font/ttf',
        'unv' => 'application/i-deas',
        'ustar' => 'application/x-ustar',
        'vcd' => 'application/x-cdlink',
        'vda' => 'application/vda',
        'xlc' => 'application/vnd.ms-excel',
        'xll' => 'application/vnd.ms-excel',
        'xlm' => 'application/vnd.ms-excel',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xlw' => 'application/vnd.ms-excel',
        'msg' => 'application/vnd.ms-outlook',
        'cat' => 'application/vnd.ms-pkiseccat',
        'p7s' => 'application/pkcs7-signature',
        'emz' => 'application/octet-stream',
        'emf' => ['image/emf', 'image/x-emf', 'application/emf', 'application/x-emf', 'image/x-emf', 'image/x-mgx-emf', 'image/x-xbitmap'],
        'aif' => 'audio/x-aiff',
        'aifc' => 'audio/x-aiff',
        'aiff' => 'audio/x-aiff',
        'au' => 'audio/basic',
        'kar' => 'audio/midi',
        'mid' => 'audio/midi',
        'midi' => 'audio/midi',
        'mp2' => 'audio/mpeg',
        'mp3' => 'audio/mpeg',
        'mpga' => 'audio/mpeg',
        'ogg' => 'audio/ogg',
        'oga' => 'audio/ogg',
        'spx' => 'audio/ogg',
        'ra' => 'audio/x-realaudio',
        'ram' => 'audio/x-pn-realaudio',
        'rm' => 'audio/x-pn-realaudio',
        'rpm' => 'audio/x-pn-realaudio-plugin',
        'snd' => 'audio/basic',
        'tsi' => 'audio/TSP-audio',
        'wav' => 'audio/x-wav',
        'aac' => 'audio/aac',
        'txt' => ['text/plain', 'text/x-algol68'],
        'text' => 'text/plain',
        'asc' => 'text/plain',
        'c' => 'text/plain',
        'cc' => 'text/plain',
        'css' => 'text/css',
        'etx' => 'text/x-setext',
        'f' => 'text/plain',
        'f90' => 'text/plain',
        'h' => 'text/plain',
        'hh' => 'text/plain',
        'ics' => 'text/calendar',
        'm' => 'text/plain',
        'rtf' => 'text/rtf',
        'rtx' => 'text/richtext',
        'sgm' => 'text/sgml',
        'sgml' => 'text/sgml',
        'tsv' => 'text/tab-separated-values',
        'tpl' => 'text/template',
        'avi' => 'video/x-msvideo',
        'fli' => 'video/x-fli',
        'mov' => 'video/quicktime',
        'movie' => 'video/x-sgi-movie',
        'mpe' => 'video/mpeg',
        'mpeg' => 'video/mpeg',
        'mpg' => 'video/mpeg',
        'qt' => 'video/quicktime',
        'viv' => 'video/vnd.vivo',
        'vivo' => 'video/vnd.vivo',
        'ogv' => 'video/ogg',
        'webm' => 'video/webm',
        'mp4' => 'video/mp4',
        'm4v' => 'video/mp4',
        'f4v' => 'video/mp4',
        'f4p' => 'video/mp4',
        'm4a' => 'audio/mp4',
        'f4a' => 'audio/mp4',
        'f4b' => 'audio/mp4',
        'gif' => 'image/gif',
        'ief' => 'image/ief',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpe' => 'image/jpeg',
        'pjpg' => 'image/pjpeg',
        'pjpeg' => 'image/pjpeg',
        'webp' => 'image/webp',
        'heif' => 'image/heif',
        'heic' => 'image/heic',
        'pbm' => 'image/x-portable-bitmap',
        'pgm' => 'image/x-portable-graymap',
        'png' => 'image/png',
        'bmp' => 'image/x-ms-bmp',
        'pnm' => 'image/x-portable-anymap',
        'ppm' => 'image/x-portable-pixmap',
        'psd' => 'image/vnd.adobe.photoshop',
        'ras' => 'image/cmu-raster',
        'rgb' => 'image/x-rgb',
        'tif' => 'image/tiff',
        'tiff' => ['image/tiff', 'image/x-canon-cr2', 'image/x-tiff'],
        'xbm' => 'image/x-xbitmap',
        'xpm' => 'image/x-xpixmap',
        'xwd' => 'image/x-xwindowdump',
        'ice' => 'x-conference/x-cooltalk',
        'iges' => 'model/iges',
        'igs' => 'model/iges',
        'mesh' => 'model/mesh',
        'msh' => 'model/mesh',
        'silo' => 'model/mesh',
        'vrml' => 'model/vrml',
        'wrl' => 'model/vrml',
        'mime' => 'www/mime',
        'pdb' => 'chemical/x-pdb',
        'xyz' => 'chemical/x-pdb',
        'javascript' => 'application/javascript',
        'form' => 'application/x-www-form-urlencoded',
        'file' => 'multipart/form-data',
        'xhtml' => ['application/xhtml+xml', 'application/xhtml', 'text/xhtml'],
        'xhtml-mobile' => 'application/vnd.wap.xhtml+xml',
        'atom' => 'application/atom+xml',
        'amf' => 'application/x-amf',
        'wap' => ['text/vnd.wap.wml', 'text/vnd.wap.wmlscript', 'image/vnd.wap.wbmp'],
        'wml' => 'text/vnd.wap.wml',
        'wmlscript' => 'text/vnd.wap.wmlscript',
        'wbmp' => 'image/vnd.wap.wbmp',
        'woff' => 'application/x-font-woff',
        'appcache' => 'text/cache-manifest',
        'manifest' => 'text/cache-manifest',
        'htc' => 'text/x-component',
        'rdf' => 'application/xml',
        'crx' => 'application/x-chrome-extension',
        'oex' => 'application/x-opera-extension',
        'xpi' => 'application/x-xpinstall',
        'safariextz' => 'application/octet-stream',
        'webapp' => 'application/x-web-app-manifest+json',
        'vcf' => ['text/x-vcard', 'text/vcard'],
        'vtt' => 'text/vtt',
        'dat' => ['application/octet-stream', 'zz-application/zz-winassoc-dat', 'application/dat'],
        'dwf' => ['model/vnd-dwf', 'application/dwf'],
        'dwfx' => ['model/vnd-dwf', 'model/vnd.dwfx+xps', 'application/dwf'],
    ];

    private static $_jpegSignatures = [
        "\xFF\xD8\xFF\xE0", // JPEG JFIF
        "\xFF\xD8\xFF\xE1", // JPEG EXIF
        "\xFF\xD8\xFF\xDB", // JPEG
        "\xFF\xD8\xFF\xEE", // JPEG with Adobe XMP metadata
    ];

/**
 * Caches known extensions by mimetype.
 *
 * @var array
 */
    protected static $_extensionsCache = [];


/**
 * Get list of supported mimetypes or respective mimetype(s) for
 * given extension.
 *
 * @param string $extension Get mimetype(s) for given extension
 * @param array Mimetype(s)
 */
    public static function get($extension = null): ?array {
        if (func_num_args() === 0) {
            return static::$_mimeTypes;
        }

        if (str_contains($extension, '/')) {
            return [$extension];
        }

        $extension = strtolower($extension);
        $mime = isset(static::$_mimeTypes[$extension]) ? static::$_mimeTypes[$extension] : null;

        // If given mime references another extension, obtain it by proxy
        if (is_string($mime) && isset(static::$_mimeTypes[$mime])) {
            $mime = static::$_mimeTypes[$mime];
        }

        if ($mime && !is_array($mime)) {
            $mime = [$mime];
        }

        return $mime;
    }

/**
 * Check if given extension matches given mimetype in map.
 *
 * @param string $extension Extension to check
 * @param string $mimetype Mimetype to check
 * @return bool True if valid, false otherwise
 */
    public static function isValid($extension, $mimetype): bool {
        $extensionMimetypes = static::get($extension);
        return $extensionMimetypes && array_intersect($extensionMimetypes, (array)$mimetype);
    }

/**
 * Check if given extension/mime matches given list of mimetypes.
 *
 * @param string $check Extension/mime to check
 * @param string|array $mimeList Mimetype(s) to check
 * @return bool True if matches, false otherwise
 */
    public static function is($check, $list = '*/*'): bool {
        $list = (array)$list;
        if (in_array('*/*', $list, true)) {
            return true;
        }

        // Check extension
        if (strpos($check, '/') === false) {
            if (!$mimes = static::get($check)) {
                return false;
            }
            [$check] = $mimes;
        }

        [$type] = explode('/', $check);
        if (in_array($type . '/*', $list, true)) {
            return true;
        }

        if (in_array($check, $list, true)) {
            return true;
        }

        $extensions = static::getExtensions($check);
        if ($extensions && $check && array_intersect($list, $extensions)) {
            return true;
        }
        return false;
    }

/**
 * Check if given extension/mime exists in mimetypes map.
 *
 * @param string $check Extension/mime to check if is part of the list
 * @return bool True if exists, false otherwise
 */
    public static function isKnown(string $check): bool {
        if (in_array($check, array_keys(static::$_mimeTypes))) {
            return true;
        }

        foreach (static::$_mimeTypes as $mimetypes) {
            if (in_array($check, (array)$mimetypes)) {
                return true;
            }
        }
        return false;
    }

/**
 * Get list of extensions associated with given mimetype.
 *
 * @param string $mimetype Mimetype
 * @param string Extension from mimtype
 * @return array Mimetype list of extensions
 */
    public static function getExtensions(string $mimetype, bool $dotPrepend = false): array {
        $mimetype = strtolower($mimetype);
        if (isset(static::$_extensionsCache[$mimetype . $dotPrepend])) {
            return static::$_extensionsCache[$mimetype . $dotPrepend];
        }

        $extensions = [];
        foreach (static::$_mimeTypes as $extension => $mimetypes) {
            if (!in_array($mimetype, (array)$mimetypes)) {
                continue;
            }
            $extensions[] = ($dotPrepend ? '.' : '') . $extension;
        }
        return static::$_extensionsCache[$mimetype . $dotPrepend] = $extensions;
    }

/**
 * Get file's extension based on mime type.
 *
 * Useful when source of file is not reliable (file upload, etc).
 * This method will return the file extension based on the mime type from
 * for example, File::mime().
 *
 * @param string $mimetype Mimetype
 * @param bool $dotPrepend Prepend dot to extension
 * @return string Extension found
 */
    public static function getExtension(string $mimetype, bool $dotPrepend = false): ?string {
        $mimetype = strtolower($mimetype);
        foreach (static::$_mimeTypes as $extension => $mimetypesList) {
            if (in_array($mimetype, (array)$mimetypesList)) {
                return ($dotPrepend ? '.' : '') . $extension;
            }
        }
        return null;
    }

/**
 * Get top level mimetype of given extension/mimetype.
 *
 * @param string $of Mimetype or extension
 * @return string MimeType top level
 */
    public static function getTopLevel(string $of): ?string {
        if (strpos($of, '/') === false) {
            if (!$of = static::get($of)) {
                return null;
            }
            [$of] = $of;
        }

        [$top] = explode('/', $of);

        return $top;
    }

/**
 * Add to list of supported mimetypes.
 *
 * @param string|array $extension Set extension
 * @param string|array $mimetypes Extension mimetypes
 * @return void
 */
    public static function add($extension, $mimetypes = null) {
        if (!is_array($extension)) {
            $extension = [$extension => $mimetypes];
        }
        static::$_mimeTypes = array_merge(static::$_mimeTypes, $extension);
    }

/**
 * Replace extension for a given filename.
 *
 * @param string $mime Mimetype
 * @param string $filename Set extension
 * @return string
 */
    public static function replaceExtension(string $mime, string $filename): string {
        $ext = static::getExtension($mime, true);
        return preg_replace('/\.[^.]*$/', $ext, $filename);
    }

/**
 * Detect mimetype from file path/contents using Fileinfo.
 *
 * @param string $file File path or contents
 * @param string|null $fallback Fallback mimetype/extension if cannot be determined
 * @return string|null Detected mimetype or null if cannot be determined
 */
    public static function detect(string $file, string|null $fallback = null): string|null {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return null;
        }

        $invalidMimetype = function ($mime) {
            return !$mime || $mime === 'application/octet-stream' || $mime === 'inode/x-empty';
        };

        // For when it's a file path
        if (is_file($file)) {
            if (!is_readable($file)) {
                finfo_close($finfo);
                return null;
            }

            $mimeType = finfo_file($finfo, $file);
            if ($invalidMimetype($mimeType) === false && function_exists('mime_content_type')) {
                $mimeType = mime_content_type($file);
            }

            if (App::isWindows() === false && $invalidMimetype($mimeType) === false) {
                $filename = escapeshellcmd($file);
                $command = sprintf("file -b --mime-type -m /usr/share/misc/magic %s", $filename);
                $mimeType = trim(shell_exec($command));
            }
        } else {
            $mimeType = finfo_buffer($finfo, $file);
        }

        // Fallback check for file signatures when detection fails
        if ($invalidMimetype($mimeType)) {
            if (self::_isJpeg($file)) {
                $mimeType = 'image/jpeg';
            } elseif (self::_isMp4($file)) {
                $mimeType = 'video/mp4';
            }
        }

        finfo_close($finfo);

        // Use fallback if detection failed
        if ($invalidMimetype($mimeType) && $fallback) {
            if (str_contains($fallback, '/') === false) {
                [$fallback] = static::get($fallback);
            }
            return $fallback;
        }

        return $mimeType;
    }

/**
 * Check if the file is a JPEG based on its signature.
 *
 * @param string $file File path or contents
 * @return bool True if the file is a JPEG, false otherwise
 */
    private static function _isJpeg(string $file): bool {
        if (str_contains($file, '/') && is_file($file)) {
            $fileHeader = file_get_contents($file, false, null, 0, 4);
        } else {
            $fileHeader = substr($file, 0, 4);
        }
        foreach (static::$_jpegSignatures as $signature) {
            if (strpos($fileHeader, $signature) === 0) {
                return true;
            }
        }
        return false;
    }

/**
 * Check if the file is an MP4 based on its signature.
 * MP4 files start with a box structure: 4 bytes size, then "ftyp", then brand identifier.
 *
 * @param string $file File path or contents
 * @return bool True if the file is an MP4, false otherwise
 */
    private static function _isMp4(string $file): bool {
        // Need at least 12 bytes to check MP4 signature
        if (str_contains($file, '/') && is_file($file)) {
            $fileHeader = @file_get_contents($file, false, null, 0, 32);
            if ($fileHeader === false || strlen($fileHeader) < 12) {
                return false;
            }
        } else {
            if (strlen($file) < 12) {
                return false;
            }
            $fileHeader = substr($file, 0, 32);
        }

        // Check for "ftyp" at offset 4 (after 4-byte box size)
        if (substr($fileHeader, 4, 4) !== "\x66\x74\x79\x70") {
            return false;
        }

        // Check for "mp4" brand identifier at offset 8 or later
        // MP4 files can have different brand identifiers, but should contain "mp4"
        $brandArea = substr($fileHeader, 8, min(24, strlen($fileHeader) - 8));
        if (strpos($brandArea, "\x6D\x70\x34") !== false) {
            return true;
        }

        // Also check for common MP4 brand identifiers
        $commonBrands = ["isom", "iso2", "avc1", "mp41", "mp42"];
        foreach ($commonBrands as $brand) {
            if (strpos($brandArea, $brand) !== false) {
                return true;
            }
        }

        return false;
    }

/**
 * Check if given mimetype is a rasterized image.
 *
 * @param string $mimetype Mimetype to check
 * @return bool True if is a rasterized image, false otherwise
 */
    public static function isRaster(string $mimetype): bool {
        return in_array($mimetype, ['image/gif', 'image/jpeg', 'image/png', 'image/webp', 'image/heif', 'image/bmp', 'image/tiff']);
    }

}
