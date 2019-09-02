<?php
/**
 * @copyright Copyright (c) 2016, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OC\Preview;

use OC\Files\View;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\IConfig;
use OCP\IImage;
use OCP\Image as OCPImage;
use OCP\Preview\IProvider;
use OCP\Preview\IProviderV2;

// TODO use autoloader
require_once 'JPEG.php';
use OC\Preview\OC_Image_JPEG as OCPImage_JPEG;


/**
 * Very small wrapper class to make the generator fully unit testable
 */
class GeneratorHelper {

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IConfig */
	private $config;

	public function __construct(IRootFolder $rootFolder, IConfig $config) {
		$this->rootFolder = $rootFolder;
		$this->config = $config;
	}

	/**
	 * @param IProvider $provider
	 * @param File $file
	 * @param int $maxWidth
	 * @param int $maxHeight
	 * @return bool|IImage
	 */
	public function getThumbnail(IProviderV2 $provider, File $file, $maxWidth, $maxHeight) {
		return $provider->getThumbnail($file, $maxWidth, $maxHeight);
	}

	/**
	 * @param ISimpleFile $maxPreview
	 * @return IImage
	 */
	public function getImage($maxPreview) {
		$mimeType = $maxPreview->getMimeType();
		$imagick_mode = (bool)$this->config->getSystemValue('preview_use_imagick', false);
		if ($imagick_mode && $mimeType !== null && extension_loaded('imagick')) {
			switch ($mimeType) {
				case 'image/jpeg':
					$image = new OCPImage_JPEG();
					break;
				default:
					$image = new OCPImage();
			}
		} else {
			$image = new OCPImage();
		}
		$image->loadFromData($maxPreview->getContent());
		return $image;
	}

	/**
	 * @param callable $providerClosure
	 * @return IProviderV2
	 */
	public function getProvider($providerClosure) {
		$provider = $providerClosure();
		if ($provider instanceof IProvider) {
			$provider = new ProviderV1Adapter($provider);
		}
		return $provider;
	}
}
