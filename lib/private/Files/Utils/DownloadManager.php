<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

namespace OC\Files\Utils;

use OCP\Files\NotFoundException;
use OCP\Files\Utils\IDownloadManager;
use OCP\IConfig;
use OCP\Security\ISecureRandom;
use RuntimeException;
use function json_decode;
use function json_encode;

class DownloadManager implements IDownloadManager {
	/**
	 * Lifetime of tokens. Period of 2 days are chosen to allow continuation
	 * of downloads with network interruptions in mind
	 */
	protected const TOKEN_TTL = 24 * 60 * 2;
	protected const TOKEN_PREFIX = 'dl_token_';

	protected const FIELD_DATA = 'downloadData';
	protected const FIELD_ACTIVITY = 'lastActivity';

	/** @var IConfig */
	private $config;
	/** @var ISecureRandom */
	private $secureRandom;

	public function __construct(IConfig $config, ISecureRandom $secureRandom) {
		$this->config = $config;
		$this->secureRandom = $secureRandom;
	}

	/**
	 * @inheritDoc
	 */
	public function register(array $data): string {
		$attempts = 0;
		do {
			if ($attempts === 10) {
				throw new RuntimeException('Failed to create unique download token');
			}
			$token = $this->secureRandom->generate(15);
			$attempts++;
		} while ($this->config->getAppValue('core', self::TOKEN_PREFIX . $token, '') !== '');

		$this->config->setAppValue(
			'core',
			self::TOKEN_PREFIX . $token,
			json_encode([
				self::FIELD_DATA => $data,
				self::FIELD_ACTIVITY => time()
			])
		);

		return $token;
	}

	/**
	 * @inheritDoc
	 */
	public function retrieve(string $token): array {
		$dataStr = $this->config->getAppValue('core', self::TOKEN_PREFIX . $token, '');
		if ($dataStr === '') {
			throw new NotFoundException();
		}

		$data = json_decode($dataStr, true);
		$data[self::FIELD_ACTIVITY] = time();
		$this->config->setAppValue('core', self::TOKEN_PREFIX . $token, json_encode($data));

		return $data[self::FIELD_DATA];
	}

	public function cleanupTokens(): void {
		$appKeys = $this->config->getAppKeys('core');
		foreach ($appKeys as $key) {
			if (strpos($key, self::TOKEN_PREFIX) !== 0) {
				continue;
			}
			$dataStr = $this->config->getAppValue('core', $key, '');
			if ($dataStr === '') {
				$this->config->deleteAppValue('core', $key);
				continue;
			}
			$data = json_decode($dataStr, true);
			if (!isset($data[self::FIELD_ACTIVITY])
				|| (time() - $data[self::FIELD_ACTIVITY]) > self::TOKEN_TTL
			) {
				$this->config->deleteAppValue('core', $key);
			}
		}
	}
}
