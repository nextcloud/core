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
 */
class ServiceFactoryRegistration extends ARegistration {

	/**
	 * @var string
	 * @psalm-var string|class-string
	 */
	private $name;

	/**
	 * @var callable
	 * @psalm-var callable(\Psr\Container\ContainerInterface): mixed
	 */
	private $factory;

	/** @var bool */
	private $shared;

	public function __construct(string $appId,
								string $alias,
								callable $target,
								bool $shared) {
		parent::__construct($appId);
		$this->name = $alias;
		$this->factory = $target;
		$this->shared = $shared;
	}

	public function getName(): string {
		return $this->name;
	}

	/**
	 * @psalm-return callable(\Psr\Container\ContainerInterface): mixed
	 */
	public function getFactory(): callable {
		return $this->factory;
	}

	public function isShared(): bool {
		return $this->shared;
	}
}
