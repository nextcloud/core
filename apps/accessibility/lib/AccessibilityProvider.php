<?php
/**
 * @copyright Copyright (c) 2018 John Molakvoæ <skjnldsv@protonmail.com>
 * @copyright Copyright (c) 2019 Janis Köhr <janiskoehr@icloud.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Greta Doci <gretadoci@gmail.com>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author Janis Köhr <janis.koehr@novatec-gmbh.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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

namespace OCA\Accessibility;

use OCP\IL10N;
use OCP\IURLGenerator;

class AccessibilityProvider {

	/** @var string */
	protected $appName;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var IL10N */
	private $l;

	/**
	 * Account constructor.
	 *
	 * @param string $appName
	 * @param IURLGenerator $urlGenerator
	 * @param IL10N $l
	 */
	public function __construct(string $appName,
								IURLGenerator $urlGenerator,
								IL10N $l) {
		$this->appName = $appName;
		$this->urlGenerator = $urlGenerator;
		$this->l = $l;
	}

	public function getThemes() {
		return [
			[
				'id' => 'dark',
				'img' => $this->urlGenerator->imagePath($this->appName, 'theme-dark.jpg'),
				'title' => $this->l->t('Dark theme'),
				'enableLabel' => $this->l->t('Enable dark theme'),
				'text' => $this->l->t('A dark theme to ease your eyes by reducing the overall luminosity and brightness. It is still under development, so please report any issues you may find.')
			]
		];
	}

	public function getHighContrast() {
		return [
			'id' => 'highcontrast',
			'img' => $this->urlGenerator->imagePath($this->appName, 'mode-highcontrast.jpg'),
			'title' => $this->l->t('High contrast mode'),
			'enableLabel' => $this->l->t('Enable high contrast mode'),
			'text' => $this->l->t('A high contrast mode to ease your navigation. Visual quality will be reduced but clarity will be increased.')
		];
	}

	public function getFonts() {
		return [
			[
				'id' => 'fontdyslexic',
				'img' => $this->urlGenerator->imagePath($this->appName, 'font-opendyslexic.jpg'),
				'title' => $this->l->t('Dyslexia font'),
				'enableLabel' => $this->l->t('Enable dyslexia font'),
				'text' => $this->l->t('OpenDyslexic is a free typeface/font designed to mitigate some of the common reading errors caused by dyslexia.')
			]
		];
	}
}
