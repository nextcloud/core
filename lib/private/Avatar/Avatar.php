<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright 2018 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Michael Weimann <mail@michael-weimann.eu>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sergey Shliakhov <husband.sergey@gmail.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\Avatar;

use Imagick;
use OC\Color;
use OC_Image;
use OCP\Files\NotFoundException;
use OCP\IAvatar;
use Psr\Log\LoggerInterface;

/**
 * This class gets and sets users avatars.
 */
abstract class Avatar implements IAvatar {

	/** @var LoggerInterface  */
	protected $logger;

	/**
	 * https://github.com/sebdesign/cap-height -- for 500px height
	 * Automated check: https://codepen.io/skjnldsv/pen/PydLBK/
	 * Noto Sans cap-height is 0.715 and we want a 200px caps height size
	 * (0.4 letter-to-total-height ratio, 500*0.4=200), so: 200/0.715 = 280px.
	 * Since we start from the baseline (text-anchor) we need to
	 * shift the y axis by 100px (half the caps height): 500/2+100=350
	 *
	 * @var string
	 */
	private $svgTemplate = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
		<svg width="{size}" height="{size}" version="1.1" viewBox="0 0 500 500" xmlns="http://www.w3.org/2000/svg">
			<rect width="100%" height="100%" fill="#{fill}"></rect>
			<text x="50%" y="350" style="font-weight:normal;font-size:280px;font-family:\'Noto Sans\';text-anchor:middle;fill:#fff">{letter}</text>
		</svg>';

	/**
	 * The base avatar constructor.
	 *
	 * @param LoggerInterface $logger The logger
	 */
	public function __construct(LoggerInterface $logger) {
		$this->logger = $logger;
	}

	/**
	 * Returns the user display name.
	 *
	 * @return string
	 */
	abstract public function getDisplayName(): string;

	/**
	 * Returns the first letter of the display name, or "?" if no name given.
	 *
	 * @return string
	 */
	private function getAvatarText(): string {
		$displayName = $this->getDisplayName();
		if (empty($displayName) === true) {
			return '?';
		}
		$firstTwoLetters = array_map(function ($namePart) {
			return mb_strtoupper(mb_substr($namePart, 0, 1), 'UTF-8');
		}, explode(' ', $displayName, 2));
		return implode('', $firstTwoLetters);
	}

	/**
	 * @inheritdoc
	 */
	public function get($size = 64) {
		$size = (int) $size;

		try {
			$file = $this->getFile($size);
		} catch (NotFoundException $e) {
			return false;
		}

		$avatar = new OC_Image();
		$avatar->loadFromData($file->getContent());
		return $avatar;
	}

	/**
	 * {size} = 500
	 * {fill} = hex color to fill
	 * {letter} = Letter to display
	 *
	 * Generate SVG avatar
	 *
	 * @param int $size The requested image size in pixel
	 * @return string
	 *
	 */
	protected function getAvatarVector(int $size): string {
		$userDisplayName = $this->getDisplayName();
		$bgRGB = $this->avatarBackgroundColor($userDisplayName);
		$bgHEX = sprintf("%02x%02x%02x", $bgRGB->r, $bgRGB->g, $bgRGB->b);
		$text = $this->getAvatarText();
		$toReplace = ['{size}', '{fill}', '{letter}'];
		return str_replace($toReplace, [$size, $bgHEX, $text], $this->svgTemplate);
	}

	/**
	 * Generate png avatar from svg with Imagick
	 *
	 * @param int $size
	 * @return string|boolean
	 */
	protected function generateAvatarFromSvg(int $size) {
		if (!extension_loaded('imagick')) {
			return false;
		}
		try {
			$font = __DIR__ . '/../../core/fonts/NotoSans-Regular.ttf';
			$svg = $this->getAvatarVector($size);
			$avatar = new Imagick();
			$avatar->setFont($font);
			$avatar->readImageBlob($svg);
			$avatar->setImageFormat('png');
			$image = new OC_Image();
			$image->loadFromData($avatar);
			return $image->data();
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Generate png avatar with GD
	 *
	 * @param string $userDisplayName
	 * @param int $size
	 * @return string
	 */
	protected function generateAvatar($userDisplayName, $size) {
		$text = $this->getAvatarText();
		$backgroundColor = $this->avatarBackgroundColor($userDisplayName);

		$im = imagecreatetruecolor($size, $size);
		$background = imagecolorallocate(
			$im,
			$backgroundColor->r,
			$backgroundColor->g,
			$backgroundColor->b
		);
		$white = imagecolorallocate($im, 255, 255, 255);
		imagefilledrectangle($im, 0, 0, $size, $size, $background);

		$font = __DIR__ . '/../../../core/fonts/NotoSans-Regular.ttf';

		$fontSize = $size * 0.4;
		list($x, $y) = $this->imageTTFCenter(
			$im, $text, $font, (int)$fontSize
		);

		imagettftext($im, $fontSize, 0, $x, $y, $white, $font, $text);

		ob_start();
		imagepng($im);
		$data = ob_get_contents();
		ob_end_clean();

		return $data;
	}

	/**
	 * Calculate real image ttf center
	 *
	 * @param resource $image
	 * @param string $text text string
	 * @param string $font font path
	 * @param int $size font size
	 * @param int $angle
	 * @return array
	 */
	protected function imageTTFCenter(
		$image,
		string $text,
		string $font,
		int $size,
		$angle = 0
	): array {
		// Image width & height
		$xi = imagesx($image);
		$yi = imagesy($image);

		// bounding box
		$box = imagettfbbox($size, $angle, $font, $text);

		// imagettfbbox can return negative int
		$xr = abs(max($box[2], $box[4]));
		$yr = abs(max($box[5], $box[7]));

		// calculate bottom left placement
		$x = intval(($xi - $xr) / 2);
		$y = intval(($yi + $yr) / 2);

		return [$x, $y];
	}

	/**
	 * Calculate steps between two Colors
	 * @param object Color $steps start color
	 * @param object Color $ends end color
	 * @return array [r,g,b] steps for each color to go from $steps to $ends
	 */
	private function stepCalc($steps, $ends) {
		$step = [];
		$step[0] = ($ends[1]->r - $ends[0]->r) / $steps;
		$step[1] = ($ends[1]->g - $ends[0]->g) / $steps;
		$step[2] = ($ends[1]->b - $ends[0]->b) / $steps;
		return $step;
	}

	/**
	 * Convert a string to an integer evenly
	 * @param string $hash the text to parse
	 * @param int $maximum the maximum range
	 * @return int[] between 0 and $maximum
	 */
	private function mixPalette($steps, $color1, $color2) {
		$palette = [$color1];
		$step = $this->stepCalc($steps, [$color1, $color2]);
		for ($i = 1; $i < $steps; $i++) {
			$r = intval($color1->r + ($step[0] * $i));
			$g = intval($color1->g + ($step[1] * $i));
			$b = intval($color1->b + ($step[2] * $i));
			$palette[] = new Color($r, $g, $b);
		}
		return $palette;
	}

	/**
	 * Convert a string to an integer evenly
	 * @param string $hash the text to parse
	 * @param int $maximum the maximum range
	 * @return int between 0 and $maximum
	 */
	private function hashToInt($hash, $maximum) {
		$final = 0;
		$result = [];

		// Splitting evenly the string
		for ($i = 0; $i < strlen($hash); $i++) {
			// chars in md5 goes up to f, hex:16
			$result[] = intval(substr($hash, $i, 1), 16) % 16;
		}
		// Adds up all results
		foreach ($result as $value) {
			$final += $value;
		}
		// chars in md5 goes up to f, hex:16
		return intval($final % $maximum);
	}

	/**
	 * @param string $hash
	 * @return Color Object containting r g b int in the range [0, 255]
	 */
	public function avatarBackgroundColor(string $hash) {
		// Normalize hash
		$hash = strtolower($hash);

		// Already a md5 hash?
		if (preg_match('/^([0-9a-f]{4}-?){8}$/', $hash, $matches) !== 1) {
			$hash = md5($hash);
		}

		// Remove unwanted char
		$hash = preg_replace('/[^0-9a-f]+/', '', $hash);

		$red = new Color(182, 70, 157);
		$yellow = new Color(221, 203, 85);
		$blue = new Color(0, 130, 201); // Nextcloud blue

		// Number of steps to go from a color to another
		// 3 colors * 6 will result in 18 generated colors
		$steps = 6;

		$palette1 = $this->mixPalette($steps, $red, $yellow);
		$palette2 = $this->mixPalette($steps, $yellow, $blue);
		$palette3 = $this->mixPalette($steps, $blue, $red);

		$finalPalette = array_merge($palette1, $palette2, $palette3);

		return $finalPalette[$this->hashToInt($hash, $steps * 3)];
	}
}
