<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Gary Kim <gary@garykim.dev>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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

use OCA\DAV\DAV\Sharing\IShareable;
use OCA\DAV\Exception\UnsupportedLimitOnInitialSyncException;
use OCP\IConfig;
use OCP\IL10N;
use Sabre\CalDAV\Backend\BackendInterface;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\PropPatch;

/**
 * Class Calendar
 *
 * @package OCA\DAV\CalDAV
 * @property BackendInterface|CalDavBackend $caldavBackend
 */
class Calendar extends \Sabre\CalDAV\Calendar implements IShareable {

	/** @var IConfig */
	private $config;

	/** @var IL10N */
	protected $l10n;

	/**
	 * Calendar constructor.
	 *
	 * @param BackendInterface $caldavBackend
	 * @param $calendarInfo
	 * @param IL10N $l10n
	 * @param IConfig $config
	 */
	public function __construct(BackendInterface $caldavBackend, $calendarInfo, IL10N $l10n, IConfig $config) {
		parent::__construct($caldavBackend, $calendarInfo);

		if ($this->getName() === BirthdayService::BIRTHDAY_CALENDAR_URI) {
			$this->calendarInfo['{DAV:}displayname'] = $l10n->t('Contact birthdays');
		}
		if ($this->getName() === CalDavBackend::PERSONAL_CALENDAR_URI &&
			$this->calendarInfo['{DAV:}displayname'] === CalDavBackend::PERSONAL_CALENDAR_NAME) {
			$this->calendarInfo['{DAV:}displayname'] = $l10n->t('Personal');
		}

		$this->config = $config;
		$this->l10n = $l10n;
	}

	/**
	 * Updates the list of shares.
	 *
	 * The first array is a list of people that are to be added to the
	 * resource.
	 *
	 * Every element in the add array has the following properties:
	 *   * href - A url. Usually a mailto: address
	 *   * commonName - Usually a first and last name, or false
	 *   * summary - A description of the share, can also be false
	 *   * readOnly - A boolean value
	 *
	 * Every element in the remove array is just the address string.
	 *
	 * @param array $add
	 * @param array $remove
	 * @return void
	 * @throws Forbidden
	 */
	public function updateShares(array $add, array $remove) {
		if ($this->isShared()) {
			throw new Forbidden();
		}
		$this->caldavBackend->updateShares($this, $add, $remove);
	}

	/**
	 * Returns the list of people whom this resource is shared with.
	 *
	 * Every element in this array should have the following properties:
	 *   * href - Often a mailto: address
	 *   * commonName - Optional, for example a first + last name
	 *   * status - See the Sabre\CalDAV\SharingPlugin::STATUS_ constants.
	 *   * readOnly - boolean
	 *   * summary - Optional, a description for the share
	 *
	 * @return array
	 */
	public function getShares() {
		if ($this->isShared()) {
			return [];
		}
		return $this->caldavBackend->getShares($this->getResourceId());
	}

	/**
	 * @return int
	 */
	public function getResourceId() {
		return $this->calendarInfo['id'];
	}

	/**
	 * @return string
	 */
	public function getPrincipalURI() {
		return $this->calendarInfo['principaluri'];
	}

	/**
	 * @return array
	 */
	public function getACL() {
		$acl = [
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner(),
				'protected' => true,
			],
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner() . '/calendar-proxy-write',
				'protected' => true,
			],
			[
				'privilege' => '{DAV:}read',
				'principal' => $this->getOwner() . '/calendar-proxy-read',
				'protected' => true,
			],
		];

		if ($this->getName() !== BirthdayService::BIRTHDAY_CALENDAR_URI) {
			$acl[] = [
				'privilege' => '{DAV:}write',
				'principal' => $this->getOwner(),
				'protected' => true,
			];
			$acl[] = [
				'privilege' => '{DAV:}write',
				'principal' => $this->getOwner() . '/calendar-proxy-write',
				'protected' => true,
			];
		} else {
			$acl[] = [
				'privilege' => '{DAV:}write-properties',
				'principal' => $this->getOwner(),
				'protected' => true,
			];
			$acl[] = [
				'privilege' => '{DAV:}write-properties',
				'principal' => $this->getOwner() . '/calendar-proxy-write',
				'protected' => true,
			];
		}

		$acl[] = [
			'privilege' => '{DAV:}write-properties',
			'principal' => $this->getOwner() . '/calendar-proxy-read',
			'protected' => true,
		];

		if (!$this->isShared()) {
			return $acl;
		}

		if ($this->getOwner() !== parent::getOwner()) {
			$acl[] = [
				'privilege' => '{DAV:}read',
				'principal' => parent::getOwner(),
				'protected' => true,
			];
			if ($this->canWrite()) {
				$acl[] = [
					'privilege' => '{DAV:}write',
					'principal' => parent::getOwner(),
					'protected' => true,
				];
			} else {
				$acl[] = [
					'privilege' => '{DAV:}write-properties',
					'principal' => parent::getOwner(),
					'protected' => true,
				];
			}
		}
		if ($this->isPublic()) {
			$acl[] = [
				'privilege' => '{DAV:}read',
				'principal' => 'principals/system/public',
				'protected' => true,
			];
		}

		$acl = $this->caldavBackend->applyShareAcl($this->getResourceId(), $acl);
		$allowedPrincipals = [
			$this->getOwner(),
			$this->getOwner(). '/calendar-proxy-read',
			$this->getOwner(). '/calendar-proxy-write',
			parent::getOwner(),
			'principals/system/public'
		];
		return array_filter($acl, function ($rule) use ($allowedPrincipals) {
			return \in_array($rule['principal'], $allowedPrincipals, true);
		});
	}

	public function getChildACL() {
		return $this->getACL();
	}

	public function getOwner() {
		if (isset($this->calendarInfo['{http://owncloud.org/ns}owner-principal'])) {
			return $this->calendarInfo['{http://owncloud.org/ns}owner-principal'];
		}
		return parent::getOwner();
	}

	public function delete() {
		if (isset($this->calendarInfo['{http://owncloud.org/ns}owner-principal']) &&
			$this->calendarInfo['{http://owncloud.org/ns}owner-principal'] !== $this->calendarInfo['principaluri']) {
			$principal = 'principal:' . parent::getOwner();
			$shares = $this->caldavBackend->getShares($this->getResourceId());
			$shares = array_filter($shares, function ($share) use ($principal) {
				return $share['href'] === $principal;
			});
			if (empty($shares)) {
				throw new Forbidden();
			}

			$this->caldavBackend->updateShares($this, [], [
				$principal
			]);
			return;
		}

		// Remember when a user deleted their birthday calendar
		// in order to not regenerate it on the next contacts change
		if ($this->getName() === BirthdayService::BIRTHDAY_CALENDAR_URI) {
			$principalURI = $this->getPrincipalURI();
			$userId = substr($principalURI, 17);

			$this->config->setUserValue($userId, 'dav', 'generateBirthdayCalendar', 'no');
		}

		parent::delete();
	}

	public function propPatch(PropPatch $propPatch) {
		// parent::propPatch will only update calendars table
		// if calendar is shared, changes have to be made to the properties table
		if (!$this->isShared()) {
			parent::propPatch($propPatch);
		}
	}

	public function getChild($name) {
		$obj = $this->caldavBackend->getCalendarObject($this->calendarInfo['id'], $name);

		if (!$obj) {
			throw new NotFound('Calendar object not found');
		}

		if ($obj['classification'] === CalDavBackend::CLASSIFICATION_PRIVATE && $this->isShared()) {
			throw new NotFound('Calendar object not found');
		}

		$obj['acl'] = $this->getChildACL();

		return new CalendarObject($this->caldavBackend, $this->l10n, $this->calendarInfo, $obj);
	}

	public function getChildren() {
		$objs = $this->caldavBackend->getCalendarObjects($this->calendarInfo['id']);
		$children = [];
		foreach ($objs as $obj) {
			if ($obj['classification'] === CalDavBackend::CLASSIFICATION_PRIVATE && $this->isShared()) {
				continue;
			}
			$obj['acl'] = $this->getChildACL();
			$children[] = new CalendarObject($this->caldavBackend, $this->l10n, $this->calendarInfo, $obj);
		}
		return $children;
	}

	public function getMultipleChildren(array $paths) {
		$objs = $this->caldavBackend->getMultipleCalendarObjects($this->calendarInfo['id'], $paths);
		$children = [];
		foreach ($objs as $obj) {
			if ($obj['classification'] === CalDavBackend::CLASSIFICATION_PRIVATE && $this->isShared()) {
				continue;
			}
			$obj['acl'] = $this->getChildACL();
			$children[] = new CalendarObject($this->caldavBackend, $this->l10n, $this->calendarInfo, $obj);
		}
		return $children;
	}

	public function childExists($name) {
		$obj = $this->caldavBackend->getCalendarObject($this->calendarInfo['id'], $name);
		if (!$obj) {
			return false;
		}
		if ($obj['classification'] === CalDavBackend::CLASSIFICATION_PRIVATE && $this->isShared()) {
			return false;
		}

		return true;
	}

	public function calendarQuery(array $filters) {
		$uris = $this->caldavBackend->calendarQuery($this->calendarInfo['id'], $filters);
		if ($this->isShared()) {
			return array_filter($uris, function ($uri) {
				return $this->childExists($uri);
			});
		}

		return $uris;
	}

	/**
	 * @param boolean $value
	 * @return string|null
	 */
	public function setPublishStatus($value) {
		$publicUri = $this->caldavBackend->setPublishStatus($value, $this);
		$this->calendarInfo['publicuri'] = $publicUri;
		return $publicUri;
	}

	/**
	 * @return mixed $value
	 */
	public function getPublishStatus() {
		return $this->caldavBackend->getPublishStatus($this);
	}

	public function canWrite() {
		if ($this->getName() === BirthdayService::BIRTHDAY_CALENDAR_URI) {
			return false;
		}

		if (isset($this->calendarInfo['{http://owncloud.org/ns}read-only'])) {
			return !$this->calendarInfo['{http://owncloud.org/ns}read-only'];
		}
		return true;
	}

	private function isPublic() {
		return isset($this->calendarInfo['{http://owncloud.org/ns}public']);
	}

	protected function isShared() {
		if (!isset($this->calendarInfo['{http://owncloud.org/ns}owner-principal'])) {
			return false;
		}

		return $this->calendarInfo['{http://owncloud.org/ns}owner-principal'] !== $this->calendarInfo['principaluri'];
	}

	public function isSubscription() {
		return isset($this->calendarInfo['{http://calendarserver.org/ns/}source']);
	}

	/**
	 * @inheritDoc
	 */
	public function getChanges($syncToken, $syncLevel, $limit = null) {
		if (!$syncToken && $limit) {
			throw new UnsupportedLimitOnInitialSyncException();
		}

		return parent::getChanges($syncToken, $syncLevel, $limit);
	}
}
