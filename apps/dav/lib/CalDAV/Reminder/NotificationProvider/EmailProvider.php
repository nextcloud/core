<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Thomas Citharel
 * @copyright Copyright (c) 2019, Georg Ehrke
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Richard Steinmetz <richard@steinmetz.cloud>
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

namespace OCA\DAV\CalDAV\Reminder\NotificationProvider;

use DateTime;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\L10N\IFactory as L10NFactory;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use Sabre\VObject;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Parameter;
use Sabre\VObject\Property;

/**
 * Class EmailProvider
 *
 * @package OCA\DAV\CalDAV\Reminder\NotificationProvider
 */
class EmailProvider extends AbstractProvider {

	/** @var string */
	public const NOTIFICATION_TYPE = 'EMAIL';

	/** @var IMailer */
	private $mailer;

	/**
	 * @param IConfig $config
	 * @param IMailer $mailer
	 * @param ILogger $logger
	 * @param L10NFactory $l10nFactory
	 * @param IUrlGenerator $urlGenerator
	 */
	public function __construct(IConfig $config,
								IMailer $mailer,
								ILogger $logger,
								L10NFactory $l10nFactory,
								IURLGenerator $urlGenerator) {
		parent::__construct($logger, $l10nFactory, $urlGenerator, $config);
		$this->mailer = $mailer;
	}

	/**
	 * Send out notification via email
	 *
	 * @param VEvent $vevent
	 * @param string $calendarDisplayName
	 * @param array $users
	 * @throws \Exception
	 */
	public function send(VEvent $vevent,
						 string $calendarDisplayName,
						 array $users = []):void {
		$fallbackLanguage = $this->getFallbackLanguage();

		$emailAddressesOfSharees = $this->getEMailAddressesOfAllUsersWithWriteAccessToCalendar($users);
		$emailAddressesOfAttendees = $this->getAllEMailAddressesFromEvent($vevent);

		// Quote from php.net:
		// If the input arrays have the same string keys, then the later value for that key will overwrite the previous one.
		// => if there are duplicate email addresses, it will always take the system value
		$emailAddresses = array_merge(
			$emailAddressesOfAttendees,
			$emailAddressesOfSharees
		);

		$sortedByLanguage = $this->sortEMailAddressesByLanguage($emailAddresses, $fallbackLanguage);
		$organizer = $this->getOrganizerEMailAndNameFromEvent($vevent);

		foreach ($sortedByLanguage as $lang => $emailAddresses) {
			if (!$this->hasL10NForLang($lang)) {
				$lang = $fallbackLanguage;
			}
			$l10n = $this->getL10NForLang($lang);
			$fromEMail = \OCP\Util::getDefaultEmailAddress('reminders-noreply');

			$template = $this->mailer->createEMailTemplate('dav.calendarReminder');
			$template->addHeader();
			$this->addSubjectAndHeading($template, $l10n, $vevent);
			$this->addBulletList($template, $l10n, $calendarDisplayName, $vevent);
			$template->addFooter();

			foreach ($emailAddresses as $emailAddress) {
				if (!$this->mailer->validateMailAddress($emailAddress)) {
					$this->logger->error('Email address {address} for reminder notification is incorrect', ['app' => 'dav', 'address' => $emailAddress]);
					continue;
				}

				$message = $this->mailer->createMessage();
				$message->setFrom([$fromEMail]);
				if ($organizer) {
					$message->setReplyTo($organizer);
				}
				$message->setTo([$emailAddress]);
				$message->useTemplate($template);

				try {
					$failed = $this->mailer->send($message);
					if ($failed) {
						$this->logger->error('Unable to deliver message to {failed}', ['app' => 'dav', 'failed' => implode(', ', $failed)]);
					}
				} catch (\Exception $ex) {
					$this->logger->logException($ex, ['app' => 'dav']);
				}
			}
		}
	}

	/**
	 * @param IEMailTemplate $template
	 * @param IL10N $l10n
	 * @param VEvent $vevent
	 */
	private function addSubjectAndHeading(IEMailTemplate $template, IL10N $l10n, VEvent $vevent):void {
		$template->setSubject('Notification: ' . $this->getTitleFromVEvent($vevent, $l10n));
		$template->addHeading($this->getTitleFromVEvent($vevent, $l10n));
	}

	/**
	 * @param IEMailTemplate $template
	 * @param IL10N $l10n
	 * @param string $calendarDisplayName
	 * @param array $eventData
	 */
	private function addBulletList(IEMailTemplate $template,
								   IL10N $l10n,
								   string $calendarDisplayName,
								   VEvent $vevent):void {
		$template->addBodyListItem($calendarDisplayName, $l10n->t('Calendar:'),
			$this->getAbsoluteImagePath('actions/info.png'));

		$template->addBodyListItem($this->generateDateString($l10n, $vevent), $l10n->t('Date:'),
			$this->getAbsoluteImagePath('places/calendar.png'));

		if (isset($vevent->LOCATION)) {
			$template->addBodyListItem((string) $vevent->LOCATION, $l10n->t('Where:'),
				$this->getAbsoluteImagePath('actions/address.png'));
		}
		if (isset($vevent->DESCRIPTION)) {
			$template->addBodyListItem((string) $vevent->DESCRIPTION, $l10n->t('Description:'),
				$this->getAbsoluteImagePath('actions/more.png'));
		}
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private function getAbsoluteImagePath(string $path):string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath('core', $path)
		);
	}

	/**
	 * @param VEvent $vevent
	 * @return array|null
	 */
	private function getOrganizerEMailAndNameFromEvent(VEvent $vevent):?array {
		if (!$vevent->ORGANIZER) {
			return null;
		}

		$organizer = $vevent->ORGANIZER;
		if (strcasecmp($organizer->getValue(), 'mailto:') !== 0) {
			return null;
		}

		$organizerEMail = substr($organizer->getValue(), 7);

		if (!$this->mailer->validateMailAddress($organizerEMail)) {
			return null;
		}

		$name = $organizer->offsetGet('CN');
		if ($name instanceof Parameter) {
			return [$organizerEMail => $name];
		}

		return [$organizerEMail];
	}

	/**
	 * @param array $emails
	 * @param string $defaultLanguage
	 * @return array
	 */
	private function sortEMailAddressesByLanguage(array $emails,
												  string $defaultLanguage):array {
		$sortedByLanguage = [];

		foreach ($emails as $emailAddress => $parameters) {
			if (isset($parameters['LANG'])) {
				$lang = $parameters['LANG'];
			} else {
				$lang = $defaultLanguage;
			}

			if (!isset($sortedByLanguage[$lang])) {
				$sortedByLanguage[$lang] = [];
			}

			$sortedByLanguage[$lang][] = $emailAddress;
		}

		return $sortedByLanguage;
	}

	/**
	 * @param VEvent $vevent
	 * @return array
	 */
	private function getAllEMailAddressesFromEvent(VEvent $vevent):array {
		$emailAddresses = [];

		if (isset($vevent->ATTENDEE)) {
			foreach ($vevent->ATTENDEE as $attendee) {
				if (!($attendee instanceof VObject\Property)) {
					continue;
				}

				$cuType = $this->getCUTypeOfAttendee($attendee);
				if (\in_array($cuType, ['RESOURCE', 'ROOM', 'UNKNOWN'])) {
					// Don't send emails to things
					continue;
				}

				$partstat = $this->getPartstatOfAttendee($attendee);
				if ($partstat === 'DECLINED') {
					// Don't send out emails to people who declined
					continue;
				}
				if ($partstat === 'DELEGATED') {
					$delegates = $attendee->offsetGet('DELEGATED-TO');
					if (!($delegates instanceof VObject\Parameter)) {
						continue;
					}

					$emailAddressesOfDelegates = $delegates->getParts();
					foreach ($emailAddressesOfDelegates as $addressesOfDelegate) {
						if (strcasecmp($addressesOfDelegate, 'mailto:') === 0) {
							$emailAddresses[substr($addressesOfDelegate, 7)] = [];
						}
					}

					continue;
				}

				$emailAddressOfAttendee = $this->getEMailAddressOfAttendee($attendee);
				if ($emailAddressOfAttendee !== null) {
					$properties = [];

					$langProp = $attendee->offsetGet('LANG');
					if ($langProp instanceof VObject\Parameter) {
						$properties['LANG'] = $langProp->getValue();
					}

					$emailAddresses[$emailAddressOfAttendee] = $properties;
				}
			}
		}

		if (isset($vevent->ORGANIZER) && $this->hasAttendeeMailURI($vevent->ORGANIZER)) {
			$emailAddresses[$this->getEMailAddressOfAttendee($vevent->ORGANIZER)] = [];
		}

		return $emailAddresses;
	}



	/**
	 * @param VObject\Property $attendee
	 * @return string
	 */
	private function getCUTypeOfAttendee(VObject\Property $attendee):string {
		$cuType = $attendee->offsetGet('CUTYPE');
		if ($cuType instanceof VObject\Parameter) {
			return strtoupper($cuType->getValue());
		}

		return 'INDIVIDUAL';
	}

	/**
	 * @param VObject\Property $attendee
	 * @return string
	 */
	private function getPartstatOfAttendee(VObject\Property $attendee):string {
		$partstat = $attendee->offsetGet('PARTSTAT');
		if ($partstat instanceof VObject\Parameter) {
			return strtoupper($partstat->getValue());
		}

		return 'NEEDS-ACTION';
	}

	/**
	 * @param VObject\Property $attendee
	 * @return bool
	 */
	private function hasAttendeeMailURI(VObject\Property $attendee):bool {
		return stripos($attendee->getValue(), 'mailto:') === 0;
	}

	/**
	 * @param VObject\Property $attendee
	 * @return string|null
	 */
	private function getEMailAddressOfAttendee(VObject\Property $attendee):?string {
		if (!$this->hasAttendeeMailURI($attendee)) {
			return null;
		}

		return substr($attendee->getValue(), 7);
	}

	/**
	 * @param array $users
	 * @return array
	 */
	private function getEMailAddressesOfAllUsersWithWriteAccessToCalendar(array $users):array {
		$emailAddresses = [];

		foreach ($users as $user) {
			$emailAddress = $user->getEMailAddress();
			if ($emailAddress) {
				$lang = $this->l10nFactory->getUserLanguage($user);
				if ($lang) {
					$emailAddresses[$emailAddress] = [
						'LANG' => $lang,
					];
				} else {
					$emailAddresses[$emailAddress] = [];
				}
			}
		}

		return $emailAddresses;
	}

	/**
	 * @param IL10N $l10n
	 * @param VEvent $vevent
	 * @return string
	 * @throws \Exception
	 */
	private function generateDateString(IL10N $l10n, VEvent $vevent):string {
		$isAllDay = $vevent->DTSTART instanceof Property\ICalendar\Date;

		/** @var Property\ICalendar\Date | Property\ICalendar\DateTime $dtstart */
		/** @var Property\ICalendar\Date | Property\ICalendar\DateTime $dtend */
		/** @var \DateTimeImmutable $dtstartDt */
		$dtstartDt = $vevent->DTSTART->getDateTime();
		/** @var \DateTimeImmutable $dtendDt */
		$dtendDt = $this->getDTEndFromEvent($vevent)->getDateTime();

		$diff = $dtstartDt->diff($dtendDt);

		$dtstartDt = new \DateTime($dtstartDt->format(\DateTime::ATOM));
		$dtendDt = new \DateTime($dtendDt->format(\DateTime::ATOM));

		if ($isAllDay) {
			// One day event
			if ($diff->days === 1) {
				return $this->getDateString($l10n, $dtstartDt);
			}

			return implode(' - ', [
				$this->getDateString($l10n, $dtstartDt),
				$this->getDateString($l10n, $dtendDt),
			]);
		}

		$startTimezone = $endTimezone = null;
		if (!$vevent->DTSTART->isFloating()) {
			$startTimezone = $vevent->DTSTART->getDateTime()->getTimezone()->getName();
			$endTimezone = $this->getDTEndFromEvent($vevent)->getDateTime()->getTimezone()->getName();
		}

		$localeStart = implode(', ', [
			$this->getWeekDayName($l10n, $dtstartDt),
			$this->getDateTimeString($l10n, $dtstartDt)
		]);

		// always show full date with timezone if timezones are different
		if ($startTimezone !== $endTimezone) {
			$localeEnd = implode(', ', [
				$this->getWeekDayName($l10n, $dtendDt),
				$this->getDateTimeString($l10n, $dtendDt)
			]);

			return $localeStart
				. ' (' . $startTimezone . ') '
				. ' - '
				. $localeEnd
				. ' (' . $endTimezone . ')';
		}

		// Show only the time if the day is the same
		$localeEnd = $this->isDayEqual($dtstartDt, $dtendDt)
			? $this->getTimeString($l10n, $dtendDt)
			: implode(', ', [
				$this->getWeekDayName($l10n, $dtendDt),
				$this->getDateTimeString($l10n, $dtendDt)
			]);

		return $localeStart
			. ' - '
			. $localeEnd
			. ' (' . $startTimezone . ')';
	}

	/**
	 * @param DateTime $dtStart
	 * @param DateTime $dtEnd
	 * @return bool
	 */
	private function isDayEqual(DateTime $dtStart,
								DateTime $dtEnd):bool {
		return $dtStart->format('Y-m-d') === $dtEnd->format('Y-m-d');
	}

	/**
	 * @param IL10N $l10n
	 * @param DateTime $dt
	 * @return string
	 */
	private function getWeekDayName(IL10N $l10n, DateTime $dt):string {
		return $l10n->l('weekdayName', $dt, ['width' => 'abbreviated']);
	}

	/**
	 * @param IL10N $l10n
	 * @param DateTime $dt
	 * @return string
	 */
	private function getDateString(IL10N $l10n, DateTime $dt):string {
		return $l10n->l('date', $dt, ['width' => 'medium']);
	}

	/**
	 * @param IL10N $l10n
	 * @param DateTime $dt
	 * @return string
	 */
	private function getDateTimeString(IL10N $l10n, DateTime $dt):string {
		return $l10n->l('datetime', $dt, ['width' => 'medium|short']);
	}

	/**
	 * @param IL10N $l10n
	 * @param DateTime $dt
	 * @return string
	 */
	private function getTimeString(IL10N $l10n, DateTime $dt):string {
		return $l10n->l('time', $dt, ['width' => 'short']);
	}

	/**
	 * @param VEvent $vevent
	 * @param IL10N $l10n
	 * @return string
	 */
	private function getTitleFromVEvent(VEvent $vevent, IL10N $l10n):string {
		if (isset($vevent->SUMMARY)) {
			return (string)$vevent->SUMMARY;
		}

		return $l10n->t('Untitled event');
	}
}
