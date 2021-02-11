<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2017, Georg Ehrke
 *
 * @author brad2014 <brad2014@users.noreply.github.com>
 * @author Brad Rubenstein <brad@wbr.tech>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Leon Klingele <leon@struktur.de>
 * @author rakekniven <mark.ziegler@rakekniven.de>
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

namespace OCA\DAV\CalDAV\Schedule;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Defaults;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory as L10NFactory;
use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\Security\ISecureRandom;
use OCP\Util;
use Sabre\CalDAV\Schedule\IMipPlugin as SabreIMipPlugin;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\ITip\Message;
use Sabre\VObject\Parameter;
use Sabre\VObject\Property;
use Sabre\VObject\Recur\EventIterator;

/**
 * iMIP handler.
 *
 * This class is responsible for sending out iMIP messages. iMIP is the
 * email-based transport for iTIP. iTIP deals with scheduling operations for
 * iCalendar objects.
 *
 * If you want to customize the email that gets sent out, you can do so by
 * extending this class and overriding the sendMessage method.
 *
 * @copyright Copyright (C) 2007-2015 fruux GmbH (https://fruux.com/).
 * @author Evert Pot (http://evertpot.com/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class IMipPlugin extends SabreIMipPlugin {

	/** @var string */
	private $userId;

	/** @var IConfig */
	private $config;

	/** @var IMailer */
	private $mailer;

	/** @var ILogger */
	private $logger;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var L10NFactory */
	private $l10nFactory;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var ISecureRandom */
	private $random;

	/** @var IDBConnection */
	private $db;

	/** @var Defaults */
	private $defaults;

	/** @var IUserManager */
	private $userManager;

	public const MAX_DATE = '2038-01-01';

	public const METHOD_REQUEST = 'request';
	public const METHOD_REPLY = 'reply';
	public const METHOD_CANCEL = 'cancel';
	public const IMIP_INDENT = 15; // Enough for the length of all body bullet items, in all languages

	/**
	 * @param IConfig $config
	 * @param IMailer $mailer
	 * @param ILogger $logger
	 * @param ITimeFactory $timeFactory
	 * @param L10NFactory $l10nFactory
	 * @param IUrlGenerator $urlGenerator
	 * @param Defaults $defaults
	 * @param ISecureRandom $random
	 * @param IDBConnection $db
	 * @param string $userId
	 */
	public function __construct(IConfig $config, IMailer $mailer, ILogger $logger,
								ITimeFactory $timeFactory, L10NFactory $l10nFactory,
								IURLGenerator $urlGenerator, Defaults $defaults,
								ISecureRandom $random, IDBConnection $db, IUserManager $userManager,
								$userId) {
		parent::__construct('');
		$this->userId = $userId;
		$this->config = $config;
		$this->mailer = $mailer;
		$this->logger = $logger;
		$this->timeFactory = $timeFactory;
		$this->l10nFactory = $l10nFactory;
		$this->urlGenerator = $urlGenerator;
		$this->random = $random;
		$this->db = $db;
		$this->defaults = $defaults;
		$this->userManager = $userManager;
	}

	/**
	 * Event handler for the 'schedule' event.
	 *
	 * @param Message $iTipMessage
	 * @return void
	 */
	public function schedule(Message $iTipMessage) {

		// Not sending any emails if the system considers the update
		// insignificant.
		if (!$iTipMessage->significantChange) {
			if (!$iTipMessage->scheduleStatus) {
				$iTipMessage->scheduleStatus = '1.0;We got the message, but it\'s not significant enough to warrant an email';
			}
			return;
		}

		$summary = $iTipMessage->message->VEVENT->SUMMARY;

		if (parse_url($iTipMessage->sender, PHP_URL_SCHEME) !== 'mailto') {
			return;
		}

		if (parse_url($iTipMessage->recipient, PHP_URL_SCHEME) !== 'mailto') {
			return;
		}

		// don't send out mails for events that already took place
		$lastOccurrence = $this->getLastOccurrence($iTipMessage->message);
		$currentTime = $this->timeFactory->getTime();
		if ($lastOccurrence < $currentTime) {
			return;
		}

		// Strip off mailto:
		$sender = substr($iTipMessage->sender, 7);
		$recipient = substr($iTipMessage->recipient, 7);

		$senderName = $iTipMessage->senderName ?: null;
		$recipientName = $iTipMessage->recipientName ?: null;

		if ($senderName === null || empty(trim($senderName))) {
			$user = $this->userManager->get($this->userId);
			if ($user) {
				// getDisplayName automatically uses the uid
				// if no display-name is set
				$senderName = $user->getDisplayName();
			}
		}

		/** @var VEvent $vevent */
		$vevent = $iTipMessage->message->VEVENT;

		$attendee = $this->getCurrentAttendee($iTipMessage);
		$defaultLang = $this->l10nFactory->findLanguage();
		$lang = $this->getAttendeeLangOrDefault($defaultLang, $attendee);
		$l10n = $this->l10nFactory->get('dav', $lang);

		$meetingAttendeeName = $recipientName ?: $recipient;
		$meetingInviteeName = $senderName ?: $sender;

		$meetingTitle = $vevent->SUMMARY;
		$meetingDescription = $vevent->DESCRIPTION;


		$meetingUrl = $vevent->URL;
		$meetingLocation = $vevent->LOCATION;

		$defaultVal = '--';

		$method = self::METHOD_REQUEST;
		switch (strtolower($iTipMessage->method)) {
			case self::METHOD_REPLY:
				$method = self::METHOD_REPLY;
				break;
			case self::METHOD_CANCEL:
				$method = self::METHOD_CANCEL;
				break;
		}

		$data = [
			'attendee_name' => (string)$meetingAttendeeName ?: $defaultVal,
			'invitee_name' => (string)$meetingInviteeName ?: $defaultVal,
			'meeting_title' => (string)$meetingTitle ?: $defaultVal,
			'meeting_description' => (string)$meetingDescription ?: $defaultVal,
			'meeting_url' => (string)$meetingUrl ?: $defaultVal,
		];

		$fromEMail = Util::getDefaultEmailAddress('invitations-noreply');
		$fromName = $l10n->t('%1$s via %2$s', [$senderName, $this->defaults->getName()]);

		$message = $this->mailer->createMessage()
			->setFrom([$fromEMail => $fromName])
			->setReplyTo([$sender => $senderName])
			->setTo([$recipient => $recipientName]);

		$template = $this->mailer->createEMailTemplate('dav.calendarInvite.' . $method, $data);
		$template->addHeader();

		$summary = ((string) $summary !== '') ? (string) $summary : $l10n->t('Untitled event');

		$this->addSubjectAndHeading($template, $l10n, $method, $summary);
		$this->addBulletList($template, $l10n, $vevent);


		// Only add response buttons to invitation requests: Fix Issue #11230
		if (($method == self::METHOD_REQUEST) && $this->getAttendeeRSVP($attendee)) {

			/*
			** Only offer invitation accept/reject buttons, which link back to the
			** nextcloud server, to recipients who can access the nextcloud server via
			** their internet/intranet.  Issue #12156
			**
			** The app setting is stored in the appconfig database table.
			**
			** For nextcloud servers accessible to the public internet, the default
			** "invitation_link_recipients" value "yes" (all recipients) is appropriate.
			**
			** When the nextcloud server is restricted behind a firewall, accessible
			** only via an internal network or via vpn, you can set "dav.invitation_link_recipients"
			** to the email address or email domain, or comma separated list of addresses or domains,
			** of recipients who can access the server.
			**
			** To always deliver URLs, set invitation_link_recipients to "yes".
			** To suppress URLs entirely, set invitation_link_recipients to boolean "no".
			*/

			$recipientDomain = substr(strrchr($recipient, "@"), 1);
			$invitationLinkRecipients = explode(',', preg_replace('/\s+/', '', strtolower($this->config->getAppValue('dav', 'invitation_link_recipients', 'yes'))));

			if (strcmp('yes', $invitationLinkRecipients[0]) === 0
				 || in_array(strtolower($recipient), $invitationLinkRecipients)
				 || in_array(strtolower($recipientDomain), $invitationLinkRecipients)) {
				$this->addResponseButtons($template, $l10n, $iTipMessage, $lastOccurrence);
			}
		}

		$template->addFooter();

		$message->useTemplate($template);

		$attachment = $this->mailer->createAttachment(
			$iTipMessage->message->serialize(),
			'event.ics',// TODO(leon): Make file name unique, e.g. add event id
			'text/calendar; method=' . $iTipMessage->method
		);
		$message->attach($attachment);

		try {
			$failed = $this->mailer->send($message);
			$iTipMessage->scheduleStatus = '1.1; Scheduling message is sent via iMip';
			if ($failed) {
				$this->logger->error('Unable to deliver message to {failed}', ['app' => 'dav', 'failed' => implode(', ', $failed)]);
				$iTipMessage->scheduleStatus = '5.0; EMail delivery failed';
			}
		} catch (\Exception $ex) {
			$this->logger->logException($ex, ['app' => 'dav']);
			$iTipMessage->scheduleStatus = '5.0; EMail delivery failed';
		}
	}

	/**
	 * check if event took place in the past already
	 * @param VCalendar $vObject
	 * @return int
	 */
	private function getLastOccurrence(VCalendar $vObject) {
		/** @var VEvent $component */
		$component = $vObject->VEVENT;

		$firstOccurrence = $component->DTSTART->getDateTime()->getTimeStamp();
		// Finding the last occurrence is a bit harder
		if (!isset($component->RRULE)) {
			if (isset($component->DTEND)) {
				$lastOccurrence = $component->DTEND->getDateTime()->getTimeStamp();
			} elseif (isset($component->DURATION)) {
				/** @var \DateTime $endDate */
				$endDate = clone $component->DTSTART->getDateTime();
				// $component->DTEND->getDateTime() returns DateTimeImmutable
				$endDate = $endDate->add(DateTimeParser::parse($component->DURATION->getValue()));
				$lastOccurrence = $endDate->getTimestamp();
			} elseif (!$component->DTSTART->hasTime()) {
				/** @var \DateTime $endDate */
				$endDate = clone $component->DTSTART->getDateTime();
				// $component->DTSTART->getDateTime() returns DateTimeImmutable
				$endDate = $endDate->modify('+1 day');
				$lastOccurrence = $endDate->getTimestamp();
			} else {
				$lastOccurrence = $firstOccurrence;
			}
		} else {
			$it = new EventIterator($vObject, (string)$component->UID);
			$maxDate = new \DateTime(self::MAX_DATE);
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

		return $lastOccurrence;
	}

	/**
	 * @param Message $iTipMessage
	 * @return null|Property
	 */
	private function getCurrentAttendee(Message $iTipMessage) {
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
	 * @param string $default
	 * @param Property|null $attendee
	 * @return string
	 */
	private function getAttendeeLangOrDefault($default, Property $attendee = null) {
		if ($attendee !== null) {
			$lang = $attendee->offsetGet('LANGUAGE');
			if ($lang instanceof Parameter) {
				return $lang->getValue();
			}
		}
		return $default;
	}

	/**
	 * @param Property|null $attendee
	 * @return bool
	 */
	private function getAttendeeRSVP(Property $attendee = null) {
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
	 * @param IL10N $l10n
	 * @param VEvent $vevent
	 */
	private function generateWhenString(IL10N $l10n, VEvent $vevent) {
		$dtstart = $vevent->DTSTART;
		if (isset($vevent->DTEND)) {
			$dtend = $vevent->DTEND;
		} elseif (isset($vevent->DURATION)) {
			$isFloating = $vevent->DTSTART->isFloating();
			$dtend = clone $vevent->DTSTART;
			$endDateTime = $dtend->getDateTime();
			$endDateTime = $endDateTime->add(DateTimeParser::parse($vevent->DURATION->getValue()));
			$dtend->setDateTime($endDateTime, $isFloating);
		} elseif (!$vevent->DTSTART->hasTime()) {
			$isFloating = $vevent->DTSTART->isFloating();
			$dtend = clone $vevent->DTSTART;
			$endDateTime = $dtend->getDateTime();
			$endDateTime = $endDateTime->modify('+1 day');
			$dtend->setDateTime($endDateTime, $isFloating);
		} else {
			$dtend = clone $vevent->DTSTART;
		}

		$isAllDay = $dtstart instanceof Property\ICalendar\Date;

		/** @var Property\ICalendar\Date | Property\ICalendar\DateTime $dtstart */
		/** @var Property\ICalendar\Date | Property\ICalendar\DateTime $dtend */
		/** @var \DateTimeImmutable $dtstartDt */
		$dtstartDt = $dtstart->getDateTime();
		/** @var \DateTimeImmutable $dtendDt */
		$dtendDt = $dtend->getDateTime();

		$diff = $dtstartDt->diff($dtendDt);

		$dtstartDt = new \DateTime($dtstartDt->format(\DateTime::ATOM));
		$dtendDt = new \DateTime($dtendDt->format(\DateTime::ATOM));

		if ($isAllDay) {
			// One day event
			if ($diff->days === 1) {
				return $l10n->l('date', $dtstartDt, ['width' => 'medium']);
			}

			// DTEND is exclusive, so if the ics data says 2020-01-01 to 2020-01-05,
			// the email should show 2020-01-01 to 2020-01-04.
			$dtendDt->modify('-1 day');

			//event that spans over multiple days
			$localeStart = $l10n->l('date', $dtstartDt, ['width' => 'medium']);
			$localeEnd = $l10n->l('date', $dtendDt, ['width' => 'medium']);

			return $localeStart . ' - ' . $localeEnd;
		}

		/** @var Property\ICalendar\DateTime $dtstart */
		/** @var Property\ICalendar\DateTime $dtend */
		$isFloating = $dtstart->isFloating();
		$startTimezone = $endTimezone = null;
		if (!$isFloating) {
			$prop = $dtstart->offsetGet('TZID');
			if ($prop instanceof Parameter) {
				$startTimezone = $prop->getValue();
			}

			$prop = $dtend->offsetGet('TZID');
			if ($prop instanceof Parameter) {
				$endTimezone = $prop->getValue();
			}
		}

		$localeStart = $l10n->l('weekdayName', $dtstartDt, ['width' => 'abbreviated']) . ', ' .
			$l10n->l('datetime', $dtstartDt, ['width' => 'medium|short']);

		// always show full date with timezone if timezones are different
		if ($startTimezone !== $endTimezone) {
			$localeEnd = $l10n->l('datetime', $dtendDt, ['width' => 'medium|short']);

			return $localeStart . ' (' . $startTimezone . ') - ' .
				$localeEnd . ' (' . $endTimezone . ')';
		}

		// show only end time if date is the same
		if ($this->isDayEqual($dtstartDt, $dtendDt)) {
			$localeEnd = $l10n->l('time', $dtendDt, ['width' => 'short']);
		} else {
			$localeEnd = $l10n->l('weekdayName', $dtendDt, ['width' => 'abbreviated']) . ', ' .
				$l10n->l('datetime', $dtendDt, ['width' => 'medium|short']);
		}

		return  $localeStart . ' - ' . $localeEnd . ' (' . $startTimezone . ')';
	}

	/**
	 * @param \DateTime $dtStart
	 * @param \DateTime $dtEnd
	 * @return bool
	 */
	private function isDayEqual(\DateTime $dtStart, \DateTime $dtEnd) {
		return $dtStart->format('Y-m-d') === $dtEnd->format('Y-m-d');
	}

	/**
	 * @param IEMailTemplate $template
	 * @param IL10N $l10n
	 * @param string $method
	 * @param string $summary
	 */
	private function addSubjectAndHeading(IEMailTemplate $template, IL10N $l10n,
										  $method, $summary) {
		if ($method === self::METHOD_CANCEL) {
			$template->setSubject('Canceled: ' . $summary);
			$template->addHeading($l10n->t('Invitation canceled'));
		} elseif ($method === self::METHOD_REPLY) {
			$template->setSubject('Re: ' . $summary);
			$template->addHeading($l10n->t('Invitation updated'));
		} else {
			$template->setSubject('Invitation: ' . $summary);
			$template->addHeading($l10n->t('Invitation'));
		}
	}

	/**
	 * @param IEMailTemplate $template
	 * @param IL10N $l10n
	 * @param VEVENT $vevent
	 */
	private function addBulletList(IEMailTemplate $template, IL10N $l10n, $vevent) {
		if ($vevent->SUMMARY) {
			$template->addBodyListItem($vevent->SUMMARY, $l10n->t('Title:'),
				$this->getAbsoluteImagePath('caldav/title.svg'),'','',self::IMIP_INDENT);
		}
		$meetingWhen = $this->generateWhenString($l10n, $vevent);
		if ($meetingWhen) {
			$template->addBodyListItem($meetingWhen, $l10n->t('Time:'),
				$this->getAbsoluteImagePath('caldav/time.svg'),'','',self::IMIP_INDENT);
		}
		if ($vevent->LOCATION) {
			$template->addBodyListItem($vevent->LOCATION, $l10n->t('Location:'),
				$this->getAbsoluteImagePath('caldav/location.svg'),'','',self::IMIP_INDENT);
		}
		if ($vevent->URL) {
			$url = $vevent->URL->getValue();
			$template->addBodyListItem(sprintf('<a href="%s">%s</a>',
					htmlspecialchars($url),
					htmlspecialchars($url)),
				$l10n->t('Link:'),
				$this->getAbsoluteImagePath('caldav/link.svg'),
				$url,'',self::IMIP_INDENT);
		}

		$this->addAttendees($template, $l10n, $vevent);

		/* Put description last, like an email body, since it can be arbitrarily long */
		if ($vevent->DESCRIPTION) {
			$template->addBodyListItem($vevent->DESCRIPTION->getValue(), $l10n->t('Description:'),
				$this->getAbsoluteImagePath('caldav/description.svg'),'','',self::IMIP_INDENT);
		}
	}

	/**
	 * addAttendees: add organizer and attendee names/emails to iMip mail.
	 *
	 * Enable with DAV setting: invitation_list_attendees (default: no)
	 *
	 * The default is 'no', which matches old behavior, and is privacy preserving.
	 *
	 * To enable including attendees in invitation emails:
	 *   % php occ config:app:set dav invitation_list_attendees --value yes
	 *
	 * @param IEMailTemplate $template
	 * @param IL10N $l10n
	 * @param Message $iTipMessage
	 * @param int $lastOccurrence
	 * @author brad2014 on github.com
	 */

	private function addAttendees(IEMailTemplate $template, IL10N $l10n, VEvent $vevent) {
		if ($this->config->getAppValue('dav', 'invitation_list_attendees', 'no') === 'no') {
			return;
		}

		if (isset($vevent->ORGANIZER)) {
			/** @var Property\ICalendar\CalAddress $organizer */
			$organizer = $vevent->ORGANIZER;
			$organizerURI = $organizer->getNormalizedValue();
			list($scheme,$organizerEmail) = explode(':',$organizerURI,2); # strip off scheme mailto:
			/** @var string|null $organizerName */
			$organizerName = isset($organizer['CN']) ? $organizer['CN'] : null;
			$organizerHTML = sprintf('<a href="%s">%s</a>',
				htmlspecialchars($organizerURI),
				htmlspecialchars($organizerName ?: $organizerEmail));
			$organizerText = sprintf('%s <%s>', $organizerName, $organizerEmail);
			if (isset($organizer['PARTSTAT'])) {
				/** @var Parameter $partstat */
				$partstat = $organizer['PARTSTAT'];
				if (strcasecmp($partstat->getValue(), 'ACCEPTED') === 0) {
					$organizerHTML .= ' ✔︎';
					$organizerText .= ' ✔︎';
				}
			}
			$template->addBodyListItem($organizerHTML, $l10n->t('Organizer:'),
				$this->getAbsoluteImagePath('caldav/organizer.svg'),
				$organizerText,'',self::IMIP_INDENT);
		}

		$attendees = $vevent->select('ATTENDEE');
		if (count($attendees) === 0) {
			return;
		}

		$attendeesHTML = [];
		$attendeesText = [];
		foreach ($attendees as $attendee) {
			$attendeeURI = $attendee->getNormalizedValue();
			list($scheme,$attendeeEmail) = explode(':',$attendeeURI,2); # strip off scheme mailto:
			$attendeeName = isset($attendee['CN']) ? $attendee['CN'] : null;
			$attendeeHTML = sprintf('<a href="%s">%s</a>',
				htmlspecialchars($attendeeURI),
				htmlspecialchars($attendeeName ?: $attendeeEmail));
			$attendeeText = sprintf('%s <%s>', $attendeeName, $attendeeEmail);
			if (isset($attendee['PARTSTAT'])
				&& strcasecmp($attendee['PARTSTAT'], 'ACCEPTED') === 0) {
				$attendeeHTML .= ' ✔︎';
				$attendeeText .= ' ✔︎';
			}
			array_push($attendeesHTML, $attendeeHTML);
			array_push($attendeesText, $attendeeText);
		}

		$template->addBodyListItem(implode('<br/>',$attendeesHTML), $l10n->t('Attendees:'),
			$this->getAbsoluteImagePath('caldav/attendees.svg'),
			implode("\n",$attendeesText),'',self::IMIP_INDENT);
	}

	/**
	 * @param IEMailTemplate $template
	 * @param IL10N $l10n
	 * @param Message $iTipMessage
	 * @param int $lastOccurrence
	 */
	private function addResponseButtons(IEMailTemplate $template, IL10N $l10n,
										Message $iTipMessage, $lastOccurrence) {
		$token = $this->createInvitationToken($iTipMessage, $lastOccurrence);

		$template->addBodyButtonGroup(
			$l10n->t('Accept'),
			$this->urlGenerator->linkToRouteAbsolute('dav.invitation_response.accept', [
				'token' => $token,
			]),
			$l10n->t('Decline'),
			$this->urlGenerator->linkToRouteAbsolute('dav.invitation_response.decline', [
				'token' => $token,
			])
		);

		$moreOptionsURL = $this->urlGenerator->linkToRouteAbsolute('dav.invitation_response.options', [
			'token' => $token,
		]);
		$html = vsprintf('<small><a href="%s">%s</a></small>', [
			$moreOptionsURL, $l10n->t('More options …')
		]);
		$text = $l10n->t('More options at %s', [$moreOptionsURL]);

		$template->addBodyText($html, $text);
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private function getAbsoluteImagePath($path) {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath('core', $path)
		);
	}

	/**
	 * @param Message $iTipMessage
	 * @param int $lastOccurrence
	 * @return string
	 */
	private function createInvitationToken(Message $iTipMessage, $lastOccurrence):string {
		$token = $this->random->generate(60, ISecureRandom::CHAR_UPPER . ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_DIGITS);

		/** @var VEvent $vevent */
		$vevent = $iTipMessage->message->VEVENT;
		$attendee = $iTipMessage->recipient;
		$organizer = $iTipMessage->sender;
		$sequence = $iTipMessage->sequence;
		$recurrenceId = isset($vevent->{'RECURRENCE-ID'}) ?
			$vevent->{'RECURRENCE-ID'}->serialize() : null;
		$uid = $vevent->{'UID'};

		$query = $this->db->getQueryBuilder();
		$query->insert('calendar_invitations')
			->values([
				'token' => $query->createNamedParameter($token),
				'attendee' => $query->createNamedParameter($attendee),
				'organizer' => $query->createNamedParameter($organizer),
				'sequence' => $query->createNamedParameter($sequence),
				'recurrenceid' => $query->createNamedParameter($recurrenceId),
				'expiration' => $query->createNamedParameter($lastOccurrence),
				'uid' => $query->createNamedParameter($uid)
			])
			->execute();

		return $token;
	}
}
