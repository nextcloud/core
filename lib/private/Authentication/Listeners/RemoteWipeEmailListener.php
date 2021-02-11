<?php

declare(strict_types=1);

/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\Authentication\Listeners;

use Exception;
use OC\Authentication\Events\RemoteWipeFinished;
use OC\Authentication\Events\RemoteWipeStarted;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IL10N;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory as IL10nFactory;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use Psr\Log\LoggerInterface;
use function substr;

/**
 * @template-implements IEventListener<\OC\Authentication\Events\ARemoteWipeEvent>
 */
class RemoteWipeEmailListener implements IEventListener {

	/** @var IMailer */
	private $mailer;

	/** @var IUserManager */
	private $userManager;

	/** @var IL10N */
	private $l10n;

	/** @var LoggerInterface */
	private $logger;

	public function __construct(IMailer $mailer,
								IUserManager $userManager,
								IL10nFactory $l10nFactory,
								LoggerInterface $logger) {
		$this->mailer = $mailer;
		$this->userManager = $userManager;
		$this->l10n = $l10nFactory->get('core');
		$this->logger = $logger;
	}

	/**
	 * @param Event $event
	 */
	public function handle(Event $event): void {
		if ($event instanceof RemoteWipeStarted) {
			$uid = $event->getToken()->getUID();
			$user = $this->userManager->get($uid);
			if ($user === null) {
				$this->logger->warning("not sending a wipe started email because user <$uid> does not exist (anymore)");
				return;
			}
			if ($user->getEMailAddress() === null) {
				$this->logger->info("not sending a wipe started email because user <$uid> has no email set");
				return;
			}

			try {
				$this->mailer->send(
					$this->getWipingStartedMessage($event, $user)
				);
			} catch (Exception $e) {
				$this->logger->error("Could not send remote wipe started email to <$uid>", [
					'exception' => $e,
				]);
			}
		} elseif ($event instanceof RemoteWipeFinished) {
			$uid = $event->getToken()->getUID();
			$user = $this->userManager->get($uid);
			if ($user === null) {
				$this->logger->warning("not sending a wipe finished email because user <$uid> does not exist (anymore)");
				return;
			}
			if ($user->getEMailAddress() === null) {
				$this->logger->info("not sending a wipe finished email because user <$uid> has no email set");
				return;
			}

			try {
				$this->mailer->send(
					$this->getWipingFinishedMessage($event, $user)
				);
			} catch (Exception $e) {
				$this->logger->error("Could not send remote wipe finished email to <$uid>", [
					'exception' => $e,
				]);
			}
		}
	}

	private function getWipingStartedMessage(RemoteWipeStarted $event, IUser $user): IMessage {
		$message = $this->mailer->createMessage();
		$emailTemplate = $this->mailer->createEMailTemplate('auth.RemoteWipeStarted');
		$plainHeading = $this->l10n->t('Wiping of device %s has started', [$event->getToken()->getName()]);
		$htmlHeading = $this->l10n->t('Wiping of device »%s« has started', [$event->getToken()->getName()]);
		$emailTemplate->setSubject(
			$this->l10n->t(
				'»%s« started remote wipe',
				[
					substr($event->getToken()->getName(), 0, 15)
				]
			)
		);
		$emailTemplate->addHeader();
		$emailTemplate->addHeading(
			$htmlHeading,
			$plainHeading
		);
		$emailTemplate->addBodyText(
			$this->l10n->t('Device or application »%s« has started the remote wipe process. You will receive another email once the process has finished', [$event->getToken()->getName()])
		);
		$emailTemplate->addFooter();
		$message->setTo([$user->getEMailAddress()]);
		$message->useTemplate($emailTemplate);

		return $message;
	}

	private function getWipingFinishedMessage(RemoteWipeFinished $event, IUser $user): IMessage {
		$message = $this->mailer->createMessage();
		$emailTemplate = $this->mailer->createEMailTemplate('auth.RemoteWipeFinished');
		$plainHeading = $this->l10n->t('Wiping of device %s has finished', [$event->getToken()->getName()]);
		$htmlHeading = $this->l10n->t('Wiping of device »%s« has finished', [$event->getToken()->getName()]);
		$emailTemplate->setSubject(
			$this->l10n->t(
				'»%s« finished remote wipe',
				[
					substr($event->getToken()->getName(), 0, 15)
				]
			)
		);
		$emailTemplate->addHeader();
		$emailTemplate->addHeading(
			$htmlHeading,
			$plainHeading
		);
		$emailTemplate->addBodyText(
			$this->l10n->t('Device or application »%s« has finished the remote wipe process.', [$event->getToken()->getName()])
		);
		$emailTemplate->addFooter();
		$message->setTo([$user->getEMailAddress()]);
		$message->useTemplate($emailTemplate);

		return $message;
	}
}
