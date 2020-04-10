<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

use InvalidArgumentException;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\IURLGenerator;
use OCP\L10N\IFactory as L10nFactory;

class SecurityProvider implements IProvider {

	/** @var L10nFactory */
	private $l10n;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var IManager */
	private $activityManager;

	public function __construct(L10nFactory $l10n, IURLGenerator $urlGenerator, IManager $activityManager) {
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;
		$this->activityManager = $activityManager;
	}

	public function parse($language, IEvent $event, IEvent $previousEvent = null) {
		if ($event->getType() !== 'security') {
			throw new InvalidArgumentException();
		}

		$l = $this->l10n->get('settings', $language);

		switch ($event->getSubject()) {
			case 'twofactor_success':
				$params = $event->getSubjectParameters();
				$event->setParsedSubject($l->t('You successfully logged in using two-factor authentication (%1$s)', [
					$params['provider'],
				]));
				if ($this->activityManager->getRequirePNG()) {
					$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/password.png')));
				} else {
					$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/password.svg')));
				}
				break;
			case 'twofactor_failed':
				$params = $event->getSubjectParameters();
				$event->setParsedSubject($l->t('A login attempt using two-factor authentication failed (%1$s)', [
					$params['provider'],
				]));
				if ($this->activityManager->getRequirePNG()) {
					$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/password.png')));
				} else {
					$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/password.svg')));
				}
				break;
			case 'remote_wipe_start':
				$params = $event->getSubjectParameters();
				$event->setParsedSubject($l->t('Remote wipe was started on %1$s', [
					$params['name'],
				]));
				if ($this->activityManager->getRequirePNG()) {
					$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/delete.png')));
				} else {
					$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/delete.svg')));
				}
				break;
			case 'remote_wipe_finish':
				$params = $event->getSubjectParameters();
				$event->setParsedSubject($l->t('Remote wipe has finished on %1$s', [
					$params['name'],
				]));
				if ($this->activityManager->getRequirePNG()) {
					$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/delete.png')));
				} else {
					$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/delete.svg')));
				}
				break;
			default:
				throw new InvalidArgumentException();
		}
		return $event;
	}

}
