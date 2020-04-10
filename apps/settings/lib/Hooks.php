<?php
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\Settings;

use OCA\Settings\Activity\GroupProvider;
use OCA\Settings\Activity\Provider;
use OCP\Activity\IManager as IActivityManager;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;

class Hooks {

	/** @var IActivityManager */
	protected $activityManager;
	/** @var IGroupManager|\OC\Group\Manager */
	protected $groupManager;
	/** @var IUserManager */
	protected $userManager;
	/** @var IUserSession */
	protected $userSession;
	/** @var IURLGenerator */
	protected $urlGenerator;
	/** @var IMailer */
	protected $mailer;
	/** @var IConfig */
	protected $config;
	/** @var IFactory */
	protected $languageFactory;
	/** @var IL10N */
	protected $l;

	public function __construct(IActivityManager $activityManager,
								IGroupManager $groupManager,
								IUserManager $userManager,
								IUserSession $userSession,
								IURLGenerator $urlGenerator,
								IMailer $mailer,
								IConfig $config,
								IFactory $languageFactory,
								IL10N $l) {
		$this->activityManager = $activityManager;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
		$this->userSession = $userSession;
		$this->urlGenerator = $urlGenerator;
		$this->mailer = $mailer;
		$this->config = $config;
		$this->languageFactory = $languageFactory;
		$this->l = $l;
	}

	/**
	 * @param string $uid
	 * @throws \InvalidArgumentException
	 * @throws \BadMethodCallException
	 * @throws \Exception
	 */
	public function onChangePassword($uid) {
		$user = $this->userManager->get($uid);

		if (!$user instanceof IUser || $user->getLastLogin() === 0) {
			// User didn't login, so don't create activities and emails.
			return;
		}

		$event = $this->activityManager->generateEvent();
		$event->setApp('settings')
			->setType('personal_settings')
			->setAffectedUser($user->getUID());

		$instanceUrl = $this->urlGenerator->getAbsoluteURL('/');

		$actor = $this->userSession->getUser();
		if ($actor instanceof IUser) {
			if ($actor->getUID() !== $user->getUID()) {
				$this->l = $this->languageFactory->get(
					'settings',
					$this->config->getUserValue(
						$user->getUID(), 'core', 'lang',
						$this->config->getSystemValue('default_language', 'en')
					)
				);

				$text = $this->l->t('%1$s changed your password on %2$s.', [$actor->getDisplayName(), $instanceUrl]);
				$event->setAuthor($actor->getUID())
					->setSubject(Provider::PASSWORD_CHANGED_BY, [$actor->getUID()]);
			} else {
				$text = $this->l->t('Your password on %s was changed.', [$instanceUrl]);
				$event->setAuthor($actor->getUID())
					->setSubject(Provider::PASSWORD_CHANGED_SELF);
			}
		} else {
			$text = $this->l->t('Your password on %s was reset by an administrator.', [$instanceUrl]);
			$event->setSubject(Provider::PASSWORD_RESET);
		}

		$this->activityManager->publish($event);

		if ($user->getEMailAddress() !== null) {
			$template = $this->mailer->createEMailTemplate('settings.PasswordChanged', [
				'displayname' => $user->getDisplayName(),
				'emailAddress' => $user->getEMailAddress(),
				'instanceUrl' => $instanceUrl,
			]);

			$template->setSubject($this->l->t('Password for %1$s changed on %2$s', [$user->getDisplayName(), $instanceUrl]));
			$template->addHeader();
			$template->addHeading($this->l->t('Password changed for %s', [$user->getDisplayName()]), false);
			$template->addBodyText($text . ' ' . $this->l->t('If you did not request this, please contact an administrator.'));
			$template->addFooter();


			$message = $this->mailer->createMessage();
			$message->setTo([$user->getEMailAddress() => $user->getDisplayName()]);
			$message->useTemplate($template);
			$this->mailer->send($message);
		}
	}

	/**
	 * @param IUser $user
	 * @param string|null $oldMailAddress
	 * @throws \InvalidArgumentException
	 * @throws \BadMethodCallException
	 */
	public function onChangeEmail(IUser $user, $oldMailAddress) {

		if ($oldMailAddress === $user->getEMailAddress() ||
			$user->getLastLogin() === 0) {
			// Email didn't really change or user didn't login,
			// so don't create activities and emails.
			return;
		}

		$event = $this->activityManager->generateEvent();
		$event->setApp('settings')
			->setType('personal_settings')
			->setAffectedUser($user->getUID());

		$instanceUrl = $this->urlGenerator->getAbsoluteURL('/');

		$actor = $this->userSession->getUser();
		if ($actor instanceof IUser) {
			$subject = Provider::EMAIL_CHANGED_SELF;
			if ($actor->getUID() !== $user->getUID()) {
				$this->l = $this->languageFactory->get(
					'settings',
					$this->config->getUserValue(
						$user->getUID(), 'core', 'lang',
						$this->config->getSystemValue('default_language', 'en')
					)
				);
				$subject = Provider::EMAIL_CHANGED;
			}
			$text = $this->l->t('Your email address on %s was changed.', [$instanceUrl]);
			$event->setAuthor($actor->getUID())
				->setSubject($subject);
		} else {
			$text = $this->l->t('Your email address on %s was changed by an administrator.', [$instanceUrl]);
			$event->setSubject(Provider::EMAIL_CHANGED);
		}
		$this->activityManager->publish($event);


		if ($oldMailAddress !== null) {
			$template = $this->mailer->createEMailTemplate('settings.EmailChanged', [
				'displayname' => $user->getDisplayName(),
				'newEMailAddress' => $user->getEMailAddress(),
				'oldEMailAddress' => $oldMailAddress,
				'instanceUrl' => $instanceUrl,
			]);

			$template->setSubject($this->l->t('Email address for %1$s changed on %2$s', [$user->getDisplayName(), $instanceUrl]));
			$template->addHeader();
			$template->addHeading($this->l->t('Email address changed for %s', [$user->getDisplayName()]), false);
			$template->addBodyText($text . ' ' . $this->l->t('If you did not request this, please contact an administrator.'));
			if ($user->getEMailAddress()) {
				$template->addBodyText($this->l->t('The new email address is %s', [$user->getEMailAddress()]));
			}
			$template->addFooter();


			$message = $this->mailer->createMessage();
			$message->setTo([$oldMailAddress => $user->getDisplayName()]);
			$message->useTemplate($template);
			$this->mailer->send($message);
		}
	}

	/**
	 * @param IGroup $group
	 * @param IUser $user
	 * @throws \InvalidArgumentException
	 * @throws \BadMethodCallException
	 */
	public function addUserToGroup(IGroup $group, IUser $user): void {
		$subAdminManager = $this->groupManager->getSubAdmin();
		$usersToNotify = $subAdminManager->getGroupsSubAdmins($group);
		$usersToNotify[] = $user;


		$event = $this->activityManager->generateEvent();
		$event->setApp('settings')
			->setType('group_settings');

		$actor = $this->userSession->getUser();
		if ($actor instanceof IUser) {
			$event->setAuthor($actor->getUID())
				->setSubject(GroupProvider::ADDED_TO_GROUP, [
					'user' => $user->getUID(),
					'group' => $group->getGID(),
					'actor' => $actor->getUID(),
				]);
		} else {
			$event->setSubject(GroupProvider::ADDED_TO_GROUP, [
				'user' => $user->getUID(),
				'group' => $group->getGID(),
			]);
		}

		foreach ($usersToNotify as $userToNotify) {
			$event->setAffectedUser($userToNotify->getUID());
			$this->activityManager->publish($event);
		}
	}

	/**
	 * @param IGroup $group
	 * @param IUser $user
	 * @throws \InvalidArgumentException
	 * @throws \BadMethodCallException
	 */
	public function removeUserFromGroup(IGroup $group, IUser $user): void {
		$subAdminManager = $this->groupManager->getSubAdmin();
		$usersToNotify = $subAdminManager->getGroupsSubAdmins($group);
		$usersToNotify[] = $user;


		$event = $this->activityManager->generateEvent();
		$event->setApp('settings')
			->setType('group_settings');

		$actor = $this->userSession->getUser();
		if ($actor instanceof IUser) {
			$event->setAuthor($actor->getUID())
				->setSubject(GroupProvider::REMOVED_FROM_GROUP, [
					'user' => $user->getUID(),
					'group' => $group->getGID(),
					'actor' => $actor->getUID(),
				]);
		} else {
			$event->setSubject(GroupProvider::REMOVED_FROM_GROUP, [
				'user' => $user->getUID(),
				'group' => $group->getGID(),
			]);
		}

		foreach ($usersToNotify as $userToNotify) {
			$event->setAffectedUser($userToNotify->getUID());
			$this->activityManager->publish($event);
		}
	}
}
