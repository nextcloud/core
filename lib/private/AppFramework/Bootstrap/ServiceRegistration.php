<?php

declare(strict_types=1);

/*
 * @copyright 2021 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2021 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 */

namespace OC\AppFramework\Bootstrap;

/**
 * @psalm-immutable
 * @template T
 */
class ServiceRegistration extends ARegistration {
	/**
	 * @var string
	 * @psalm-var class-string<T>
	 */
	private $service;

	/**
	 * @psalm-param class-string<T> $service
	 */
	public function __construct(string $appId, string $service) {
		parent::__construct($appId);
		$this->service = $service;
	}

	/**
	 * @psalm-return class-string<T>
	 */
	public function getService(): string {
		return $this->service;
	}
}
