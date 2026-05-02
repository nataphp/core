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

namespace Nata\FilesystemManager\File\Application;

use Nata\FilesystemManager\Exception\FilesystemException;
use Nata\FilesystemManager\File\Application;
use Imagick;
use Throwable;

/**
 * PDF file instance class for reading and writing PDF files.
 */
class Pdf extends Application {


/**
 * PDF data as array.
 *
 * @return array
 */
    public function toArray() {
        return [];
    }

/**
 * Convert PDF to JPG images using the best available method.
 *
 * @param int $dpi DPI for image quality. Default: 150
 * @param array $options Additional options for conversion
 * @return array Array of JPG image file paths
 * @throws FilesystemException When conversion fails
 */
    public function toJpgImages(int $dpi = 150, array $options = []): array {
        return $this->toImages('jpg', $dpi, 'auto', $options);
    }

/**
 * Convert PDF to PNG images using the best available method.
 *
 * @param int $dpi DPI for image quality. Default: 150
 * @param array $options Additional options for conversion
 * @return array Array of PNG image file paths
 * @throws FilesystemException When conversion fails
 */
    public function toPngImages(int $dpi = 150, array $options = []): array {
        return $this->toImages('png', $dpi, 'auto', $options);
    }

/**
 * Check if PDF conversion is available on this system.
 *
 * @return array Available conversion methods
 */
    public function getAvailableConversionMethods(): array {
        $methods = [];

        // Check for Imagick extension
        if (extension_loaded('imagick') && class_exists('Imagick')) {
            $methods[] = 'imagick';
        }

        // Check for CLI tools
        if ($this->_isCliToolAvailable('pdftoppm')) {
            $methods[] = 'pdftoppm';
        }

        if ($this->_isCliToolAvailable('convert')) {
            $methods[] = 'imagemagick_cli';
        }

        return $methods;
    }

/**
 * Convert PDF to images (JPG or PNG).
 *
 * @param string $format Output format ('jpg', 'jpeg', 'png'). Default: 'jpg'
 * @param int $dpi DPI for image quality. Default: 150
 * @param string $method Conversion method ('imagick', 'cli', 'auto'). Default: 'auto'
 * @param array $options Additional options for conversion
 * @return array Array of image file paths or false on failure
 * @throws FilesystemException When PDF file doesn't exist or conversion fails
 */
    public function toImages(string $format = 'jpg', int $dpi = 150, string $method = 'auto', array $options = []): array|false {
        if (!$this->exists()) {
            throw new FilesystemException('PDF file does not exist: ' . $this->getAbsolutePath());
        }

        // Normalize format
        $format = strtolower($format);
        if ($format === 'jpeg') {
            $format = 'jpg';
        }

        if (!in_array($format, ['jpg', 'png'])) {
            throw new FilesystemException('Unsupported format: ' . $format . '. Supported formats: jpg, png');
        }

        // Determine method
        if ($method === 'auto') {
            $method = $this->_detectBestMethod();
        }

        switch ($method) {
            case 'imagick':
                return $this->_convertWithImagick($format, $dpi, $options);
            case 'cli':
                return $this->_convertWithCli($format, $dpi, $options);
            default:
                throw new FilesystemException('Invalid conversion method: ' . $method);
        }
    }

/**
 * Detect the best available conversion method.
 *
 * @return string The best available method ('imagick' or 'cli')
 * @throws FilesystemException When no conversion method is available
 */
    private function _detectBestMethod(): string {
        // Check for Imagick extension
        if (extension_loaded('imagick') && class_exists('Imagick')) {
            return 'imagick';
        }

        // Check for CLI tools
        if ($this->_isCliToolAvailable('pdftoppm') || $this->_isCliToolAvailable('convert')) {
            return 'cli';
        }

        throw new FilesystemException('No PDF conversion method available. Please install PHP Imagick extension or CLI tools (poppler-utils/imagemagick)');
    }

/**
 * Check if a CLI tool is available.
 *
 * @param string $tool Tool name to check
 * @return bool True if tool is available
 */
    private function _isCliToolAvailable(string $tool): bool {
        $command = PHP_OS_FAMILY === 'Windows' ? "where $tool" : "which $tool";
        exec($command . ' 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }

/**
 * Convert PDF to images using Imagick.
 *
 * @param string $format Output format
 * @param int $dpi DPI for image quality
 * @param array $options Additional options
 * @return array Array of image file paths
 * @throws FilesystemException When conversion fails
 */
    private function _convertWithImagick(string $format, int $dpi, array $options): array {
        if (!extension_loaded('imagick') || !class_exists('Imagick')) {
            throw new FilesystemException('Imagick extension is not available to convert PDF to images');
        }

        try {
            $imagick = new Imagick();
            $imagick->setResolution($dpi, $dpi);

            // Set compression quality for JPEG
            if ($format === 'jpg') {
                $imagick->setImageCompressionQuality($options['quality'] ?? 85);
            }

            $pdfPath = $this->getAbsoluteLocalPath();
            $imagick->readImage($pdfPath);

            $images = [];
            $outputDir = $options['output_dir'] ?? dirname($pdfPath);
            $basename = $this->name() ?? pathinfo($pdfPath, PATHINFO_FILENAME);

            foreach ($imagick as $pageIndex => $page) {
                $page->setImageFormat($format);

                if ($format === 'jpg') {
                    $page->setImageCompressionQuality($options['quality'] ?? 85);
                    $page->setImageBackgroundColor('white');
                    // Use constant value directly to avoid linter issues
                    $page->mergeImageLayers(1); // LAYERMETHOD_FLATTEN = 1
                }

                $outputPath = $outputDir . DIRECTORY_SEPARATOR . $basename . '_page_' . ($pageIndex + 1) . '.' . $format;

                if (!$page->writeImage($outputPath)) {
                    throw new FilesystemException("Failed to write image page " . ($pageIndex + 1));
                }

                $images[] = $outputPath;
            }

            $imagick->clear();
            $imagick->destroy();

            return $images;
        } catch (Throwable $e) {
            throw new FilesystemException('Imagick conversion failed: ' . $e->getMessage());
        }
    }

/**
 * Convert PDF to images using CLI tools.
 *
 * @param string $format Output format
 * @param int $dpi DPI for image quality
 * @param array $options Additional options
 * @return array Array of image file paths
 * @throws FilesystemException When conversion fails
 */
    private function _convertWithCli(string $format, int $dpi, array $options): array {
        $pdfPath = $this->getAbsoluteLocalPath();
        $outputDir = $options['output_dir'] ?? dirname($pdfPath);
        $basename = $this->name() ?? pathinfo($pdfPath, PATHINFO_FILENAME);

        // Try pdftoppm first (part of poppler-utils)
        if ($this->_isCliToolAvailable('pdftoppm')) {
            return $this->_convertWithPdftoppm($pdfPath, $outputDir, $basename, $format, $dpi, $options);
        }

        // Fallback to ImageMagick convert
        if ($this->_isCliToolAvailable('convert')) {
            return $this->_convertWithImageMagickCli($pdfPath, $outputDir, $basename, $format, $dpi, $options);
        }

        throw new FilesystemException('No CLI conversion tools available (pdftoppm or convert)');
    }

/**
 * Convert using pdftoppm command.
 *
 * @param string $pdfPath Input PDF path
 * @param string $outputDir Output directory
 * @param string $basename Base name for output files
 * @param string $format Output format
 * @param int $dpi DPI setting
 * @param array $options Additional options
 * @return array Array of image file paths
 * @throws FilesystemException When conversion fails
 */
    private function _convertWithPdftoppm(string $pdfPath, string $outputDir, string $basename, string $format, int $dpi, array $options): array {
        $formatFlag = $format === 'png' ? '-png' : '-jpeg';
        $outputPrefix = $outputDir . DIRECTORY_SEPARATOR . $basename;

        $command = sprintf(
            'pdftoppm %s -r %d "%s" "%s" 2>&1',
            $formatFlag,
            $dpi,
            escapeshellarg($pdfPath),
            escapeshellarg($outputPrefix)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new FilesystemException('pdftoppm conversion failed: ' . implode("\n", $output));
        }

        // Find generated files
        $pattern = $outputPrefix . '-*.' . $format;
        $images = glob($pattern);

        if (empty($images)) {
            throw new FilesystemException('No images were generated by pdftoppm');
        }

        sort($images, SORT_NATURAL);
        return $images;
    }

/**
 * Convert using ImageMagick convert command.
 *
 * @param string $pdfPath Input PDF path
 * @param string $outputDir Output directory
 * @param string $basename Base name for output files
 * @param string $format Output format
 * @param int $dpi DPI setting
 * @param array $options Additional options
 * @return array Array of image file paths
 * @throws FilesystemException When conversion fails
 */
    private function _convertWithImageMagickCli(string $pdfPath, string $outputDir, string $basename, string $format, int $dpi, array $options): array {
        $outputPattern = $outputDir . DIRECTORY_SEPARATOR . $basename . '_page_%d.' . $format;

        $command = sprintf(
            'convert -density %d "%s" ',
            $dpi,
            escapeshellarg($pdfPath)
        );

        if ($format === 'jpg') {
            $quality = $options['quality'] ?? 85;
            $command .= sprintf('-quality %d -background white -flatten ', $quality);
        }

        $command .= sprintf('"%s" 2>&1', escapeshellarg($outputPattern));

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new FilesystemException('ImageMagick convert failed: ' . implode("\n", $output));
        }

        // Find generated files
        $searchPattern = str_replace('%d', '*', $outputPattern);
        $images = glob($searchPattern);

        if (empty($images)) {
            throw new FilesystemException('No images were generated by convert');
        }

        sort($images, SORT_NATURAL);
        return $images;
    }
}
