<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Robin Appelman <robin@icewind.nl>
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Activity;

use OCP\Activity\ActivitySettings;
use OCP\Activity\ISetting;
use OCP\IL10N;

/**
 * Adapt the old interface based settings into the new abstract
 * class based one
 */
class ActivitySettingsAdapter extends ActivitySettings {
	private $oldSettings;
	private $l10n;

	public function __construct(ISetting $oldSettings, IL10N $l10n) {
		$this->oldSettings = $oldSettings;
		$this->l10n = $l10n;
	}

	public function getIdentifier() {
		return $this->oldSettings->getIdentifier();
	}

	public function getName() {
		return $this->oldSettings->getName();
	}

	public function getGroupIdentifier() {
		return 'other';
	}

	public function getGroupName() {
		return $this->l10n->t('Other activities');
	}

	public function getPriority() {
		return $this->oldSettings->getPriority();
	}

	public function canChangeMail() {
		return $this->oldSettings->canChangeMail();
	}

	public function isDefaultEnabledMail() {
		return $this->oldSettings->isDefaultEnabledMail();
	}
}
