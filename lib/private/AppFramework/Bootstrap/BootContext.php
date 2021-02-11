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

namespace OC\AppFramework\Bootstrap;

use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\IAppContainer;
use OCP\IServerContainer;

class BootContext implements IBootContext {

	/** @var IAppContainer */
	private $appContainer;

	public function __construct(IAppContainer $appContainer) {
		$this->appContainer = $appContainer;
	}

	public function getAppContainer(): IAppContainer {
		return $this->appContainer;
	}

	public function getServerContainer(): IServerContainer {
		return $this->appContainer->get(IServerContainer::class);
	}

	public function injectFn(callable $fn) {
		return (new FunctionInjector($this->appContainer))->injectFn($fn);
	}
}
