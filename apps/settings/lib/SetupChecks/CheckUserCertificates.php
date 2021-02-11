<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Morris Jobke <hey@morrisjobke.de>
 *
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

namespace OCA\Settings\SetupChecks;

use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;

class CheckUserCertificates {
	/** @var IL10N */
	private $l10n;
	/** @var string */
	private $configValue;
	/** @var IURLGenerator */
	private $urlGenerator;

	public function __construct(IL10N $l10n, IConfig $config, IURLGenerator $urlGenerator) {
		$this->l10n = $l10n;
		$configValue = $config->getAppValue('files_external', 'user_certificate_scan', false);
		if (!is_string($configValue)) {
			$configValue = '';
		}
		$this->configValue = $configValue;
		$this->urlGenerator = $urlGenerator;
	}

	public function description(): string {
		if ($this->configValue === '') {
			return '';
		}
		if ($this->configValue === 'not-run-yet') {
			return $this->l10n->t('A background job is pending that checks for user imported SSL certificates. Please check back later.');
		}
		return $this->l10n->t('There are some user imported SSL certificates present, that are not used anymore with Nextcloud 21. They can be imported on the command line via "occ security:certificates:import" command. Their paths inside the data directory are shown below.');
	}

	public function severity(): string {
		return 'warning';
	}

	public function run(): bool {
		// all fine if neither "not-run-yet" nor a result
		return $this->configValue === '';
	}

	public function elements(): array {
		if ($this->configValue === '' || $this->configValue === 'not-run-yet') {
			return [];
		}
		$data = json_decode($this->configValue);
		if (!is_array($data)) {
			return [];
		}
		return $data;
	}
}
