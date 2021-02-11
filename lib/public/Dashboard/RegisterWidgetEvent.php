<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCP\Dashboard;

use OCP\EventDispatcher\Event;

/**
 * Class RegisterPanelEvent
 *
 * This event is dispatched to allow apps supporting older Nextcloud versions to
 * still register their dashboard panels so that they are only constructed when
 * they are needed. Deprecated right away so we can drop it again after 19 is EOL
 * and backward compatible apps can use OCP\AppFramework\Bootstrap\IBootstrap
 *
 * @since 20.0.0
 * @deprecated 20.0.0
 */
class RegisterWidgetEvent extends Event {
	private $manager;

	public function __construct(IManager $manager) {
		parent::__construct();

		$this->manager = $manager;
	}

	/**
	 * @param string $panelClass
	 * @since 20.0.0
	 */
	public function registerWidget(string $panelClass) {
		$this->manager->lazyRegisterWidget($panelClass);
	}
}
