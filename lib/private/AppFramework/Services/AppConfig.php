<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Morris Jobke <hey@morrisjobke.de>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\AppFramework\Services;

use OCP\AppFramework\Services\IAppConfig;
use OCP\IConfig;

class AppConfig implements IAppConfig {

	/** @var IConfig */
	private $config;

	/** @var string */
	private $appName;

	public function __construct(IConfig $config, string $appName) {
		$this->config = $config;
		$this->appName = $appName;
	}

	public function getAppKeys(): array {
		return $this->config->getAppKeys($this->appName);
	}

	public function setAppValue(string $key, string $value): void {
		$this->config->setAppValue($this->appName, $key, $value);
	}

	public function getAppValue(string $key, string $default = ''): string {
		return $this->config->getAppValue($this->appName, $key, $default);
	}

	public function deleteAppValue(string $key): void {
		$this->config->deleteAppValue($this->appName, $key);
	}

	public function deleteAppValues(): void {
		$this->config->deleteAppValues($this->appName);
	}

	public function setUserValue(string $userId, string $key, string $value, ?string $preCondition = null): void {
		$this->config->setUserValue($userId, $this->appName, $key, $value, $preCondition);
	}

	public function getUserValue(string $userId, string $key, string $default = ''): string {
		return $this->config->getUserValue($userId, $this->appName, $key, $default);
	}

	public function deleteUserValue(string $userId, string $key): void {
		$this->config->deleteUserValue($userId, $this->appName, $key);
	}
}
