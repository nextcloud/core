<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\UpdateNotification\Settings;

use OCA\UpdateNotification\UpdateChecker;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\IGroupManager;
use OCP\L10N\IFactory;
use OCP\Settings\ISettings;
use OCP\Support\Subscription\IRegistry;
use OCP\Util;

class Admin implements ISettings {
	/** @var IConfig */
	private $config;
	/** @var UpdateChecker */
	private $updateChecker;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IDateTimeFormatter */
	private $dateTimeFormatter;
	/** @var IFactory */
	private $l10nFactory;
	/** @var IRegistry */
	private $subscriptionRegistry;

	public function __construct(
		IConfig $config,
		UpdateChecker $updateChecker,
		IGroupManager $groupManager,
		IDateTimeFormatter $dateTimeFormatter,
		IFactory $l10nFactory,
		IRegistry $subscriptionRegistry
	) {
		$this->config = $config;
		$this->updateChecker = $updateChecker;
		$this->groupManager = $groupManager;
		$this->dateTimeFormatter = $dateTimeFormatter;
		$this->l10nFactory = $l10nFactory;
		$this->subscriptionRegistry = $subscriptionRegistry;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$lastUpdateCheckTimestamp = $this->config->getAppValue('core', 'lastupdatedat');
		$lastUpdateCheck = $this->dateTimeFormatter->formatDateTime($lastUpdateCheckTimestamp);

		$channels = [
			'daily',
			'beta',
			'stable',
			'production',
		];
		$currentChannel = Util::getChannel();
		if ($currentChannel === 'git') {
			$channels[] = 'git';
		}

		$updateState = $this->updateChecker->getUpdateState();

		$notifyGroups = json_decode($this->config->getAppValue('updatenotification', 'notify_groups', '["admin"]'), true);

		$defaultUpdateServerURL = 'https://updates.nextcloud.com/updater_server/';
		$updateServerURL = $this->config->getSystemValue('updater.server.url', $defaultUpdateServerURL);
		$defaultCustomerUpdateServerURLPrefix = 'https://updates.nextcloud.com/customers/';

		$isDefaultUpdateServerURL = $updateServerURL === $defaultUpdateServerURL
			|| $updateServerURL === substr($updateServerURL, 0, strlen($defaultCustomerUpdateServerURLPrefix));

		$hasValidSubscription = $this->subscriptionRegistry->delegateHasValidSubscription();

		$params = [
			'isNewVersionAvailable' => !empty($updateState['updateAvailable']),
			'isUpdateChecked' => $lastUpdateCheckTimestamp > 0,
			'lastChecked' => $lastUpdateCheck,
			'currentChannel' => $currentChannel,
			'channels' => $channels,
			'newVersion' => empty($updateState['updateVersion']) ? '' : $updateState['updateVersion'],
			'newVersionString' => empty($updateState['updateVersionString']) ? '' : $updateState['updateVersionString'],
			'downloadLink' => empty($updateState['downloadLink']) ? '' : $updateState['downloadLink'],
			'changes' => $this->filterChanges($updateState['changes'] ?? []),
			'updaterEnabled' => empty($updateState['updaterEnabled']) ? false : $updateState['updaterEnabled'],
			'versionIsEol' => empty($updateState['versionIsEol']) ? false : $updateState['versionIsEol'],
			'isDefaultUpdateServerURL' => $isDefaultUpdateServerURL,
			'updateServerURL' => $updateServerURL,
			'notifyGroups' => $this->getSelectedGroups($notifyGroups),
			'hasValidSubscription' => $hasValidSubscription,
		];

		$params = [
			'json' => json_encode($params),
		];

		return new TemplateResponse('updatenotification', 'admin', $params, '');
	}

	protected function filterChanges(array $changes): array {
		$filtered = [];
		if(isset($changes['changelogURL'])) {
			$filtered['changelogURL'] = $changes['changelogURL'];
		}
		if(!isset($changes['whatsNew'])) {
			return $filtered;
		}

		$iterator = $this->l10nFactory->getLanguageIterator();
		do {
			$lang = $iterator->current();
			if(isset($changes['whatsNew'][$lang])) {
				$filtered['whatsNew'] = $changes['whatsNew'][$lang];
				return $filtered;
			}
			$iterator->next();
		} while($lang !== 'en' && $iterator->valid());

		return $filtered;
	}

	/**
	 * @param array $groupIds
	 * @return array
	 */
	protected function getSelectedGroups(array $groupIds): array {
		$result = [];
		foreach ($groupIds as $groupId) {
			$group = $this->groupManager->get($groupId);

			if ($group === null) {
				continue;
			}

			$result[] = ['value' => $group->getGID(), 'label' => $group->getDisplayName()];
		}

		return $result;
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 */
	public function getSection(): string {
		return 'overview';
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 * the admin section. The forms are arranged in ascending order of the
	 * priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 */
	public function getPriority(): int {
		return 11;
	}
}
