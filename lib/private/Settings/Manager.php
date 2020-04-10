<?php
/**
 * @copyright Copyright (c) 2016 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author sualko <klaus@jsxc.org>
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

namespace OC\Settings;

use Closure;
use OCP\AppFramework\QueryException;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IServerContainer;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Settings\IManager;
use OCP\Settings\ISection;
use OCP\Settings\ISettings;
use OCP\Settings\ISubAdminSettings;

class Manager implements IManager {

	/** @var ILogger */
	private $log;

	/** @var IL10N */
	private $l;

	/** @var IFactory */
	private $l10nFactory;

	/** @var IURLGenerator */
	private $url;

	/** @var IServerContainer */
	private $container;

	public function __construct(
		ILogger $log,
		IFactory $l10nFactory,
		IURLGenerator $url,
		IServerContainer $container
	) {
		$this->log = $log;
		$this->l10nFactory = $l10nFactory;
		$this->url = $url;
		$this->container = $container;
	}

	/** @var array */
	protected $sectionClasses = [];

	/** @var array */
	protected $sections = [];

	/**
	 * @param string $type 'admin' or 'personal'
	 * @param string $section Class must implement OCP\Settings\ISection
	 *
	 * @return void
	 */
	public function registerSection(string $type, string $section) {
		if (!isset($this->sectionClasses[$type])) {
			$this->sectionClasses[$type] = [];
		}

		$this->sectionClasses[$type][] = $section;
	}

	/**
	 * @param string $type 'admin' or 'personal'
	 *
	 * @return ISection[]
	 */
	protected function getSections(string $type): array {
		if (!isset($this->sections[$type])) {
			$this->sections[$type] = [];
		}

		if (!isset($this->sectionClasses[$type])) {
			return $this->sections[$type];
		}

		foreach ($this->sectionClasses[$type] as $index => $class) {
			try {
				/** @var ISection $section */
				$section = \OC::$server->query($class);
			} catch (QueryException $e) {
				$this->log->logException($e, ['level' => ILogger::INFO]);
				continue;
			}

			if (!$section instanceof ISection) {
				$this->log->logException(new \InvalidArgumentException('Invalid settings section registered'), ['level' => ILogger::INFO]);
				continue;
			}

			$sectionID = $section->getID();

			if (isset($this->sections[$type][$sectionID])) {
				$this->log->logException(new \InvalidArgumentException('Section with the same ID already registered'), ['level' => ILogger::INFO]);
				continue;
			}

			$this->sections[$type][$sectionID] = $section;

			unset($this->sectionClasses[$type][$index]);
		}

		return $this->sections[$type];
	}

	/** @var array */
	protected $settingClasses = [];

	/** @var array */
	protected $settings = [];

	/**
	 * @param string $type 'admin' or 'personal'
	 * @param string $setting Class must implement OCP\Settings\ISetting
	 *
	 * @return void
	 */
	public function registerSetting(string $type, string $setting) {
		$this->settingClasses[$setting] = $type;
	}

	/**
	 * @param string $type 'admin' or 'personal'
	 * @param string $section
	 * @param Closure $filter optional filter to apply on all loaded ISettings
	 *
	 * @return ISettings[]
	 */
	protected function getSettings(string $type, string $section, Closure $filter = null): array {
		if (!isset($this->settings[$type])) {
			$this->settings[$type] = [];
		}
		if (!isset($this->settings[$type][$section])) {
			$this->settings[$type][$section] = [];
		}

		foreach ($this->settingClasses as $class => $settingsType) {
			if ($type !== $settingsType) {
				continue;
			}

			try {
				/** @var ISettings $setting */
				$setting = $this->container->query($class);
			} catch (QueryException $e) {
				$this->log->logException($e, ['level' => ILogger::INFO]);
				continue;
			}

			if (!$setting instanceof ISettings) {
				$this->log->logException(new \InvalidArgumentException('Invalid settings setting registered (' . $class . ')'), ['level' => ILogger::INFO]);
				continue;
			}

			if ($filter !== null && !$filter($setting)) {
				continue;
			}
			if ($setting->getSection() === null) {
				continue;
			}

			if (!isset($this->settings[$settingsType][$setting->getSection()])) {
				$this->settings[$settingsType][$setting->getSection()] = [];
			}
			$this->settings[$settingsType][$setting->getSection()][] = $setting;

			unset($this->settingClasses[$class]);
		}

		return $this->settings[$type][$section];
	}

	/**
	 * @inheritdoc
	 */
	public function getAdminSections(): array {
		// built-in sections
		$sections = [];

		$appSections = $this->getSections('admin');

		foreach ($appSections as $section) {
			/** @var ISection $section */
			if (!isset($sections[$section->getPriority()])) {
				$sections[$section->getPriority()] = [];
			}

			$sections[$section->getPriority()][] = $section;
		}

		ksort($sections);

		return $sections;
	}

	/**
	 * @inheritdoc
	 */
	public function getAdminSettings($section, bool $subAdminOnly = false): array {
		if ($subAdminOnly) {
			$subAdminSettingsFilter = function (ISettings $settings) {
				return $settings instanceof ISubAdminSettings;
			};
			$appSettings = $this->getSettings('admin', $section, $subAdminSettingsFilter);
		} else {
			$appSettings = $this->getSettings('admin', $section);
		}

		$settings = [];
		foreach ($appSettings as $setting) {
			if (!isset($settings[$setting->getPriority()])) {
				$settings[$setting->getPriority()] = [];
			}
			$settings[$setting->getPriority()][] = $setting;
		}

		ksort($settings);
		return $settings;
	}

	/**
	 * @inheritdoc
	 */
	public function getPersonalSections(): array {
		if ($this->l === null) {
			$this->l = $this->l10nFactory->get('lib');
		}

		$sections = [];

		$legacyForms = \OC_App::getForms('personal');
		if (!empty($legacyForms) && $this->hasLegacyPersonalSettingsToRender($legacyForms)) {
			$sections[98] = [new Section('additional', $this->l->t('Additional settings'), 0, $this->url->imagePath('core', 'actions/settings-dark.svg'))];
		}

		$appSections = $this->getSections('personal');

		foreach ($appSections as $section) {
			/** @var ISection $section */
			if (!isset($sections[$section->getPriority()])) {
				$sections[$section->getPriority()] = [];
			}

			$sections[$section->getPriority()][] = $section;
		}

		ksort($sections);

		return $sections;
	}

	/**
	 * @param string[] $forms
	 *
	 * @return bool
	 */
	private function hasLegacyPersonalSettingsToRender(array $forms): bool {
		foreach ($forms as $form) {
			if (trim($form) !== '') {
				return true;
			}
		}
		return false;
	}

	/**
	 * @inheritdoc
	 */
	public function getPersonalSettings($section): array {
		$settings = [];
		$appSettings = $this->getSettings('personal', $section);

		foreach ($appSettings as $setting) {
			if (!isset($settings[$setting->getPriority()])) {
				$settings[$setting->getPriority()] = [];
			}
			$settings[$setting->getPriority()][] = $setting;
		}

		ksort($settings);
		return $settings;
	}
}
