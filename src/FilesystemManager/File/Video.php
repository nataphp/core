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

namespace Nata\FilesystemManager\File;

use Nata\FilesystemManager\File;
use Nata\Utility\Math;

/**
 * Video file class.
 *
 * Wraps a video file and exposes its metadata (dimensions, duration,
 * orientation, aspect ratio) plus processing helpers. PHP has no native video
 * support, so every operation shells out to the ffmpeg toolkit: `ffprobe` for
 * inspection and `ffmpeg` for the faststart remux. All of them degrade
 * gracefully to a safe no-op / empty result when the toolkit is unavailable.
 */
class Video extends File {

/**
 * Video width in pixels (display orientation).
 *
 * @var int|null
 */
    protected $_width;

/**
 * Video height in pixels (display orientation).
 *
 * @var int|null
 */
    protected $_height;

/**
 * Video orientation ('portrait' or 'landscape').
 *
 * @var string|null
 */
    protected $_orientation;

/**
 * Display aspect ratio (e.g. "16:9").
 *
 * @var string|null
 */
    protected $_aspectRatio;

/**
 * Duration in seconds.
 *
 * @var float|null
 */
    protected $_duration;

/**
 * Cached ffprobe result (decoded JSON with 'streams' and 'format' keys).
 *
 * @var array|null
 */
    protected $_probe;

/**
 * Cached result of the ffmpeg availability probe (null = not yet probed).
 *
 * @var bool|null
 */
    protected static $_ffmpegAvailable;


/**
 * Constructor.
 *
 * @param mixed $path Video path/URL/contents.
 * @param array $options Options; `width`, `height` and `aspectRatio` seed
 *  known values so ffprobe is not invoked when they are already available.
 * @see \Nata\FilesystemManager\File::__construct()
 */
    public function __construct($path, array $options = []) {
        $options += [
            'width' => null,
            'height' => null,
            'aspectRatio' => null,
        ];

        if ($options['width'] !== null) {
            $this->_width = $options['width'];
        }
        if ($options['height'] !== null) {
            $this->_height = $options['height'];
        }
        if ($options['aspectRatio'] !== null) {
            $this->_aspectRatio = $options['aspectRatio'];
        }

        parent::__construct($path, $options);
    }

/**
 * Returns the file info array, augmented with the video's dimensions,
 * orientation and aspect ratio.
 *
 * @param string|null $info Specific info key to return, or null for the whole array.
 * @return array|string|null File information.
 */
    public function info($info = null) {
        parent::info($info);
        if (!isset($this->_info['width']) || !isset($this->_info['height'])) {
            $this->_info += $this->dimensions();
            $this->_info['orientation'] = $this->orientation();
            $this->_info['aspectRatio'] = $this->aspectRatio();
        }

        if ($info === null) {
            return $this->_info;
        }
        return $this->_info[$info] ?? null;
    }

/**
 * Run ffprobe once and cache the decoded result (streams + format).
 *
 * Reads from the local materialisation of the file, so remote files are
 * pulled down as needed. Returns an empty array when the file cannot be
 * probed (frozen, not local, or ffprobe unavailable/failed), letting callers
 * treat a missing toolkit as "no metadata" rather than an error.
 *
 * @return array Decoded ffprobe output, or an empty array on failure.
 */
    public function probe() {
        if ($this->_probe !== null) {
            return $this->_probe;
        }

        $this->_probe = [];
        if ($this->_freeze !== false || !function_exists('shell_exec')) {
            return $this->_probe;
        }

        $path = $this->getAbsoluteLocalPath();
        if (!$path || !is_file($path)) {
            return $this->_probe;
        }

        $command = 'ffprobe -v quiet -print_format json -show_streams -show_format '
            . escapeshellarg($path);
        $data = json_decode((string)shell_exec($command), true);
        if (is_array($data)) {
            $this->_probe = $data;
        }

        return $this->_probe;
    }

/**
 * Get the first video stream from the probe result.
 *
 * @return array The video stream data, or an empty array when none is found.
 */
    protected function _videoStream() {
        foreach ($this->probe()['streams'] ?? [] as $stream) {
            if (($stream['codec_type'] ?? null) === 'video') {
                return $stream;
            }
        }
        return [];
    }

/**
 * Read the display rotation (in degrees) of a video stream.
 *
 * ffmpeg exposes rotation either as a legacy `tags.rotate` value or, on newer
 * files, inside a Display Matrix side-data entry (whose sign is inverted
 * relative to the visual rotation).
 *
 * @param array $stream Video stream data from ffprobe.
 * @return int Rotation normalised to 0, 90, 180 or 270 degrees.
 */
    protected function _streamRotation(array $stream) {
        if (isset($stream['tags']['rotate'])) {
            return (((int)$stream['tags']['rotate'] % 360) + 360) % 360;
        }
        foreach ($stream['side_data_list'] ?? [] as $sideData) {
            if (isset($sideData['rotation'])) {
                return (((-(int)$sideData['rotation']) % 360) + 360) % 360;
            }
        }
        return 0;
    }

/**
 * Get current video width in pixels (display orientation).
 *
 * @return int|null Width, or null when it cannot be determined.
 */
    public function width() {
        if ($this->_width === null) {
            $this->dimensions();
        }
        return $this->_width;
    }

/**
 * Get current video height in pixels (display orientation).
 *
 * @return int|null Height, or null when it cannot be determined.
 */
    public function height() {
        if ($this->_height === null) {
            $this->dimensions();
        }
        return $this->_height;
    }

/**
 * Get the video's display dimensions, probing with ffprobe when needed.
 *
 * The encoded frame size is swapped when the stream carries a quarter-turn
 * rotation (how phones tag portrait clips), so the returned width/height
 * always reflect what the viewer actually sees.
 *
 * @return array{width: int|null, height: int|null} Display dimensions.
 */
    public function dimensions() {
        if ($this->_width === null && $this->_freeze === false) {
            $stream = $this->_videoStream();
            if (isset($stream['width'], $stream['height'])) {
                $width = (int)$stream['width'];
                $height = (int)$stream['height'];

                $rotation = $this->_streamRotation($stream);
                if ($rotation === 90 || $rotation === 270) {
                    [$width, $height] = [$height, $width];
                }

                $this->_width = $width;
                $this->_height = $height;
            }
        }
        return [
            'width' => $this->_width,
            'height' => $this->_height,
        ];
    }

/**
 * Get the video duration in seconds.
 *
 * @return float Duration in seconds, or 0.0 when it cannot be determined.
 */
    public function duration() {
        if ($this->_duration === null && $this->_freeze === false) {
            $format = $this->probe()['format'] ?? [];
            $this->_duration = isset($format['duration']) ? (float)$format['duration'] : 0.0;
        }
        return (float)$this->_duration;
    }

/**
 * Get the video's display aspect ratio (e.g. "16:9").
 *
 * @return string|null Aspect ratio, or null when dimensions are unknown.
 */
    public function aspectRatio() {
        if ($this->_aspectRatio === null && $this->width() > 0 && $this->height() > 0) {
            $this->_aspectRatio = Math::calcAspectRatio($this->width(), $this->height());
        }
        return $this->_aspectRatio;
    }

/**
 * Set the video's aspect ratio.
 *
 * @param string $aspectRatio Aspect ratio.
 * @return $this
 */
    public function setAspectRatio($aspectRatio) {
        $this->_aspectRatio = $aspectRatio;
        return $this;
    }

/**
 * Get the video orientation ('portrait' or 'landscape') from its display
 * dimensions.
 *
 * @return string|null 'landscape', 'portrait', or null when dimensions are unknown.
 */
    public function orientation() {
        if ($this->_orientation === null && $this->width() > 0 && $this->height() > 0) {
            $this->_orientation = $this->width() > $this->height() ? 'landscape' : 'portrait';
        }
        return $this->_orientation;
    }

/**
 * Whether the ffmpeg binary is available on this host.
 *
 * Probed once per request (via `ffmpeg -version`) and cached, so callers can
 * cheaply skip video post-processing when ffmpeg is not installed.
 *
 * @return bool True when ffmpeg can be invoked, false otherwise.
 */
    public static function ffmpegAvailable() {
        if (static::$_ffmpegAvailable === null) {
            if (!function_exists('shell_exec') || !function_exists('exec')) {
                return static::$_ffmpegAvailable = false;
            }
            $output = @shell_exec('ffmpeg -version 2>&1');
            static::$_ffmpegAvailable = is_string($output) && stripos($output, 'ffmpeg version') !== false;
        }
        return static::$_ffmpegAvailable;
    }

/**
 * Rewrite the file in place with the moov atom moved to the front of the
 * container (MP4 "faststart").
 *
 * Without faststart the moov atom (the index a player needs before it can
 * render a frame or start playback) sits at the end of the file, forcing the
 * browser to download most — or all — of the video just to read it. Moving it
 * to the front lets playback start from a small initial range request. The
 * remux is a stream copy (`-c copy`), so it is fast and lossless: no
 * re-encoding, identical audio/video.
 *
 * No-op (returns false, original file untouched) when the file is not on the
 * local disk, is not an MP4/QuickTime container, ffmpeg is unavailable, or the
 * remux fails for any reason.
 *
 * @return bool True when the file was rewritten, false otherwise.
 */
    public function fixMoovFlags() {
        if ($this->_freeze !== false) {
            return false;
        }

        // +faststart only applies to the MP4/QuickTime (ISO-BMFF) muxer; skip
        // other containers (webm, ...) where the flag is meaningless.
        $mime = (string)$this->mime();
        if (stripos($mime, 'mp4') === false && stripos($mime, 'quicktime') === false) {
            return false;
        }

        $source = $this->getAbsoluteLocalPath();
        if (!$source || !is_file($source)) {
            return false;
        }

        if (!static::ffmpegAvailable()) {
            return false;
        }

        // Release any open handle first: ffmpeg writes a sibling file and we
        // rename it over the source, which an open descriptor would break
        // (stale reads on Linux, a locked rename on Windows).
        if (is_resource($this->_handle)) {
            fclose($this->_handle);
            $this->_handle = null;
        }

        $destination = $source . '.faststart.mp4';

        $command = 'ffmpeg -y -i ' . escapeshellarg($source)
            . ' -c copy -movflags +faststart ' . escapeshellarg($destination) . ' 2>&1';
        exec($command, $output, $status);

        if ($status !== 0 || !is_file($destination) || filesize($destination) === 0) {
            if (is_file($destination)) {
                @unlink($destination);
            }
            return false;
        }

        // Replace the source with the remuxed file. rename() cannot overwrite an
        // existing file on Windows, so drop the source first on that failure.
        if (!@rename($destination, $source)) {
            @unlink($source);
            if (!@rename($destination, $source)) {
                @unlink($destination);
                return false;
            }
        }

        // The bytes changed: drop cached size/hash so they are recomputed.
        $this->_size = null;
        $this->_hash = [];
        clearstatcache(true, $source);

        return true;
    }

}
