<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCP;

use Closure;

/**
 * @since 16.0.0
 */
interface IInitialStateService {
	/**
	 * Allows an app to provide its initial state to the template system.
	 * Use this if you know your initial state sill be used for example if
	 * you are in the render function of you controller.
	 *
	 * @since 16.0.0
	 *
	 * @param string $appName
	 * @param string $key
	 * @param bool|int|float|string|array|\JsonSerializable $data
	 */
	public function provideInitialState(string $appName, string $key, $data): void;

	/**
	 * Allows an app to provide its initial state via a lazy method.
	 * This will call the closure when the template is being generated.
	 * Use this if your app is injected into pages. Since then the render method
	 * is not called explicitly. But we do not want to load the state on webdav
	 * requests for example.
	 *
	 * @since 16.0.0
	 *
	 * @param string $appName
	 * @param string $key
	 * @param Closure $closure returns a primitive or an object that implements JsonSerializable
	 */
	public function provideLazyInitialState(string $appName, string $key, Closure $closure): void;
}
