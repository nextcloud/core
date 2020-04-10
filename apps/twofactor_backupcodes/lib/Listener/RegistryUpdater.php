<?php

declare(strict_types=1);

/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\TwoFactorBackupCodes\Listener;

use OCA\TwoFactorBackupCodes\Event\CodesGenerated;
use OCA\TwoFactorBackupCodes\Provider\BackupCodesProvider;
use OCP\Authentication\TwoFactorAuth\IRegistry;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

class RegistryUpdater implements IEventListener {

	/** @var IRegistry */
	private $registry;

	/** @var BackupCodesProvider */
	private $provider;

	public function __construct(IRegistry $registry, BackupCodesProvider $provider) {
		$this->registry = $registry;
		$this->provider = $provider;
	}

	public function handle(Event $event): void {
		if ($event instanceof CodesGenerated) {
			$this->registry->enableProviderFor($this->provider, $event->getUser());
		}
	}

}
