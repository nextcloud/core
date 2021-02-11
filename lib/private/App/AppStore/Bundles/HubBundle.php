<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OC\App\AppStore\Bundles;

class HubBundle extends Bundle {
	public function getName() {
		return $this->l10n->t('Hub bundle');
	}

	public function getAppIdentifiers() {
		$hubApps = [
			'spreed',
			'contacts',
			'calendar',
			'mail',
		];

		$architecture = php_uname('m');
		if (PHP_OS_FAMILY === 'Linux' && in_array($architecture, ['x86_64', 'aarch64'])) {
			$hubApps[] = 'richdocuments';
			$hubApps[] = 'richdocumentscode' . ($architecture === 'aarch64' ? '_arm64' : '');
		}

		return $hubApps;
	}
}
