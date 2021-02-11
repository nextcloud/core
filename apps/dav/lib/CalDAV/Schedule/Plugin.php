<?php
/**
 * @copyright Copyright (c) 2016, Roeland Jago Douma <roeland@famdouma.nl>
 * @copyright Copyright (c) 2016, Joas Schilling <coding@schilljs.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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

namespace OCA\DAV\CalDAV\Schedule;

use DateTimeZone;
use OCA\DAV\CalDAV\CalDavBackend;
use OCA\DAV\CalDAV\CalendarHome;
use OCP\IConfig;
use Sabre\CalDAV\ICalendar;
use Sabre\DAV\INode;
use Sabre\DAV\IProperties;
use Sabre\DAV\PropFind;
use Sabre\DAV\Server;
use Sabre\DAV\Xml\Property\LocalHref;
use Sabre\DAVACL\IPrincipal;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Sabre\VObject\Component;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\FreeBusyGenerator;
use Sabre\VObject\ITip;
use Sabre\VObject\Parameter;
use Sabre\VObject\Property;
use Sabre\VObject\Reader;
use function \Sabre\Uri\split;

class Plugin extends \Sabre\CalDAV\Schedule\Plugin {

	/**
	 * @var IConfig
	 */
	private $config;

	/** @var ITip\Message[] */
	private $schedulingResponses = [];

	/** @var string|null */
	private $pathOfCalendarObjectChange = null;

	public const CALENDAR_USER_TYPE = '{' . self::NS_CALDAV . '}calendar-user-type';
	public const SCHEDULE_DEFAULT_CALENDAR_URL = '{' . Plugin::NS_CALDAV . '}schedule-default-calendar-URL';

	/**
	 * @param IConfig $config
	 */
	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * Initializes the plugin
	 *
	 * @param Server $server
	 * @return void
	 */
	public function initialize(Server $server) {
		parent::initialize($server);
		$server->on('propFind', [$this, 'propFindDefaultCalendarUrl'], 90);
		$server->on('afterWriteContent', [$this, 'dispatchSchedulingResponses']);
		$server->on('afterCreateFile', [$this, 'dispatchSchedulingResponses']);
	}

	/**
	 * This method handler is invoked during fetching of properties.
	 *
	 * We use this event to add calendar-auto-schedule-specific properties.
	 *
	 * @param PropFind $propFind
	 * @param INode $node
	 * @return void
	 */
	public function propFind(PropFind $propFind, INode $node) {
		if ($node instanceof IPrincipal) {
			// overwrite Sabre/Dav's implementation
			$propFind->handle(self::CALENDAR_USER_TYPE, function () use ($node) {
				if ($node instanceof IProperties) {
					$props = $node->getProperties([self::CALENDAR_USER_TYPE]);

					if (isset($props[self::CALENDAR_USER_TYPE])) {
						return $props[self::CALENDAR_USER_TYPE];
					}
				}

				return 'INDIVIDUAL';
			});
		}

		parent::propFind($propFind, $node);
	}

	/**
	 * Returns a list of addresses that are associated with a principal.
	 *
	 * @param string $principal
	 * @return array
	 */
	protected function getAddressesForPrincipal($principal) {
		$result = parent::getAddressesForPrincipal($principal);

		if ($result === null) {
			$result = [];
		}

		return $result;
	}

	/**
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 * @param VCalendar $vCal
	 * @param mixed $calendarPath
	 * @param mixed $modified
	 * @param mixed $isNew
	 */
	public function calendarObjectChange(RequestInterface $request, ResponseInterface $response, VCalendar $vCal, $calendarPath, &$modified, $isNew) {
		// Save the first path we get as a calendar-object-change request
		if (!$this->pathOfCalendarObjectChange) {
			$this->pathOfCalendarObjectChange = $request->getPath();
		}

		parent::calendarObjectChange($request, $response, $vCal, $calendarPath, $modified, $isNew);
	}

	/**
	 * @inheritDoc
	 */
	public function scheduleLocalDelivery(ITip\Message $iTipMessage):void {
		parent::scheduleLocalDelivery($iTipMessage);

		// We only care when the message was successfully delivered locally
		if ($iTipMessage->scheduleStatus !== '1.2;Message delivered locally') {
			return;
		}

		// We only care about request. reply and cancel are properly handled
		// by parent::scheduleLocalDelivery already
		if (strcasecmp($iTipMessage->method, 'REQUEST') !== 0) {
			return;
		}

		// If parent::scheduleLocalDelivery set scheduleStatus to 1.2,
		// it means that it was successfully delivered locally.
		// Meaning that the ACL plugin is loaded and that a principial
		// exists for the given recipient id, no need to double check
		/** @var \Sabre\DAVACL\Plugin $aclPlugin */
		$aclPlugin = $this->server->getPlugin('acl');
		$principalUri = $aclPlugin->getPrincipalByUri($iTipMessage->recipient);
		$calendarUserType = $this->getCalendarUserTypeForPrincipal($principalUri);
		if (strcasecmp($calendarUserType, 'ROOM') !== 0 && strcasecmp($calendarUserType, 'RESOURCE') !== 0) {
			return;
		}

		$attendee = $this->getCurrentAttendee($iTipMessage);
		if (!$attendee) {
			return;
		}

		// We only respond when a response was actually requested
		$rsvp = $this->getAttendeeRSVP($attendee);
		if (!$rsvp) {
			return;
		}

		if (!isset($iTipMessage->message)) {
			return;
		}

		$vcalendar = $iTipMessage->message;
		if (!isset($vcalendar->VEVENT)) {
			return;
		}

		/** @var Component $vevent */
		$vevent = $vcalendar->VEVENT;

		// We don't support autoresponses for recurrencing events for now
		if (isset($vevent->RRULE) || isset($vevent->RDATE)) {
			return;
		}

		$dtstart = $vevent->DTSTART;
		$dtend = $this->getDTEndFromVEvent($vevent);
		$uid = $vevent->UID->getValue();
		$sequence = isset($vevent->SEQUENCE) ? $vevent->SEQUENCE->getValue() : 0;
		$recurrenceId = isset($vevent->{'RECURRENCE-ID'}) ? $vevent->{'RECURRENCE-ID'}->serialize() : '';

		$message = <<<EOF
BEGIN:VCALENDAR
PRODID:-//Nextcloud/Nextcloud CalDAV Server//EN
METHOD:REPLY
VERSION:2.0
BEGIN:VEVENT
ATTENDEE;PARTSTAT=%s:%s
ORGANIZER:%s
UID:%s
SEQUENCE:%s
REQUEST-STATUS:2.0;Success
%sEND:VEVENT
END:VCALENDAR
EOF;

		if ($this->isAvailableAtTime($attendee->getValue(), $dtstart->getDateTime(), $dtend->getDateTime(), $uid)) {
			$partStat = 'ACCEPTED';
		} else {
			$partStat = 'DECLINED';
		}

		$vObject = Reader::read(vsprintf($message, [
			$partStat,
			$iTipMessage->recipient,
			$iTipMessage->sender,
			$uid,
			$sequence,
			$recurrenceId
		]));

		$responseITipMessage = new ITip\Message();
		$responseITipMessage->uid = $uid;
		$responseITipMessage->component = 'VEVENT';
		$responseITipMessage->method = 'REPLY';
		$responseITipMessage->sequence = $sequence;
		$responseITipMessage->sender = $iTipMessage->recipient;
		$responseITipMessage->recipient = $iTipMessage->sender;
		$responseITipMessage->message = $vObject;

		// We can't dispatch them now already, because the organizers calendar-object
		// was not yet created. Hence Sabre/DAV won't find a calendar-object, when we
		// send our reply.
		$this->schedulingResponses[] = $responseITipMessage;
	}

	/**
	 * @param string $uri
	 */
	public function dispatchSchedulingResponses(string $uri):void {
		if ($uri !== $this->pathOfCalendarObjectChange) {
			return;
		}

		foreach ($this->schedulingResponses as $schedulingResponse) {
			$this->scheduleLocalDelivery($schedulingResponse);
		}
	}

	/**
	 * Always use the personal calendar as target for scheduled events
	 *
	 * @param PropFind $propFind
	 * @param INode $node
	 * @return void
	 */
	public function propFindDefaultCalendarUrl(PropFind $propFind, INode $node) {
		if ($node instanceof IPrincipal) {
			$propFind->handle(self::SCHEDULE_DEFAULT_CALENDAR_URL, function () use ($node) {
				/** @var \OCA\DAV\CalDAV\Plugin $caldavPlugin */
				$caldavPlugin = $this->server->getPlugin('caldav');
				$principalUrl = $node->getPrincipalUrl();

				$calendarHomePath = $caldavPlugin->getCalendarHomeForPrincipal($principalUrl);
				if (!$calendarHomePath) {
					return null;
				}

				if (strpos($principalUrl, 'principals/users') === 0) {
					list(, $userId) = split($principalUrl);
					$uri = $this->config->getUserValue($userId, 'dav', 'defaultCalendar', CalDavBackend::PERSONAL_CALENDAR_URI);
					$displayName = CalDavBackend::PERSONAL_CALENDAR_NAME;
				} elseif (strpos($principalUrl, 'principals/calendar-resources') === 0 ||
						  strpos($principalUrl, 'principals/calendar-rooms') === 0) {
					$uri = CalDavBackend::RESOURCE_BOOKING_CALENDAR_URI;
					$displayName = CalDavBackend::RESOURCE_BOOKING_CALENDAR_NAME;
				} else {
					// How did we end up here?
					// TODO - throw exception or just ignore?
					return null;
				}

				/** @var CalendarHome $calendarHome */
				$calendarHome = $this->server->tree->getNodeForPath($calendarHomePath);
				if (!$calendarHome->childExists($uri)) {
					$calendarHome->getCalDAVBackend()->createCalendar($principalUrl, $uri, [
						'{DAV:}displayname' => $displayName,
					]);
				}

				$result = $this->server->getPropertiesForPath($calendarHomePath . '/' . $uri, [], 1);
				if (empty($result)) {
					return null;
				}

				return new LocalHref($result[0]['href']);
			});
		}
	}

	/**
	 * Returns a list of addresses that are associated with a principal.
	 *
	 * @param string $principal
	 * @return string|null
	 */
	protected function getCalendarUserTypeForPrincipal($principal):?string {
		$calendarUserType = '{' . self::NS_CALDAV . '}calendar-user-type';
		$properties = $this->server->getProperties(
			$principal,
			[$calendarUserType]
		);

		// If we can't find this information, we'll stop processing
		if (!isset($properties[$calendarUserType])) {
			return null;
		}

		return $properties[$calendarUserType];
	}

	/**
	 * @param ITip\Message $iTipMessage
	 * @return null|Property
	 */
	private function getCurrentAttendee(ITip\Message $iTipMessage):?Property {
		/** @var VEvent $vevent */
		$vevent = $iTipMessage->message->VEVENT;
		$attendees = $vevent->select('ATTENDEE');
		foreach ($attendees as $attendee) {
			/** @var Property $attendee */
			if (strcasecmp($attendee->getValue(), $iTipMessage->recipient) === 0) {
				return $attendee;
			}
		}
		return null;
	}

	/**
	 * @param Property|null $attendee
	 * @return bool
	 */
	private function getAttendeeRSVP(Property $attendee = null):bool {
		if ($attendee !== null) {
			$rsvp = $attendee->offsetGet('RSVP');
			if (($rsvp instanceof Parameter) && (strcasecmp($rsvp->getValue(), 'TRUE') === 0)) {
				return true;
			}
		}
		// RFC 5545 3.2.17: default RSVP is false
		return false;
	}

	/**
	 * @param VEvent $vevent
	 * @return Property\ICalendar\DateTime
	 */
	private function getDTEndFromVEvent(VEvent $vevent):Property\ICalendar\DateTime {
		if (isset($vevent->DTEND)) {
			return $vevent->DTEND;
		}

		if (isset($vevent->DURATION)) {
			$isFloating = $vevent->DTSTART->isFloating();
			/** @var Property\ICalendar\DateTime $end */
			$end = clone $vevent->DTSTART;
			$endDateTime = $end->getDateTime();
			$endDateTime = $endDateTime->add(DateTimeParser::parse($vevent->DURATION->getValue()));
			$end->setDateTime($endDateTime, $isFloating);
			return $end;
		}

		if (!$vevent->DTSTART->hasTime()) {
			$isFloating = $vevent->DTSTART->isFloating();
			/** @var Property\ICalendar\DateTime $end */
			$end = clone $vevent->DTSTART;
			$endDateTime = $end->getDateTime();
			$endDateTime = $endDateTime->modify('+1 day');
			$end->setDateTime($endDateTime, $isFloating);
			return $end;
		}

		return clone $vevent->DTSTART;
	}

	/**
	 * @param string $email
	 * @param \DateTimeInterface $start
	 * @param \DateTimeInterface $end
	 * @param string $ignoreUID
	 * @return bool
	 */
	private function isAvailableAtTime(string $email, \DateTimeInterface $start, \DateTimeInterface $end, string $ignoreUID):bool {
		// This method is heavily inspired by Sabre\CalDAV\Schedule\Plugin::scheduleLocalDelivery
		// and Sabre\CalDAV\Schedule\Plugin::getFreeBusyForEmail

		$aclPlugin = $this->server->getPlugin('acl');
		$this->server->removeListener('propFind', [$aclPlugin, 'propFind']);

		$result = $aclPlugin->principalSearch(
			['{http://sabredav.org/ns}email-address' => $this->stripOffMailTo($email)],
			[
				'{DAV:}principal-URL',
				'{' . self::NS_CALDAV . '}calendar-home-set',
				'{' . self::NS_CALDAV . '}schedule-inbox-URL',
				'{http://sabredav.org/ns}email-address',

			]
		);
		$this->server->on('propFind', [$aclPlugin, 'propFind'], 20);


		// Grabbing the calendar list
		$objects = [];
		$calendarTimeZone = new DateTimeZone('UTC');

		$homePath = $result[0][200]['{' . self::NS_CALDAV . '}calendar-home-set']->getHref();
		foreach ($this->server->tree->getNodeForPath($homePath)->getChildren() as $node) {
			if (!$node instanceof ICalendar) {
				continue;
			}

			// Getting the list of object uris within the time-range
			$urls = $node->calendarQuery([
				'name' => 'VCALENDAR',
				'comp-filters' => [
					[
						'name' => 'VEVENT',
						'is-not-defined' => false,
						'time-range' => [
							'start' => $start,
							'end' => $end,
						],
						'comp-filters' => [],
						'prop-filters' => [],
					],
					[
						'name' => 'VEVENT',
						'is-not-defined' => false,
						'time-range' => null,
						'comp-filters' => [],
						'prop-filters' => [
							[
								'name' => 'UID',
								'is-not-defined' => false,
								'time-range' => null,
								'text-match' => [
									'value' => $ignoreUID,
									'negate-condition' => true,
									'collation' => 'i;octet',
								],
								'param-filters' => [],
							],
						]
					],
				],
				'prop-filters' => [],
				'is-not-defined' => false,
				'time-range' => null,
			]);

			foreach ($urls as $url) {
				$objects[] = $node->getChild($url)->get();
			}
		}

		$inboxProps = $this->server->getProperties(
			$result[0][200]['{' . self::NS_CALDAV . '}schedule-inbox-URL']->getHref(),
			['{' . self::NS_CALDAV . '}calendar-availability']
		);

		$vcalendar = new VCalendar();
		$vcalendar->METHOD = 'REPLY';

		$generator = new FreeBusyGenerator();
		$generator->setObjects($objects);
		$generator->setTimeRange($start, $end);
		$generator->setBaseObject($vcalendar);
		$generator->setTimeZone($calendarTimeZone);

		if (isset($inboxProps['{' . self::NS_CALDAV . '}calendar-availability'])) {
			$generator->setVAvailability(
				Reader::read(
					$inboxProps['{' . self::NS_CALDAV . '}calendar-availability']
				)
			);
		}

		$result = $generator->getResult();
		if (!isset($result->VFREEBUSY)) {
			return false;
		}

		/** @var Component $freeBusyComponent */
		$freeBusyComponent = $result->VFREEBUSY;
		$freeBusyProperties = $freeBusyComponent->select('FREEBUSY');
		// If there is no Free-busy property at all, the time-range is empty and available
		if (count($freeBusyProperties) === 0) {
			return true;
		}

		// If more than one Free-Busy property was returned, it means that an event
		// starts or ends inside this time-range, so it's not availabe and we return false
		if (count($freeBusyProperties) > 1) {
			return false;
		}

		/** @var Property $freeBusyProperty */
		$freeBusyProperty = $freeBusyProperties[0];
		if (!$freeBusyProperty->offsetExists('FBTYPE')) {
			// If there is no FBTYPE, it means it's busy
			return false;
		}

		$fbTypeParameter = $freeBusyProperty->offsetGet('FBTYPE');
		if (!($fbTypeParameter instanceof Parameter)) {
			return false;
		}

		return (strcasecmp($fbTypeParameter->getValue(), 'FREE') === 0);
	}

	/**
	 * @param string $email
	 * @return string
	 */
	private function stripOffMailTo(string $email): string {
		if (stripos($email, 'mailto:') === 0) {
			return substr($email, 7);
		}

		return $email;
	}
}
