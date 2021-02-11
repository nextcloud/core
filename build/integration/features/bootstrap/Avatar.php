<?php
/**
 * @copyright Copyright (c) 2020, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

use Behat\Gherkin\Node\TableNode;
use PHPUnit\Framework\Assert;

require __DIR__ . '/../../vendor/autoload.php';

trait Avatar {

	/** @var string **/
	private $lastAvatar;

	/** @AfterScenario **/
	public function cleanupLastAvatar() {
		$this->lastAvatar = null;
	}

	private function getLastAvatar() {
		$this->lastAvatar = '';

		$body = $this->response->getBody();
		while (!$body->eof()) {
			$this->lastAvatar .= $body->read(8192);
		}
		$body->close();
	}

	/**
	 * @When user :user gets avatar for user :userAvatar
	 *
	 * @param string $user
	 * @param string $userAvatar
	 */
	public function userGetsAvatarForUser(string $user, string $userAvatar) {
		$this->userGetsAvatarForUserWithSize($user, $userAvatar, '128');
	}

	/**
	 * @When user :user gets avatar for user :userAvatar with size :size
	 *
	 * @param string $user
	 * @param string $userAvatar
	 * @param string $size
	 */
	public function userGetsAvatarForUserWithSize(string $user, string $userAvatar, string $size) {
		$this->asAn($user);
		$this->sendingToDirectUrl('GET', '/index.php/avatar/' . $userAvatar . '/' . $size);
		$this->theHTTPStatusCodeShouldBe('200');

		$this->getLastAvatar();
	}

	/**
	 * @When user :user gets avatar for guest :guestAvatar
	 *
	 * @param string $user
	 * @param string $guestAvatar
	 */
	public function userGetsAvatarForGuest(string $user, string $guestAvatar) {
		$this->asAn($user);
		$this->sendingToDirectUrl('GET', '/index.php/avatar/guest/' . $guestAvatar . '/128');
		$this->theHTTPStatusCodeShouldBe('201');

		$this->getLastAvatar();
	}

	/**
	 * @When logged in user gets temporary avatar
	 */
	public function loggedInUserGetsTemporaryAvatar() {
		$this->loggedInUserGetsTemporaryAvatarWith('200');
	}

	/**
	 * @When logged in user gets temporary avatar with :statusCode
	 *
	 * @param string $statusCode
	 */
	public function loggedInUserGetsTemporaryAvatarWith(string $statusCode) {
		$this->sendingAToWithRequesttoken('GET', '/index.php/avatar/tmp');
		$this->theHTTPStatusCodeShouldBe($statusCode);

		$this->getLastAvatar();
	}

	/**
	 * @When logged in user posts temporary avatar from file :source
	 *
	 * @param string $source
	 */
	public function loggedInUserPostsTemporaryAvatarFromFile(string $source) {
		$file = \GuzzleHttp\Psr7\stream_for(fopen($source, 'r'));

		$this->sendingAToWithRequesttoken('POST', '/index.php/avatar',
			[
				'multipart' => [
					[
						'name' => 'files[]',
						'contents' => $file
					]
				]
			]);
		$this->theHTTPStatusCodeShouldBe('200');
	}

	/**
	 * @When logged in user posts temporary avatar from internal path :path
	 *
	 * @param string $path
	 */
	public function loggedInUserPostsTemporaryAvatarFromInternalPath(string $path) {
		$this->sendingAToWithRequesttoken('POST', '/index.php/avatar?path=' . $path);
		$this->theHTTPStatusCodeShouldBe('200');
	}

	/**
	 * @When logged in user crops temporary avatar
	 *
	 * @param TableNode $crop
	 */
	public function loggedInUserCropsTemporaryAvatar(TableNode $crop) {
		$this->loggedInUserCropsTemporaryAvatarWith('200', $crop);
	}

	/**
	 * @When logged in user crops temporary avatar with :statusCode
	 *
	 * @param string $statusCode
	 * @param TableNode $crop
	 */
	public function loggedInUserCropsTemporaryAvatarWith(string $statusCode, TableNode $crop) {
		$parameters = [];
		foreach ($crop->getRowsHash() as $key => $value) {
			$parameters[] = 'crop[' . $key . ']=' . $value;
		}

		$this->sendingAToWithRequesttoken('POST', '/index.php/avatar/cropped?' . implode('&', $parameters));
		$this->theHTTPStatusCodeShouldBe($statusCode);
	}

	/**
	 * @When logged in user deletes the user avatar
	 */
	public function loggedInUserDeletesTheUserAvatar() {
		$this->sendingAToWithRequesttoken('DELETE', '/index.php/avatar');
		$this->theHTTPStatusCodeShouldBe('200');
	}

	/**
	 * @Then last avatar is a square of size :size
	 *
	 * @param string size
	 */
	public function lastAvatarIsASquareOfSize(string $size) {
		list($width, $height) = getimagesizefromstring($this->lastAvatar);

		Assert::assertEquals($width, $height, 'Avatar is not a square');
		Assert::assertEquals($size, $width);
	}

	/**
	 * @Then last avatar is not a single color
	 */
	public function lastAvatarIsNotASingleColor() {
		Assert::assertEquals(null, $this->getColorFromLastAvatar());
	}

	/**
	 * @Then last avatar is a single :color color
	 *
	 * @param string $color
	 * @param string $size
	 */
	public function lastAvatarIsASingleColor(string $color) {
		$expectedColor = $this->hexStringToRgbColor($color);
		$colorFromLastAvatar = $this->getColorFromLastAvatar();

		Assert::assertTrue($this->isSameColor($expectedColor, $colorFromLastAvatar),
			$this->rgbColorToHexString($colorFromLastAvatar) . ' does not match expected ' . $color);
	}

	private function hexStringToRgbColor($hexString) {
		// Strip initial "#"
		$hexString = substr($hexString, 1);

		$rgbColorInt = hexdec($hexString);

		// RGBA hex strings are not supported; the given string is assumed to be
		// an RGB hex string.
		return [
			'red' => ($rgbColorInt >> 16) & 0xFF,
			'green' => ($rgbColorInt >> 8) & 0xFF,
			'blue' => $rgbColorInt & 0xFF,
			'alpha' => 0
		];
	}

	private function rgbColorToHexString($rgbColor) {
		$rgbColorInt = ($rgbColor['red'] << 16) + ($rgbColor['green'] << 8) + ($rgbColor['blue']);

		return '#' . str_pad(strtoupper(dechex($rgbColorInt)), 6, '0', STR_PAD_LEFT);
	}

	private function getColorFromLastAvatar() {
		$image = imagecreatefromstring($this->lastAvatar);

		$firstPixelColorIndex = imagecolorat($image, 0, 0);
		$firstPixelColor = imagecolorsforindex($image, $firstPixelColorIndex);

		for ($i = 0; $i < imagesx($image); $i++) {
			for ($j = 0; $j < imagesx($image); $j++) {
				$currentPixelColorIndex = imagecolorat($image, $i, $j);
				$currentPixelColor = imagecolorsforindex($image, $currentPixelColorIndex);

				// The colors are compared with a small allowed delta, as even
				// on solid color images the resizing can cause some small
				// artifacts that slightly modify the color of certain pixels.
				if (!$this->isSameColor($firstPixelColor, $currentPixelColor)) {
					imagedestroy($image);

					return null;
				}
			}
		}

		imagedestroy($image);

		return $firstPixelColor;
	}

	private function isSameColor(array $firstColor, array $secondColor, int $allowedDelta = 1) {
		if ($this->isSameColorComponent($firstColor['red'], $secondColor['red'], $allowedDelta) &&
			$this->isSameColorComponent($firstColor['green'], $secondColor['green'], $allowedDelta) &&
			$this->isSameColorComponent($firstColor['blue'], $secondColor['blue'], $allowedDelta) &&
			$this->isSameColorComponent($firstColor['alpha'], $secondColor['alpha'], $allowedDelta)) {
			return true;
		}

		return false;
	}

	private function isSameColorComponent(int $firstColorComponent, int $secondColorComponent, int $allowedDelta) {
		if ($firstColorComponent >= ($secondColorComponent - $allowedDelta) &&
			$firstColorComponent <= ($secondColorComponent + $allowedDelta)) {
			return true;
		}

		return false;
	}
}
