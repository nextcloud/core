<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\AppFramework\Services;

use OCP\AppFramework\Services\IInitialState;
use OCP\IInitialStateService;

class InitialState implements IInitialState {
	/** @var IInitialStateService */
	private $state;

	/** @var string */
	private $appName;

	public function __construct(IInitialStateService $state, string $appName) {
		$this->state = $state;
		$this->appName = $appName;
	}

	public function provideInitialState(string $key, $data): void {
		$this->state->provideInitialState($this->appName, $key, $data);
	}

	public function provideLazyInitialState(string $key, \Closure $closure): void {
		$this->state->provideLazyInitialState($this->appName, $key, $closure);
	}
}
