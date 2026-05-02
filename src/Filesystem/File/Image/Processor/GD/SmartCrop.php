<?php
/**
 * NataPHP Framework.
 *
 * This class is based on the work of Xiyuan Mak:
 * https://github.com/xymak
 *
 * Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright	Copyright (c) Sérgio Dinis Lopes. (http://nataphp.com)
 * @link		http://nataphp.com NataPHP Project
 * @since		NataPHP 1.0.0
 * @license		http://www.opensource.org/licenses/mit-license.php MIT License
 * @see			https://github.com/xymak/smartcrop.php
 */

namespace Nata\Filesystem\File\Image\Processor\GD;

use Nata\Filesystem\File\Image\Processor\GD;
use Nata\Core\NataObject;
use Nata\Filesystem\File\Image;
use InvalidArgumentException;
use SplFixedArray;

class SmartCrop extends NataObject {

/**
 * Default config.
 *
 * @var array
 */
	protected $_defaultConfig = [
		'newWidth' => 0,
		'newHeight' => 0,
		'aspect' => 0,
		'cropWidth' => 0,
		'cropHeight' => 0,
		'detailWeight' => 0.2,
		'skinColor' => [
			0.78,
			0.57,
			0.44
		],
		'skinBias' => 0.01,
		'skinBrightnessMin' => 0.2,
		'skinBrightnessMax' => 1.0,
		'skinThreshold' => 0.8,
		'skinWeight' => 1.8,
		'saturationBrightnessMin' => 0.05,
		'saturationBrightnessMax' => 0.9,
		'saturationThreshold' => 0.4,
		'saturationBias' => 0.2,
		'saturationWeight' => 0.1,
		'scoreDownSample' => 8,
		'step' => 8,
		'scaleStep' => 0.1,
		'minScale' => 1.0,
		'maxScale' => 1.0,
		'edgeRadius' => 0.4,
		'edgeWeight' => -20.0,
		'outsideImportance' => - 0.5,
		'boostWeight' => 100.0,
		'ruleOfThirds' => true,
		'prescale' => true,
		'imageOperations' => null,
		'canvasFactory' => 'defaultCanvasFactory',
		'debug' => true
	];

/**
 * Output data.
 *
 * @var int
 */
	protected $scale;

/**
 * Output data.
 *
 * @var int
 */
	protected $prescale;

/**
 * Output image.
 *
 * @var resource
 */
	protected $oImg;

/**
 * Output data.
 *
 * @var array
 */
	protected $outputData = [];

/**
 * Image sample.
 *
 * @var array
 */
	protected $aSample = [];

/**
 * Image width.
 *
 * @var int
 */
	protected $h = 0;

/**
 * Image height.
 *
 * @var int
 */
	protected $w = 0;


/**
 * Constructor.
 *
 * @param \Nata\Filesystem\File\Image\Editor|string $image Image to analyze
 * @param array $options Options
 * @return void
 */
	public function __construct(GD $GD, array $options = []) {
		$this->oImg = $GD->workingImage();

		$this->config($options);

		if ($this->_config['aspect']) {
			$this->_config['newWidth'] = $this->_config['aspect'];
			$this->_config['newHeight'] = 1;
		}

		$this->scale = 1;
		$this->preScale = 1;

		$this->canvasImageScale();

		return $this;
	}

/**
 * Scale the image before smartcrop analyse
 *
 * @return \xymak\image\smartcrop
 */
	public function canvasImageScale() {
		$imageOriginalWidth = imagesx($this->oImg);
		$imageOriginalHeight = imagesy($this->oImg);

		$scale = min(
			$imageOriginalWidth / $this->_config['newWidth'],
			$imageOriginalHeight / $this->_config['newHeight']
		);

		$this->_config['cropWidth'] = ceil($this->_config['newWidth'] * $scale);
		$this->_config['cropHeight'] = ceil($this->_config['newHeight'] * $scale);

		$this->_config['minScale'] = min($this->_config['maxScale'], max(1 / $scale, $this->_config['minScale']));

		if ($this->_config['prescale'] !== false) {
			$this->preScale = 1 / $scale / $this->_config['minScale'];

			if ($this->preScale < 1) {
				$this->canvasImageResample(
					ceil($imageOriginalWidth * $this->preScale),
					ceil($imageOriginalHeight * $this->preScale)
				);

				$this->_config['cropWidth'] = ceil($this->_config['cropWidth'] * $this->preScale);
				$this->_config['cropHeight'] = ceil($this->_config['cropHeight'] * $this->preScale);
			} else {
				$this->preScale = 1;
			}

		}

		return $this;
	}

/**
 * Function for scale image
 *
 * @param integer $width
 * @param integer $height
 * @return \xymak\image\smartcrop
 */
	public function canvasImageResample($width, $height) {
		$oCanvas = imagecreatetruecolor($width, $height);

		imagecopyresampled(
			$oCanvas,
			$this->oImg,
			0,
			0,
			0,
			0,
			$width,
			$height,
			imagesx($this->oImg),
			imagesy($this->oImg)
		);

		$this->oImg = $oCanvas;

		return $this;
	}

/**
 * Analyse the image, find out the optimal crop scheme
 *
 * @return array
 */
	public function analyse() {
		$result = [];

		$w = $this->w = imagesx($this->oImg);
		$h = $this->h = imagesy($this->oImg);

		$this->outputData = new SplFixedArray($h * $w * 4);
		$this->aSample = new SplFixedArray($h * $w);

		for ($y = 0; $y < $h; $y++) {
			for ($x = 0; $x < $w; $x ++) {
				$p = ($y * $this->w + $x) * 4;

				$aRgb = $this->getRgbColorAt($x, $y);

				$this->outputData[$p + 1] = $this->edgeDetect($x, $y, $w, $h, $p);

				$this->outputData[$p] = $this->skinDetect(
					$aRgb[0],
					$aRgb[1],
					$aRgb[2],
					$this->sample($x, $y)
				);

				$this->outputData[$p + 2] = $this->saturationDetect(
					$aRgb[0],
					$aRgb[1],
					$aRgb[2],
					$this->sample($x, $y)
				);

				// $this->applyBoosts();

			}
		}

		$scoreOutput = $this->downSample($this->_config['scoreDownSample']);
		$topScore = -INF;
		$topCrop = null;
		$crops = $this->generateCrops();

		foreach ($crops as &$crop) {
			$crop['score'] = $this->score($scoreOutput, $crop);

			if ($crop['score']['total'] > $topScore) {
				$topCrop = $crop;
				$topScore = $crop['score']['total'];
			}

		}

		$result = $topCrop;

		if ($this->_config['debug'] && $topCrop) {
			$result['crops'] = $crops;
			$result['debugOutput'] = $scoreOutput;
			$result['debugOptions'] = $this->config();
			$result['debugTopCrop'] = array_merge([], $topCrop);
		}

		//print_a($result);die;

		$result['oldImage'] = $this->oImg;

		return $result;
	}

/**
 * Down sample the image.
 *
 * @param int $factor
 * @return \SplFixedArray
 */
	protected function downSample($factor) {
		$width = floor($this->w / $factor);
		$height = floor($this->h / $factor);

		$ifactor2 = (1 / ($factor * $factor));

		$data = new SplFixedArray($height * $width * 4);

		for ($y = 0; $y < $height; $y++) {
			for ($x = 0; $x < $width; $x++) {
				$r = 0;
				$g = 0;
				$b = 0;
				$a = 0;

				$mr = 0;
				$mg = 0;
				// $mb = 0;

				for ($v = 0; $v < $factor; $v++) {
					for ($u = 0; $u < $factor; $u++) {
						$p = (($y * $factor + $v) * $this->w + ($x * $factor + $u)) * 4;

						$pR = $this->outputData[$p];
						$pG = $this->outputData[$p + 1];
						$pB = $this->outputData[$p + 2];
						$pA = $this->outputData[$p + 3];
						$r += $pR;
						$g += $pG;
						$b += $pB;
						$a += $pA;
						$mr = max($mr, $pR);
						$mg = max($mg, $pG);
						// unused
						// $mb = max($mb, $pB);
					}
				}

				$p = ($y * $width + $x) * 4;
				$data[$p] = round($r * $ifactor2 * 0.5 + $mr * 0.5, 0, PHP_ROUND_HALF_EVEN);
				$data[$p + 1] = round($g * $ifactor2 * 0.7 + $mg * 0.3, 0, PHP_ROUND_HALF_EVEN);
				$data[$p + 2] = round($b * $ifactor2, 0, PHP_ROUND_HALF_EVEN);
				$data[$p + 3] = round($a * $ifactor2, 0, PHP_ROUND_HALF_EVEN);

			}
		}

		return $data;
	}

/**
 * Edge detection.
 *
 * @param integer $x
 * @param integer $y
 * @param integer $w
 * @param integer $h
 * @return integer
 */
	protected function edgeDetect($x, $y, $w, $h, $p) {
		if ($x === 0 || $x >= $w - 1 || $y === 0 || $y >= $h - 1) {
			$lightness = $this->sample($x, $y);
		} else {
			$leftLightness = $this->sample($x - 1, $y);
			$centerLightness = $this->sample($x, $y);
			$rightLightness = $this->sample($x + 1, $y);
			$topLightness = $this->sample($x, $y - 1);
			$bottomLightness = $this->sample($x, $y + 1);
			$lightness = $centerLightness * 4 - $leftLightness - $rightLightness - $topLightness - $bottomLightness;
		}

		return round($lightness, 0, PHP_ROUND_HALF_EVEN);
	}

/**
 * Skin detection.
 *
 * @param integer $r
 * @param integer $g
 * @param integer $b
 * @param float $lightness
 * @return integer
 */
	protected function skinDetect($r, $g, $b, $lightness) {
		$lightness = $lightness / 255;
		$skin = $this->skinColor($r, $g, $b);
		$isSkinColor = $skin > $this->_config['skinThreshold'];
		$isSkinBrightness = $lightness > $this->_config['skinBrightnessMin'] && $lightness <= $this->_config['skinBrightnessMax'];

		if ($isSkinColor && $isSkinBrightness) {
			return round(
				($skin - $this->_config['skinThreshold']) * (255 / (1 - $this->_config['skinThreshold'])),
				0,
				PHP_ROUND_HALF_EVEN
			);
		}

		return 0;
	}

/**
 * Saturation detection.
 *
 * @param integer $r
 * @param integer $g
 * @param integer $b
 * @param float $lightness
 * @return integer
 */
	protected function saturationDetect($r, $g, $b, $lightness) {
		$lightness = $lightness / 255;
		$sat = $this->saturation($r, $g, $b);
		$acceptableSaturation = $sat > $this->_config['saturationThreshold'];
		$acceptableLightness = $lightness >= $this->_config['saturationBrightnessMin'] && $lightness <= $this->_config['saturationBrightnessMax'];

		if ($acceptableLightness && $acceptableSaturation) {
			return round(
				($sat - $this->_config['saturationThreshold']) * (255 / (1 - $this->_config['saturationThreshold'])),
				0,
				PHP_ROUND_HALF_EVEN
			);
		}

		return 0;
	}

/**
 * Generate crop schemes.
 *
 * @return array
 */
	protected function generateCrops() {
		$w = imagesx($this->oImg);
		$h = imagesy($this->oImg);

		$minDimension = min($w, $h);
		$cropWidth = empty($this->_config['cropWidth']) ? $minDimension : $this->_config['cropWidth'];
		$cropHeight = empty($this->_config['cropHeight']) ? $minDimension : $this->_config['cropHeight'];
		$step = $this->_config['step'];
		$scaleStep = $this->_config['scaleStep'];
		$minScale = $this->_config['minScale'];
		$maxScale = $this->_config['maxScale'];

		$results = [];
		for ($scale = $maxScale; $scale >= $minScale; $scale -= $scaleStep) {
			for ($y = 0; $y + $cropHeight * $scale <= $h; $y += $step) {
				for ($x = 0; $x + $cropWidth * $scale <= $w; $x += $step) {
					$results[] = [
						'x' => $x,
						'y' => $y,
						'width' => $cropWidth * $scale,
						'height' => $cropHeight * $scale
					];
				}
			}
		}

		return $results;
	}

/**
 * Apply boost to score.
 *
 * @todo Still needs implementation
 * @return void
 */
	protected function applyBoosts() {
		if (!$this->_config['boost']) {
			return;
		}

		$width = $this->w;
		$od = $this->outputData;

		for ($i = 0; $i < $width; $i += 4) {
			$od[$i + 3] = 0;
		}

		$boost = $this->_config['boost'];
		for ($i = 0; $i < count($boost); $i++) {
			$this->applyBoost($boost[$i]);
		}

	}

/**
 * Apply boost to score.
 *
 * @todo Still needs implementation
 * @return void
 */
	protected function applyBoost() {}

/**
 * Score a crop scheme.
 *
 * @param array $output
 * @param array $crop
 * @return array
 */
	protected function score($output, $crop) {
		$result = [
			'detail' => 0,
			'saturation' => 0,
			'skin' => 0,
			'boost' => 0,
			'total' => 0
		];

		$downSample = $this->_config['scoreDownSample'];
		$invDownSample = 1 / $downSample;
		$outputHeightDownSample = floor($this->h / $downSample) * $downSample;
		$outputWidthDownSample = floor($this->w / $downSample) * $downSample;
		$outputWidth = floor($this->w / $downSample);

		for($y = 0; $y < $outputHeightDownSample; $y += $downSample) {
			for ($x = 0; $x < $outputWidthDownSample; $x += $downSample) {
				$i = $this->importance($crop, $x, $y);
				$p = floor($y / $downSample) * $outputWidth * 4 + floor($x / $downSample) * 4;
				$detail = $output[$p + 1] / 255;

				$result['skin'] += ($output[$p] / 255) * ($detail + $this->_config['skinBias']) * $i;
				$result['saturation'] += ($output[$p + 2] / 255) * ($detail + $this->_config['saturationBias']) * $i;
				$result['detail'] += $detail * $i;
				$result['boost'] += ($output[$p + 3] / 255) * $i;
			}
		}

		$result['total'] = ($result['detail'] * $this->_config['detailWeight'] + $result['skin'] * $this->_config['skinWeight'] + $result['saturation'] * $this->_config['saturationWeight'] + $result['boost'] * $this->_config['boostWeight']) / ($crop['width'] * $crop['height']);

		return $result;
	}

/**
 * Obtain the importance.
 *
 * @param array $crop
 * @param integer $x
 * @param integer $y
 * @return float|number
 */
	protected function importance($crop, $x, $y) {
		if ($crop['x'] > $x
			|| $x >= $crop['x'] + $crop['width']
			|| $crop['y'] > $y
			|| $y > $crop['y'] + $crop['height']) {
				return $this->_config['outsideImportance'];
		}

		$x = ($x - $crop['x']) / $crop['width'];
		$y = ($y - $crop['y']) / $crop['height'];
		$px = abs(0.5 - $x) * 2;
		$py = abs(0.5 - $y) * 2;
		// Distance from edge
		$dx = max($px - 1.0 + $this->_config['edgeRadius'], 0);
		$dy = max($py - 1.0 + $this->_config['edgeRadius'], 0);
		$d = ($dx * $dx + $dy * $dy) * $this->_config['edgeWeight'];
		$s = 1.41 - sqrt($px * $px + $py * $py);

		if ($this->_config['ruleOfThirds']) {
			$s += (max(0, $s + $d + 0.5 ) * 1.2) * ($this->thirds($px) + $this->thirds($py));
		}

		return $s + $d;
	}

/**
 * Get thirds.
 *
 * @param integer $x
 * @return float
 */
	protected function thirds($x) {
		$x = (($x - (1 / 3) + 1.0) % 2.0 * 0.5 - 0.5) * 16;
		return max(1.0 - $x * $x, 0.0);
	}

/**
 * Get sample.
 *
 * @param integer $x
 * @param integer $y
 * @return float
 */
	protected function sample($x, $y, $p = null) {
		if ($p === null) {
			$p = ($y * $this->w) + $x;
		}

		if (isset($this->aSample[$p])) {
			return $this->aSample[$p];
		}

		$aRgbColor = $this->getRgbColorAt($x, $y);
		$this->aSample[$p] = $this->cie($aRgbColor[0], $aRgbColor[1], $aRgbColor[2]);
		return $this->aSample[$p];
	}

/**
 * Get RGB color at given coords.
 *
 * @param integer $x
 * @param integer $y
 * @return float
 */
	protected function getRgbColorAt($x, $y) {
		$rgb = imagecolorat($this->oImg, $x, $y);
		return [
			$rgb >> 16,
			$rgb >> 8 & 255,
			$rgb & 255
		];
	}

/**
 * @param integer $r
 * @param integer $g
 * @param integer $b
 * @return float
 */
	protected function cie($r, $g, $b) {
		return 0.5126 * $b + 0.7152 * $g + 0.0722 * $r;
	}

/**
 * Get skin color.
 *
 * @param integer $r
 * @param integer $g
 * @param integer $b
 * @return float
 */
	protected function skinColor($r, $g, $b) {
		$mag = sqrt($r * $r + $g * $g + $b * $b);
		$mag = $mag > 0 ? $mag : 1;

		list($skinR, $skinG, $skinB) = $this->_config['skinColor'];

		$rd = ($r / $mag - $skinR);
		$gd = ($g / $mag - $skinG);
		$bd = ($b / $mag - $skinB);
		$d = sqrt($rd * $rd + $gd * $gd + $bd * $bd);

		return 1 - $d;
	}

/**
 * Get saturation.
 *
 * @param integer $r
 * @param integer $g
 * @param integer $b
 * @return float
 */
	protected function saturation($r, $g, $b) {
		$maximum = max($r / 255, $g / 255, $b / 255);
		$minumum = min($r / 255, $g / 255, $b / 255);

		if ($maximum === $minumum) {
			return 0;
		}

		$l = ($maximum + $minumum) / 2;
		$d = ($maximum - $minumum);

		return $l > 0.5 ? $d / (2 - $maximum - $minumum) : $d / ($maximum + $minumum);
	}

}
