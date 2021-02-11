<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Arthur Schiwon <blizzz@arthur-schiwon.de>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCP\WorkflowEngine;

/**
 * Interface IEntityCompat
 *
 * This interface extends IEntity to provide compatibility with old style
 * Event classes. It is only present for a transition period and will be
 * removed in 2023 again.
 *
 * @since 18.0.0
 * @deprecated
 */
interface IEntityCompat extends IEntity {
	/**
	 * Like prepareRuleMatcherCompat, but works with events that are not based
	 * on \OCP\EventDispatcher\Event.
	 *
	 * @since 18.0.0
	 * @deprecated
	 */
	public function prepareRuleMatcherCompat(IRuleMatcher $ruleMatcher, string $eventName, $event): void;
}
