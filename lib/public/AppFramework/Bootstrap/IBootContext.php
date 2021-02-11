<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCP\AppFramework\Bootstrap;

use OCP\AppFramework\IAppContainer;
use OCP\IServerContainer;
use Psr\Container\ContainerExceptionInterface;
use Throwable;

/**
 * @since 20.0.0
 */
interface IBootContext {

	/**
	 * Get hold of the app's container
	 *
	 * Useful to register and query app-specific services
	 *
	 * @return IAppContainer
	 * @since 20.0.0
	 */
	public function getAppContainer(): IAppContainer;

	/**
	 * Get hold of the server DI container
	 *
	 * Useful to register and query system-wide services
	 *
	 * @return IServerContainer
	 * @since 20.0.0
	 */
	public function getServerContainer(): IServerContainer;

	/**
	 * Invoke the given callable and inject all parameters based on their types
	 * and names
	 *
	 * Note: when used with methods, make sure they are public or use \Closure::fromCallable
	 * to wrap the private method call, e.g.
	 *  * `$context->injectFn([$obj, 'publicMethod'])`
	 *  * `$context->injectFn([$this, 'publicMethod'])`
	 *  * `$context->injectFn(\Closure::fromCallable([$this, 'privateMethod']))`
	 *
	 * Note: the app container will be queried
	 *
	 * @param callable $fn
	 * @throws ContainerExceptionInterface if at least one of the parameter can't be resolved
	 * @throws Throwable any error the function invocation might cause
	 * @return mixed|null the return value of the invoked function, if any
	 * @since 20.0.0
	 */
	public function injectFn(callable $fn);
}
