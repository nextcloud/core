<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCP\Activity;

/**
 * Interface IManager
 *
 * @package OCP\Activity
 * @since 6.0.0
 */
interface IManager {
	/**
	 * Generates a new IEvent object
	 *
	 * Make sure to call at least the following methods before sending it to the
	 * app with via the publish() method:
	 *  - setApp()
	 *  - setType()
	 *  - setAffectedUser()
	 *  - setSubject()
	 *
	 * @return IEvent
	 * @since 8.2.0
	 */
	public function generateEvent(): IEvent;

	/**
	 * Publish an event to the activity consumers
	 *
	 * Make sure to call at least the following methods before sending an Event:
	 *  - setApp()
	 *  - setType()
	 *  - setAffectedUser()
	 *  - setSubject()
	 *
	 * @param IEvent $event
	 * @throws \BadMethodCallException if required values have not been set
	 * @since 8.2.0
	 */
	public function publish(IEvent $event): void;

	/**
	 * In order to improve lazy loading a closure can be registered which will be called in case
	 * activity consumers are actually requested
	 *
	 * $callable has to return an instance of \OCP\Activity\IConsumer
	 *
	 * @param \Closure $callable
	 * @since 6.0.0
	 */
	public function registerConsumer(\Closure $callable): void;

	/**
	 * @param string $filter Class must implement OCA\Activity\IFilter
	 * @since 11.0.0
	 */
	public function registerFilter(string $filter): void;

	/**
	 * @return IFilter[]
	 * @since 11.0.0
	 */
	public function getFilters(): array;

	/**
	 * @param string $id
	 * @return IFilter
	 * @throws \InvalidArgumentException when the filter was not found
	 * @since 11.0.0
	 */
	public function getFilterById(string $id): IFilter;

	/**
	 * @param string $setting Class must implement OCA\Activity\ISetting
	 * @since 11.0.0
	 */
	public function registerSetting(string $setting): void;

	/**
	 * @return ISetting[]
	 * @since 11.0.0
	 */
	public function getSettings(): array;

	/**
	 * @param string $provider Class must implement OCA\Activity\IProvider
	 * @since 11.0.0
	 */
	public function registerProvider(string $provider): void;

	/**
	 * @return IProvider[]
	 * @since 11.0.0
	 */
	public function getProviders(): array;

	/**
	 * @param string $id
	 * @return ISetting
	 * @throws \InvalidArgumentException when the setting was not found
	 * @since 11.0.0
	 */
	public function getSettingById(string $id): ISetting;

	/**
	 * @param string $type
	 * @param int $id
	 * @since 8.2.0
	 */
	public function setFormattingObject(string $type, int $id): void;

	/**
	 * @return bool
	 * @since 8.2.0
	 */
	public function isFormattingFilteredObject(): bool;

	/**
	 * @param bool $status Set to true, when parsing events should not use SVG icons
	 * @since 12.0.1
	 */
	public function setRequirePNG(bool $status): void;

	/**
	 * @return bool
	 * @since 12.0.1
	 */
	public function getRequirePNG(): bool;

	/**
	 * Set the user we need to use
	 *
	 * @param string|null $currentUserId
	 * @throws \UnexpectedValueException If the user is invalid
	 * @since 9.0.1
	 */
	public function setCurrentUserId(string $currentUserId = null): void;

	/**
	 * Get the user we need to use
	 *
	 * Either the user is logged in, or we try to get it from the token
	 *
	 * @return string
	 * @throws \UnexpectedValueException If the token is invalid, does not exist or is not unique
	 * @since 8.1.0
	 */
	public function getCurrentUserId(): string;
}
