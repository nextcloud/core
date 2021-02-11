<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
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

namespace OCA\ShareByMail;

use OCA\ShareByMail\Settings\SettingsManager;
use OCP\Capabilities\ICapability;

class Capabilities implements ICapability {

	/** @var SettingsManager */
	private $manager;

	public function __construct(SettingsManager $manager) {
		$this->manager = $manager;
	}

	public function getCapabilities(): array {
		return [
			'files_sharing' =>
				[
					'sharebymail' =>
						[
							'enabled' => true,
							'upload_files_drop' => [
								'enabled' => true,
							],
							'password' => [
								'enabled' => true,
								'enforced' => $this->manager->enforcePasswordProtection(),
							],
							'expire_date' => [
								'enabled' => true,
							],
						]
				]
		];
	}
}
