<?php

declare(strict_types=1);

/*
 * @copyright 2021 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2021 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\DAV\Listener;

use OCA\DAV\Connector\Sabre\Principal;
use OCA\DAV\Events\CalendarObjectCreatedEvent;
use OCA\DAV\Events\CalendarObjectUpdatedEvent;
use OCA\DAV\Events\CalendarShareUpdatedEvent;
use OCP\Contacts\Events\ContactInteractedWithEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\EventDispatcher\IEventListener;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Parameter;
use Sabre\VObject\Property;
use Sabre\VObject\Reader;
use Throwable;
use function strlen;
use function strpos;
use function substr;

class CalendarContactInteractionListener implements IEventListener {
	private const URI_USERS = 'principals/users/';

	/** @var IEventDispatcher */
	private $dispatcher;

	/** @var IUserSession */
	private $userSession;

	/** @var Principal */
	private $principalConnector;

	/** @var IMailer */
	private $mailer;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(IEventDispatcher $dispatcher,
								IUserSession $userSession,
								Principal $principalConnector,
								IMailer $mailer,
								LoggerInterface $logger) {
		$this->dispatcher = $dispatcher;
		$this->userSession = $userSession;
		$this->principalConnector = $principalConnector;
		$this->mailer = $mailer;
		$this->logger = $logger;
	}

	public function handle(Event $event): void {
		if (($user = $this->userSession->getUser()) === null) {
			// Without user context we can't do anything
			return;
		}

		if ($event instanceof CalendarObjectCreatedEvent || $event instanceof CalendarObjectUpdatedEvent) {
			// users: href => principal:principals/users/admin
			foreach ($event->getShares() as $share) {
				if (!isset($share['href'])) {
					continue;
				}
				$this->emitFromUri($share['href'], $user);
			}

			// emit interaction for email attendees as well
			if (isset($event->getObjectData()['calendardata'])) {
				try {
					$calendar = Reader::read($event->getObjectData()['calendardata']);
					if ($calendar->VEVENT) {
						foreach ($calendar->VEVENT as $calendarEvent) {
							$this->emitFromObject($calendarEvent, $user);
						}
					}
				} catch (Throwable $e) {
					$this->logger->warning('Could not read calendar data for interaction events: ' . $e->getMessage(), [
						'exception' => $e,
					]);
				}
			}
		}

		if ($event instanceof CalendarShareUpdatedEvent && !empty($event->getAdded())) {
			// group: href => principal:principals/groups/admin
			// users: href => principal:principals/users/admin
			foreach ($event->getAdded() as $added) {
				if (!isset($added['href'])) {
					// Nothing to work with
					continue;
				}
				$this->emitFromUri($added['href'], $user);
			}
		}
	}

	private function emitFromUri(string $uri, IUser $user): void {
		$principal = $this->principalConnector->findByUri(
			$uri,
			$this->principalConnector->getPrincipalPrefix()
		);
		if ($principal === null) {
			// Invalid principal
			return;
		}
		if (strpos($principal, self::URI_USERS) !== 0) {
			// Not a user principal
			return;
		}

		$uid = substr($principal, strlen(self::URI_USERS));
		$this->dispatcher->dispatchTyped(
			(new ContactInteractedWithEvent($user))->setUid($uid)
		);
	}

	private function emitFromObject(VEvent $vevent, IUser $user): void {
		if (!$vevent->ATTENDEE) {
			// Nothing left to do
			return;
		}

		foreach ($vevent->ATTENDEE as $attendee) {
			if (!($attendee instanceof Property)) {
				continue;
			}

			$cuType = $attendee->offsetGet('CUTYPE');
			if ($cuType instanceof Parameter && $cuType->getValue() !== 'INDIVIDUAL') {
				// Don't care about those
				continue;
			}

			$mailTo = $attendee->getValue();
			if (strpos($mailTo, 'mailto:') !== 0) {
				// Doesn't look like an email
				continue;
			}
			$email = substr($mailTo, strlen('mailto:'));
			if (!$this->mailer->validateMailAddress($email)) {
				// This really isn't a valid email
				continue;
			}

			$this->dispatcher->dispatchTyped(
				(new ContactInteractedWithEvent($user))->setEmail($email)
			);
		}
	}
}
