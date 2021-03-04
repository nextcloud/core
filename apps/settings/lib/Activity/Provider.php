<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Settings\Activity;

use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;

class Provider implements IProvider {
	public const PASSWORD_CHANGED_BY = 'password_changed_by';
	public const PASSWORD_CHANGED_SELF = 'password_changed_self';
	public const PASSWORD_RESET = 'password_changed';
	public const PASSWORD_RESET_SELF = 'password_reset_self';
	public const EMAIL_CHANGED_BY = 'email_changed_by';
	public const EMAIL_CHANGED_SELF = 'email_changed_self';
	public const EMAIL_CHANGED = 'email_changed';
	public const DISPLAY_NAME_CHANGED_BY = 'displayname_changed_by';
	public const DISPLAY_NAME_CHANGED_SELF = 'displayname_changed_self';
	public const DISPLAY_NAME_CHANGED = 'displayname_changed';
	public const APP_TOKEN_CREATED = 'app_token_created';
	public const APP_TOKEN_DELETED = 'app_token_deleted';
	public const APP_TOKEN_RENAMED = 'app_token_renamed';
	public const APP_TOKEN_FILESYSTEM_GRANTED = 'app_token_filesystem_granted';
	public const APP_TOKEN_FILESYSTEM_REVOKED = 'app_token_filesystem_revoked';

	/** @var IFactory */
	protected $languageFactory;

	/** @var IL10N */
	protected $l;

	/** @var IURLGenerator */
	protected $url;

	/** @var IUserManager */
	protected $userManager;

	/** @var IManager */
	private $activityManager;

	/** @var string[] cached displayNames - key is the UID and value the displayname */
	protected $displayNames = [];

	public function __construct(IFactory $languageFactory,
								IURLGenerator $url,
								IUserManager $userManager,
								IManager $activityManager) {
		$this->languageFactory = $languageFactory;
		$this->url = $url;
		$this->userManager = $userManager;
		$this->activityManager = $activityManager;
	}

	/**
	 * @param string $language
	 * @param IEvent $event
	 * @param IEvent|null $previousEvent
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parse($language, IEvent $event, IEvent $previousEvent = null): IEvent {
		if ($event->getApp() !== 'settings') {
			throw new \InvalidArgumentException('Unknown app');
		}

		$this->l = $this->languageFactory->get('settings', $language);

		if ($this->activityManager->getRequirePNG()) {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('settings', 'personal.png')));
		} else {
			$event->setIcon($this->url->getAbsoluteURL($this->url->imagePath('settings', 'personal.svg')));
		}

		if ($event->getSubject() === self::PASSWORD_CHANGED_BY) {
			$subject = $this->l->t('{actor} changed your password');
		} elseif ($event->getSubject() === self::PASSWORD_CHANGED_SELF) {
			$subject = $this->l->t('You changed your password');
		} elseif ($event->getSubject() === self::PASSWORD_RESET) {
			$subject = $this->l->t('Your password was reset by an administrator');
		} elseif ($event->getSubject() === self::PASSWORD_RESET_SELF) {
			$subject = $this->l->t('Your password was reset');
		} elseif ($event->getSubject() === self::EMAIL_CHANGED_BY) {
			$subject = $this->l->t('{actor} changed your email address');
		} elseif ($event->getSubject() === self::EMAIL_CHANGED_SELF) {
			$subject = $this->l->t('You changed your email address');
		} elseif ($event->getSubject() === self::EMAIL_CHANGED) {
			$subject = $this->l->t('Your email address was changed by an administrator');
		} elseif ($event->getSubject() === self::DISPLAY_NAME_CHANGED_BY) {
			$subject = $this->l->t('{actor} changed your display name');
		} elseif ($event->getSubject() === self::DISPLAY_NAME_CHANGED_SELF) {
			$subject = $this->l->t('You changed your display name');
		} elseif ($event->getSubject() === self::DISPLAY_NAME_CHANGED) {
			$subject = $this->l->t('Your display name was changed by the system');
		} elseif ($event->getSubject() === self::APP_TOKEN_CREATED) {
			$subject = $this->l->t('You created app password "{token}"');
		} elseif ($event->getSubject() === self::APP_TOKEN_DELETED) {
			$subject = $this->l->t('You deleted app password "{token}"');
		} elseif ($event->getSubject() === self::APP_TOKEN_RENAMED) {
			$subject = $this->l->t('You renamed app password "{token}" to "{newToken}"');
		} elseif ($event->getSubject() === self::APP_TOKEN_FILESYSTEM_GRANTED) {
			$subject = $this->l->t('You granted filesystem access to app password "{token}"');
		} elseif ($event->getSubject() === self::APP_TOKEN_FILESYSTEM_REVOKED) {
			$subject = $this->l->t('You revoked filesystem access from app password "{token}"');
		} else {
			throw new \InvalidArgumentException('Unknown subject');
		}

		$parsedParameters = $this->getParameters($event);
		$this->setSubjects($event, $subject, $parsedParameters);

		return $event;
	}

	/**
	 * @param IEvent $event
	 * @return array
	 * @throws \InvalidArgumentException
	 */
	protected function getParameters(IEvent $event): array {
		$subject = $event->getSubject();
		$parameters = $event->getSubjectParameters();

		switch ($subject) {
			case self::PASSWORD_CHANGED_SELF:
			case self::PASSWORD_RESET:
			case self::PASSWORD_RESET_SELF:
			case self::EMAIL_CHANGED_SELF:
			case self::EMAIL_CHANGED:
			case self::DISPLAY_NAME_CHANGED_SELF:
			case self::DISPLAY_NAME_CHANGED:
				return [];
			case self::PASSWORD_CHANGED_BY:
			case self::EMAIL_CHANGED_BY:
				return [
					'actor' => $this->generateUserParameter($parameters[0]),
				];
			case self::DISPLAY_NAME_CHANGED_BY:
				return [
					'actor' => $this->generateUserParameter($parameters['actor']),
				];
			case self::APP_TOKEN_CREATED:
			case self::APP_TOKEN_DELETED:
			case self::APP_TOKEN_FILESYSTEM_GRANTED:
			case self::APP_TOKEN_FILESYSTEM_REVOKED:
				return [
					'token' => [
						'type' => 'highlight',
						'id' => $event->getObjectId(),
						'name' => $parameters['name'],
					]
				];
			case self::APP_TOKEN_RENAMED:
				return [
					'token' => [
						'type' => 'highlight',
						'id' => $event->getObjectId(),
						'name' => $parameters['name'],
					],
					'newToken' => [
						'type' => 'highlight',
						'id' => $event->getObjectId(),
						'name' => $parameters['newName'],
					]
				];
		}

		throw new \InvalidArgumentException('Unknown subject');
	}

	/**
	 * @param IEvent $event
	 * @param string $subject
	 * @param array $parameters
	 * @throws \InvalidArgumentException
	 */
	protected function setSubjects(IEvent $event, string $subject, array $parameters): void {
		$placeholders = $replacements = [];
		foreach ($parameters as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			$replacements[] = $parameter['name'];
		}

		$event->setParsedSubject(str_replace($placeholders, $replacements, $subject))
			->setRichSubject($subject, $parameters);
	}

	protected function generateUserParameter(string $uid): array {
		if (!isset($this->displayNames[$uid])) {
			$this->displayNames[$uid] = $this->getDisplayName($uid);
		}

		return [
			'type' => 'user',
			'id' => $uid,
			'name' => $this->displayNames[$uid],
		];
	}

	protected function getDisplayName(string $uid): string {
		$user = $this->userManager->get($uid);
		if ($user instanceof IUser) {
			return $user->getDisplayName();
		}

		return $uid;
	}
}
