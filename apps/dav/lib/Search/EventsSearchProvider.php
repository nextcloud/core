<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Georg Ehrke
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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
namespace OCA\DAV\Search;

use OCA\DAV\CalDAV\CalDavBackend;
use OCP\IUser;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;
use Sabre\VObject\Component;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\Property;

/**
 * Class EventsSearchProvider
 *
 * @package OCA\DAV\Search
 */
class EventsSearchProvider extends ACalendarSearchProvider {

	/**
	 * @var string[]
	 */
	private static $searchProperties = [
		'SUMMARY',
		'LOCATION',
		'DESCRIPTION',
		'ATTENDEE',
		'ORGANIZER',
		'CATEGORIES',
	];

	/**
	 * @var string[]
	 */
	private static $searchParameters = [
		'ATTENDEE' => ['CN'],
		'ORGANIZER' => ['CN'],
	];

	/**
	 * @var string
	 */
	private static $componentType = 'VEVENT';

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'calendar';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return $this->l10n->t('Events');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(string $route, array $routeParameters): int {
		if ($route === 'calendar.View.index') {
			return -1;
		}
		return 30;
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user,
						   ISearchQuery $query): SearchResult {
		if (!$this->appManager->isEnabledForUser('calendar', $user)) {
			return SearchResult::complete($this->getName(), []);
		}

		$principalUri = 'principals/users/' . $user->getUID();
		$calendarsById = $this->getSortedCalendars($principalUri);
		$subscriptionsById = $this->getSortedSubscriptions($principalUri);

		$searchResults = $this->backend->searchPrincipalUri(
			$principalUri,
			$query->getTerm(),
			[self::$componentType],
			self::$searchProperties,
			self::$searchParameters,
			[
				'limit' => $query->getLimit(),
				'offset' => $query->getCursor(),
			]
		);
		$formattedResults = \array_map(function (array $eventRow) use ($calendarsById, $subscriptionsById):SearchResultEntry {
			$component = $this->getPrimaryComponent($eventRow['calendardata'], self::$componentType);
			$title = (string)($component->SUMMARY ?? $this->l10n->t('Untitled event'));
			$subline = $this->generateSubline($component);

			if ($eventRow['calendartype'] === CalDavBackend::CALENDAR_TYPE_CALENDAR) {
				$calendar = $calendarsById[$eventRow['calendarid']];
			} else {
				$calendar = $subscriptionsById[$eventRow['calendarid']];
			}
			$resourceUrl = $this->getDeepLinkToCalendarApp($calendar['principaluri'], $calendar['uri'], $eventRow['uri']);

			return new SearchResultEntry('', $title, $subline, $resourceUrl, 'icon-calendar-dark', false);
		}, $searchResults);

		return SearchResult::paginated(
			$this->getName(),
			$formattedResults,
			$query->getCursor() + count($formattedResults)
		);
	}

	/**
	 * @param string $principalUri
	 * @param string $calendarUri
	 * @param string $calendarObjectUri
	 * @return string
	 */
	protected function getDeepLinkToCalendarApp(string $principalUri,
												string $calendarUri,
												string $calendarObjectUri): string {
		$davUrl = $this->getDavUrlForCalendarObject($principalUri, $calendarUri, $calendarObjectUri);
		// This route will automatically figure out what recurrence-id to open
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->linkToRoute('calendar.view.index')
			. 'edit/'
			. base64_encode($davUrl)
		);
	}

	/**
	 * @param string $principalUri
	 * @param string $calendarUri
	 * @param string $calendarObjectUri
	 * @return string
	 */
	protected function getDavUrlForCalendarObject(string $principalUri,
												  string $calendarUri,
												  string $calendarObjectUri): string {
		[,, $principalId] = explode('/', $principalUri, 3);

		return $this->urlGenerator->linkTo('', 'remote.php') . '/dav/calendars/'
			. $principalId . '/'
			. $calendarUri . '/'
			. $calendarObjectUri;
	}

	/**
	 * @param Component $eventComponent
	 * @return string
	 */
	protected function generateSubline(Component $eventComponent): string {
		$dtStart = $eventComponent->DTSTART;
		$dtEnd = $this->getDTEndForEvent($eventComponent);
		$isAllDayEvent = $dtStart instanceof Property\ICalendar\Date;
		$startDateTime = new \DateTime($dtStart->getDateTime()->format(\DateTime::ATOM));
		$endDateTime = new \DateTime($dtEnd->getDateTime()->format(\DateTime::ATOM));

		if ($isAllDayEvent) {
			$endDateTime->modify('-1 day');
			if ($this->isDayEqual($startDateTime, $endDateTime)) {
				return $this->l10n->l('date', $startDateTime, ['width' => 'medium']);
			}

			$formattedStart = $this->l10n->l('date', $startDateTime, ['width' => 'medium']);
			$formattedEnd = $this->l10n->l('date', $endDateTime, ['width' => 'medium']);
			return "$formattedStart - $formattedEnd";
		}

		$formattedStartDate = $this->l10n->l('date', $startDateTime, ['width' => 'medium']);
		$formattedEndDate = $this->l10n->l('date', $endDateTime, ['width' => 'medium']);
		$formattedStartTime = $this->l10n->l('time', $startDateTime, ['width' => 'short']);
		$formattedEndTime = $this->l10n->l('time', $endDateTime, ['width' => 'short']);

		if ($this->isDayEqual($startDateTime, $endDateTime)) {
			return "$formattedStartDate $formattedStartTime - $formattedEndTime";
		}

		return "$formattedStartDate $formattedStartTime - $formattedEndDate $formattedEndTime";
	}

	/**
	 * @param Component $eventComponent
	 * @return Property
	 */
	protected function getDTEndForEvent(Component $eventComponent):Property {
		if (isset($eventComponent->DTEND)) {
			$end = $eventComponent->DTEND;
		} elseif (isset($eventComponent->DURATION)) {
			$isFloating = $eventComponent->DTSTART->isFloating();
			$end = clone $eventComponent->DTSTART;
			$endDateTime = $end->getDateTime();
			$endDateTime = $endDateTime->add(DateTimeParser::parse($eventComponent->DURATION->getValue()));
			$end->setDateTime($endDateTime, $isFloating);
		} elseif (!$eventComponent->DTSTART->hasTime()) {
			$isFloating = $eventComponent->DTSTART->isFloating();
			$end = clone $eventComponent->DTSTART;
			$endDateTime = $end->getDateTime();
			$endDateTime = $endDateTime->modify('+1 day');
			$end->setDateTime($endDateTime, $isFloating);
		} else {
			$end = clone $eventComponent->DTSTART;
		}

		return $end;
	}

	/**
	 * @param \DateTime $dtStart
	 * @param \DateTime $dtEnd
	 * @return bool
	 */
	protected function isDayEqual(\DateTime $dtStart,
								  \DateTime $dtEnd) {
		return $dtStart->format('Y-m-d') === $dtEnd->format('Y-m-d');
	}
}
