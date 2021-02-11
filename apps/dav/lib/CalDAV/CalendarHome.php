<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\DAV\CalDAV;

use OCA\DAV\AppInfo\PluginManager;
use OCA\DAV\CalDAV\Integration\ExternalCalendar;
use OCA\DAV\CalDAV\Integration\ICalendarProvider;
use Sabre\CalDAV\Backend\BackendInterface;
use Sabre\CalDAV\Backend\NotificationSupport;
use Sabre\CalDAV\Backend\SchedulingSupport;
use Sabre\CalDAV\Backend\SubscriptionSupport;
use Sabre\CalDAV\Schedule\Inbox;
use Sabre\CalDAV\Subscriptions\Subscription;
use Sabre\DAV\Exception\MethodNotAllowed;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\MkCol;

class CalendarHome extends \Sabre\CalDAV\CalendarHome {

	/** @var \OCP\IL10N */
	private $l10n;

	/** @var \OCP\IConfig */
	private $config;

	/** @var PluginManager */
	private $pluginManager;

	/** @var bool */
	private $returnCachedSubscriptions = false;

	public function __construct(BackendInterface $caldavBackend, $principalInfo) {
		parent::__construct($caldavBackend, $principalInfo);
		$this->l10n = \OC::$server->getL10N('dav');
		$this->config = \OC::$server->getConfig();
		$this->pluginManager = new PluginManager(
			\OC::$server,
			\OC::$server->getAppManager()
		);
	}

	/**
	 * @return BackendInterface
	 */
	public function getCalDAVBackend() {
		return $this->caldavBackend;
	}

	/**
	 * @inheritdoc
	 */
	public function createExtendedCollection($name, MkCol $mkCol) {
		$reservedNames = [BirthdayService::BIRTHDAY_CALENDAR_URI];

		if (\in_array($name, $reservedNames, true) || ExternalCalendar::doesViolateReservedName($name)) {
			throw new MethodNotAllowed('The resource you tried to create has a reserved name');
		}

		parent::createExtendedCollection($name, $mkCol);
	}

	/**
	 * @inheritdoc
	 */
	public function getChildren() {
		$calendars = $this->caldavBackend->getCalendarsForUser($this->principalInfo['uri']);
		$objects = [];
		foreach ($calendars as $calendar) {
			$objects[] = new Calendar($this->caldavBackend, $calendar, $this->l10n, $this->config);
		}

		if ($this->caldavBackend instanceof SchedulingSupport) {
			$objects[] = new Inbox($this->caldavBackend, $this->principalInfo['uri']);
			$objects[] = new Outbox($this->config, $this->principalInfo['uri']);
		}

		// We're adding a notifications node, if it's supported by the backend.
		if ($this->caldavBackend instanceof NotificationSupport) {
			$objects[] = new \Sabre\CalDAV\Notifications\Collection($this->caldavBackend, $this->principalInfo['uri']);
		}

		// If the backend supports subscriptions, we'll add those as well,
		if ($this->caldavBackend instanceof SubscriptionSupport) {
			foreach ($this->caldavBackend->getSubscriptionsForUser($this->principalInfo['uri']) as $subscription) {
				if ($this->returnCachedSubscriptions) {
					$objects[] = new CachedSubscription($this->caldavBackend, $subscription);
				} else {
					$objects[] = new Subscription($this->caldavBackend, $subscription);
				}
			}
		}

		foreach ($this->pluginManager->getCalendarPlugins() as $calendarPlugin) {
			/** @var ICalendarProvider $calendarPlugin */
			$calendars = $calendarPlugin->fetchAllForCalendarHome($this->principalInfo['uri']);
			foreach ($calendars as $calendar) {
				$objects[] = $calendar;
			}
		}

		return $objects;
	}

	/**
	 * @inheritdoc
	 */
	public function getChild($name) {
		// Special nodes
		if ($name === 'inbox' && $this->caldavBackend instanceof SchedulingSupport) {
			return new Inbox($this->caldavBackend, $this->principalInfo['uri']);
		}
		if ($name === 'outbox' && $this->caldavBackend instanceof SchedulingSupport) {
			return new Outbox($this->config, $this->principalInfo['uri']);
		}
		if ($name === 'notifications' && $this->caldavBackend instanceof NotificationSupport) {
			return new \Sabre\CalDAv\Notifications\Collection($this->caldavBackend, $this->principalInfo['uri']);
		}

		// Calendars
		foreach ($this->caldavBackend->getCalendarsForUser($this->principalInfo['uri']) as $calendar) {
			if ($calendar['uri'] === $name) {
				return new Calendar($this->caldavBackend, $calendar, $this->l10n, $this->config);
			}
		}

		if ($this->caldavBackend instanceof SubscriptionSupport) {
			foreach ($this->caldavBackend->getSubscriptionsForUser($this->principalInfo['uri']) as $subscription) {
				if ($subscription['uri'] === $name) {
					if ($this->returnCachedSubscriptions) {
						return new CachedSubscription($this->caldavBackend, $subscription);
					}

					return new Subscription($this->caldavBackend, $subscription);
				}
			}
		}

		if (ExternalCalendar::isAppGeneratedCalendar($name)) {
			[$appId, $calendarUri] = ExternalCalendar::splitAppGeneratedCalendarUri($name);

			foreach ($this->pluginManager->getCalendarPlugins() as $calendarPlugin) {
				/** @var ICalendarProvider $calendarPlugin */
				if ($calendarPlugin->getAppId() !== $appId) {
					continue;
				}

				if ($calendarPlugin->hasCalendarInCalendarHome($this->principalInfo['uri'], $calendarUri)) {
					return $calendarPlugin->getCalendarInCalendarHome($this->principalInfo['uri'], $calendarUri);
				}
			}
		}

		throw new NotFound('Node with name \'' . $name . '\' could not be found');
	}

	/**
	 * @param array $filters
	 * @param integer|null $limit
	 * @param integer|null $offset
	 */
	public function calendarSearch(array $filters, $limit = null, $offset = null) {
		$principalUri = $this->principalInfo['uri'];
		return $this->caldavBackend->calendarSearch($principalUri, $filters, $limit, $offset);
	}


	public function enableCachedSubscriptionsForThisRequest() {
		$this->returnCachedSubscriptions = true;
	}
}
