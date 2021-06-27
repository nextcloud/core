<?php
/**
 * @copyright Copyright (c) 2020, Nextcloud, GmbH.
 *
 * @author Vincent Petry <vincent@nextcloud.com>
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

namespace OC\Preview;

use OCP\Files\File;
use OCP\Http\Client;
use OCP\IImage;

class Imaginary extends ProviderV2 {
	/**
	 * {@inheritDoc}
	 */
	public function getMimeType(): string {
		return '/image\/.*/';
	}

	/**
	 * {@inheritDoc}
	 */
	public function getThumbnail(File $file, int $maxX, int $maxY): ?IImage {
		\OCP\Util::writeLog(self::class, "#### Imaginary getThumbnail", \OCP\ILogger::DEBUG);
		$maxSizeForImages = \OC::$server->getConfig()->getSystemValue('preview_max_filesize_image', 50);
		$size = $file->getSize();

		if ($maxSizeForImages !== -1 && $size > ($maxSizeForImages * 1024 * 1024)) {
			return null;
		}

		$config = \OC::$server->getSystemConfig();
		$service = \OC::$server->getHTTPClientService();
		$baseUrl = $config->getValue('preview_imaginary_url', 'http://vvortex.local:9090');
		$baseUrl = rtrim($baseUrl, '/');
		$stream = $file->fopen('r');

		// TODO: when dealing with local storage, could send a local file path
		// to retrieve the file directly, assuming that the docker has access

		try {
			\OCP\Util::writeLog(self::class, "#### Imaginary sending stream", \OCP\ILogger::DEBUG);
			$client = $service->newClient();
			$response = $client->post(
				$baseUrl . "/fit?width=$maxX&height=$maxY&stripmeta=true", [
					//'headers' => [ 'Content-Type' => $file->getMimeType()],
					'stream' => true,
					'content-type' => $file->getMimeType(),
					'body' => $stream,
					'nextcloud' => ['allow_local_address' => true],
				]);
			\OCP\Util::writeLog(self::class, "#### Imaginary response status " . $response->getStatusCode(), \OCP\ILogger::DEBUG);
		} finally {
			fclose($stream);
		}

		if ($response->getStatusCode() !== 200) {
			return null;
		}

		$image = new \OC_Image();
		$image->loadFromFileHandle($response->getBody());
		if ($image->valid()) {
			return $image;
		}

		return null;
	}
}
