<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2019, Georg Ehrke
 *
 * @author Achim Königs <garfonso@tratschtante.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Sven Strickroth <email@cs-ware.de>
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

use Exception;
use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\DAV\GroupPrincipalBackend;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\Document;
use Sabre\VObject\InvalidDataException;
use Sabre\VObject\Property\VCard\DateAndOrTime;
use Sabre\VObject\Reader;

/**
 * Class BirthdayService
 *
 * @package OCA\DAV\CalDAV
 */
class BirthdayService {
	public const BIRTHDAY_CALENDAR_URI = 'contact_birthdays';

	/** @var GroupPrincipalBackend */
	private $principalBackend;

	/** @var CalDavBackend  */
	private $calDavBackEnd;

	/** @var CardDavBackend  */
	private $cardDavBackEnd;

	/** @var IConfig */
	private $config;

	/** @var IDBConnection */
	private $dbConnection;

	/** @var IL10N */
	private $l10n;

	/**
	 * BirthdayService constructor.
	 *
	 * @param CalDavBackend $calDavBackEnd
	 * @param CardDavBackend $cardDavBackEnd
	 * @param GroupPrincipalBackend $principalBackend
	 * @param IConfig $config
	 * @param IDBConnection $dbConnection
	 * @param IL10N $l10n
	 */
	public function __construct(CalDavBackend $calDavBackEnd,
								CardDavBackend $cardDavBackEnd,
								GroupPrincipalBackend $principalBackend,
								IConfig $config,
								IDBConnection $dbConnection,
								IL10N $l10n) {
		$this->calDavBackEnd = $calDavBackEnd;
		$this->cardDavBackEnd = $cardDavBackEnd;
		$this->principalBackend = $principalBackend;
		$this->config = $config;
		$this->dbConnection = $dbConnection;
		$this->l10n = $l10n;
	}

	/**
	 * @param int $addressBookId
	 * @param string $cardUri
	 * @param string $cardData
	 */
	public function onCardChanged(int $addressBookId,
								  string $cardUri,
								  string $cardData) {
		if (!$this->isGloballyEnabled()) {
			return;
		}

		$targetPrincipals = $this->getAllAffectedPrincipals($addressBookId);
		$book = $this->cardDavBackEnd->getAddressBookById($addressBookId);
		$targetPrincipals[] = $book['principaluri'];
		$datesToSync = [
			['postfix' => '', 'field' => 'BDAY'],
			['postfix' => '-death', 'field' => 'DEATHDATE'],
			['postfix' => '-anniversary', 'field' => 'ANNIVERSARY'],
		];

		foreach ($targetPrincipals as $principalUri) {
			if (!$this->isUserEnabled($principalUri)) {
				continue;
			}

			$calendar = $this->ensureCalendarExists($principalUri);
			foreach ($datesToSync as $type) {
				$this->updateCalendar($cardUri, $cardData, $book, (int) $calendar['id'], $type);
			}
		}
	}

	/**
	 * @param int $addressBookId
	 * @param string $cardUri
	 */
	public function onCardDeleted(int $addressBookId,
								  string $cardUri) {
		if (!$this->isGloballyEnabled()) {
			return;
		}

		$targetPrincipals = $this->getAllAffectedPrincipals($addressBookId);
		$book = $this->cardDavBackEnd->getAddressBookById($addressBookId);
		$targetPrincipals[] = $book['principaluri'];
		foreach ($targetPrincipals as $principalUri) {
			if (!$this->isUserEnabled($principalUri)) {
				continue;
			}

			$calendar = $this->ensureCalendarExists($principalUri);
			foreach (['', '-death', '-anniversary'] as $tag) {
				$objectUri = $book['uri'] . '-' . $cardUri . $tag .'.ics';
				$this->calDavBackEnd->deleteCalendarObject($calendar['id'], $objectUri);
			}
		}
	}

	/**
	 * @param string $principal
	 * @return array|null
	 * @throws \Sabre\DAV\Exception\BadRequest
	 */
	public function ensureCalendarExists(string $principal):?array {
		$calendar = $this->calDavBackEnd->getCalendarByUri($principal, self::BIRTHDAY_CALENDAR_URI);
		if (!is_null($calendar)) {
			return $calendar;
		}
		$this->calDavBackEnd->createCalendar($principal, self::BIRTHDAY_CALENDAR_URI, [
			'{DAV:}displayname' => 'Contact birthdays',
			'{http://apple.com/ns/ical/}calendar-color' => '#E9D859',
			'components' => 'VEVENT',
		]);

		return $this->calDavBackEnd->getCalendarByUri($principal, self::BIRTHDAY_CALENDAR_URI);
	}

	/**
	 * @param $cardData
	 * @param $dateField
	 * @param $postfix
	 * @return VCalendar|null
	 * @throws InvalidDataException
	 */
	public function buildDateFromContact(string $cardData,
										 string $dateField,
										 string $postfix):?VCalendar {
		if (empty($cardData)) {
			return null;
		}
		try {
			$doc = Reader::read($cardData);
			// We're always converting to vCard 4.0 so we can rely on the
			// VCardConverter handling the X-APPLE-OMIT-YEAR property for us.
			if (!$doc instanceof VCard) {
				return null;
			}
			$doc = $doc->convert(Document::VCARD40);
		} catch (Exception $e) {
			return null;
		}

		if (!isset($doc->{$dateField})) {
			return null;
		}
		if (!isset($doc->FN)) {
			return null;
		}
		$birthday = $doc->{$dateField};
		if (!(string)$birthday) {
			return null;
		}
		// Skip if the BDAY property is not of the right type.
		if (!$birthday instanceof DateAndOrTime) {
			return null;
		}

		// Skip if we can't parse the BDAY value.
		try {
			$dateParts = DateTimeParser::parseVCardDateTime($birthday->getValue());
		} catch (InvalidDataException $e) {
			return null;
		}

		$unknownYear = false;
		$originalYear = null;
		if (!$dateParts['year']) {
			$birthday = '1970-' . $dateParts['month'] . '-' . $dateParts['date'];

			$unknownYear = true;
		} else {
			$parameters = $birthday->parameters();
			if (isset($parameters['X-APPLE-OMIT-YEAR'])) {
				$omitYear = $parameters['X-APPLE-OMIT-YEAR'];
				if ($dateParts['year'] === $omitYear) {
					$birthday = '1970-' . $dateParts['month'] . '-' . $dateParts['date'];
					$unknownYear = true;
				}
			} else {
				$originalYear = (int)$dateParts['year'];
				// 'X-APPLE-OMIT-YEAR' is not always present, at least iOS 12.4 uses the hard coded date of 1604 (the start of the gregorian calendar) when the year is unknown
				if ($originalYear == 1604) {
					$originalYear = null;
					$unknownYear = true;
					$birthday = '1970-' . $dateParts['month'] . '-' . $dateParts['date'];
				}
				if ($originalYear < 1970) {
					$birthday = '1970-' . $dateParts['month'] . '-' . $dateParts['date'];
				}
			}
		}

		try {
			if ($birthday instanceof DateAndOrTime) {
				$date = $birthday->getDateTime();
			} else {
				$date = new \DateTimeImmutable($birthday);
			}
		} catch (Exception $e) {
			return null;
		}

		$summary = $this->formatTitle($dateField, $doc->FN->getValue(), $originalYear, $this->dbConnection->supports4ByteText());

		$vCal = new VCalendar();
		$vCal->VERSION = '2.0';
		$vCal->PRODID = '-//IDN nextcloud.com//Birthday calendar//EN';
		$vEvent = $vCal->createComponent('VEVENT');
		$vEvent->add('DTSTART');
		$vEvent->DTSTART->setDateTime(
			$date
		);
		$vEvent->DTSTART['VALUE'] = 'DATE';
		$vEvent->add('DTEND');

		$dtEndDate = (new \DateTime())->setTimestamp($date->getTimeStamp());
		$dtEndDate->add(new \DateInterval('P1D'));
		$vEvent->DTEND->setDateTime(
			$dtEndDate
		);

		$vEvent->DTEND['VALUE'] = 'DATE';
		$vEvent->{'UID'} = $doc->UID . $postfix;
		$vEvent->{'RRULE'} = 'FREQ=YEARLY';
		$vEvent->{'SUMMARY'} = $summary;
		$vEvent->{'TRANSP'} = 'TRANSPARENT';
		$vEvent->{'X-NEXTCLOUD-BC-FIELD-TYPE'} = $dateField;
		$vEvent->{'X-NEXTCLOUD-BC-UNKNOWN-YEAR'} = $unknownYear ? '1' : '0';
		if ($originalYear !== null) {
			$vEvent->{'X-NEXTCLOUD-BC-YEAR'} = (string) $originalYear;
		}
		$alarm = $vCal->createComponent('VALARM');
		$alarm->add($vCal->createProperty('TRIGGER', '-PT0M', ['VALUE' => 'DURATION']));
		$alarm->add($vCal->createProperty('ACTION', 'DISPLAY'));
		$alarm->add($vCal->createProperty('DESCRIPTION', $vEvent->{'SUMMARY'}));
		$vEvent->add($alarm);
		$vCal->add($vEvent);
		return $vCal;
	}

	/**
	 * @param string $user
	 */
	public function resetForUser(string $user):void {
		$principal = 'principals/users/'.$user;
		$calendar = $this->calDavBackEnd->getCalendarByUri($principal, self::BIRTHDAY_CALENDAR_URI);
		$calendarObjects = $this->calDavBackEnd->getCalendarObjects($calendar['id'], CalDavBackend::CALENDAR_TYPE_CALENDAR);

		foreach ($calendarObjects as $calendarObject) {
			$this->calDavBackEnd->deleteCalendarObject($calendar['id'], $calendarObject['uri'], CalDavBackend::CALENDAR_TYPE_CALENDAR);
		}
	}

	/**
	 * @param string $user
	 * @throws \Sabre\DAV\Exception\BadRequest
	 */
	public function syncUser(string $user):void {
		$principal = 'principals/users/'.$user;
		$this->ensureCalendarExists($principal);
		$books = $this->cardDavBackEnd->getAddressBooksForUser($principal);
		foreach ($books as $book) {
			$cards = $this->cardDavBackEnd->getCards($book['id']);
			foreach ($cards as $card) {
				$this->onCardChanged((int) $book['id'], $card['uri'], $card['carddata']);
			}
		}
	}

	/**
	 * @param string $existingCalendarData
	 * @param VCalendar $newCalendarData
	 * @return bool
	 */
	public function birthdayEvenChanged(string $existingCalendarData,
										VCalendar $newCalendarData):bool {
		try {
			$existingBirthday = Reader::read($existingCalendarData);
		} catch (Exception $ex) {
			return true;
		}

		return (
			$newCalendarData->VEVENT->DTSTART->getValue() !== $existingBirthday->VEVENT->DTSTART->getValue() ||
			$newCalendarData->VEVENT->SUMMARY->getValue() !== $existingBirthday->VEVENT->SUMMARY->getValue()
		);
	}

	/**
	 * @param integer $addressBookId
	 * @return mixed
	 */
	protected function getAllAffectedPrincipals(int $addressBookId) {
		$targetPrincipals = [];
		$shares = $this->cardDavBackEnd->getShares($addressBookId);
		foreach ($shares as $share) {
			if ($share['{http://owncloud.org/ns}group-share']) {
				$users = $this->principalBackend->getGroupMemberSet($share['{http://owncloud.org/ns}principal']);
				foreach ($users as $user) {
					$targetPrincipals[] = $user['uri'];
				}
			} else {
				$targetPrincipals[] = $share['{http://owncloud.org/ns}principal'];
			}
		}
		return array_values(array_unique($targetPrincipals, SORT_STRING));
	}

	/**
	 * @param string $cardUri
	 * @param string $cardData
	 * @param array $book
	 * @param int $calendarId
	 * @param array $type
	 * @throws InvalidDataException
	 * @throws \Sabre\DAV\Exception\BadRequest
	 */
	private function updateCalendar(string $cardUri,
									string $cardData,
									array $book,
									int $calendarId,
									array $type):void {
		$objectUri = $book['uri'] . '-' . $cardUri . $type['postfix'] . '.ics';
		$calendarData = $this->buildDateFromContact($cardData, $type['field'], $type['postfix']);
		$existing = $this->calDavBackEnd->getCalendarObject($calendarId, $objectUri);
		if (is_null($calendarData)) {
			if (!is_null($existing)) {
				$this->calDavBackEnd->deleteCalendarObject($calendarId, $objectUri);
			}
		} else {
			if (is_null($existing)) {
				$this->calDavBackEnd->createCalendarObject($calendarId, $objectUri, $calendarData->serialize());
			} else {
				if ($this->birthdayEvenChanged($existing['calendardata'], $calendarData)) {
					$this->calDavBackEnd->updateCalendarObject($calendarId, $objectUri, $calendarData->serialize());
				}
			}
		}
	}

	/**
	 * checks if the admin opted-out of birthday calendars
	 *
	 * @return bool
	 */
	private function isGloballyEnabled():bool {
		return $this->config->getAppValue('dav', 'generateBirthdayCalendar', 'yes') === 'yes';
	}

	/**
	 * Checks if the user opted-out of birthday calendars
	 *
	 * @param string $userPrincipal The user principal to check for
	 * @return bool
	 */
	private function isUserEnabled(string $userPrincipal):bool {
		if (strpos($userPrincipal, 'principals/users/') === 0) {
			$userId = substr($userPrincipal, 17);
			$isEnabled = $this->config->getUserValue($userId, 'dav', 'generateBirthdayCalendar', 'yes');
			return $isEnabled === 'yes';
		}

		// not sure how we got here, just be on the safe side and return true
		return true;
	}

	/**
	 * Formats title of Birthday event
	 *
	 * @param string $field Field name like BDAY, ANNIVERSARY, ...
	 * @param string $name Name of contact
	 * @param int|null $year Year of birth, anniversary, ...
	 * @param bool $supports4Byte Whether or not the database supports 4 byte chars
	 * @return string The formatted title
	 */
	private function formatTitle(string $field,
								 string $name,
								 int $year = null,
								 bool $supports4Byte = true):string {
		if ($supports4Byte) {
			switch ($field) {
				case 'BDAY':
					return implode('', [
						'🎂 ',
						$name,
						$year ? (' (' . $year . ')') : '',
					]);

				case 'DEATHDATE':
					return implode('', [
						$this->l10n->t('Death of %s', [$name]),
						$year ? (' (' . $year . ')') : '',
					]);

				case 'ANNIVERSARY':
					return implode('', [
						'💍 ',
						$name,
						$year ? (' (' . $year . ')') : '',
					]);

				default:
					return '';
			}
		} else {
			switch ($field) {
				case 'BDAY':
					return implode('', [
						$name,
						' ',
						$year ? ('(*' . $year . ')') : '*',
					]);

				case 'DEATHDATE':
					return implode('', [
						$this->l10n->t('Death of %s', [$name]),
						$year ? (' (' . $year . ')') : '',
					]);

				case 'ANNIVERSARY':
					return implode('', [
						$name,
						' ',
						$year ? ('(⚭' . $year . ')') : '⚭',
					]);

				default:
					return '';
			}
		}
	}
}
