<?php
/**
 * @copyright Copyright (c) 2016 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\TwoFactorBackupCodes\Activity;

use InvalidArgumentException;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\IURLGenerator;
use OCP\L10N\IFactory as L10nFactory;

class Provider implements IProvider {

	/** @var L10nFactory */
	private $l10n;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var IManager */
	private $activityManager;

	/**
	 * @param L10nFactory $l10n
	 * @param IURLGenerator $urlGenerator
	 * @param IManager $activityManager
	 */
	public function __construct(L10nFactory $l10n, IURLGenerator $urlGenerator, IManager $activityManager) {
		$this->urlGenerator = $urlGenerator;
		$this->activityManager = $activityManager;
		$this->l10n = $l10n;
	}

	public function parse($language, IEvent $event, IEvent $previousEvent = null) {
		if ($event->getApp() !== 'twofactor_backupcodes') {
			throw new InvalidArgumentException();
		}

		$l = $this->l10n->get('twofactor_backupcodes', $language);

		switch ($event->getSubject()) {
			case 'codes_generated':
				$event->setParsedSubject($l->t('You created two-factor backup codes for your account'));

				if ($this->activityManager->getRequirePNG()) {
					$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/password.png')));
				} else {
					$event->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/password.svg')));
				}
				break;
			default:
				throw new InvalidArgumentException();
		}
		return $event;
	}

}
