<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2018 Georg Ehrke
 * @copyright Copyright (c) 2020, leith abdulla (<online-nextcloud@eleith.com>)
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author dartcafe <github@dartcafe.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author leith abdulla <online-nextcloud@eleith.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Simon Spannagel <simonspa@kth.se>
 * @author Stefan Weil <sw@weilnetz.de>
 * @author Thomas Citharel <nextcloud@tcit.fr>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vinicius Cubas Brand <vinicius@eita.org.br>
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

use DateTime;
use OCA\DAV\Connector\Sabre\Principal;
use OCA\DAV\DAV\Sharing\Backend;
use OCA\DAV\DAV\Sharing\IShareable;
use OCA\DAV\Events\CachedCalendarObjectCreatedEvent;
use OCA\DAV\Events\CachedCalendarObjectDeletedEvent;
use OCA\DAV\Events\CachedCalendarObjectUpdatedEvent;
use OCA\DAV\Events\CalendarCreatedEvent;
use OCA\DAV\Events\CalendarDeletedEvent;
use OCA\DAV\Events\CalendarObjectCreatedEvent;
use OCA\DAV\Events\CalendarObjectDeletedEvent;
use OCA\DAV\Events\CalendarObjectUpdatedEvent;
use OCA\DAV\Events\CalendarPublishedEvent;
use OCA\DAV\Events\CalendarShareUpdatedEvent;
use OCA\DAV\Events\CalendarUnpublishedEvent;
use OCA\DAV\Events\CalendarUpdatedEvent;
use OCA\DAV\Events\SubscriptionCreatedEvent;
use OCA\DAV\Events\SubscriptionDeletedEvent;
use OCA\DAV\Events\SubscriptionUpdatedEvent;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use Sabre\CalDAV\Backend\AbstractBackend;
use Sabre\CalDAV\Backend\SchedulingSupport;
use Sabre\CalDAV\Backend\SubscriptionSupport;
use Sabre\CalDAV\Backend\SyncSupport;
use Sabre\CalDAV\Xml\Property\ScheduleCalendarTransp;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\PropPatch;
use Sabre\Uri;
use Sabre\VObject\Component;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VTimeZone;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\ParseException;
use Sabre\VObject\Property;
use Sabre\VObject\Reader;
use Sabre\VObject\Recur\EventIterator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class CalDavBackend
 *
 * Code is heavily inspired by https://github.com/fruux/sabre-dav/blob/master/lib/CalDAV/Backend/PDO.php
 *
 * @package OCA\DAV\CalDAV
 */
class CalDavBackend extends AbstractBackend implements SyncSupport, SubscriptionSupport, SchedulingSupport {
	public const CALENDAR_TYPE_CALENDAR = 0;
	public const CALENDAR_TYPE_SUBSCRIPTION = 1;

	public const PERSONAL_CALENDAR_URI = 'personal';
	public const PERSONAL_CALENDAR_NAME = 'Personal';

	public const RESOURCE_BOOKING_CALENDAR_URI = 'calendar';
	public const RESOURCE_BOOKING_CALENDAR_NAME = 'Calendar';

	/**
	 * We need to specify a max date, because we need to stop *somewhere*
	 *
	 * On 32 bit system the maximum for a signed integer is 2147483647, so
	 * MAX_DATE cannot be higher than date('Y-m-d', 2147483647) which results
	 * in 2038-01-19 to avoid problems when the date is converted
	 * to a unix timestamp.
	 */
	public const MAX_DATE = '2038-01-01';

	public const ACCESS_PUBLIC = 4;
	public const CLASSIFICATION_PUBLIC = 0;
	public const CLASSIFICATION_PRIVATE = 1;
	public const CLASSIFICATION_CONFIDENTIAL = 2;

	/**
	 * List of CalDAV properties, and how they map to database field names
	 * Add your own properties by simply adding on to this array.
	 *
	 * Note that only string-based properties are supported here.
	 *
	 * @var array
	 */
	public $propertyMap = [
		'{DAV:}displayname' => 'displayname',
		'{urn:ietf:params:xml:ns:caldav}calendar-description' => 'description',
		'{urn:ietf:params:xml:ns:caldav}calendar-timezone' => 'timezone',
		'{http://apple.com/ns/ical/}calendar-order' => 'calendarorder',
		'{http://apple.com/ns/ical/}calendar-color' => 'calendarcolor',
	];

	/**
	 * List of subscription properties, and how they map to database field names.
	 *
	 * @var array
	 */
	public $subscriptionPropertyMap = [
		'{DAV:}displayname' => 'displayname',
		'{http://apple.com/ns/ical/}refreshrate' => 'refreshrate',
		'{http://apple.com/ns/ical/}calendar-order' => 'calendarorder',
		'{http://apple.com/ns/ical/}calendar-color' => 'calendarcolor',
		'{http://calendarserver.org/ns/}subscribed-strip-todos' => 'striptodos',
		'{http://calendarserver.org/ns/}subscribed-strip-alarms' => 'stripalarms',
		'{http://calendarserver.org/ns/}subscribed-strip-attachments' => 'stripattachments',
	];

	/** @var array properties to index */
	public static $indexProperties = ['CATEGORIES', 'COMMENT', 'DESCRIPTION',
		'LOCATION', 'RESOURCES', 'STATUS', 'SUMMARY', 'ATTENDEE', 'CONTACT',
		'ORGANIZER'];

	/** @var array parameters to index */
	public static $indexParameters = [
		'ATTENDEE' => ['CN'],
		'ORGANIZER' => ['CN'],
	];

	/**
	 * @var string[] Map of uid => display name
	 */
	protected $userDisplayNames;

	/** @var IDBConnection */
	private $db;

	/** @var Backend */
	private $calendarSharingBackend;

	/** @var Principal */
	private $principalBackend;

	/** @var IUserManager */
	private $userManager;

	/** @var ISecureRandom */
	private $random;

	/** @var ILogger */
	private $logger;

	/** @var IEventDispatcher */
	private $dispatcher;

	/** @var EventDispatcherInterface */
	private $legacyDispatcher;

	/** @var bool */
	private $legacyEndpoint;

	/** @var string */
	private $dbObjectPropertiesTable = 'calendarobjects_props';

	/**
	 * CalDavBackend constructor.
	 *
	 * @param IDBConnection $db
	 * @param Principal $principalBackend
	 * @param IUserManager $userManager
	 * @param IGroupManager $groupManager
	 * @param ISecureRandom $random
	 * @param ILogger $logger
	 * @param IEventDispatcher $dispatcher
	 * @param EventDispatcherInterface $legacyDispatcher
	 * @param bool $legacyEndpoint
	 */
	public function __construct(IDBConnection $db,
								Principal $principalBackend,
								IUserManager $userManager,
								IGroupManager $groupManager,
								ISecureRandom $random,
								ILogger $logger,
								IEventDispatcher $dispatcher,
								EventDispatcherInterface $legacyDispatcher,
								bool $legacyEndpoint = false) {
		$this->db = $db;
		$this->principalBackend = $principalBackend;
		$this->userManager = $userManager;
		$this->calendarSharingBackend = new Backend($this->db, $this->userManager, $groupManager, $principalBackend, 'calendar');
		$this->random = $random;
		$this->logger = $logger;
		$this->dispatcher = $dispatcher;
		$this->legacyDispatcher = $legacyDispatcher;
		$this->legacyEndpoint = $legacyEndpoint;
	}

	/**
	 * Return the number of calendars for a principal
	 *
	 * By default this excludes the automatically generated birthday calendar
	 *
	 * @param $principalUri
	 * @param bool $excludeBirthday
	 * @return int
	 */
	public function getCalendarsForUserCount($principalUri, $excludeBirthday = true) {
		$principalUri = $this->convertPrincipal($principalUri, true);
		$query = $this->db->getQueryBuilder();
		$query->select($query->func()->count('*'))
			->from('calendars');

		if ($principalUri === '') {
			$query->where($query->expr()->emptyString('principaluri'));
		} else {
			$query->where($query->expr()->eq('principaluri', $query->createNamedParameter($principalUri)));
		}

		if ($excludeBirthday) {
			$query->andWhere($query->expr()->neq('uri', $query->createNamedParameter(BirthdayService::BIRTHDAY_CALENDAR_URI)));
		}

		$result = $query->execute();
		$column = (int)$result->fetchOne();
		$result->closeCursor();
		return $column;
	}

	/**
	 * Returns a list of calendars for a principal.
	 *
	 * Every project is an array with the following keys:
	 *  * id, a unique id that will be used by other functions to modify the
	 *    calendar. This can be the same as the uri or a database key.
	 *  * uri, which the basename of the uri with which the calendar is
	 *    accessed.
	 *  * principaluri. The owner of the calendar. Almost always the same as
	 *    principalUri passed to this method.
	 *
	 * Furthermore it can contain webdav properties in clark notation. A very
	 * common one is '{DAV:}displayname'.
	 *
	 * Many clients also require:
	 * {urn:ietf:params:xml:ns:caldav}supported-calendar-component-set
	 * For this property, you can just return an instance of
	 * Sabre\CalDAV\Property\SupportedCalendarComponentSet.
	 *
	 * If you return {http://sabredav.org/ns}read-only and set the value to 1,
	 * ACL will automatically be put in read-only mode.
	 *
	 * @param string $principalUri
	 * @return array
	 */
	public function getCalendarsForUser($principalUri) {
		$principalUriOriginal = $principalUri;
		$principalUri = $this->convertPrincipal($principalUri, true);
		$fields = array_values($this->propertyMap);
		$fields[] = 'id';
		$fields[] = 'uri';
		$fields[] = 'synctoken';
		$fields[] = 'components';
		$fields[] = 'principaluri';
		$fields[] = 'transparent';

		// Making fields a comma-delimited list
		$query = $this->db->getQueryBuilder();
		$query->select($fields)
			->from('calendars')
			->orderBy('calendarorder', 'ASC');

		if ($principalUri === '') {
			$query->where($query->expr()->emptyString('principaluri'));
		} else {
			$query->where($query->expr()->eq('principaluri', $query->createNamedParameter($principalUri)));
		}

		$result = $query->execute();

		$calendars = [];
		while ($row = $result->fetch()) {
			$row['principaluri'] = (string) $row['principaluri'];
			$components = [];
			if ($row['components']) {
				$components = explode(',',$row['components']);
			}

			$calendar = [
				'id' => $row['id'],
				'uri' => $row['uri'],
				'principaluri' => $this->convertPrincipal($row['principaluri'], !$this->legacyEndpoint),
				'{' . Plugin::NS_CALENDARSERVER . '}getctag' => 'http://sabre.io/ns/sync/' . ($row['synctoken']?$row['synctoken']:'0'),
				'{http://sabredav.org/ns}sync-token' => $row['synctoken']?$row['synctoken']:'0',
				'{' . Plugin::NS_CALDAV . '}supported-calendar-component-set' => new SupportedCalendarComponentSet($components),
				'{' . Plugin::NS_CALDAV . '}schedule-calendar-transp' => new ScheduleCalendarTransp($row['transparent']?'transparent':'opaque'),
				'{' . \OCA\DAV\DAV\Sharing\Plugin::NS_OWNCLOUD . '}owner-principal' => $this->convertPrincipal($principalUri, !$this->legacyEndpoint),
			];

			foreach ($this->propertyMap as $xmlName => $dbName) {
				$calendar[$xmlName] = $row[$dbName];
			}

			$this->addOwnerPrincipal($calendar);

			if (!isset($calendars[$calendar['id']])) {
				$calendars[$calendar['id']] = $calendar;
			}
		}
		$result->closeCursor();

		// query for shared calendars
		$principals = $this->principalBackend->getGroupMembership($principalUriOriginal, true);
		$principals = array_merge($principals, $this->principalBackend->getCircleMembership($principalUriOriginal));

		$principals[] = $principalUri;

		$fields = array_values($this->propertyMap);
		$fields[] = 'a.id';
		$fields[] = 'a.uri';
		$fields[] = 'a.synctoken';
		$fields[] = 'a.components';
		$fields[] = 'a.principaluri';
		$fields[] = 'a.transparent';
		$fields[] = 's.access';
		$query = $this->db->getQueryBuilder();
		$query->select($fields)
			->from('dav_shares', 's')
			->join('s', 'calendars', 'a', $query->expr()->eq('s.resourceid', 'a.id'))
			->where($query->expr()->in('s.principaluri', $query->createParameter('principaluri')))
			->andWhere($query->expr()->eq('s.type', $query->createParameter('type')))
			->setParameter('type', 'calendar')
			->setParameter('principaluri', $principals, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);

		$result	= $query->execute();

		$readOnlyPropertyName = '{' . \OCA\DAV\DAV\Sharing\Plugin::NS_OWNCLOUD . '}read-only';
		while ($row = $result->fetch()) {
			$row['principaluri'] = (string) $row['principaluri'];
			if ($row['principaluri'] === $principalUri) {
				continue;
			}

			$readOnly = (int) $row['access'] === Backend::ACCESS_READ;
			if (isset($calendars[$row['id']])) {
				if ($readOnly) {
					// New share can not have more permissions then the old one.
					continue;
				}
				if (isset($calendars[$row['id']][$readOnlyPropertyName]) &&
					$calendars[$row['id']][$readOnlyPropertyName] === 0) {
					// Old share is already read-write, no more permissions can be gained
					continue;
				}
			}

			list(, $name) = Uri\split($row['principaluri']);
			$uri = $row['uri'] . '_shared_by_' . $name;
			$row['displayname'] = $row['displayname'] . ' (' . $this->getUserDisplayName($name) . ')';
			$components = [];
			if ($row['components']) {
				$components = explode(',',$row['components']);
			}
			$calendar = [
				'id' => $row['id'],
				'uri' => $uri,
				'principaluri' => $this->convertPrincipal($principalUri, !$this->legacyEndpoint),
				'{' . Plugin::NS_CALENDARSERVER . '}getctag' => 'http://sabre.io/ns/sync/' . ($row['synctoken']?$row['synctoken']:'0'),
				'{http://sabredav.org/ns}sync-token' => $row['synctoken']?$row['synctoken']:'0',
				'{' . Plugin::NS_CALDAV . '}supported-calendar-component-set' => new SupportedCalendarComponentSet($components),
				'{' . Plugin::NS_CALDAV . '}schedule-calendar-transp' => new ScheduleCalendarTransp('transparent'),
				'{' . \OCA\DAV\DAV\Sharing\Plugin::NS_OWNCLOUD . '}owner-principal' => $this->convertPrincipal($row['principaluri'], !$this->legacyEndpoint),
				$readOnlyPropertyName => $readOnly,
			];

			foreach ($this->propertyMap as $xmlName => $dbName) {
				$calendar[$xmlName] = $row[$dbName];
			}

			$this->addOwnerPrincipal($calendar);

			$calendars[$calendar['id']] = $calendar;
		}
		$result->closeCursor();

		return array_values($calendars);
	}

	/**
	 * @param $principalUri
	 * @return array
	 */
	public function getUsersOwnCalendars($principalUri) {
		$principalUri = $this->convertPrincipal($principalUri, true);
		$fields = array_values($this->propertyMap);
		$fields[] = 'id';
		$fields[] = 'uri';
		$fields[] = 'synctoken';
		$fields[] = 'components';
		$fields[] = 'principaluri';
		$fields[] = 'transparent';
		// Making fields a comma-delimited list
		$query = $this->db->getQueryBuilder();
		$query->select($fields)->from('calendars')
			->where($query->expr()->eq('principaluri', $query->createNamedParameter($principalUri)))
			->orderBy('calendarorder', 'ASC');
		$stmt = $query->execute();
		$calendars = [];
		while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			$row['principaluri'] = (string) $row['principaluri'];
			$components = [];
			if ($row['components']) {
				$components = explode(',',$row['components']);
			}
			$calendar = [
				'id' => $row['id'],
				'uri' => $row['uri'],
				'principaluri' => $this->convertPrincipal($row['principaluri'], !$this->legacyEndpoint),
				'{' . Plugin::NS_CALENDARSERVER . '}getctag' => 'http://sabre.io/ns/sync/' . ($row['synctoken']?$row['synctoken']:'0'),
				'{http://sabredav.org/ns}sync-token' => $row['synctoken']?$row['synctoken']:'0',
				'{' . Plugin::NS_CALDAV . '}supported-calendar-component-set' => new SupportedCalendarComponentSet($components),
				'{' . Plugin::NS_CALDAV . '}schedule-calendar-transp' => new ScheduleCalendarTransp($row['transparent']?'transparent':'opaque'),
			];
			foreach ($this->propertyMap as $xmlName => $dbName) {
				$calendar[$xmlName] = $row[$dbName];
			}

			$this->addOwnerPrincipal($calendar);

			if (!isset($calendars[$calendar['id']])) {
				$calendars[$calendar['id']] = $calendar;
			}
		}
		$stmt->closeCursor();
		return array_values($calendars);
	}


	/**
	 * @param $uid
	 * @return string
	 */
	private function getUserDisplayName($uid) {
		if (!isset($this->userDisplayNames[$uid])) {
			$user = $this->userManager->get($uid);

			if ($user instanceof IUser) {
				$this->userDisplayNames[$uid] = $user->getDisplayName();
			} else {
				$this->userDisplayNames[$uid] = $uid;
			}
		}

		return $this->userDisplayNames[$uid];
	}

	/**
	 * @return array
	 */
	public function getPublicCalendars() {
		$fields = array_values($this->propertyMap);
		$fields[] = 'a.id';
		$fields[] = 'a.uri';
		$fields[] = 'a.synctoken';
		$fields[] = 'a.components';
		$fields[] = 'a.principaluri';
		$fields[] = 'a.transparent';
		$fields[] = 's.access';
		$fields[] = 's.publicuri';
		$calendars = [];
		$query = $this->db->getQueryBuilder();
		$result = $query->select($fields)
			->from('dav_shares', 's')
			->join('s', 'calendars', 'a', $query->expr()->eq('s.resourceid', 'a.id'))
			->where($query->expr()->in('s.access', $query->createNamedParameter(self::ACCESS_PUBLIC)))
			->andWhere($query->expr()->eq('s.type', $query->createNamedParameter('calendar')))
			->execute();

		while ($row = $result->fetch()) {
			$row['principaluri'] = (string) $row['principaluri'];
			list(, $name) = Uri\split($row['principaluri']);
			$row['displayname'] = $row['displayname'] . "($name)";
			$components = [];
			if ($row['components']) {
				$components = explode(',',$row['components']);
			}
			$calendar = [
				'id' => $row['id'],
				'uri' => $row['publicuri'],
				'principaluri' => $this->convertPrincipal($row['principaluri'], !$this->legacyEndpoint),
				'{' . Plugin::NS_CALENDARSERVER . '}getctag' => 'http://sabre.io/ns/sync/' . ($row['synctoken']?$row['synctoken']:'0'),
				'{http://sabredav.org/ns}sync-token' => $row['synctoken']?$row['synctoken']:'0',
				'{' . Plugin::NS_CALDAV . '}supported-calendar-component-set' => new SupportedCalendarComponentSet($components),
				'{' . Plugin::NS_CALDAV . '}schedule-calendar-transp' => new ScheduleCalendarTransp($row['transparent']?'transparent':'opaque'),
				'{' . \OCA\DAV\DAV\Sharing\Plugin::NS_OWNCLOUD . '}owner-principal' => $this->convertPrincipal($row['principaluri'], $this->legacyEndpoint),
				'{' . \OCA\DAV\DAV\Sharing\Plugin::NS_OWNCLOUD . '}read-only' => (int)$row['access'] === Backend::ACCESS_READ,
				'{' . \OCA\DAV\DAV\Sharing\Plugin::NS_OWNCLOUD . '}public' => (int)$row['access'] === self::ACCESS_PUBLIC,
			];

			foreach ($this->propertyMap as $xmlName => $dbName) {
				$calendar[$xmlName] = $row[$dbName];
			}

			$this->addOwnerPrincipal($calendar);

			if (!isset($calendars[$calendar['id']])) {
				$calendars[$calendar['id']] = $calendar;
			}
		}
		$result->closeCursor();

		return array_values($calendars);
	}

	/**
	 * @param string $uri
	 * @return array
	 * @throws NotFound
	 */
	public function getPublicCalendar($uri) {
		$fields = array_values($this->propertyMap);
		$fields[] = 'a.id';
		$fields[] = 'a.uri';
		$fields[] = 'a.synctoken';
		$fields[] = 'a.components';
		$fields[] = 'a.principaluri';
		$fields[] = 'a.transparent';
		$fields[] = 's.access';
		$fields[] = 's.publicuri';
		$query = $this->db->getQueryBuilder();
		$result = $query->select($fields)
			->from('dav_shares', 's')
			->join('s', 'calendars', 'a', $query->expr()->eq('s.resourceid', 'a.id'))
			->where($query->expr()->in('s.access', $query->createNamedParameter(self::ACCESS_PUBLIC)))
			->andWhere($query->expr()->eq('s.type', $query->createNamedParameter('calendar')))
			->andWhere($query->expr()->eq('s.publicuri', $query->createNamedParameter($uri)))
			->execute();

		$row = $result->fetch(\PDO::FETCH_ASSOC);

		$result->closeCursor();

		if ($row === false) {
			throw new NotFound('Node with name \'' . $uri . '\' could not be found');
		}

		$row['principaluri'] = (string) $row['principaluri'];
		list(, $name) = Uri\split($row['principaluri']);
		$row['displayname'] = $row['displayname'] . ' ' . "($name)";
		$components = [];
		if ($row['components']) {
			$components = explode(',',$row['components']);
		}
		$calendar = [
			'id' => $row['id'],
			'uri' => $row['publicuri'],
			'principaluri' => $this->convertPrincipal($row['principaluri'], !$this->legacyEndpoint),
			'{' . Plugin::NS_CALENDARSERVER . '}getctag' => 'http://sabre.io/ns/sync/' . ($row['synctoken']?$row['synctoken']:'0'),
			'{http://sabredav.org/ns}sync-token' => $row['synctoken']?$row['synctoken']:'0',
			'{' . Plugin::NS_CALDAV . '}supported-calendar-component-set' => new SupportedCalendarComponentSet($components),
			'{' . Plugin::NS_CALDAV . '}schedule-calendar-transp' => new ScheduleCalendarTransp($row['transparent']?'transparent':'opaque'),
			'{' . \OCA\DAV\DAV\Sharing\Plugin::NS_OWNCLOUD . '}owner-principal' => $this->convertPrincipal($row['principaluri'], !$this->legacyEndpoint),
			'{' . \OCA\DAV\DAV\Sharing\Plugin::NS_OWNCLOUD . '}read-only' => (int)$row['access'] === Backend::ACCESS_READ,
			'{' . \OCA\DAV\DAV\Sharing\Plugin::NS_OWNCLOUD . '}public' => (int)$row['access'] === self::ACCESS_PUBLIC,
		];

		foreach ($this->propertyMap as $xmlName => $dbName) {
			$calendar[$xmlName] = $row[$dbName];
		}

		$this->addOwnerPrincipal($calendar);

		return $calendar;
	}

	/**
	 * @param string $principal
	 * @param string $uri
	 * @return array|null
	 */
	public function getCalendarByUri($principal, $uri) {
		$fields = array_values($this->propertyMap);
		$fields[] = 'id';
		$fields[] = 'uri';
		$fields[] = 'synctoken';
		$fields[] = 'components';
		$fields[] = 'principaluri';
		$fields[] = 'transparent';

		// Making fields a comma-delimited list
		$query = $this->db->getQueryBuilder();
		$query->select($fields)->from('calendars')
			->where($query->expr()->eq('uri', $query->createNamedParameter($uri)))
			->andWhere($query->expr()->eq('principaluri', $query->createNamedParameter($principal)))
			->setMaxResults(1);
		$stmt = $query->execute();

		$row = $stmt->fetch(\PDO::FETCH_ASSOC);
		$stmt->closeCursor();
		if ($row === false) {
			return null;
		}

		$row['principaluri'] = (string) $row['principaluri'];
		$components = [];
		if ($row['components']) {
			$components = explode(',',$row['components']);
		}

		$calendar = [
			'id' => $row['id'],
			'uri' => $row['uri'],
			'principaluri' => $this->convertPrincipal($row['principaluri'], !$this->legacyEndpoint),
			'{' . Plugin::NS_CALENDARSERVER . '}getctag' => 'http://sabre.io/ns/sync/' . ($row['synctoken']?$row['synctoken']:'0'),
			'{http://sabredav.org/ns}sync-token' => $row['synctoken']?$row['synctoken']:'0',
			'{' . Plugin::NS_CALDAV . '}supported-calendar-component-set' => new SupportedCalendarComponentSet($components),
			'{' . Plugin::NS_CALDAV . '}schedule-calendar-transp' => new ScheduleCalendarTransp($row['transparent']?'transparent':'opaque'),
		];

		foreach ($this->propertyMap as $xmlName => $dbName) {
			$calendar[$xmlName] = $row[$dbName];
		}

		$this->addOwnerPrincipal($calendar);

		return $calendar;
	}

	/**
	 * @param $calendarId
	 * @return array|null
	 */
	public function getCalendarById($calendarId) {
		$fields = array_values($this->propertyMap);
		$fields[] = 'id';
		$fields[] = 'uri';
		$fields[] = 'synctoken';
		$fields[] = 'components';
		$fields[] = 'principaluri';
		$fields[] = 'transparent';

		// Making fields a comma-delimited list
		$query = $this->db->getQueryBuilder();
		$query->select($fields)->from('calendars')
			->where($query->expr()->eq('id', $query->createNamedParameter($calendarId)))
			->setMaxResults(1);
		$stmt = $query->execute();

		$row = $stmt->fetch(\PDO::FETCH_ASSOC);
		$stmt->closeCursor();
		if ($row === false) {
			return null;
		}

		$row['principaluri'] = (string) $row['principaluri'];
		$components = [];
		if ($row['components']) {
			$components = explode(',',$row['components']);
		}

		$calendar = [
			'id' => $row['id'],
			'uri' => $row['uri'],
			'principaluri' => $this->convertPrincipal($row['principaluri'], !$this->legacyEndpoint),
			'{' . Plugin::NS_CALENDARSERVER . '}getctag' => 'http://sabre.io/ns/sync/' . ($row['synctoken']?$row['synctoken']:'0'),
			'{http://sabredav.org/ns}sync-token' => $row['synctoken']?$row['synctoken']:'0',
			'{' . Plugin::NS_CALDAV . '}supported-calendar-component-set' => new SupportedCalendarComponentSet($components),
			'{' . Plugin::NS_CALDAV . '}schedule-calendar-transp' => new ScheduleCalendarTransp($row['transparent']?'transparent':'opaque'),
		];

		foreach ($this->propertyMap as $xmlName => $dbName) {
			$calendar[$xmlName] = $row[$dbName];
		}

		$this->addOwnerPrincipal($calendar);

		return $calendar;
	}

	/**
	 * @param $subscriptionId
	 */
	public function getSubscriptionById($subscriptionId) {
		$fields = array_values($this->subscriptionPropertyMap);
		$fields[] = 'id';
		$fields[] = 'uri';
		$fields[] = 'source';
		$fields[] = 'synctoken';
		$fields[] = 'principaluri';
		$fields[] = 'lastmodified';

		$query = $this->db->getQueryBuilder();
		$query->select($fields)
			->from('calendarsubscriptions')
			->where($query->expr()->eq('id', $query->createNamedParameter($subscriptionId)))
			->orderBy('calendarorder', 'asc');
		$stmt = $query->execute();

		$row = $stmt->fetch(\PDO::FETCH_ASSOC);
		$stmt->closeCursor();
		if ($row === false) {
			return null;
		}

		$row['principaluri'] = (string) $row['principaluri'];
		$subscription = [
			'id' => $row['id'],
			'uri' => $row['uri'],
			'principaluri' => $row['principaluri'],
			'source' => $row['source'],
			'lastmodified' => $row['lastmodified'],
			'{' . Plugin::NS_CALDAV . '}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VTODO', 'VEVENT']),
			'{http://sabredav.org/ns}sync-token' => $row['synctoken']?$row['synctoken']:'0',
		];

		foreach ($this->subscriptionPropertyMap as $xmlName => $dbName) {
			if (!is_null($row[$dbName])) {
				$subscription[$xmlName] = $row[$dbName];
			}
		}

		return $subscription;
	}

	/**
	 * Creates a new calendar for a principal.
	 *
	 * If the creation was a success, an id must be returned that can be used to reference
	 * this calendar in other methods, such as updateCalendar.
	 *
	 * @param string $principalUri
	 * @param string $calendarUri
	 * @param array $properties
	 * @return int
	 */
	public function createCalendar($principalUri, $calendarUri, array $properties) {
		$values = [
			'principaluri' => $this->convertPrincipal($principalUri, true),
			'uri' => $calendarUri,
			'synctoken' => 1,
			'transparent' => 0,
			'components' => 'VEVENT,VTODO',
			'displayname' => $calendarUri
		];

		// Default value
		$sccs = '{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set';
		if (isset($properties[$sccs])) {
			if (!($properties[$sccs] instanceof SupportedCalendarComponentSet)) {
				throw new DAV\Exception('The ' . $sccs . ' property must be of type: \Sabre\CalDAV\Property\SupportedCalendarComponentSet');
			}
			$values['components'] = implode(',',$properties[$sccs]->getValue());
		} elseif (isset($properties['components'])) {
			// Allow to provide components internally without having
			// to create a SupportedCalendarComponentSet object
			$values['components'] = $properties['components'];
		}

		$transp = '{' . Plugin::NS_CALDAV . '}schedule-calendar-transp';
		if (isset($properties[$transp])) {
			$values['transparent'] = (int) ($properties[$transp]->getValue() === 'transparent');
		}

		foreach ($this->propertyMap as $xmlName => $dbName) {
			if (isset($properties[$xmlName])) {
				$values[$dbName] = $properties[$xmlName];
			}
		}

		$query = $this->db->getQueryBuilder();
		$query->insert('calendars');
		foreach ($values as $column => $value) {
			$query->setValue($column, $query->createNamedParameter($value));
		}
		$query->execute();
		$calendarId = $query->getLastInsertId();

		$calendarData = $this->getCalendarById($calendarId);
		$this->dispatcher->dispatchTyped(new CalendarCreatedEvent((int)$calendarId, $calendarData));
		$this->legacyDispatcher->dispatch('\OCA\DAV\CalDAV\CalDavBackend::createCalendar', new GenericEvent(
			'\OCA\DAV\CalDAV\CalDavBackend::createCalendar',
			[
				'calendarId' => $calendarId,
				'calendarData' => $calendarData,
			]));

		return $calendarId;
	}

	/**
	 * Updates properties for a calendar.
	 *
	 * The list of mutations is stored in a Sabre\DAV\PropPatch object.
	 * To do the actual updates, you must tell this object which properties
	 * you're going to process with the handle() method.
	 *
	 * Calling the handle method is like telling the PropPatch object "I
	 * promise I can handle updating this property".
	 *
	 * Read the PropPatch documentation for more info and examples.
	 *
	 * @param mixed $calendarId
	 * @param PropPatch $propPatch
	 * @return void
	 */
	public function updateCalendar($calendarId, PropPatch $propPatch) {
		$supportedProperties = array_keys($this->propertyMap);
		$supportedProperties[] = '{' . Plugin::NS_CALDAV . '}schedule-calendar-transp';

		$propPatch->handle($supportedProperties, function ($mutations) use ($calendarId) {
			$newValues = [];
			foreach ($mutations as $propertyName => $propertyValue) {
				switch ($propertyName) {
					case '{' . Plugin::NS_CALDAV . '}schedule-calendar-transp':
						$fieldName = 'transparent';
						$newValues[$fieldName] = (int) ($propertyValue->getValue() === 'transparent');
						break;
					default:
						$fieldName = $this->propertyMap[$propertyName];
						$newValues[$fieldName] = $propertyValue;
						break;
				}
			}
			$query = $this->db->getQueryBuilder();
			$query->update('calendars');
			foreach ($newValues as $fieldName => $value) {
				$query->set($fieldName, $query->createNamedParameter($value));
			}
			$query->where($query->expr()->eq('id', $query->createNamedParameter($calendarId)));
			$query->execute();

			$this->addChange($calendarId, "", 2);

			$calendarData = $this->getCalendarById($calendarId);
			$shares = $this->getShares($calendarId);
			$this->dispatcher->dispatchTyped(new CalendarUpdatedEvent((int)$calendarId, $calendarData, $shares, $mutations));
			$this->legacyDispatcher->dispatch('\OCA\DAV\CalDAV\CalDavBackend::updateCalendar', new GenericEvent(
				'\OCA\DAV\CalDAV\CalDavBackend::updateCalendar',
				[
					'calendarId' => $calendarId,
					'calendarData' => $calendarData,
					'shares' => $shares,
					'propertyMutations' => $mutations,
				]));

			return true;
		});
	}

	/**
	 * Delete a calendar and all it's objects
	 *
	 * @param mixed $calendarId
	 * @return void
	 */
	public function deleteCalendar($calendarId) {
		$calendarData = $this->getCalendarById($calendarId);
		$shares = $this->getShares($calendarId);

		$this->legacyDispatcher->dispatch('\OCA\DAV\CalDAV\CalDavBackend::deleteCalendar', new GenericEvent(
			'\OCA\DAV\CalDAV\CalDavBackend::deleteCalendar',
			[
				'calendarId' => $calendarId,
				'calendarData' => $calendarData,
				'shares' => $shares,
			]));

		$stmt = $this->db->prepare('DELETE FROM `*PREFIX*calendarobjects` WHERE `calendarid` = ? AND `calendartype` = ?');
		$stmt->execute([$calendarId, self::CALENDAR_TYPE_CALENDAR]);

		$stmt = $this->db->prepare('DELETE FROM `*PREFIX*calendars` WHERE `id` = ?');
		$stmt->execute([$calendarId]);

		$stmt = $this->db->prepare('DELETE FROM `*PREFIX*calendarchanges` WHERE `calendarid` = ? AND `calendartype` = ?');
		$stmt->execute([$calendarId, self::CALENDAR_TYPE_CALENDAR]);

		$this->calendarSharingBackend->deleteAllShares($calendarId);

		$query = $this->db->getQueryBuilder();
		$query->delete($this->dbObjectPropertiesTable)
			->where($query->expr()->eq('calendarid', $query->createNamedParameter($calendarId)))
			->andWhere($query->expr()->eq('calendartype', $query->createNamedParameter(self::CALENDAR_TYPE_CALENDAR)))
			->execute();

		if ($calendarData) {
			$this->dispatcher->dispatchTyped(new CalendarDeletedEvent((int)$calendarId, $calendarData, $shares));
		}
	}

	/**
	 * Delete all of an user's shares
	 *
	 * @param string $principaluri
	 * @return void
	 */
	public function deleteAllSharesByUser($principaluri) {
		$this->calendarSharingBackend->deleteAllSharesByUser($principaluri);
	}

	/**
	 * Returns all calendar objects within a calendar.
	 *
	 * Every item contains an array with the following keys:
	 *   * calendardata - The iCalendar-compatible calendar data
	 *   * uri - a unique key which will be used to construct the uri. This can
	 *     be any arbitrary string, but making sure it ends with '.ics' is a
	 *     good idea. This is only the basename, or filename, not the full
	 *     path.
	 *   * lastmodified - a timestamp of the last modification time
	 *   * etag - An arbitrary string, surrounded by double-quotes. (e.g.:
	 *   '"abcdef"')
	 *   * size - The size of the calendar objects, in bytes.
	 *   * component - optional, a string containing the type of object, such
	 *     as 'vevent' or 'vtodo'. If specified, this will be used to populate
	 *     the Content-Type header.
	 *
	 * Note that the etag is optional, but it's highly encouraged to return for
	 * speed reasons.
	 *
	 * The calendardata is also optional. If it's not returned
	 * 'getCalendarObject' will be called later, which *is* expected to return
	 * calendardata.
	 *
	 * If neither etag or size are specified, the calendardata will be
	 * used/fetched to determine these numbers. If both are specified the
	 * amount of times this is needed is reduced by a great degree.
	 *
	 * @param mixed $calendarId
	 * @param int $calendarType
	 * @return array
	 */
	public function getCalendarObjects($calendarId, $calendarType = self::CALENDAR_TYPE_CALENDAR):array {
		$query = $this->db->getQueryBuilder();
		$query->select(['id', 'uri', 'lastmodified', 'etag', 'calendarid', 'size', 'componenttype', 'classification'])
			->from('calendarobjects')
			->where($query->expr()->eq('calendarid', $query->createNamedParameter($calendarId)))
			->andWhere($query->expr()->eq('calendartype', $query->createNamedParameter($calendarType)));
		$stmt = $query->execute();

		$result = [];
		foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
			$result[] = [
				'id' => $row['id'],
				'uri' => $row['uri'],
				'lastmodified' => $row['lastmodified'],
				'etag' => '"' . $row['etag'] . '"',
				'calendarid' => $row['calendarid'],
				'size' => (int)$row['size'],
				'component' => strtolower($row['componenttype']),
				'classification' => (int)$row['classification']
			];
		}
		$stmt->closeCursor();

		return $result;
	}

	/**
	 * Returns information from a single calendar object, based on it's object
	 * uri.
	 *
	 * The object uri is only the basename, or filename and not a full path.
	 *
	 * The returned array must have the same keys as getCalendarObjects. The
	 * 'calendardata' object is required here though, while it's not required
	 * for getCalendarObjects.
	 *
	 * This method must return null if the object did not exist.
	 *
	 * @param mixed $calendarId
	 * @param string $objectUri
	 * @param int $calendarType
	 * @return array|null
	 */
	public function getCalendarObject($calendarId, $objectUri, $calendarType = self::CALENDAR_TYPE_CALENDAR) {
		$query = $this->db->getQueryBuilder();
		$query->select(['id', 'uri', 'lastmodified', 'etag', 'calendarid', 'size', 'calendardata', 'componenttype', 'classification'])
			->from('calendarobjects')
			->where($query->expr()->eq('calendarid', $query->createNamedParameter($calendarId)))
			->andWhere($query->expr()->eq('uri', $query->createNamedParameter($objectUri)))
			->andWhere($query->expr()->eq('calendartype', $query->createNamedParameter($calendarType)));
		$stmt = $query->execute();
		$row = $stmt->fetch(\PDO::FETCH_ASSOC);
		$stmt->closeCursor();

		if (!$row) {
			return null;
		}

		return [
			'id' => $row['id'],
			'uri' => $row['uri'],
			'lastmodified' => $row['lastmodified'],
			'etag' => '"' . $row['etag'] . '"',
			'calendarid' => $row['calendarid'],
			'size' => (int)$row['size'],
			'calendardata' => $this->readBlob($row['calendardata']),
			'component' => strtolower($row['componenttype']),
			'classification' => (int)$row['classification']
		];
	}

	/**
	 * Returns a list of calendar objects.
	 *
	 * This method should work identical to getCalendarObject, but instead
	 * return all the calendar objects in the list as an array.
	 *
	 * If the backend supports this, it may allow for some speed-ups.
	 *
	 * @param mixed $calendarId
	 * @param string[] $uris
	 * @param int $calendarType
	 * @return array
	 */
	public function getMultipleCalendarObjects($calendarId, array $uris, $calendarType = self::CALENDAR_TYPE_CALENDAR):array {
		if (empty($uris)) {
			return [];
		}

		$chunks = array_chunk($uris, 100);
		$objects = [];

		$query = $this->db->getQueryBuilder();
		$query->select(['id', 'uri', 'lastmodified', 'etag', 'calendarid', 'size', 'calendardata', 'componenttype', 'classification'])
			->from('calendarobjects')
			->where($query->expr()->eq('calendarid', $query->createNamedParameter($calendarId)))
			->andWhere($query->expr()->in('uri', $query->createParameter('uri')))
			->andWhere($query->expr()->eq('calendartype', $query->createNamedParameter($calendarType)));

		foreach ($chunks as $uris) {
			$query->setParameter('uri', $uris, IQueryBuilder::PARAM_STR_ARRAY);
			$result = $query->execute();

			while ($row = $result->fetch()) {
				$objects[] = [
					'id' => $row['id'],
					'uri' => $row['uri'],
					'lastmodified' => $row['lastmodified'],
					'etag' => '"' . $row['etag'] . '"',
					'calendarid' => $row['calendarid'],
					'size' => (int)$row['size'],
					'calendardata' => $this->readBlob($row['calendardata']),
					'component' => strtolower($row['componenttype']),
					'classification' => (int)$row['classification']
				];
			}
			$result->closeCursor();
		}

		return $objects;
	}

	/**
	 * Creates a new calendar object.
	 *
	 * The object uri is only the basename, or filename and not a full path.
	 *
	 * It is possible return an etag from this function, which will be used in
	 * the response to this PUT request. Note that the ETag must be surrounded
	 * by double-quotes.
	 *
	 * However, you should only really return this ETag if you don't mangle the
	 * calendar-data. If the result of a subsequent GET to this object is not
	 * the exact same as this request body, you should omit the ETag.
	 *
	 * @param mixed $calendarId
	 * @param string $objectUri
	 * @param string $calendarData
	 * @param int $calendarType
	 * @return string
	 */
	public function createCalendarObject($calendarId, $objectUri, $calendarData, $calendarType = self::CALENDAR_TYPE_CALENDAR) {
		$extraData = $this->getDenormalizedData($calendarData);

		$q = $this->db->getQueryBuilder();
		$q->select($q->func()->count('*'))
			->from('calendarobjects')
			->where($q->expr()->eq('calendarid', $q->createNamedParameter($calendarId)))
			->andWhere($q->expr()->eq('uid', $q->createNamedParameter($extraData['uid'])))
			->andWhere($q->expr()->eq('calendartype', $q->createNamedParameter($calendarType)));

		$result = $q->execute();
		$count = (int) $result->fetchOne();
		$result->closeCursor();

		if ($count !== 0) {
			throw new \Sabre\DAV\Exception\BadRequest('Calendar object with uid already exists in this calendar collection.');
		}

		$query = $this->db->getQueryBuilder();
		$query->insert('calendarobjects')
			->values([
				'calendarid' => $query->createNamedParameter($calendarId),
				'uri' => $query->createNamedParameter($objectUri),
				'calendardata' => $query->createNamedParameter($calendarData, IQueryBuilder::PARAM_LOB),
				'lastmodified' => $query->createNamedParameter(time()),
				'etag' => $query->createNamedParameter($extraData['etag']),
				'size' => $query->createNamedParameter($extraData['size']),
				'componenttype' => $query->createNamedParameter($extraData['componentType']),
				'firstoccurence' => $query->createNamedParameter($extraData['firstOccurence']),
				'lastoccurence' => $query->createNamedParameter($extraData['lastOccurence']),
				'classification' => $query->createNamedParameter($extraData['classification']),
				'uid' => $query->createNamedParameter($extraData['uid']),
				'calendartype' => $query->createNamedParameter($calendarType),
			])
			->execute();

		$this->updateProperties($calendarId, $objectUri, $calendarData, $calendarType);
		$this->addChange($calendarId, $objectUri, 1, $calendarType);

		$objectRow = $this->getCalendarObject($calendarId, $objectUri, $calendarType);
		if ($calendarType === self::CALENDAR_TYPE_CALENDAR) {
			$calendarRow = $this->getCalendarById($calendarId);
			$shares = $this->getShares($calendarId);

			$this->dispatcher->dispatchTyped(new CalendarObjectCreatedEvent((int)$calendarId, $calendarRow, $shares, $objectRow));
			$this->legacyDispatcher->dispatch('\OCA\DAV\CalDAV\CalDavBackend::createCalendarObject', new GenericEvent(
				'\OCA\DAV\CalDAV\CalDavBackend::createCalendarObject',
				[
					'calendarId' => $calendarId,
					'calendarData' => $calendarRow,
					'shares' => $shares,
					'objectData' => $objectRow,
				]
			));
		} else {
			$subscriptionRow = $this->getSubscriptionById($calendarId);

			$this->dispatcher->dispatchTyped(new CachedCalendarObjectCreatedEvent((int)$calendarId, $subscriptionRow, [], $objectRow));
			$this->legacyDispatcher->dispatch('\OCA\DAV\CalDAV\CalDavBackend::createCachedCalendarObject', new GenericEvent(
				'\OCA\DAV\CalDAV\CalDavBackend::createCachedCalendarObject',
				[
					'subscriptionId' => $calendarId,
					'calendarData' => $subscriptionRow,
					'shares' => [],
					'objectData' => $objectRow,
				]
			));
		}

		return '"' . $extraData['etag'] . '"';
	}

	/**
	 * Updates an existing calendarobject, based on it's uri.
	 *
	 * The object uri is only the basename, or filename and not a full path.
	 *
	 * It is possible return an etag from this function, which will be used in
	 * the response to this PUT request. Note that the ETag must be surrounded
	 * by double-quotes.
	 *
	 * However, you should only really return this ETag if you don't mangle the
	 * calendar-data. If the result of a subsequent GET to this object is not
	 * the exact same as this request body, you should omit the ETag.
	 *
	 * @param mixed $calendarId
	 * @param string $objectUri
	 * @param string $calendarData
	 * @param int $calendarType
	 * @return string
	 */
	public function updateCalendarObject($calendarId, $objectUri, $calendarData, $calendarType = self::CALENDAR_TYPE_CALENDAR) {
		$extraData = $this->getDenormalizedData($calendarData);
		$query = $this->db->getQueryBuilder();
		$query->update('calendarobjects')
				->set('calendardata', $query->createNamedParameter($calendarData, IQueryBuilder::PARAM_LOB))
				->set('lastmodified', $query->createNamedParameter(time()))
				->set('etag', $query->createNamedParameter($extraData['etag']))
				->set('size', $query->createNamedParameter($extraData['size']))
				->set('componenttype', $query->createNamedParameter($extraData['componentType']))
				->set('firstoccurence', $query->createNamedParameter($extraData['firstOccurence']))
				->set('lastoccurence', $query->createNamedParameter($extraData['lastOccurence']))
				->set('classification', $query->createNamedParameter($extraData['classification']))
				->set('uid', $query->createNamedParameter($extraData['uid']))
			->where($query->expr()->eq('calendarid', $query->createNamedParameter($calendarId)))
			->andWhere($query->expr()->eq('uri', $query->createNamedParameter($objectUri)))
			->andWhere($query->expr()->eq('calendartype', $query->createNamedParameter($calendarType)))
			->execute();

		$this->updateProperties($calendarId, $objectUri, $calendarData, $calendarType);
		$this->addChange($calendarId, $objectUri, 2, $calendarType);

		$objectRow = $this->getCalendarObject($calendarId, $objectUri, $calendarType);
		if (is_array($objectRow)) {
			if ($calendarType === self::CALENDAR_TYPE_CALENDAR) {
				$calendarRow = $this->getCalendarById($calendarId);
				$shares = $this->getShares($calendarId);

				$this->dispatcher->dispatchTyped(new CalendarObjectUpdatedEvent((int)$calendarId, $calendarRow, $shares, $objectRow));
				$this->legacyDispatcher->dispatch('\OCA\DAV\CalDAV\CalDavBackend::updateCalendarObject', new GenericEvent(
					'\OCA\DAV\CalDAV\CalDavBackend::updateCalendarObject',
					[
						'calendarId' => $calendarId,
						'calendarData' => $calendarRow,
						'shares' => $shares,
						'objectData' => $objectRow,
					]
				));
			} else {
				$subscriptionRow = $this->getSubscriptionById($calendarId);

				$this->dispatcher->dispatchTyped(new CachedCalendarObjectUpdatedEvent((int)$calendarId, $subscriptionRow, [], $objectRow));
				$this->legacyDispatcher->dispatch('\OCA\DAV\CalDAV\CalDavBackend::updateCachedCalendarObject', new GenericEvent(
					'\OCA\DAV\CalDAV\CalDavBackend::updateCachedCalendarObject',
					[
						'subscriptionId' => $calendarId,
						'calendarData' => $subscriptionRow,
						'shares' => [],
						'objectData' => $objectRow,
					]
				));
			}
		}

		return '"' . $extraData['etag'] . '"';
	}

	/**
	 * @param int $calendarObjectId
	 * @param int $classification
	 */
	public function setClassification($calendarObjectId, $classification) {
		if (!in_array($classification, [
			self::CLASSIFICATION_PUBLIC, self::CLASSIFICATION_PRIVATE, self::CLASSIFICATION_CONFIDENTIAL
		])) {
			throw new \InvalidArgumentException();
		}
		$query = $this->db->getQueryBuilder();
		$query->update('calendarobjects')
			->set('classification', $query->createNamedParameter($classification))
			->where($query->expr()->eq('id', $query->createNamedParameter($calendarObjectId)))
			->execute();
	}

	/**
	 * Deletes an existing calendar object.
	 *
	 * The object uri is only the basename, or filename and not a full path.
	 *
	 * @param mixed $calendarId
	 * @param string $objectUri
	 * @param int $calendarType
	 * @return void
	 */
	public function deleteCalendarObject($calendarId, $objectUri, $calendarType = self::CALENDAR_TYPE_CALENDAR) {
		$data = $this->getCalendarObject($calendarId, $objectUri, $calendarType);
		if (is_array($data)) {
			if ($calendarType === self::CALENDAR_TYPE_CALENDAR) {
				$calendarRow = $this->getCalendarById($calendarId);
				$shares = $this->getShares($calendarId);

				$this->dispatcher->dispatchTyped(new CalendarObjectDeletedEvent((int)$calendarId, $calendarRow, $shares, $data));
				$this->legacyDispatcher->dispatch('\OCA\DAV\CalDAV\CalDavBackend::deleteCalendarObject', new GenericEvent(
					'\OCA\DAV\CalDAV\CalDavBackend::deleteCalendarObject',
					[
						'calendarId' => $calendarId,
						'calendarData' => $calendarRow,
						'shares' => $shares,
						'objectData' => $data,
					]
				));
			} else {
				$subscriptionRow = $this->getSubscriptionById($calendarId);

				$this->dispatcher->dispatchTyped(new CachedCalendarObjectDeletedEvent((int)$calendarId, $subscriptionRow, [], $data));
				$this->legacyDispatcher->dispatch('\OCA\DAV\CalDAV\CalDavBackend::deleteCachedCalendarObject', new GenericEvent(
					'\OCA\DAV\CalDAV\CalDavBackend::deleteCachedCalendarObject',
					[
						'subscriptionId' => $calendarId,
						'calendarData' => $subscriptionRow,
						'shares' => [],
						'objectData' => $data,
					]
				));
			}
		}

		$stmt = $this->db->prepare('DELETE FROM `*PREFIX*calendarobjects` WHERE `calendarid` = ? AND `uri` = ? AND `calendartype` = ?');
		$stmt->execute([$calendarId, $objectUri, $calendarType]);

		if (is_array($data)) {
			$this->purgeProperties($calendarId, $data['id'], $calendarType);
		}

		$this->addChange($calendarId, $objectUri, 3, $calendarType);
	}

	/**
	 * Performs a calendar-query on the contents of this calendar.
	 *
	 * The calendar-query is defined in RFC4791 : CalDAV. Using the
	 * calendar-query it is possible for a client to request a specific set of
	 * object, based on contents of iCalendar properties, date-ranges and
	 * iCalendar component types (VTODO, VEVENT).
	 *
	 * This method should just return a list of (relative) urls that match this
	 * query.
	 *
	 * The list of filters are specified as an array. The exact array is
	 * documented by Sabre\CalDAV\CalendarQueryParser.
	 *
	 * Note that it is extremely likely that getCalendarObject for every path
	 * returned from this method will be called almost immediately after. You
	 * may want to anticipate this to speed up these requests.
	 *
	 * This method provides a default implementation, which parses *all* the
	 * iCalendar objects in the specified calendar.
	 *
	 * This default may well be good enough for personal use, and calendars
	 * that aren't very large. But if you anticipate high usage, big calendars
	 * or high loads, you are strongly advised to optimize certain paths.
	 *
	 * The best way to do so is override this method and to optimize
	 * specifically for 'common filters'.
	 *
	 * Requests that are extremely common are:
	 *   * requests for just VEVENTS
	 *   * requests for just VTODO
	 *   * requests with a time-range-filter on either VEVENT or VTODO.
	 *
	 * ..and combinations of these requests. It may not be worth it to try to
	 * handle every possible situation and just rely on the (relatively
	 * easy to use) CalendarQueryValidator to handle the rest.
	 *
	 * Note that especially time-range-filters may be difficult to parse. A
	 * time-range filter specified on a VEVENT must for instance also handle
	 * recurrence rules correctly.
	 * A good example of how to interprete all these filters can also simply
	 * be found in Sabre\CalDAV\CalendarQueryFilter. This class is as correct
	 * as possible, so it gives you a good idea on what type of stuff you need
	 * to think of.
	 *
	 * @param mixed $calendarId
	 * @param array $filters
	 * @param int $calendarType
	 * @return array
	 */
	public function calendarQuery($calendarId, array $filters, $calendarType = self::CALENDAR_TYPE_CALENDAR):array {
		$componentType = null;
		$requirePostFilter = true;
		$timeRange = null;

		// if no filters were specified, we don't need to filter after a query
		if (!$filters['prop-filters'] && !$filters['comp-filters']) {
			$requirePostFilter = false;
		}

		// Figuring out if there's a component filter
		if (count($filters['comp-filters']) > 0 && !$filters['comp-filters'][0]['is-not-defined']) {
			$componentType = $filters['comp-filters'][0]['name'];

			// Checking if we need post-filters
			if (!$filters['prop-filters'] && !$filters['comp-filters'][0]['comp-filters'] && !$filters['comp-filters'][0]['time-range'] && !$filters['comp-filters'][0]['prop-filters']) {
				$requirePostFilter = false;
			}
			// There was a time-range filter
			if ($componentType === 'VEVENT' && isset($filters['comp-filters'][0]['time-range']) && is_array($filters['comp-filters'][0]['time-range'])) {
				$timeRange = $filters['comp-filters'][0]['time-range'];

				// If start time OR the end time is not specified, we can do a
				// 100% accurate mysql query.
				if (!$filters['prop-filters'] && !$filters['comp-filters'][0]['comp-filters'] && !$filters['comp-filters'][0]['prop-filters'] && (!$timeRange['start'] || !$timeRange['end'])) {
					$requirePostFilter = false;
				}
			}
		}
		$columns = ['uri'];
		if ($requirePostFilter) {
			$columns = ['uri', 'calendardata'];
		}
		$query = $this->db->getQueryBuilder();
		$query->select($columns)
			->from('calendarobjects')
			->where($query->expr()->eq('calendarid', $query->createNamedParameter($calendarId)))
			->andWhere($query->expr()->eq('calendartype', $query->createNamedParameter($calendarType)));

		if ($componentType) {
			$query->andWhere($query->expr()->eq('componenttype', $query->createNamedParameter($componentType)));
		}

		if ($timeRange && $timeRange['start']) {
			$query->andWhere($query->expr()->gt('lastoccurence', $query->createNamedParameter($timeRange['start']->getTimeStamp())));
		}
		if ($timeRange && $timeRange['end']) {
			$query->andWhere($query->expr()->lt('firstoccurence', $query->createNamedParameter($timeRange['end']->getTimeStamp())));
		}

		$stmt = $query->execute();

		$result = [];
		while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			if ($requirePostFilter) {
				// validateFilterForObject will parse the calendar data
				// catch parsing errors
				try {
					$matches = $this->validateFilterForObject($row, $filters);
				} catch (ParseException $ex) {
					$this->logger->logException($ex, [
						'app' => 'dav',
						'message' => 'Caught parsing exception for calendar data. This usually indicates invalid calendar data. calendar-id:'.$calendarId.' uri:'.$row['uri']
					]);
					continue;
				} catch (InvalidDataException $ex) {
					$this->logger->logException($ex, [
						'app' => 'dav',
						'message' => 'Caught invalid data exception for calendar data. This usually indicates invalid calendar data. calendar-id:'.$calendarId.' uri:'.$row['uri']
					]);
					continue;
				}

				if (!$matches) {
					continue;
				}
			}
			$result[] = $row['uri'];
		}

		return $result;
	}

	/**
	 * custom Nextcloud search extension for CalDAV
	 *
	 * TODO - this should optionally cover cached calendar objects as well
	 *
	 * @param string $principalUri
	 * @param array $filters
	 * @param integer|null $limit
	 * @param integer|null $offset
	 * @return array
	 */
	public function calendarSearch($principalUri, array $filters, $limit = null, $offset = null) {
		$calendars = $this->getCalendarsForUser($principalUri);
		$ownCalendars = [];
		$sharedCalendars = [];

		$uriMapper = [];

		foreach ($calendars as $calendar) {
			if ($calendar['{http://owncloud.org/ns}owner-principal'] === $principalUri) {
				$ownCalendars[] = $calendar['id'];
			} else {
				$sharedCalendars[] = $calendar['id'];
			}
			$uriMapper[$calendar['id']] = $calendar['uri'];
		}
		if (count($ownCalendars) === 0 && count($sharedCalendars) === 0) {
			return [];
		}

		$query = $this->db->getQueryBuilder();
		// Calendar id expressions
		$calendarExpressions = [];
		foreach ($ownCalendars as $id) {
			$calendarExpressions[] = $query->expr()->andX(
				$query->expr()->eq('c.calendarid',
					$query->createNamedParameter($id)),
				$query->expr()->eq('c.calendartype',
						$query->createNamedParameter(self::CALENDAR_TYPE_CALENDAR)));
		}
		foreach ($sharedCalendars as $id) {
			$calendarExpressions[] = $query->expr()->andX(
				$query->expr()->eq('c.calendarid',
					$query->createNamedParameter($id)),
				$query->expr()->eq('c.classification',
					$query->createNamedParameter(self::CLASSIFICATION_PUBLIC)),
				$query->expr()->eq('c.calendartype',
					$query->createNamedParameter(self::CALENDAR_TYPE_CALENDAR)));
		}

		if (count($calendarExpressions) === 1) {
			$calExpr = $calendarExpressions[0];
		} else {
			$calExpr = call_user_func_array([$query->expr(), 'orX'], $calendarExpressions);
		}

		// Component expressions
		$compExpressions = [];
		foreach ($filters['comps'] as $comp) {
			$compExpressions[] = $query->expr()
				->eq('c.componenttype', $query->createNamedParameter($comp));
		}

		if (count($compExpressions) === 1) {
			$compExpr = $compExpressions[0];
		} else {
			$compExpr = call_user_func_array([$query->expr(), 'orX'], $compExpressions);
		}

		if (!isset($filters['props'])) {
			$filters['props'] = [];
		}
		if (!isset($filters['params'])) {
			$filters['params'] = [];
		}

		$propParamExpressions = [];
		foreach ($filters['props'] as $prop) {
			$propParamExpressions[] = $query->expr()->andX(
				$query->expr()->eq('i.name', $query->createNamedParameter($prop)),
				$query->expr()->isNull('i.parameter')
			);
		}
		foreach ($filters['params'] as $param) {
			$propParamExpressions[] = $query->expr()->andX(
				$query->expr()->eq('i.name', $query->createNamedParameter($param['property'])),
				$query->expr()->eq('i.parameter', $query->createNamedParameter($param['parameter']))
			);
		}

		if (count($propParamExpressions) === 1) {
			$propParamExpr = $propParamExpressions[0];
		} else {
			$propParamExpr = call_user_func_array([$query->expr(), 'orX'], $propParamExpressions);
		}

		$query->select(['c.calendarid', 'c.uri'])
			->from($this->dbObjectPropertiesTable, 'i')
			->join('i', 'calendarobjects', 'c', $query->expr()->eq('i.objectid', 'c.id'))
			->where($calExpr)
			->andWhere($compExpr)
			->andWhere($propParamExpr)
			->andWhere($query->expr()->iLike('i.value',
				$query->createNamedParameter('%'.$this->db->escapeLikeParameter($filters['search-term']).'%')));

		if ($offset) {
			$query->setFirstResult($offset);
		}
		if ($limit) {
			$query->setMaxResults($limit);
		}

		$stmt = $query->execute();

		$result = [];
		while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			$path = $uriMapper[$row['calendarid']] . '/' . $row['uri'];
			if (!in_array($path, $result)) {
				$result[] = $path;
			}
		}

		return $result;
	}

	/**
	 * used for Nextcloud's calendar API
	 *
	 * @param array $calendarInfo
	 * @param string $pattern
	 * @param array $searchProperties
	 * @param array $options
	 * @param integer|null $limit
	 * @param integer|null $offset
	 *
	 * @return array
	 */
	public function search(array $calendarInfo, $pattern, array $searchProperties,
						   array $options, $limit, $offset) {
		$outerQuery = $this->db->getQueryBuilder();
		$innerQuery = $this->db->getQueryBuilder();

		$innerQuery->selectDistinct('op.objectid')
			->from($this->dbObjectPropertiesTable, 'op')
			->andWhere($innerQuery->expr()->eq('op.calendarid',
				$outerQuery->createNamedParameter($calendarInfo['id'])))
			->andWhere($innerQuery->expr()->eq('op.calendartype',
				$outerQuery->createNamedParameter(self::CALENDAR_TYPE_CALENDAR)));

		// only return public items for shared calendars for now
		if ($calendarInfo['principaluri'] !== $calendarInfo['{http://owncloud.org/ns}owner-principal']) {
			$innerQuery->andWhere($innerQuery->expr()->eq('c.classification',
				$outerQuery->createNamedParameter(self::CLASSIFICATION_PUBLIC)));
		}

		$or = $innerQuery->expr()->orX();
		foreach ($searchProperties as $searchProperty) {
			$or->add($innerQuery->expr()->eq('op.name',
				$outerQuery->createNamedParameter($searchProperty)));
		}
		$innerQuery->andWhere($or);

		if ($pattern !== '') {
			$innerQuery->andWhere($innerQuery->expr()->iLike('op.value',
				$outerQuery->createNamedParameter('%' .
					$this->db->escapeLikeParameter($pattern) . '%')));
		}

		$outerQuery->select('c.id', 'c.calendardata', 'c.componenttype', 'c.uid', 'c.uri')
			->from('calendarobjects', 'c');

		if (isset($options['timerange'])) {
			if (isset($options['timerange']['start']) && $options['timerange']['start'] instanceof DateTime) {
				$outerQuery->andWhere($outerQuery->expr()->gt('lastoccurence',
					$outerQuery->createNamedParameter($options['timerange']['start']->getTimeStamp())));
			}
			if (isset($options['timerange']['end']) && $options['timerange']['end'] instanceof DateTime) {
				$outerQuery->andWhere($outerQuery->expr()->lt('firstoccurence',
					$outerQuery->createNamedParameter($options['timerange']['end']->getTimeStamp())));
			}
		}

		if (isset($options['types'])) {
			$or = $outerQuery->expr()->orX();
			foreach ($options['types'] as $type) {
				$or->add($outerQuery->expr()->eq('componenttype',
					$outerQuery->createNamedParameter($type)));
			}
			$outerQuery->andWhere($or);
		}

		$outerQuery->andWhere($outerQuery->expr()->in('c.id',
			$outerQuery->createFunction($innerQuery->getSQL())));

		if ($offset) {
			$outerQuery->setFirstResult($offset);
		}
		if ($limit) {
			$outerQuery->setMaxResults($limit);
		}

		$result = $outerQuery->execute();
		$calendarObjects = $result->fetchAll();

		return array_map(function ($o) {
			$calendarData = Reader::read($o['calendardata']);
			$comps = $calendarData->getComponents();
			$objects = [];
			$timezones = [];
			foreach ($comps as $comp) {
				if ($comp instanceof VTimeZone) {
					$timezones[] = $comp;
				} else {
					$objects[] = $comp;
				}
			}

			return [
				'id' => $o['id'],
				'type' => $o['componenttype'],
				'uid' => $o['uid'],
				'uri' => $o['uri'],
				'objects' => array_map(function ($c) {
					return $this->transformSearchData($c);
				}, $objects),
				'timezones' => array_map(function ($c) {
					return $this->transformSearchData($c);
				}, $timezones),
			];
		}, $calendarObjects);
	}

	/**
	 * @param Component $comp
	 * @return array
	 */
	private function transformSearchData(Component $comp) {
		$data = [];
		/** @var Component[] $subComponents */
		$subComponents = $comp->getComponents();
		/** @var Property[] $properties */
		$properties = array_filter($comp->children(), function ($c) {
			return $c instanceof Property;
		});
		$validationRules = $comp->getValidationRules();

		foreach ($subComponents as $subComponent) {
			$name = $subComponent->name;
			if (!isset($data[$name])) {
				$data[$name] = [];
			}
			$data[$name][] = $this->transformSearchData($subComponent);
		}

		foreach ($properties as $property) {
			$name = $property->name;
			if (!isset($validationRules[$name])) {
				$validationRules[$name] = '*';
			}

			$rule = $validationRules[$property->name];
			if ($rule === '+' || $rule === '*') { // multiple
				if (!isset($data[$name])) {
					$data[$name] = [];
				}

				$data[$name][] = $this->transformSearchProperty($property);
			} else { // once
				$data[$name] = $this->transformSearchProperty($property);
			}
		}

		return $data;
	}

	/**
	 * @param Property $prop
	 * @return array
	 */
	private function transformSearchProperty(Property $prop) {
		// No need to check Date, as it extends DateTime
		if ($prop instanceof Property\ICalendar\DateTime) {
			$value = $prop->getDateTime();
		} else {
			$value = $prop->getValue();
		}

		return [
			$value,
			$prop->parameters()
		];
	}

	/**
	 * @param string $principalUri
	 * @param string $pattern
	 * @param array $componentTypes
	 * @param array $searchProperties
	 * @param array $searchParameters
	 * @param array $options
	 * @return array
	 */
	public function searchPrincipalUri(string $principalUri,
									   string $pattern,
									   array $componentTypes,
									   array $searchProperties,
									   array $searchParameters,
									   array $options = []): array {
		$escapePattern = !\array_key_exists('escape_like_param', $options) || $options['escape_like_param'] !== false;

		$calendarObjectIdQuery = $this->db->getQueryBuilder();
		$calendarOr = $calendarObjectIdQuery->expr()->orX();
		$searchOr = $calendarObjectIdQuery->expr()->orX();

		// Fetch calendars and subscription
		$calendars = $this->getCalendarsForUser($principalUri);
		$subscriptions = $this->getSubscriptionsForUser($principalUri);
		foreach ($calendars as $calendar) {
			$calendarAnd = $calendarObjectIdQuery->expr()->andX();
			$calendarAnd->add($calendarObjectIdQuery->expr()->eq('cob.calendarid', $calendarObjectIdQuery->createNamedParameter((int)$calendar['id'])));
			$calendarAnd->add($calendarObjectIdQuery->expr()->eq('cob.calendartype', $calendarObjectIdQuery->createNamedParameter(self::CALENDAR_TYPE_CALENDAR)));

			// If it's shared, limit search to public events
			if (isset($calendar['{http://owncloud.org/ns}owner-principal'])
				&& $calendar['principaluri'] !== $calendar['{http://owncloud.org/ns}owner-principal']) {
				$calendarAnd->add($calendarObjectIdQuery->expr()->eq('co.classification', $calendarObjectIdQuery->createNamedParameter(self::CLASSIFICATION_PUBLIC)));
			}

			$calendarOr->add($calendarAnd);
		}
		foreach ($subscriptions as $subscription) {
			$subscriptionAnd = $calendarObjectIdQuery->expr()->andX();
			$subscriptionAnd->add($calendarObjectIdQuery->expr()->eq('cob.calendarid', $calendarObjectIdQuery->createNamedParameter((int)$subscription['id'])));
			$subscriptionAnd->add($calendarObjectIdQuery->expr()->eq('cob.calendartype', $calendarObjectIdQuery->createNamedParameter(self::CALENDAR_TYPE_SUBSCRIPTION)));

			// If it's shared, limit search to public events
			if (isset($subscription['{http://owncloud.org/ns}owner-principal'])
				&& $subscription['principaluri'] !== $subscription['{http://owncloud.org/ns}owner-principal']) {
				$subscriptionAnd->add($calendarObjectIdQuery->expr()->eq('co.classification', $calendarObjectIdQuery->createNamedParameter(self::CLASSIFICATION_PUBLIC)));
			}

			$calendarOr->add($subscriptionAnd);
		}

		foreach ($searchProperties as $property) {
			$propertyAnd = $calendarObjectIdQuery->expr()->andX();
			$propertyAnd->add($calendarObjectIdQuery->expr()->eq('cob.name', $calendarObjectIdQuery->createNamedParameter($property, IQueryBuilder::PARAM_STR)));
			$propertyAnd->add($calendarObjectIdQuery->expr()->isNull('cob.parameter'));

			$searchOr->add($propertyAnd);
		}
		foreach ($searchParameters as $property => $parameter) {
			$parameterAnd = $calendarObjectIdQuery->expr()->andX();
			$parameterAnd->add($calendarObjectIdQuery->expr()->eq('cob.name', $calendarObjectIdQuery->createNamedParameter($property, IQueryBuilder::PARAM_STR)));
			$parameterAnd->add($calendarObjectIdQuery->expr()->eq('cob.parameter', $calendarObjectIdQuery->createNamedParameter($parameter, IQueryBuilder::PARAM_STR_ARRAY)));

			$searchOr->add($parameterAnd);
		}

		if ($calendarOr->count() === 0) {
			return [];
		}
		if ($searchOr->count() === 0) {
			return [];
		}

		$calendarObjectIdQuery->selectDistinct('cob.objectid')
			->from($this->dbObjectPropertiesTable, 'cob')
			->leftJoin('cob', 'calendarobjects', 'co', $calendarObjectIdQuery->expr()->eq('co.id', 'cob.objectid'))
			->andWhere($calendarObjectIdQuery->expr()->in('co.componenttype', $calendarObjectIdQuery->createNamedParameter($componentTypes, IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($calendarOr)
			->andWhere($searchOr);

		if ('' !== $pattern) {
			if (!$escapePattern) {
				$calendarObjectIdQuery->andWhere($calendarObjectIdQuery->expr()->ilike('cob.value', $calendarObjectIdQuery->createNamedParameter($pattern)));
			} else {
				$calendarObjectIdQuery->andWhere($calendarObjectIdQuery->expr()->ilike('cob.value', $calendarObjectIdQuery->createNamedParameter('%' . $this->db->escapeLikeParameter($pattern) . '%')));
			}
		}

		if (isset($options['limit'])) {
			$calendarObjectIdQuery->setMaxResults($options['limit']);
		}
		if (isset($options['offset'])) {
			$calendarObjectIdQuery->setFirstResult($options['offset']);
		}

		$result = $calendarObjectIdQuery->execute();
		$matches = $result->fetchAll();
		$result->closeCursor();
		$matches = array_map(static function (array $match):int {
			return (int) $match['objectid'];
		}, $matches);

		$query = $this->db->getQueryBuilder();
		$query->select('calendardata', 'uri', 'calendarid', 'calendartype')
			->from('calendarobjects')
			->where($query->expr()->in('id', $query->createNamedParameter($matches, IQueryBuilder::PARAM_INT_ARRAY)));

		$result = $query->execute();
		$calendarObjects = $result->fetchAll();
		$result->closeCursor();

		return array_map(function (array $array): array {
			$array['calendarid'] = (int)$array['calendarid'];
			$array['calendartype'] = (int)$array['calendartype'];
			$array['calendardata'] = $this->readBlob($array['calendardata']);

			return $array;
		}, $calendarObjects);
	}

	/**
	 * Searches through all of a users calendars and calendar objects to find
	 * an object with a specific UID.
	 *
	 * This method should return the path to this object, relative to the
	 * calendar home, so this path usually only contains two parts:
	 *
	 * calendarpath/objectpath.ics
	 *
	 * If the uid is not found, return null.
	 *
	 * This method should only consider * objects that the principal owns, so
	 * any calendars owned by other principals that also appear in this
	 * collection should be ignored.
	 *
	 * @param string $principalUri
	 * @param string $uid
	 * @return string|null
	 */
	public function getCalendarObjectByUID($principalUri, $uid) {
		$query = $this->db->getQueryBuilder();
		$query->selectAlias('c.uri', 'calendaruri')->selectAlias('co.uri', 'objecturi')
			->from('calendarobjects', 'co')
			->leftJoin('co', 'calendars', 'c', $query->expr()->eq('co.calendarid', 'c.id'))
			->where($query->expr()->eq('c.principaluri', $query->createNamedParameter($principalUri)))
			->andWhere($query->expr()->eq('co.uid', $query->createNamedParameter($uid)));

		$stmt = $query->execute();

		if ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			return $row['calendaruri'] . '/' . $row['objecturi'];
		}

		return null;
	}

	/**
	 * The getChanges method returns all the changes that have happened, since
	 * the specified syncToken in the specified calendar.
	 *
	 * This function should return an array, such as the following:
	 *
	 * [
	 *   'syncToken' => 'The current synctoken',
	 *   'added'   => [
	 *      'new.txt',
	 *   ],
	 *   'modified'   => [
	 *      'modified.txt',
	 *   ],
	 *   'deleted' => [
	 *      'foo.php.bak',
	 *      'old.txt'
	 *   ]
	 * );
	 *
	 * The returned syncToken property should reflect the *current* syncToken
	 * of the calendar, as reported in the {http://sabredav.org/ns}sync-token
	 * property This is * needed here too, to ensure the operation is atomic.
	 *
	 * If the $syncToken argument is specified as null, this is an initial
	 * sync, and all members should be reported.
	 *
	 * The modified property is an array of nodenames that have changed since
	 * the last token.
	 *
	 * The deleted property is an array with nodenames, that have been deleted
	 * from collection.
	 *
	 * The $syncLevel argument is basically the 'depth' of the report. If it's
	 * 1, you only have to report changes that happened only directly in
	 * immediate descendants. If it's 2, it should also include changes from
	 * the nodes below the child collections. (grandchildren)
	 *
	 * The $limit argument allows a client to specify how many results should
	 * be returned at most. If the limit is not specified, it should be treated
	 * as infinite.
	 *
	 * If the limit (infinite or not) is higher than you're willing to return,
	 * you should throw a Sabre\DAV\Exception\TooMuchMatches() exception.
	 *
	 * If the syncToken is expired (due to data cleanup) or unknown, you must
	 * return null.
	 *
	 * The limit is 'suggestive'. You are free to ignore it.
	 *
	 * @param string $calendarId
	 * @param string $syncToken
	 * @param int $syncLevel
	 * @param int $limit
	 * @param int $calendarType
	 * @return array
	 */
	public function getChangesForCalendar($calendarId, $syncToken, $syncLevel, $limit = null, $calendarType = self::CALENDAR_TYPE_CALENDAR) {
		// Current synctoken
		$stmt = $this->db->prepare('SELECT `synctoken` FROM `*PREFIX*calendars` WHERE `id` = ?');
		$stmt->execute([ $calendarId ]);
		$currentToken = $stmt->fetchOne();

		if (is_null($currentToken)) {
			return null;
		}

		$result = [
			'syncToken' => $currentToken,
			'added' => [],
			'modified' => [],
			'deleted' => [],
		];

		if ($syncToken) {
			$query = "SELECT `uri`, `operation` FROM `*PREFIX*calendarchanges` WHERE `synctoken` >= ? AND `synctoken` < ? AND `calendarid` = ? AND `calendartype` = ? ORDER BY `synctoken`";
			if ($limit > 0) {
				$query .= " LIMIT " . (int)$limit;
			}

			// Fetching all changes
			$stmt = $this->db->prepare($query);
			$stmt->execute([$syncToken, $currentToken, $calendarId, $calendarType]);

			$changes = [];

			// This loop ensures that any duplicates are overwritten, only the
			// last change on a node is relevant.
			while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
				$changes[$row['uri']] = $row['operation'];
			}

			foreach ($changes as $uri => $operation) {
				switch ($operation) {
					case 1:
						$result['added'][] = $uri;
						break;
					case 2:
						$result['modified'][] = $uri;
						break;
					case 3:
						$result['deleted'][] = $uri;
						break;
				}
			}
		} else {
			// No synctoken supplied, this is the initial sync.
			$query = "SELECT `uri` FROM `*PREFIX*calendarobjects` WHERE `calendarid` = ? AND `calendartype` = ?";
			$stmt = $this->db->prepare($query);
			$stmt->execute([$calendarId, $calendarType]);

			$result['added'] = $stmt->fetchAll(\PDO::FETCH_COLUMN);
		}
		return $result;
	}

	/**
	 * Returns a list of subscriptions for a principal.
	 *
	 * Every subscription is an array with the following keys:
	 *  * id, a unique id that will be used by other functions to modify the
	 *    subscription. This can be the same as the uri or a database key.
	 *  * uri. This is just the 'base uri' or 'filename' of the subscription.
	 *  * principaluri. The owner of the subscription. Almost always the same as
	 *    principalUri passed to this method.
	 *
	 * Furthermore, all the subscription info must be returned too:
	 *
	 * 1. {DAV:}displayname
	 * 2. {http://apple.com/ns/ical/}refreshrate
	 * 3. {http://calendarserver.org/ns/}subscribed-strip-todos (omit if todos
	 *    should not be stripped).
	 * 4. {http://calendarserver.org/ns/}subscribed-strip-alarms (omit if alarms
	 *    should not be stripped).
	 * 5. {http://calendarserver.org/ns/}subscribed-strip-attachments (omit if
	 *    attachments should not be stripped).
	 * 6. {http://calendarserver.org/ns/}source (Must be a
	 *     Sabre\DAV\Property\Href).
	 * 7. {http://apple.com/ns/ical/}calendar-color
	 * 8. {http://apple.com/ns/ical/}calendar-order
	 * 9. {urn:ietf:params:xml:ns:caldav}supported-calendar-component-set
	 *    (should just be an instance of
	 *    Sabre\CalDAV\Property\SupportedCalendarComponentSet, with a bunch of
	 *    default components).
	 *
	 * @param string $principalUri
	 * @return array
	 */
	public function getSubscriptionsForUser($principalUri) {
		$fields = array_values($this->subscriptionPropertyMap);
		$fields[] = 'id';
		$fields[] = 'uri';
		$fields[] = 'source';
		$fields[] = 'principaluri';
		$fields[] = 'lastmodified';
		$fields[] = 'synctoken';

		$query = $this->db->getQueryBuilder();
		$query->select($fields)
			->from('calendarsubscriptions')
			->where($query->expr()->eq('principaluri', $query->createNamedParameter($principalUri)))
			->orderBy('calendarorder', 'asc');
		$stmt = $query->execute();

		$subscriptions = [];
		while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
			$subscription = [
				'id' => $row['id'],
				'uri' => $row['uri'],
				'principaluri' => $row['principaluri'],
				'source' => $row['source'],
				'lastmodified' => $row['lastmodified'],

				'{' . Plugin::NS_CALDAV . '}supported-calendar-component-set' => new SupportedCalendarComponentSet(['VTODO', 'VEVENT']),
				'{http://sabredav.org/ns}sync-token' => $row['synctoken']?$row['synctoken']:'0',
			];

			foreach ($this->subscriptionPropertyMap as $xmlName => $dbName) {
				if (!is_null($row[$dbName])) {
					$subscription[$xmlName] = $row[$dbName];
				}
			}

			$subscriptions[] = $subscription;
		}

		return $subscriptions;
	}

	/**
	 * Creates a new subscription for a principal.
	 *
	 * If the creation was a success, an id must be returned that can be used to reference
	 * this subscription in other methods, such as updateSubscription.
	 *
	 * @param string $principalUri
	 * @param string $uri
	 * @param array $properties
	 * @return mixed
	 */
	public function createSubscription($principalUri, $uri, array $properties) {
		if (!isset($properties['{http://calendarserver.org/ns/}source'])) {
			throw new Forbidden('The {http://calendarserver.org/ns/}source property is required when creating subscriptions');
		}

		$values = [
			'principaluri' => $principalUri,
			'uri' => $uri,
			'source' => $properties['{http://calendarserver.org/ns/}source']->getHref(),
			'lastmodified' => time(),
		];

		$propertiesBoolean = ['striptodos', 'stripalarms', 'stripattachments'];

		foreach ($this->subscriptionPropertyMap as $xmlName => $dbName) {
			if (array_key_exists($xmlName, $properties)) {
				$values[$dbName] = $properties[$xmlName];
				if (in_array($dbName, $propertiesBoolean)) {
					$values[$dbName] = true;
				}
			}
		}

		$valuesToInsert = [];

		$query = $this->db->getQueryBuilder();

		foreach (array_keys($values) as $name) {
			$valuesToInsert[$name] = $query->createNamedParameter($values[$name]);
		}

		$query->insert('calendarsubscriptions')
			->values($valuesToInsert)
			->execute();

		$subscriptionId = $this->db->lastInsertId('*PREFIX*calendarsubscriptions');

		$subscriptionRow = $this->getSubscriptionById($subscriptionId);
		$this->dispatcher->dispatchTyped(new SubscriptionCreatedEvent((int)$subscriptionId, $subscriptionRow));
		$this->legacyDispatcher->dispatch('\OCA\DAV\CalDAV\CalDavBackend::createSubscription', new GenericEvent(
			'\OCA\DAV\CalDAV\CalDavBackend::createSubscription',
			[
				'subscriptionId' => $subscriptionId,
				'subscriptionData' => $subscriptionRow,
			]));

		return $subscriptionId;
	}

	/**
	 * Updates a subscription
	 *
	 * The list of mutations is stored in a Sabre\DAV\PropPatch object.
	 * To do the actual updates, you must tell this object which properties
	 * you're going to process with the handle() method.
	 *
	 * Calling the handle method is like telling the PropPatch object "I
	 * promise I can handle updating this property".
	 *
	 * Read the PropPatch documentation for more info and examples.
	 *
	 * @param mixed $subscriptionId
	 * @param PropPatch $propPatch
	 * @return void
	 */
	public function updateSubscription($subscriptionId, PropPatch $propPatch) {
		$supportedProperties = array_keys($this->subscriptionPropertyMap);
		$supportedProperties[] = '{http://calendarserver.org/ns/}source';

		$propPatch->handle($supportedProperties, function ($mutations) use ($subscriptionId) {
			$newValues = [];

			foreach ($mutations as $propertyName => $propertyValue) {
				if ($propertyName === '{http://calendarserver.org/ns/}source') {
					$newValues['source'] = $propertyValue->getHref();
				} else {
					$fieldName = $this->subscriptionPropertyMap[$propertyName];
					$newValues[$fieldName] = $propertyValue;
				}
			}

			$query = $this->db->getQueryBuilder();
			$query->update('calendarsubscriptions')
				->set('lastmodified', $query->createNamedParameter(time()));
			foreach ($newValues as $fieldName => $value) {
				$query->set($fieldName, $query->createNamedParameter($value));
			}
			$query->where($query->expr()->eq('id', $query->createNamedParameter($subscriptionId)))
				->execute();

			$subscriptionRow = $this->getSubscriptionById($subscriptionId);
			$this->dispatcher->dispatchTyped(new SubscriptionUpdatedEvent((int)$subscriptionId, $subscriptionRow, [], $mutations));
			$this->legacyDispatcher->dispatch('\OCA\DAV\CalDAV\CalDavBackend::updateSubscription', new GenericEvent(
				'\OCA\DAV\CalDAV\CalDavBackend::updateSubscription',
				[
					'subscriptionId' => $subscriptionId,
					'subscriptionData' => $subscriptionRow,
					'propertyMutations' => $mutations,
				]));

			return true;
		});
	}

	/**
	 * Deletes a subscription.
	 *
	 * @param mixed $subscriptionId
	 * @return void
	 */
	public function deleteSubscription($subscriptionId) {
		$subscriptionRow = $this->getSubscriptionById($subscriptionId);

		$this->legacyDispatcher->dispatch('\OCA\DAV\CalDAV\CalDavBackend::deleteSubscription', new GenericEvent(
			'\OCA\DAV\CalDAV\CalDavBackend::deleteSubscription',
			[
				'subscriptionId' => $subscriptionId,
				'subscriptionData' => $this->getSubscriptionById($subscriptionId),
			]));

		$query = $this->db->getQueryBuilder();
		$query->delete('calendarsubscriptions')
			->where($query->expr()->eq('id', $query->createNamedParameter($subscriptionId)))
			->execute();

		$query = $this->db->getQueryBuilder();
		$query->delete('calendarobjects')
			->where($query->expr()->eq('calendarid', $query->createNamedParameter($subscriptionId)))
			->andWhere($query->expr()->eq('calendartype', $query->createNamedParameter(self::CALENDAR_TYPE_SUBSCRIPTION)))
			->execute();

		$query->delete('calendarchanges')
			->where($query->expr()->eq('calendarid', $query->createNamedParameter($subscriptionId)))
			->andWhere($query->expr()->eq('calendartype', $query->createNamedParameter(self::CALENDAR_TYPE_SUBSCRIPTION)))
			->execute();

		$query->delete($this->dbObjectPropertiesTable)
			->where($query->expr()->eq('calendarid', $query->createNamedParameter($subscriptionId)))
			->andWhere($query->expr()->eq('calendartype', $query->createNamedParameter(self::CALENDAR_TYPE_SUBSCRIPTION)))
			->execute();

		if ($subscriptionRow) {
			$this->dispatcher->dispatchTyped(new SubscriptionDeletedEvent((int)$subscriptionId, $subscriptionRow, []));
		}
	}

	/**
	 * Returns a single scheduling object for the inbox collection.
	 *
	 * The returned array should contain the following elements:
	 *   * uri - A unique basename for the object. This will be used to
	 *           construct a full uri.
	 *   * calendardata - The iCalendar object
	 *   * lastmodified - The last modification date. Can be an int for a unix
	 *                    timestamp, or a PHP DateTime object.
	 *   * etag - A unique token that must change if the object changed.
	 *   * size - The size of the object, in bytes.
	 *
	 * @param string $principalUri
	 * @param string $objectUri
	 * @return array
	 */
	public function getSchedulingObject($principalUri, $objectUri) {
		$query = $this->db->getQueryBuilder();
		$stmt = $query->select(['uri', 'calendardata', 'lastmodified', 'etag', 'size'])
			->from('schedulingobjects')
			->where($query->expr()->eq('principaluri', $query->createNamedParameter($principalUri)))
			->andWhere($query->expr()->eq('uri', $query->createNamedParameter($objectUri)))
			->execute();

		$row = $stmt->fetch(\PDO::FETCH_ASSOC);

		if (!$row) {
			return null;
		}

		return [
			'uri' => $row['uri'],
			'calendardata' => $row['calendardata'],
			'lastmodified' => $row['lastmodified'],
			'etag' => '"' . $row['etag'] . '"',
			'size' => (int)$row['size'],
		];
	}

	/**
	 * Returns all scheduling objects for the inbox collection.
	 *
	 * These objects should be returned as an array. Every item in the array
	 * should follow the same structure as returned from getSchedulingObject.
	 *
	 * The main difference is that 'calendardata' is optional.
	 *
	 * @param string $principalUri
	 * @return array
	 */
	public function getSchedulingObjects($principalUri) {
		$query = $this->db->getQueryBuilder();
		$stmt = $query->select(['uri', 'calendardata', 'lastmodified', 'etag', 'size'])
				->from('schedulingobjects')
				->where($query->expr()->eq('principaluri', $query->createNamedParameter($principalUri)))
				->execute();

		$result = [];
		foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
			$result[] = [
				'calendardata' => $row['calendardata'],
				'uri' => $row['uri'],
				'lastmodified' => $row['lastmodified'],
				'etag' => '"' . $row['etag'] . '"',
				'size' => (int)$row['size'],
			];
		}

		return $result;
	}

	/**
	 * Deletes a scheduling object from the inbox collection.
	 *
	 * @param string $principalUri
	 * @param string $objectUri
	 * @return void
	 */
	public function deleteSchedulingObject($principalUri, $objectUri) {
		$query = $this->db->getQueryBuilder();
		$query->delete('schedulingobjects')
				->where($query->expr()->eq('principaluri', $query->createNamedParameter($principalUri)))
				->andWhere($query->expr()->eq('uri', $query->createNamedParameter($objectUri)))
				->execute();
	}

	/**
	 * Creates a new scheduling object. This should land in a users' inbox.
	 *
	 * @param string $principalUri
	 * @param string $objectUri
	 * @param string $objectData
	 * @return void
	 */
	public function createSchedulingObject($principalUri, $objectUri, $objectData) {
		$query = $this->db->getQueryBuilder();
		$query->insert('schedulingobjects')
			->values([
				'principaluri' => $query->createNamedParameter($principalUri),
				'calendardata' => $query->createNamedParameter($objectData, IQueryBuilder::PARAM_LOB),
				'uri' => $query->createNamedParameter($objectUri),
				'lastmodified' => $query->createNamedParameter(time()),
				'etag' => $query->createNamedParameter(md5($objectData)),
				'size' => $query->createNamedParameter(strlen($objectData))
			])
			->execute();
	}

	/**
	 * Adds a change record to the calendarchanges table.
	 *
	 * @param mixed $calendarId
	 * @param string $objectUri
	 * @param int $operation 1 = add, 2 = modify, 3 = delete.
	 * @param int $calendarType
	 * @return void
	 */
	protected function addChange($calendarId, $objectUri, $operation, $calendarType = self::CALENDAR_TYPE_CALENDAR) {
		$table = $calendarType === self::CALENDAR_TYPE_CALENDAR ? 'calendars': 'calendarsubscriptions';

		$query = $this->db->getQueryBuilder();
		$query->select('synctoken')
			->from($table)
			->where($query->expr()->eq('id', $query->createNamedParameter($calendarId)));
		$result = $query->execute();
		$syncToken = (int)$result->fetchOne();
		$result->closeCursor();

		$query = $this->db->getQueryBuilder();
		$query->insert('calendarchanges')
			->values([
				'uri' => $query->createNamedParameter($objectUri),
				'synctoken' => $query->createNamedParameter($syncToken),
				'calendarid' => $query->createNamedParameter($calendarId),
				'operation' => $query->createNamedParameter($operation),
				'calendartype' => $query->createNamedParameter($calendarType),
			])
			->execute();

		$stmt = $this->db->prepare("UPDATE `*PREFIX*$table` SET `synctoken` = `synctoken` + 1 WHERE `id` = ?");
		$stmt->execute([
			$calendarId
		]);
	}

	/**
	 * Parses some information from calendar objects, used for optimized
	 * calendar-queries.
	 *
	 * Returns an array with the following keys:
	 *   * etag - An md5 checksum of the object without the quotes.
	 *   * size - Size of the object in bytes
	 *   * componentType - VEVENT, VTODO or VJOURNAL
	 *   * firstOccurence
	 *   * lastOccurence
	 *   * uid - value of the UID property
	 *
	 * @param string $calendarData
	 * @return array
	 */
	public function getDenormalizedData($calendarData) {
		$vObject = Reader::read($calendarData);
		$vEvents = [];
		$componentType = null;
		$component = null;
		$firstOccurrence = null;
		$lastOccurrence = null;
		$uid = null;
		$classification = self::CLASSIFICATION_PUBLIC;
		$hasDTSTART = false;
		foreach ($vObject->getComponents() as $component) {
			if ($component->name !== 'VTIMEZONE') {
				// Finding all VEVENTs, and track them
				if ($component->name === 'VEVENT') {
					array_push($vEvents, $component);
					if ($component->DTSTART) {
						$hasDTSTART = true;
					}
				}
				// Track first component type and uid
				if ($uid === null) {
					$componentType = $component->name;
					$uid = (string)$component->UID;
				}
			}
		}
		if (!$componentType) {
			throw new \Sabre\DAV\Exception\BadRequest('Calendar objects must have a VJOURNAL, VEVENT or VTODO component');
		}

		if ($hasDTSTART) {
			$component = $vEvents[0];

			// Finding the last occurrence is a bit harder
			if (!isset($component->RRULE) && count($vEvents) === 1) {
				$firstOccurrence = $component->DTSTART->getDateTime()->getTimeStamp();
				if (isset($component->DTEND)) {
					$lastOccurrence = $component->DTEND->getDateTime()->getTimeStamp();
				} elseif (isset($component->DURATION)) {
					$endDate = clone $component->DTSTART->getDateTime();
					$endDate->add(DateTimeParser::parse($component->DURATION->getValue()));
					$lastOccurrence = $endDate->getTimeStamp();
				} elseif (!$component->DTSTART->hasTime()) {
					$endDate = clone $component->DTSTART->getDateTime();
					$endDate->modify('+1 day');
					$lastOccurrence = $endDate->getTimeStamp();
				} else {
					$lastOccurrence = $firstOccurrence;
				}
			} else {
				$it = new EventIterator($vEvents);
				$maxDate = new DateTime(self::MAX_DATE);
				$firstOccurrence = $it->getDtStart()->getTimestamp();
				if ($it->isInfinite()) {
					$lastOccurrence = $maxDate->getTimestamp();
				} else {
					$end = $it->getDtEnd();
					while ($it->valid() && $end < $maxDate) {
						$end = $it->getDtEnd();
						$it->next();
					}
					$lastOccurrence = $end->getTimestamp();
				}
			}
		}

		if ($component->CLASS) {
			$classification = CalDavBackend::CLASSIFICATION_PRIVATE;
			switch ($component->CLASS->getValue()) {
				case 'PUBLIC':
					$classification = CalDavBackend::CLASSIFICATION_PUBLIC;
					break;
				case 'CONFIDENTIAL':
					$classification = CalDavBackend::CLASSIFICATION_CONFIDENTIAL;
					break;
			}
		}
		return [
			'etag' => md5($calendarData),
			'size' => strlen($calendarData),
			'componentType' => $componentType,
			'firstOccurence' => is_null($firstOccurrence) ? null : max(0, $firstOccurrence),
			'lastOccurence' => $lastOccurrence,
			'uid' => $uid,
			'classification' => $classification
		];
	}

	/**
	 * @param $cardData
	 * @return bool|string
	 */
	private function readBlob($cardData) {
		if (is_resource($cardData)) {
			return stream_get_contents($cardData);
		}

		return $cardData;
	}

	/**
	 * @param IShareable $shareable
	 * @param array $add
	 * @param array $remove
	 */
	public function updateShares($shareable, $add, $remove) {
		$calendarId = $shareable->getResourceId();
		$calendarRow = $this->getCalendarById($calendarId);
		$oldShares = $this->getShares($calendarId);

		$this->legacyDispatcher->dispatch('\OCA\DAV\CalDAV\CalDavBackend::updateShares', new GenericEvent(
			'\OCA\DAV\CalDAV\CalDavBackend::updateShares',
			[
				'calendarId' => $calendarId,
				'calendarData' => $calendarRow,
				'shares' => $oldShares,
				'add' => $add,
				'remove' => $remove,
			]));
		$this->calendarSharingBackend->updateShares($shareable, $add, $remove);

		$this->dispatcher->dispatchTyped(new CalendarShareUpdatedEvent((int)$calendarId, $calendarRow, $oldShares, $add, $remove));
	}

	/**
	 * @param int $resourceId
	 * @param int $calendarType
	 * @return array
	 */
	public function getShares($resourceId, $calendarType = self::CALENDAR_TYPE_CALENDAR) {
		return $this->calendarSharingBackend->getShares($resourceId);
	}

	/**
	 * @param boolean $value
	 * @param \OCA\DAV\CalDAV\Calendar $calendar
	 * @return string|null
	 */
	public function setPublishStatus($value, $calendar) {
		$calendarId = $calendar->getResourceId();
		$calendarData = $this->getCalendarById($calendarId);
		$this->legacyDispatcher->dispatch('\OCA\DAV\CalDAV\CalDavBackend::publishCalendar', new GenericEvent(
			'\OCA\DAV\CalDAV\CalDavBackend::updateShares',
			[
				'calendarId' => $calendarId,
				'calendarData' => $calendarData,
				'public' => $value,
			]));

		$query = $this->db->getQueryBuilder();
		if ($value) {
			$publicUri = $this->random->generate(16, ISecureRandom::CHAR_HUMAN_READABLE);
			$query->insert('dav_shares')
				->values([
					'principaluri' => $query->createNamedParameter($calendar->getPrincipalURI()),
					'type' => $query->createNamedParameter('calendar'),
					'access' => $query->createNamedParameter(self::ACCESS_PUBLIC),
					'resourceid' => $query->createNamedParameter($calendar->getResourceId()),
					'publicuri' => $query->createNamedParameter($publicUri)
				]);
			$query->execute();

			$this->dispatcher->dispatchTyped(new CalendarPublishedEvent((int)$calendarId, $calendarData, $publicUri));
			return $publicUri;
		}
		$query->delete('dav_shares')
			->where($query->expr()->eq('resourceid', $query->createNamedParameter($calendar->getResourceId())))
			->andWhere($query->expr()->eq('access', $query->createNamedParameter(self::ACCESS_PUBLIC)));
		$query->execute();

		$this->dispatcher->dispatchTyped(new CalendarUnpublishedEvent((int)$calendarId, $calendarData));
		return null;
	}

	/**
	 * @param \OCA\DAV\CalDAV\Calendar $calendar
	 * @return mixed
	 */
	public function getPublishStatus($calendar) {
		$query = $this->db->getQueryBuilder();
		$result = $query->select('publicuri')
			->from('dav_shares')
			->where($query->expr()->eq('resourceid', $query->createNamedParameter($calendar->getResourceId())))
			->andWhere($query->expr()->eq('access', $query->createNamedParameter(self::ACCESS_PUBLIC)))
			->execute();

		$row = $result->fetch();
		$result->closeCursor();
		return $row ? reset($row) : false;
	}

	/**
	 * @param int $resourceId
	 * @param array $acl
	 * @return array
	 */
	public function applyShareAcl($resourceId, $acl) {
		return $this->calendarSharingBackend->applyShareAcl($resourceId, $acl);
	}



	/**
	 * update properties table
	 *
	 * @param int $calendarId
	 * @param string $objectUri
	 * @param string $calendarData
	 * @param int $calendarType
	 */
	public function updateProperties($calendarId, $objectUri, $calendarData, $calendarType = self::CALENDAR_TYPE_CALENDAR) {
		$objectId = $this->getCalendarObjectId($calendarId, $objectUri, $calendarType);

		try {
			$vCalendar = $this->readCalendarData($calendarData);
		} catch (\Exception $ex) {
			return;
		}

		$this->purgeProperties($calendarId, $objectId);

		$query = $this->db->getQueryBuilder();
		$query->insert($this->dbObjectPropertiesTable)
			->values(
				[
					'calendarid' => $query->createNamedParameter($calendarId),
					'calendartype' => $query->createNamedParameter($calendarType),
					'objectid' => $query->createNamedParameter($objectId),
					'name' => $query->createParameter('name'),
					'parameter' => $query->createParameter('parameter'),
					'value' => $query->createParameter('value'),
				]
			);

		$indexComponents = ['VEVENT', 'VJOURNAL', 'VTODO'];
		foreach ($vCalendar->getComponents() as $component) {
			if (!in_array($component->name, $indexComponents)) {
				continue;
			}

			foreach ($component->children() as $property) {
				if (in_array($property->name, self::$indexProperties)) {
					$value = $property->getValue();
					// is this a shitty db?
					if (!$this->db->supports4ByteText()) {
						$value = preg_replace('/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", $value);
					}
					$value = mb_substr($value, 0, 254);

					$query->setParameter('name', $property->name);
					$query->setParameter('parameter', null);
					$query->setParameter('value', $value);
					$query->execute();
				}

				if (array_key_exists($property->name, self::$indexParameters)) {
					$parameters = $property->parameters();
					$indexedParametersForProperty = self::$indexParameters[$property->name];

					foreach ($parameters as $key => $value) {
						if (in_array($key, $indexedParametersForProperty)) {
							// is this a shitty db?
							if ($this->db->supports4ByteText()) {
								$value = preg_replace('/[\x{10000}-\x{10FFFF}]/u', "\xEF\xBF\xBD", $value);
							}

							$query->setParameter('name', $property->name);
							$query->setParameter('parameter', mb_substr($key, 0, 254));
							$query->setParameter('value', mb_substr($value, 0, 254));
							$query->execute();
						}
					}
				}
			}
		}
	}

	/**
	 * deletes all birthday calendars
	 */
	public function deleteAllBirthdayCalendars() {
		$query = $this->db->getQueryBuilder();
		$result = $query->select(['id'])->from('calendars')
			->where($query->expr()->eq('uri', $query->createNamedParameter(BirthdayService::BIRTHDAY_CALENDAR_URI)))
			->execute();

		$ids = $result->fetchAll();
		foreach ($ids as $id) {
			$this->deleteCalendar($id['id']);
		}
	}

	/**
	 * @param $subscriptionId
	 */
	public function purgeAllCachedEventsForSubscription($subscriptionId) {
		$query = $this->db->getQueryBuilder();
		$query->select('uri')
			->from('calendarobjects')
			->where($query->expr()->eq('calendarid', $query->createNamedParameter($subscriptionId)))
			->andWhere($query->expr()->eq('calendartype', $query->createNamedParameter(self::CALENDAR_TYPE_SUBSCRIPTION)));
		$stmt = $query->execute();

		$uris = [];
		foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
			$uris[] = $row['uri'];
		}
		$stmt->closeCursor();

		$query = $this->db->getQueryBuilder();
		$query->delete('calendarobjects')
			->where($query->expr()->eq('calendarid', $query->createNamedParameter($subscriptionId)))
			->andWhere($query->expr()->eq('calendartype', $query->createNamedParameter(self::CALENDAR_TYPE_SUBSCRIPTION)))
			->execute();

		$query->delete('calendarchanges')
			->where($query->expr()->eq('calendarid', $query->createNamedParameter($subscriptionId)))
			->andWhere($query->expr()->eq('calendartype', $query->createNamedParameter(self::CALENDAR_TYPE_SUBSCRIPTION)))
			->execute();

		$query->delete($this->dbObjectPropertiesTable)
			->where($query->expr()->eq('calendarid', $query->createNamedParameter($subscriptionId)))
			->andWhere($query->expr()->eq('calendartype', $query->createNamedParameter(self::CALENDAR_TYPE_SUBSCRIPTION)))
			->execute();

		foreach ($uris as $uri) {
			$this->addChange($subscriptionId, $uri, 3, self::CALENDAR_TYPE_SUBSCRIPTION);
		}
	}

	/**
	 * Move a calendar from one user to another
	 *
	 * @param string $uriName
	 * @param string $uriOrigin
	 * @param string $uriDestination
	 * @param string $newUriName (optional) the new uriName
	 */
	public function moveCalendar($uriName, $uriOrigin, $uriDestination, $newUriName = null) {
		$query = $this->db->getQueryBuilder();
		$query->update('calendars')
			->set('principaluri', $query->createNamedParameter($uriDestination))
			->set('uri', $query->createNamedParameter($newUriName ?: $uriName))
			->where($query->expr()->eq('principaluri', $query->createNamedParameter($uriOrigin)))
			->andWhere($query->expr()->eq('uri', $query->createNamedParameter($uriName)))
			->execute();
	}

	/**
	 * read VCalendar data into a VCalendar object
	 *
	 * @param string $objectData
	 * @return VCalendar
	 */
	protected function readCalendarData($objectData) {
		return Reader::read($objectData);
	}

	/**
	 * delete all properties from a given calendar object
	 *
	 * @param int $calendarId
	 * @param int $objectId
	 */
	protected function purgeProperties($calendarId, $objectId) {
		$query = $this->db->getQueryBuilder();
		$query->delete($this->dbObjectPropertiesTable)
			->where($query->expr()->eq('objectid', $query->createNamedParameter($objectId)))
			->andWhere($query->expr()->eq('calendarid', $query->createNamedParameter($calendarId)));
		$query->execute();
	}

	/**
	 * get ID from a given calendar object
	 *
	 * @param int $calendarId
	 * @param string $uri
	 * @param int $calendarType
	 * @return int
	 */
	protected function getCalendarObjectId($calendarId, $uri, $calendarType):int {
		$query = $this->db->getQueryBuilder();
		$query->select('id')
			->from('calendarobjects')
			->where($query->expr()->eq('uri', $query->createNamedParameter($uri)))
			->andWhere($query->expr()->eq('calendarid', $query->createNamedParameter($calendarId)))
			->andWhere($query->expr()->eq('calendartype', $query->createNamedParameter($calendarType)));

		$result = $query->execute();
		$objectIds = $result->fetch();
		$result->closeCursor();

		if (!isset($objectIds['id'])) {
			throw new \InvalidArgumentException('Calendarobject does not exists: ' . $uri);
		}

		return (int)$objectIds['id'];
	}

	/**
	 * return legacy endpoint principal name to new principal name
	 *
	 * @param $principalUri
	 * @param $toV2
	 * @return string
	 */
	private function convertPrincipal($principalUri, $toV2) {
		if ($this->principalBackend->getPrincipalPrefix() === 'principals') {
			list(, $name) = Uri\split($principalUri);
			if ($toV2 === true) {
				return "principals/users/$name";
			}
			return "principals/$name";
		}
		return $principalUri;
	}

	/**
	 * adds information about an owner to the calendar data
	 *
	 * @param $calendarInfo
	 */
	private function addOwnerPrincipal(&$calendarInfo) {
		$ownerPrincipalKey = '{' . \OCA\DAV\DAV\Sharing\Plugin::NS_OWNCLOUD . '}owner-principal';
		$displaynameKey = '{' . \OCA\DAV\DAV\Sharing\Plugin::NS_NEXTCLOUD . '}owner-displayname';
		if (isset($calendarInfo[$ownerPrincipalKey])) {
			$uri = $calendarInfo[$ownerPrincipalKey];
		} else {
			$uri = $calendarInfo['principaluri'];
		}

		$principalInformation = $this->principalBackend->getPrincipalByPath($uri);
		if (isset($principalInformation['{DAV:}displayname'])) {
			$calendarInfo[$displaynameKey] = $principalInformation['{DAV:}displayname'];
		}
	}
}
