<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OC\AppFramework\Bootstrap;

use Closure;
use OCP\AppFramework\QueryException;
use Psr\Container\ContainerInterface;
use ReflectionFunction;
use ReflectionParameter;
use function array_map;

class FunctionInjector {

	/** @var ContainerInterface */
	private $container;

	public function __construct(ContainerInterface $container) {
		$this->container = $container;
	}

	public function injectFn(callable $fn) {
		$reflected = new ReflectionFunction(Closure::fromCallable($fn));
		return $fn(...array_map(function (ReflectionParameter $param) {
			// First we try by type (more likely these days)
			if (($type = $param->getType()) !== null) {
				try {
					return $this->container->get($type->getName());
				} catch (QueryException $ex) {
					// Ignore and try name as well
				}
			}
			// Second we try by name (mostly for primitives)
			try {
				return $this->container->get($param->getName());
			} catch (QueryException $ex) {
				// As a last resort we pass `null` if allowed
				if ($type !== null && $type->allowsNull()) {
					return null;
				}

				// Nothing worked, time to bail out
				throw $ex;
			}
		}, $reflected->getParameters()));
	}
}
