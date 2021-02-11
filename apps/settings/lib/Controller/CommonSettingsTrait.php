<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OCA\Settings\Controller;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Group\ISubAdmin;
use OCP\IGroupManager;
use OCP\INavigationManager;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Settings\IManager as ISettingsManager;
use OCP\Settings\ISettings;

trait CommonSettingsTrait {

	/** @var ISettingsManager */
	private $settingsManager;

	/** @var INavigationManager */
	private $navigationManager;

	/** @var IUserSession */
	private $userSession;

	/** @var IGroupManager */
	private $groupManager;

	/** @var ISubAdmin */
	private $subAdmin;

	/**
	 * @param string $currentSection
	 * @return array
	 */
	private function getNavigationParameters($currentType, $currentSection) {
		$templateParameters = [
			'personal' => $this->formatPersonalSections($currentType, $currentSection),
			'admin' => []
		];

		/** @var IUser $user */
		$user = $this->userSession->getUser();
		$isAdmin = $this->groupManager->isAdmin($user->getUID());
		$isSubAdmin = $this->subAdmin->isSubAdmin($user);
		if ($isAdmin || $isSubAdmin) {
			$templateParameters['admin'] = $this->formatAdminSections(
				$currentType,
				$currentSection,
				!$isAdmin && $isSubAdmin
			);
		}

		return [
			'forms' => $templateParameters
		];
	}

	protected function formatSections($sections, $currentSection, $type, $currentType, bool $subAdminOnly = false) {
		$templateParameters = [];
		/** @var \OCP\Settings\IIconSection[] $prioritizedSections */
		foreach ($sections as $prioritizedSections) {
			foreach ($prioritizedSections as $section) {
				if ($type === 'admin') {
					$settings = $this->settingsManager->getAdminSettings($section->getID(), $subAdminOnly);
				} elseif ($type === 'personal') {
					$settings = $this->settingsManager->getPersonalSettings($section->getID());
				}
				if (empty($settings) && !($section->getID() === 'additional' && count(\OC_App::getForms('admin')) > 0)) {
					continue;
				}

				$icon = $section->getIcon();

				$active = $section->getID() === $currentSection
					&& $type === $currentType;

				$templateParameters[] = [
					'anchor' => $section->getID(),
					'section-name' => $section->getName(),
					'active' => $active,
					'icon' => $icon,
				];
			}
		}
		return $templateParameters;
	}

	protected function formatPersonalSections($currentType, $currentSections) {
		$sections = $this->settingsManager->getPersonalSections();
		$templateParameters = $this->formatSections($sections, $currentSections, 'personal', $currentType);

		return $templateParameters;
	}

	protected function formatAdminSections($currentType, $currentSections, bool $subAdminOnly) {
		$sections = $this->settingsManager->getAdminSections();
		$templateParameters = $this->formatSections($sections, $currentSections, 'admin', $currentType, $subAdminOnly);

		return $templateParameters;
	}

	/**
	 * @param ISettings[] $settings
	 * @return array
	 */
	private function formatSettings($settings) {
		$html = '';
		foreach ($settings as $prioritizedSettings) {
			foreach ($prioritizedSettings as $setting) {
				/** @var \OCP\Settings\ISettings $setting */
				$form = $setting->getForm();
				$html .= $form->renderAs('')->render();
			}
		}
		return ['content' => $html];
	}

	private function getIndexResponse($type, $section) {
		$this->navigationManager->setActiveEntry('settings');
		$templateParams = [];
		$templateParams = array_merge($templateParams, $this->getNavigationParameters($type, $section));
		$templateParams = array_merge($templateParams, $this->getSettings($section));

		return new TemplateResponse('settings', 'settings/frame', $templateParams);
	}

	abstract protected function getSettings($section);
}
